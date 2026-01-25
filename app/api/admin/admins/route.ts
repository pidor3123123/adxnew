import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, isAdmin } from '@/lib/auth'
import { getAdmins, createAdmin, createAdminWithPassword, deleteAdmin } from '@/lib/supabase-admin'
import { getOrCreateAdminIdByEmail } from '@/lib/supabase-admin'

export async function GET() {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const { data, error } = await getAdmins()

    if (error) {
      return NextResponse.json({ error: error.message }, { status: 500 })
    }

    return NextResponse.json({ data })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}

export async function POST(request: NextRequest) {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const adminId = await getOrCreateAdminIdByEmail(session.user.email)

    const body = await request.json()
    const { email, role, password } = body

    if (!email || !role) {
      return NextResponse.json({ error: 'email and role are required' }, { status: 400 })
    }

    // If password is provided, create admin with password
    let result
    if (password) {
      if (password.length < 8) {
        return NextResponse.json({ error: 'Password must be at least 8 characters' }, { status: 400 })
      }
      result = await createAdminWithPassword(adminId, email, password, role)
    } else {
      // Create admin without password (for GitHub OAuth admins)
      result = await createAdmin(adminId, email, role)
    }

    if (!result.success) {
      return NextResponse.json({ error: result.error }, { status: 400 })
    }

    return NextResponse.json({ success: true, data: result.data })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}

export async function DELETE(request: NextRequest) {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const adminId = await getOrCreateAdminIdByEmail(session.user.email)

    const searchParams = request.nextUrl.searchParams
    const targetAdminId = searchParams.get('id')

    if (!targetAdminId) {
      return NextResponse.json({ error: 'id is required' }, { status: 400 })
    }

    const result = await deleteAdmin(adminId, targetAdminId)

    if (!result.success) {
      return NextResponse.json({ error: result.error }, { status: 400 })
    }

    return NextResponse.json({ success: true })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
