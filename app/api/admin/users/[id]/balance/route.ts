import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, isAdmin } from '@/lib/auth'
import { getOrCreateAdminIdByEmail } from '@/lib/supabase-admin'
import { supabaseServer } from '@/lib/supabase-server'
import { randomUUID } from 'crypto'

export async function PATCH(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const { id: userId } = await params
    const adminId = await getOrCreateAdminIdByEmail(session.user.email)
    const body = await request.json()
    const { currency, available_balance, locked_balance } = body

    if (!currency) {
      return NextResponse.json({ error: 'currency is required' }, { status: 400 })
    }

    const supabase = supabaseServer()

    // Get existing balance from wallets (single source of truth)
    const { data: existingWallet } = await supabase
      .from('wallets')
      .select('balance')
      .eq('user_id', userId)
      .eq('currency', currency)
      .maybeSingle()

    // Also get from user_balances for compatibility
    const { data: existingBalance } = await supabase
      .from('user_balances')
      .select('*')
      .eq('user_id', userId)
      .eq('currency', currency)
      .maybeSingle()

    const oldBalance = existingBalance
    // If wallet doesn't exist, balance is 0 (will be created by trigger on first transaction)
    const oldWalletBalance = existingWallet ? parseFloat(existingWallet.balance?.toString() || '0') : 0
    const newAvailableBalance = parseFloat((available_balance ?? 0).toString())
    
    // Log for debugging
    console.log(`[Admin Balance Update] User: ${userId}, Currency: ${currency}`)
    console.log(`[Admin Balance Update] Existing wallet:`, existingWallet)
    console.log(`[Admin Balance Update] Old wallet balance: ${oldWalletBalance}, New balance: ${newAvailableBalance}`)
    
    // Calculate difference
    const difference = newAvailableBalance - oldWalletBalance

    // If there's a difference, create a transaction using apply_transaction RPC
    // This will create the wallet record if it doesn't exist (via trigger)
    if (Math.abs(difference) > 0.0001) { // Use small epsilon to avoid floating point issues
      console.log(`[Admin Balance Update] Creating transaction with difference: ${difference}`)
      // Use admin_topup for both positive and negative adjustments
      // The RPC function handles negative amounts correctly
      const idempotencyKey = `admin_balance_update_${userId}_${currency}_${Date.now()}_${randomUUID()}`

      // Call apply_transaction RPC
      // p_amount can be positive (topup) or negative (adjustment down)
      // The RPC function will handle the sign and update the balance accordingly
      // The trigger will create the wallet record if it doesn't exist
      const { data: transactionResult, error: transactionError } = await supabase.rpc('apply_transaction', {
        p_user_id: userId,
        p_amount: difference.toString(), // Pass difference directly (positive or negative)
        p_type: 'admin_topup', // Use admin_topup for all admin adjustments
        p_currency: currency,
        p_idempotency_key: idempotencyKey,
        p_metadata: {
          source: 'admin_panel',
          admin_id: adminId,
          old_balance: oldWalletBalance,
          new_balance: newAvailableBalance,
          difference: difference,
          adjustment_type: difference > 0 ? 'increase' : 'decrease',
          wallet_existed: existingWallet !== null // Track if wallet existed before
        }
      })

      if (transactionError) {
        console.error('[Admin Balance Update] Error calling apply_transaction RPC:', transactionError)
        return NextResponse.json({ 
          error: 'Failed to process transaction: ' + transactionError.message 
        }, { status: 500 })
      }

      if (!transactionResult || !transactionResult.success) {
        console.error('[Admin Balance Update] apply_transaction returned error:', transactionResult)
        return NextResponse.json({ 
          error: 'Transaction failed: ' + (transactionResult?.message || 'Unknown error')
        }, { status: 500 })
      }
      
      console.log(`[Admin Balance Update] Transaction created successfully:`, transactionResult)
    } else {
      console.log(`[Admin Balance Update] No difference, skipping transaction (old: ${oldWalletBalance}, new: ${newAvailableBalance})`)
    }

    // Get updated balance from wallets (single source of truth)
    // Wait a bit to ensure transaction is fully processed and trigger has executed
    await new Promise(resolve => setTimeout(resolve, 200))
    
    const { data: updatedWallet } = await supabase
      .from('wallets')
      .select('balance')
      .eq('user_id', userId)
      .eq('currency', currency)
      .maybeSingle()

    const finalBalance = parseFloat(updatedWallet?.balance?.toString() || newAvailableBalance.toString())
    
    console.log(`[Admin Balance Update] Final wallet balance: ${finalBalance} (expected: ${newAvailableBalance})`)
    
    // Verify that wallet record exists after transaction
    if (!updatedWallet && Math.abs(newAvailableBalance) > 0.0001) {
      console.warn(`[Admin Balance Update] WARNING: Wallet record not found after transaction for ${currency}. This may indicate a trigger issue.`)
    }

    // Update user_balances for compatibility (if it exists)
    const balanceId = existingBalance?.id || randomUUID()
    const { data: balance, error: balanceError } = await supabase
      .from('user_balances')
      .upsert({
        id: balanceId,
        user_id: userId,
        currency,
        available_balance: finalBalance, // Use balance from wallets
        locked_balance: locked_balance ?? 0,
        updated_at: new Date().toISOString(),
      }, {
        onConflict: 'user_id,currency'
      })
      .select()
      .single()

    if (balanceError) {
      // Log but don't fail - user_balances is for compatibility only
      console.warn('Failed to update user_balances (non-critical):', balanceError.message)
    }

    // Log audit (only if balance was successfully updated)
    if (balance?.id) {
      await supabase.from('admin_actions_audit_log').insert({
        admin_id: adminId,
        action: 'update_balance',
        table_name: 'user_balances',
        record_id: balance.id,
        old_data: oldBalance,
        new_data: balance,
      })
    }

    await supabase.from('admin_actions_log').insert({
      admin_id: adminId,
      user_id: userId,
      action: `Updated balance: ${currency} = ${available_balance ?? 0} (locked: ${locked_balance ?? 0})`,
    })

    // Get user email for webhook
    const { data: user } = await supabase
      .from('users')
      .select('email')
      .eq('id', userId)
      .single()

    // Send webhook to main site
    const webhookUrl = process.env.WEBHOOK_URL
    const webhookSecret = process.env.WEBHOOK_SECRET
    
    if (webhookUrl && webhookSecret) {
      try {
        const webhookResponse = await fetch(webhookUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Webhook-Secret': webhookSecret,
          },
          body: JSON.stringify({
            type: 'balance_updated',
            payload: {
              user_id: userId, // Supabase UUID
              email: user?.email,
              currency: currency,
              available_balance: finalBalance, // Use balance from wallets (single source of truth)
              locked_balance: locked_balance ?? 0,
            },
          }),
        })

        if (!webhookResponse.ok) {
          const errorText = await webhookResponse.text()
          console.error(`Webhook failed: ${webhookResponse.status} - ${errorText}`)
        }
      } catch (error: any) {
        // Don't fail the request if webhook fails
        console.error('Webhook error:', error.message)
      }
    } else {
      console.error('Webhook configuration missing: WEBHOOK_URL and WEBHOOK_SECRET must be set')
    }

    return NextResponse.json({ 
      success: true, 
      balance: balance || {
        id: balanceId,
        user_id: userId,
        currency,
        available_balance: finalBalance,
        locked_balance: locked_balance ?? 0
      },
      wallet_balance: finalBalance // Include wallet balance for verification
    })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
