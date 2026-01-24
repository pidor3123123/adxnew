'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'

export default function TestDataPage() {
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
        const passwordText = data.password ? `\nПароль: ${data.password}` : ''
        setMessage({ type: 'success', text: `Пользователь создан: ${data.user.email}${passwordText}` })
        if (data.password) {
          setCreatedPassword(data.password)
        }
        setUserData({
          ...userData,
          email: `test${Date.now()}@example.com`,
        })
      } else {
        setMessage({ type: 'error', text: data.error || 'Ошибка при создании пользователя' })
      }
    } catch (error) {
      setMessage({ type: 'error', text: 'Ошибка при создании пользователя' })
    } finally {
      setLoading(false)
    }
  }

  async function createAdmin() {
    if (!adminData.email) {
      setMessage({ type: 'error', text: 'Email обязателен' })
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
        setMessage({ type: 'success', text: `Админ создан: ${adminData.email}` })
        setAdminData({ email: '', role: 'admin' })
      } else {
        setMessage({ type: 'error', text: data.error || 'Ошибка при создании админа' })
      }
    } catch (error) {
      setMessage({ type: 'error', text: 'Ошибка при создании админа' })
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Тестовые данные</h1>
        <p className="text-gray-400">Создание тестовых пользователей и админов</p>
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
          <h2 className="text-xl font-semibold text-white mb-4">Создать тестового пользователя</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Имя
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
                Фамилия
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
                Email
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
                Страна
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
                KYC Статус
              </label>
              <select
                value={userData.kyc_status}
                onChange={(e) => setUserData({ ...userData, kyc_status: e.target.value as any })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="under_review">Under Review</option>
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
                Верифицирован
              </label>
            </div>
            <button
              onClick={createTestUser}
              disabled={loading}
              className="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
            >
              {loading ? 'Создание...' : 'Создать пользователя'}
            </button>
            {createdPassword && (
              <div className="mt-4 p-3 bg-green-900/50 border border-green-700 rounded-lg">
                <p className="text-green-300 text-sm font-medium mb-1">Пароль созданного пользователя:</p>
                <p className="text-white font-mono text-lg">{createdPassword}</p>
                <p className="text-green-400 text-xs mt-2">Сохраните этот пароль! Он больше не будет показан.</p>
              </div>
            )}
          </div>
        </div>

        {/* Create Admin */}
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">Добавить админа</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Email
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
                Роль
              </label>
              <select
                value={adminData.role}
                onChange={(e) => setAdminData({ ...adminData, role: e.target.value as any })}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="admin">Admin</option>
                <option value="compliance">Compliance</option>
              </select>
            </div>
            <button
              onClick={createAdmin}
              disabled={loading}
              className="w-full px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
            >
              {loading ? 'Создание...' : 'Добавить админа'}
            </button>
            <p className="text-xs text-gray-500">
              После добавления админа, его email нужно также добавить в список ADMINS в коде для доступа к панели.
            </p>
          </div>
        </div>
      </div>

      <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 className="text-xl font-semibold text-white mb-4">Быстрые действия</h2>
        <div className="flex gap-4">
          <button
            onClick={() => router.push('/admin/users')}
            className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
          >
            Просмотр пользователей →
          </button>
          <button
            onClick={() => router.push('/admin/settings')}
            className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
          >
            Управление админами →
          </button>
        </div>
      </div>
    </div>
  )
}
