'use client'

import { useEffect, useState } from 'react'
import Modal from './Modal'
import { useTranslations } from 'next-intl'

interface AuthInfoProps {
  userId: string
}

interface AuthData {
  providers: string[]
  email: string | null
  email_confirmed: boolean
  created_at: string
}

export default function AuthInfo({ userId }: AuthInfoProps) {
  const [authData, setAuthData] = useState<AuthData | null>(null)
  const [loading, setLoading] = useState(true)
  const [resetModalOpen, setResetModalOpen] = useState(false)
  const [newPassword, setNewPassword] = useState('')
  const [resetting, setResetting] = useState(false)

  useEffect(() => {
    fetchAuthData()
  }, [userId])

  async function fetchAuthData() {
    setLoading(true)
    try {
      const res = await fetch(`/api/admin/users/${userId}/auth`)
      if (res.ok) {
        const data = await res.json()
        setAuthData(data)
      }
    } catch (error) {
      console.error('Failed to fetch auth data:', error)
    } finally {
      setLoading(false)
    }
  }

  async function handleResetPassword() {
    if (!newPassword) {
      alert(t('auth.enterNewPassword'))
      return
    }

    setResetting(true)
    try {
      const res = await fetch(`/api/admin/users/${userId}/auth`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'reset_password',
          newPassword,
        }),
      })

      if (res.ok) {
        alert('Password reset successfully')
        setResetModalOpen(false)
        setNewPassword('')
      } else {
        const error = await res.json()
        alert(error.error || 'Failed to reset password')
      }
    } catch (error) {
      console.error('Failed to reset password:', error)
      alert('Failed to reset password')
    } finally {
      setResetting(false)
    }
  }

  if (loading) {
    return <p className="text-gray-400">{t('userTabs.loadingAuthInfo')}</p>
  }

  if (!authData) {
    return <p className="text-gray-400">{t('userTabs.noAuthData')}</p>
  }

  return (
    <div className="bg-gray-900 rounded-lg p-4 mb-4">
      <h4 className="text-md font-semibold text-white mb-4">Authentication</h4>
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="text-sm font-medium text-gray-400">Auth Providers</label>
          <div className="mt-1 flex gap-2 flex-wrap">
            {authData.providers.length > 0 ? (
              authData.providers.map((provider) => (
                <span
                  key={provider}
                  className="px-2 py-1 bg-blue-900 text-blue-300 rounded text-xs font-medium"
                >
                  {provider}
                </span>
              ))
            ) : (
              <span className="text-gray-400">{t('userTabs.none')}</span>
            )}
          </div>
        </div>
        <div>
          <label className="text-sm font-medium text-gray-400">{t('userTabs.emailConfirmed')}</label>
          <p className="mt-1 text-white">{authData.email_confirmed ? t('common.yes') : t('common.no')}</p>
        </div>
        <div>
          <label className="text-sm font-medium text-gray-400">{t('userTabs.accountCreated')}</label>
          <p className="mt-1 text-white">{new Date(authData.created_at).toLocaleString()}</p>
        </div>
        <div>
          <label className="text-sm font-medium text-gray-400">{t('auth.password')}</label>
          <div className="mt-1">
            <button
              onClick={() => setResetModalOpen(true)}
              className="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded transition-colors"
            >
              {t('auth.resetPassword')}
            </button>
          </div>
        </div>
      </div>

      <Modal
        isOpen={resetModalOpen}
        onClose={() => {
          setResetModalOpen(false)
          setNewPassword('')
        }}
        title={t('auth.resetPassword')}
      >
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">
              {t('auth.newPassword')}
            </label>
            <input
              type="text"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              placeholder={t('auth.enterNewPassword')}
              className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p className="text-xs text-gray-500 mt-1">
              {t('auth.userWillUsePassword')}
            </p>
          </div>
          <div className="flex justify-end gap-2">
            <button
              onClick={() => {
                setResetModalOpen(false)
                setNewPassword('')
              }}
              className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {t('common.cancel')}
            </button>
            <button
              onClick={handleResetPassword}
              disabled={resetting || !newPassword}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
            >
              {resetting ? t('auth.resetting') : t('auth.resetPassword')}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
