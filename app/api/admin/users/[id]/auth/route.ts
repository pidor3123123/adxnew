import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, isAdmin } from '@/lib/auth'
import { getOrCreateAdminIdByEmail } from '@/lib/supabase-admin'
import { supabaseServer } from '@/lib/supabase-server'

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const { id: userId } = await params
    const supabase = supabaseServer()

    // Get user from auth
    const { data: authUser, error: authError } = await supabase.auth.admin.getUserById(userId)

    if (authError || !authUser) {
      return NextResponse.json({ error: 'User not found in auth' }, { status: 404 })
    }

    // Get providers
    const providers = authUser.user.app_metadata?.providers || []
    const emailProvider = authUser.user.email ? 'email' : null
    const allProviders = [...new Set([...providers, emailProvider].filter(Boolean))]

    return NextResponse.json({
      providers: allProviders,
      email: authUser.user.email,
      email_confirmed: authUser.user.email_confirmed_at !== null,
      created_at: authUser.user.created_at,
    })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}

export async function POST(
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
    const { action, newPassword } = body

    const supabase = supabaseServer()

    if (action === 'reset_password') {
      if (!newPassword) {
        return NextResponse.json({ error: 'newPassword is required' }, { status: 400 })
      }

      // Update password
      const { data, error } = await supabase.auth.admin.updateUserById(userId, {
        password: newPassword,
      })

      if (error) {
        return NextResponse.json({ error: error.message }, { status: 400 })
      }

      // Log action
      await supabase.from('admin_actions_log').insert({
        admin_id: adminId,
        user_id: userId,
        action: 'Reset password',
      })

      return NextResponse.json({ success: true, message: 'Password reset successfully' })
    }

    return NextResponse.json({ error: 'Invalid action' }, { status: 400 })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
