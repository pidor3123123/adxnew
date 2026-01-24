import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, getAdminEmails } from '@/lib/auth'
import { getOrCreateAdminIdByEmail } from '@/lib/supabase-admin'
import { supabaseServer } from '@/lib/supabase-server'
import { randomUUID } from 'crypto'

export async function PATCH(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getServerSession(authOptions)

  const admins = getAdminEmails()
  if (!session?.user?.email || admins.length === 0 || !admins.includes(session.user.email)) {
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

    return NextResponse.json({ success: true, balance })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
