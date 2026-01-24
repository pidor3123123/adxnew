import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, getAdminEmails } from '@/lib/auth'
import { getBalances, updateBalance } from '@/lib/supabase-admin'
import { getAdminIdByEmail } from '@/lib/supabase-admin'

export async function GET(request: NextRequest) {
  const session = await getServerSession(authOptions)
  const admins = getAdminEmails()

  if (!session?.user?.email || admins.length === 0 || !admins.includes(session.user.email)) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const searchParams = request.nextUrl.searchParams
    const limit = parseInt(searchParams.get('limit') || '50')
    const offset = parseInt(searchParams.get('offset') || '0')
    const currency = searchParams.get('currency') || undefined

    const { data, error } = await getBalances(limit, offset, currency)

    if (error) {
      return NextResponse.json({ error: error.message }, { status: 500 })
    }

    return NextResponse.json({ data })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}

export async function PATCH(request: NextRequest) {
  const session = await getServerSession(authOptions)
  const admins = getAdminEmails()

  if (!session?.user?.email || admins.length === 0 || !admins.includes(session.user.email)) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const adminId = await getAdminIdByEmail(session.user.email)
    if (!adminId) {
      return NextResponse.json({ error: 'Admin not found' }, { status: 404 })
    }

    const body = await request.json()
    const { balanceId, ...updates } = body

    if (!balanceId) {
      return NextResponse.json({ error: 'balanceId is required' }, { status: 400 })
    }

    const result = await updateBalance(adminId, balanceId, updates)

    if (!result.success) {
      return NextResponse.json({ error: result.error }, { status: 400 })
    }

    return NextResponse.json({ success: true })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
