import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, isAdmin } from '@/lib/auth'
import { getOrCreateAdminIdByEmail } from '@/lib/supabase-admin'
import { supabaseServer } from '@/lib/supabase-server'
import { randomUUID } from 'crypto'

export async function POST(request: NextRequest) {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const adminId = await getOrCreateAdminIdByEmail(session.user.email)

    const body = await request.json()
    const { action, ...data } = body

    const supabase = supabaseServer()

    if (action === 'create_test_user') {
      const userEmail = data.email || `test${Date.now()}@example.com`
      const generatedPassword = randomUUID() // Generate password for test user
      
      // First, create user in auth.users using Admin API
      const { data: authUser, error: authError } = await supabase.auth.admin.createUser({
        email: userEmail,
        email_confirm: true,
        password: generatedPassword,
      })

      if (authError || !authUser?.user) {
        return NextResponse.json({ 
          error: `Failed to create auth user: ${authError?.message || 'Unknown error'}` 
        }, { status: 400 })
      }

      const userId = authUser.user.id
      
      // Then create user in users table
      const { data: user, error: userError } = await supabase
        .from('users')
        .insert({
          id: userId,
          first_name: data.first_name || 'Test',
          last_name: data.last_name || 'User',
          email: userEmail,
          country: data.country || 'US',
          is_verified: data.is_verified || false,
          kyc_status: data.kyc_status || 'pending',
          kyc_verified: data.kyc_verified || false,
        })
        .select()
        .single()

      if (userError) {
        return NextResponse.json({ error: userError.message }, { status: 400 })
      }

      // Create user_security entry
      await supabase.from('user_security').insert({
        user_id: user.id,
        two_fa_enabled: false,
        failed_login_attempts: 0,
      })

      // Log action
      await supabase.from('admin_actions_log').insert({
        admin_id: adminId,
        user_id: user.id,
        action: `Created test user: ${user.email}`,
      })

      return NextResponse.json({ 
        success: true, 
        user,
        password: generatedPassword, // Return password for test users
      })
    }

    if (action === 'create_test_balance') {
      // Create test balance for user
      const { data: balance, error: balanceError } = await supabase
        .from('user_balances')
        .insert({
          user_id: data.user_id,
          currency: data.currency || 'USD',
          available_balance: data.available_balance || 1000,
          locked_balance: data.locked_balance || 0,
        })
        .select()
        .single()

      if (balanceError) {
        return NextResponse.json({ error: balanceError.message }, { status: 400 })
      }

      // Log audit
      await supabase.from('admin_actions_audit_log').insert({
        admin_id: adminId,
        action: 'create_balance',
        table_name: 'user_balances',
        record_id: balance.id,
        old_data: null,
        new_data: balance,
      })

      return NextResponse.json({ success: true, balance })
    }

    return NextResponse.json({ error: 'Invalid action' }, { status: 400 })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
