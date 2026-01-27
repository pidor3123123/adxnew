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

    // Get existing balance
    const { data: existingBalance } = await supabase
      .from('user_balances')
      .select('*')
      .eq('user_id', userId)
      .eq('currency', currency)
      .maybeSingle()

    const oldBalance = existingBalance
    const balanceId = existingBalance?.id || randomUUID()

    // Upsert balance
    const { data: balance, error: balanceError } = await supabase
      .from('user_balances')
      .upsert({
        id: balanceId,
        user_id: userId,
        currency,
        available_balance: available_balance ?? 0,
        locked_balance: locked_balance ?? 0,
        updated_at: new Date().toISOString(),
      }, {
        onConflict: 'user_id,currency'
      })
      .select()
      .single()

    if (balanceError) {
      return NextResponse.json({ error: balanceError.message }, { status: 400 })
    }

    if (!balance || !balance.id) {
      return NextResponse.json({ error: 'Failed to create/update balance' }, { status: 500 })
    }

    // Log audit
    await supabase.from('admin_actions_audit_log').insert({
      admin_id: adminId,
      action: 'update_balance',
      table_name: 'user_balances',
      record_id: balance.id,
      old_data: oldBalance,
      new_data: balance,
    })

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
              available_balance: available_balance ?? balance.available_balance ?? 0,
              locked_balance: locked_balance ?? balance.locked_balance ?? 0,
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

    return NextResponse.json({ success: true, balance })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
