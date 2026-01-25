import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

export function middleware(request: NextRequest) {
  // Убрали редирект с корня, чтобы избежать бесконечного цикла
  // Корень будет показывать страницу входа из app/page.tsx
  return NextResponse.next()
}

export const config = {
  matcher: '/',
}
