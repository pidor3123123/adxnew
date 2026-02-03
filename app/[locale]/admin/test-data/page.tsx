'use client'

import { useState } from 'react'
import { useRouter } from '@/lib/navigation'
import { useTranslations } from 'next-intl'

export default function TestDataPage() {
  const t = useTranslations()
  const router = useRouter()
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)
  const [createdPassword, setCreatedPassword] = useState<string | null>(null)

  const [userData, setUserData] = useState({
    first_name: 'Test',
    last_name: 'User',
    email: `test${Date.now()}@example.com`,
    country: 'US',
    is_verified: false,
    kyc_status: 'pending' as const,
  })

  const [adminData, setAdminData] = useState({
    email: '',
    role: 'admin' as const,
  })

  async function createTestUser() {
    setLoading(true)
    setMessage(null)
    try {
      const res = await fetch('/api/admin/test-data', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create_test_user',
          ...userData,
        }),
      })

      const data = await res.json()

      if (res.ok) {
        const passwordText = data.password ? `\n${t('testData.passwordLabel')}: ${data.password}` : ''
        setMessage({ type: 'success', text: `${t('testData.userCreated')}: ${data.user.email}${passwordText}` })
        if (data.password) {
          setCreatedPassword(data.password)
        }
        setUserData({
          ...userData,
          email: `test${Date.now()}@example.com`,
        })
      } else {
        setMessage({ type: 'error', text: data.error || t('testData.createUserError') })
      }
    } catch (error) {
      setMessage({ type: 'error', text: t('testData.createUserError') })
    } finally {
      setLoading(false)
    }
  }

  async function createAdmin() {
    if (!adminData.email) {
      setMessage({ type: 'error', text: t('testData.emailRequired') })
      return
    }

    setLoading(true)
    setMessage(null)
    try {
      const res = await fetch('/api/admin/admins', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(adminData),
      })

      const data = await res.json()

      if (res.ok) {
        setMessage({ type: 'success', text: `${t('testData.adminCreated')}: ${adminData.email}` })
        setAdminData({ email: '', role: 'admin' })
      } else {
        setMessage({ type: 'error', text: data.error || t('testData.createAdminError') })
      }
    } catch (error) {
      setMessage({ type: 'error', text: t('testData.createAdminError') })
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">{t('testData.title')}</h1>
        <p className="text-gray-400">{t('testData.subtitle')}</p>
      </div>

      {message && (
        <div
          className={`p-4 rounded-lg ${
            message.type === 'success'
              ? 'bg-green-900/50 border border-green-700 text-green-300'
              : 'bg-red-900/50 border border-red-700 text-red-300'
          }`}
        >
          {message.text}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Create Test User */}
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">{t('testData.createTestUser')}</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('testData.firstName')}
              </label>
              <input
                type="text"
                value={userData.first_name}
                onChange={(e) => setUserData({ ...userData, first_name: e.target.value })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('testData.lastName')}
              </label>
              <input
                type="text"
                value={userData.last_name}
                onChange={(e) => setUserData({ ...userData, last_name: e.target.value })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('common.email')}
              </label>
              <input
                type="email"
                value={userData.email}
                onChange={(e) => setUserData({ ...userData, email: e.target.value })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('testData.country')}
              </label>
              <input
                type="text"
                value={userData.country}
                onChange={(e) => setUserData({ ...userData, country: e.target.value })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('testData.kycStatus')}
              </label>
              <select
                value={userData.kyc_status}
                onChange={(e) => setUserData({ ...userData, kyc_status: e.target.value as any })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="pending">{t('kycStatus.pending')}</option>
                <option value="approved">{t('kycStatus.approved')}</option>
                <option value="rejected">{t('kycStatus.rejected')}</option>
                <option value="under_review">{t('kycStatus.underReview')}</option>
              </select>
            </div>
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is_verified"
                checked={userData.is_verified}
                onChange={(e) => setUserData({ ...userData, is_verified: e.target.checked })}
                className="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
              />
              <label htmlFor="is_verified" className="ml-2 text-sm text-gray-300">
                {t('testData.verified')}
              </label>
            </div>
            <button
              onClick={createTestUser}
              disabled={loading}
              className="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
            >
              {loading ? t('testData.creating') : t('testData.createUser')}
            </button>
            {createdPassword && (
              <div className="mt-4 p-3 bg-green-900/50 border border-green-700 rounded-lg">
                <p className="text-green-300 text-sm font-medium mb-1">{t('testData.createdUserPassword')}:</p>
                <p className="text-white font-mono text-lg">{createdPassword}</p>
                <p className="text-green-400 text-xs mt-2">{t('testData.savePassword')}</p>
              </div>
            )}
          </div>
        </div>

        {/* Create Admin */}
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">{t('testData.addAdmin')}</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('common.email')}
              </label>
              <input
                type="email"
                value={adminData.email}
                onChange={(e) => setAdminData({ ...adminData, email: e.target.value })}
                placeholder="admin@example.com"
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                {t('testData.role')}
              </label>
              <select
                value={adminData.role}
                onChange={(e) => setAdminData({ ...adminData, role: e.target.value as any })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="admin">{t('settings.admin')}</option>
                <option value="compliance">{t('settings.compliance')}</option>
              </select>
            </div>
            <button
              onClick={createAdmin}
              disabled={loading}
              className="w-full px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
            >
              {loading ? t('testData.creating') : t('testData.addAdmin')}
            </button>
            <p className="text-xs text-gray-500">
              {t('testData.adminNote')}
            </p>
          </div>
        </div>
      </div>

      <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 className="text-xl font-semibold text-white mb-4">{t('testData.quickActions')}</h2>
        <div className="flex gap-4">
          <button
            onClick={() => router.push('/admin/users')}
            className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
          >
            {t('testData.viewUsers')} →
          </button>
          <button
            onClick={() => router.push('/admin/settings')}
            className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
          >
            {t('testData.manageAdmins')} →
          </button>
        </div>
      </div>
    </div>
  )
}
