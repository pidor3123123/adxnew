'use client'

import { signIn, signOut } from 'next-auth/react'
import type { Session } from 'next-auth'

interface AuthButtonsProps {
  session: Session | null
  isAdmin: boolean
}

export default function AuthButtons({ session, isAdmin }: AuthButtonsProps) {
  if (session) {
    return (
      <div className="flex flex-col gap-2 items-end">
        {isAdmin && (
          <a
            href="/admin"
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium"
          >
            Админ панель
          </a>
        )}
        <button
          onClick={() => signOut({ callbackUrl: '/' })}
          className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors text-sm font-medium"
        >
          Выйти
        </button>
      </div>
    )
  }

  return (
    <button
      onClick={() => signIn('github', { callbackUrl: '/admin' })}
      className="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-lg transition-colors text-sm font-medium dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200"
    >
      Войти через GitHub
    </button>
  )
}
