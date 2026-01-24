import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, getAdminEmails } from '@/lib/auth'
import { getAuditLogs } from '@/lib/supabase-admin'

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
    const tableName = searchParams.get('table_name') || undefined
    const adminId = searchParams.get('admin_id') || undefined

    const { data, error } = await getAuditLogs(limit, offset, { table_name: tableName, admin_id: adminId })

    if (error) {
      return NextResponse.json({ error: error.message }, { status: 500 })
    }

    return NextResponse.json({ data })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
