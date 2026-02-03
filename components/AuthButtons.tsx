'use client'

import { signIn, signOut } from 'next-auth/react'
import type { Session } from 'next-auth'
import { useState } from 'react'
import { useTranslations, useLocale } from 'next-intl'
import { Link } from '@/lib/navigation'

interface AuthButtonsProps {
  session: Session | null
  isAdmin: boolean
}

export default function AuthButtons({ session, isAdmin }: AuthButtonsProps) {
  const t = useTranslations()
  const locale = useLocale()
  const [showEmailForm, setShowEmailForm] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      const result = await signIn('credentials', {
        email,
        password,
        redirect: false,
        callbackUrl: `/${locale}/admin`,
      })

      if (result?.error) {
        setError(t('auth.wrongCredentials'))
      } else if (result?.ok) {
        window.location.href = `/${locale}/admin`
      }
    } catch (err) {
      setError(t('auth.loginError'))
    } finally {
      setLoading(false)
    }
  }

  if (session) {
    return (
      <div className="flex flex-col gap-2 items-end">
        {isAdmin && (
          <Link
            href="/admin"
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium"
          >
            {t('auth.adminPanel')}
          </Link>
        )}
        <button
          onClick={() => signOut({ callbackUrl: `/${locale}` })}
          className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors text-sm font-medium"
        >
          {t('auth.logout')}
        </button>
      </div>
    )
  }

  if (showEmailForm) {
    return (
      <div className="w-full max-w-md">
        <form onSubmit={handleEmailLogin} className="space-y-4">
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('common.email')}
            </label>
            <input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              placeholder="admin@example.com"
            />
          </div>
          <div>
            <label htmlFor="password" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('auth.password')}
            </label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
              placeholder="••••••••"
            />
          </div>
          {error && (
            <div className="text-red-600 text-sm dark:text-red-400">{error}</div>
          )}
          <div className="flex gap-2">
            <button
              type="submit"
              disabled={loading}
              className="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors text-sm font-medium"
            >
              {loading ? t('auth.loggingIn') : t('auth.login')}
            </button>
            <button
              type="button"
              onClick={() => {
                setShowEmailForm(false)
                setError('')
                setEmail('')
                setPassword('')
              }}
              className="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded-lg transition-colors text-sm font-medium"
            >
              {t('common.cancel')}
            </button>
          </div>
        </form>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-2 w-full max-w-md">
      <button
        onClick={() => setShowEmailForm(true)}
        className="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium"
      >
        {t('auth.loginWithEmail')}
      </button>
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-gray-300 dark:border-gray-600"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 bg-white dark:bg-black text-gray-500 dark:text-gray-400">{t('auth.or')}</span>
        </div>
      </div>
      <button
        onClick={() => signIn('github', { callbackUrl: `/${locale}/admin` })}
        className="w-full px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded-lg transition-colors text-sm font-medium dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200"
      >
        {t('auth.loginWithGitHub')}
      </button>
    </div>
  )
}
