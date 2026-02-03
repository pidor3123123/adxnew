import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, isAdmin } from '@/lib/auth'

const MAIN_SITE_URL = process.env.MAIN_SITE_URL || ''
const ADMIN_API_KEY = process.env.ADMIN_API_KEY || ''

async function proxyToMainSite(
  path: string,
  method: string,
  body?: object
): Promise<Response> {
  if (!MAIN_SITE_URL || !ADMIN_API_KEY) {
    return NextResponse.json(
      { error: 'Deposits API not configured. Set MAIN_SITE_URL and ADMIN_API_KEY.' },
      { status: 500 }
    )
  }
  const url = `${MAIN_SITE_URL.replace(/\/$/, '')}/api/admin_deposits.php${path}`
  const res = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-Admin-API-Key': ADMIN_API_KEY,
    },
    ...(body && Object.keys(body).length > 0 && { body: JSON.stringify(body) }),
  })
  const data = await res.json().catch(() => ({}))
  return NextResponse.json(data, { status: res.status })
}

export async function GET(request: NextRequest) {
  const session = await getServerSession(authOptions)
  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }
  const status = request.nextUrl.searchParams.get('status') || 'PENDING'
  const limit = request.nextUrl.searchParams.get('limit') || '50'
  return proxyToMainSite(`?action=list&status=${status}&limit=${limit}`, 'GET')
}

export async function POST(request: NextRequest) {
  const session = await getServerSession(authOptions)
  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }
  const body = await request.json().catch(() => ({}))
  const action = body.action || 'approve'
  const path = `?action=${action}`
  return proxyToMainSite(path, 'POST', body)
}
