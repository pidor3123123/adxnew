import { NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, getAdminEmails } from '@/lib/auth'
import { getStats } from '@/lib/supabase-admin'

export async function GET() {
  const session = await getServerSession(authOptions)
  const admins = getAdminEmails()

  if (!session?.user?.email || admins.length === 0 || !admins.includes(session.user.email)) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const stats = await getStats()
    return NextResponse.json(stats)
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
