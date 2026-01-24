import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, getAdminEmails } from '@/lib/auth'
import { blockUser } from '@/lib/supabase-admin'
import { getOrCreateAdminIdByEmail } from '@/lib/supabase-admin'

export async function POST(
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
    const { lockedUntil } = body // ISO date string or null

    const result = await blockUser(adminId, userId, lockedUntil)

    if (!result.success) {
      return NextResponse.json({ error: result.error }, { status: 400 })
    }

    return NextResponse.json({ success: true })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
