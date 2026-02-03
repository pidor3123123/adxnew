'use client'

import { useEffect, useState } from 'react'
import { useRouter } from '@/lib/navigation'
import DataTable from '@/components/admin/DataTable'
import KycStatusBadge from '@/components/admin/KycStatusBadge'
import Modal from '@/components/admin/Modal'
import type { UserWithSecurity, KycStatus } from '@/lib/types'
import { useTranslations } from 'next-intl'

export default function UsersPage() {
  const t = useTranslations()
  const router = useRouter()
  const [users, setUsers] = useState<UserWithSecurity[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [filterKyc, setFilterKyc] = useState<string>('all')
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [selectedUser, setSelectedUser] = useState<UserWithSecurity | null>(null)
  const [editData, setEditData] = useState({
    is_verified: false,
    kyc_status: 'pending' as KycStatus,
    kyc_verified: false,
    balance_currency: 'USD',
    available_balance: '0',
    locked_balance: '0',
  })
  const [saving, setSaving] = useState(false)
  const [syncing, setSyncing] = useState(false)
  const [syncMessage, setSyncMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  useEffect(() => {
    fetchUsers()
  }, [search, filterKyc])

  async function fetchUsers() {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('limit', '1000') // Увеличен лимит для отображения всех пользователей
      if (search) params.set('search', search)
      
      const res = await fetch(`/api/admin/users?${params}`)
      const data = await res.json()
      
      let filtered = data.data || []
      
      if (filterKyc !== 'all') {
        filtered = filtered.filter((user: UserWithSecurity) => user.kyc_status === filterKyc)
      }
      
      setUsers(filtered)
    } catch (error) {
      console.error('Failed to fetch users:', error)
    } finally {
      setLoading(false)
    }
  }

  function openEditModal(user: UserWithSecurity) {
    setSelectedUser(user)
    const mainBalance = user.user_balances?.find(b => b.currency === 'USD') || user.user_balances?.[0]
    setEditData({
      is_verified: user.is_verified || false,
      kyc_status: (user.kyc_status || 'pending') as KycStatus,
      kyc_verified: user.kyc_verified || false,
      balance_currency: mainBalance?.currency || 'USD',
      available_balance: mainBalance?.available_balance?.toString() || '0',
      locked_balance: mainBalance?.locked_balance?.toString() || '0',
    })
    setEditModalOpen(true)
  }

  async function handleSave() {
    if (!selectedUser) return

    setSaving(true)
    try {
      // Update user statuses
      const userRes = await fetch(`/api/admin/users/${selectedUser.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          is_verified: editData.is_verified,
          kyc_status: editData.kyc_status,
          kyc_verified: editData.kyc_verified,
        }),
      })

      if (!userRes.ok) {
        const error = await userRes.json()
        throw new Error(error.error || 'Failed to update user')
      }

      // Update balance
      const balanceRes = await fetch(`/api/admin/users/${selectedUser.id}/balance`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          currency: editData.balance_currency,
          available_balance: parseFloat(editData.available_balance),
          locked_balance: parseFloat(editData.locked_balance),
        }),
      })

      if (!balanceRes.ok) {
        const error = await balanceRes.json()
        throw new Error(error.error || 'Failed to update balance')
      }

      setEditModalOpen(false)
      setSelectedUser(null)
      fetchUsers()
    } catch (error: any) {
      alert(error.message || 'Failed to save changes')
    } finally {
      setSaving(false)
    }
  }

  function getTotalBalance(user: UserWithSecurity): number {
    if (!user.user_balances || user.user_balances.length === 0) return 0
    return user.user_balances.reduce((sum, b) => 
      sum + parseFloat(b.available_balance?.toString() || '0') + parseFloat(b.locked_balance?.toString() || '0'), 
      0
    )
  }

  // Рыночные цены для конвертации в USD (можно обновлять через API)
  const getCryptoPrices = (): Record<string, number> => {
    return {
      'USD': 1.0,
      'BTC': 80000, // Примерная цена, можно получать через API
      'ETH': 3000,
      'BNB': 600,
      'XRP': 0.6,
      'SOL': 150,
      'ADA': 0.6,
      'DOGE': 0.1,
      'DOT': 8,
      'MATIC': 1,
      'LTC': 100,
    }
  }

  function getMainBalance(user: UserWithSecurity): string {
    if (!user.user_balances || user.user_balances.length === 0) return '$0.00'
    
    const prices = getCryptoPrices()
    let totalUsd = 0
    
    // Суммируем все балансы, конвертируя в USD
    user.user_balances.forEach(balance => {
      const available = parseFloat(balance.available_balance?.toString() || '0')
      const locked = parseFloat(balance.locked_balance?.toString() || '0')
      const total = available + locked
      const price = prices[balance.currency] || 0
      totalUsd += total * price
    })
    
    return `$${totalUsd.toFixed(2)}`
  }

  async function handleSyncUsers() {
    setSyncing(true)
    setSyncMessage(null)
    try {
      const res = await fetch('/api/admin/users/sync')
      const data = await res.json()

      if (res.ok) {
        const message = data.message || t('users.syncSuccess', { synced: data.synced, total: data.total })
        const details: string[] = []
        
        if (data.syncedUsers && data.syncedUsers.length > 0) {
          details.push(`${t('users.created')}: ${data.syncedUsers.slice(0, 5).join(', ')}${data.syncedUsers.length > 5 ? '...' : ''}`)
        }
        if (data.skipped && data.skipped > 0) {
          details.push(`${t('users.skipped')}: ${data.skipped}`)
        }
        if (data.errors && data.errors.length > 0) {
          details.push(`${t('users.errors')}: ${data.errors.length}`)
        }
        
        setSyncMessage({
          type: 'success',
          text: `${message}${details.length > 0 ? '\n' + details.join('\n') : ''}`,
        })
        // Refresh users list
        fetchUsers()
      } else {
        setSyncMessage({
          type: 'error',
          text: data.error || t('users.syncError'),
        })
      }
    } catch (error: any) {
      setSyncMessage({
        type: 'error',
        text: t('users.syncError'),
      })
    } finally {
      setSyncing(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-white mb-2">{t('users.title')}</h1>
          <p className="text-gray-400">{t('users.subtitle')}</p>
        </div>
        <button
          onClick={handleSyncUsers}
          disabled={syncing}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
        >
          {syncing ? t('users.syncing') : t('users.syncUsers')}
        </button>
      </div>

      {syncMessage && (
        <div
          className={`p-4 rounded-lg ${
            syncMessage.type === 'success'
              ? 'bg-green-900/50 border border-green-700 text-green-300'
              : 'bg-red-900/50 border border-red-700 text-red-300'
          }`}
        >
          {syncMessage.text}
        </div>
      )}

      {/* Filters */}
      <div className="flex gap-4">
        <input
          type="text"
          placeholder={t('users.searchPlaceholder')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="flex-1 px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <select
          value={filterKyc}
          onChange={(e) => setFilterKyc(e.target.value)}
          className="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">{t('users.allKycStatus')}</option>
          <option value="pending">{t('kycStatus.pending')}</option>
          <option value="approved">{t('kycStatus.approved')}</option>
          <option value="rejected">{t('kycStatus.rejected')}</option>
          <option value="under_review">{t('kycStatus.underReview')}</option>
        </select>
      </div>

      {/* Users Table */}
      <DataTable
        data={users}
        columns={[
          {
            key: 'first_name',
            label: t('common.name'),
            render: (_, row: UserWithSecurity) => {
              const name = `${row.first_name || ''} ${row.last_name || ''}`.trim()
              return name || t('common.nA')
            },
          },
          { key: 'email', label: t('common.email') },
          { key: 'country', label: t('userTabs.country') },
          {
            key: 'is_verified',
            label: t('users.verified'),
            render: (verified) => (
              <span className={verified ? 'text-green-400' : 'text-gray-400'}>
                {verified ? t('common.yes') : t('common.no')}
              </span>
            ),
          },
          {
            key: 'kyc_status',
            label: t('users.kycStatus'),
            render: (status) => <KycStatusBadge status={status} />,
          },
          {
            key: 'user_security.account_locked_until',
            label: t('users.accountStatus'),
            render: (lockedUntil) => {
              if (!lockedUntil) return <span className="text-green-400">{t('common.active')}</span>
              const lockedDate = new Date(lockedUntil)
              const now = new Date()
              if (lockedDate > now) {
                return <span className="text-red-400">{t('common.blocked')}</span>
              }
              return <span className="text-green-400">{t('common.active')}</span>
            },
          },
          {
            key: 'user_security.last_login_at',
            label: t('users.lastLogin'),
            render: (lastLogin) => {
              if (!lastLogin) return <span className="text-gray-500">{t('common.never')}</span>
              return <span className="text-white">{new Date(lastLogin).toLocaleDateString()}</span>
            },
          },
          {
            key: 'user_balances',
            label: t('users.balance'),
            render: (balances, row: UserWithSecurity) => (
              <span className="text-white font-medium">{getMainBalance(row)}</span>
            ),
          },
          {
            key: 'created_at',
            label: t('dashboard.joined'),
            render: (date) => new Date(date).toLocaleDateString(),
          },
          {
            key: 'id',
            label: t('common.actions'),
            render: (id, row: UserWithSecurity) => (
              <div className="flex gap-2">
                <button
                  onClick={(e) => {
                    e.stopPropagation()
                    openEditModal(row)
                  }}
                  className="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded transition-colors"
                >
                  {t('users.editUser')}
                </button>
                <button
                  onClick={(e) => {
                    e.stopPropagation()
                    router.push(`/admin/users/${id}`)
                  }}
                  className="px-2 py-1 bg-gray-600 hover:bg-gray-700 text-white text-xs rounded transition-colors"
                >
                  {t('users.viewUser')}
                </button>
              </div>
            ),
          },
        ]}
        onRowClick={(row: UserWithSecurity) => {
          router.push(`/admin/users/${row.id}`)
        }}
        isLoading={loading}
        emptyMessage={t('users.noUsers')}
      />

      {/* Edit Modal */}
      <Modal
        isOpen={editModalOpen}
        onClose={() => {
          setEditModalOpen(false)
          setSelectedUser(null)
        }}
        title={t('users.editUserStatusBalance')}
        size="lg"
      >
        {selectedUser && (
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-semibold text-white mb-4">{t('common.user')}: {selectedUser.first_name} {selectedUser.last_name}</h3>
              <p className="text-gray-400 text-sm">{selectedUser.email}</p>
            </div>

            <div className="space-y-4">
              <h4 className="text-md font-semibold text-white">{t('users.statuses')}</h4>
              
              <div>
                <label className="flex items-center space-x-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={editData.is_verified}
                    onChange={(e) => setEditData({ ...editData, is_verified: e.target.checked })}
                    className="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                  />
                  <span className="text-white">{t('users.verified')}</span>
                </label>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  {t('users.kycStatus')}
                </label>
                <select
                  value={editData.kyc_status}
                  onChange={(e) => setEditData({ ...editData, kyc_status: e.target.value as KycStatus })}
                  className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="pending">{t('kycStatus.pending')}</option>
                  <option value="approved">{t('kycStatus.approved')}</option>
                  <option value="rejected">{t('kycStatus.rejected')}</option>
                  <option value="under_review">{t('kycStatus.underReview')}</option>
                </select>
              </div>

              <div>
                <label className="flex items-center space-x-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={editData.kyc_verified}
                    onChange={(e) => setEditData({ ...editData, kyc_verified: e.target.checked })}
                    className="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                  />
                  <span className="text-white">{t('users.kycVerified')}</span>
                </label>
              </div>
            </div>

            <div className="space-y-4 border-t border-gray-700 pt-4">
              <h4 className="text-md font-semibold text-white">{t('users.balance')}</h4>
              
              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  {t('common.currency')}
                </label>
                <select
                  value={editData.balance_currency}
                  onChange={(e) => setEditData({ ...editData, balance_currency: e.target.value })}
                  className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="USD">USD</option>
                  <option value="EUR">EUR</option>
                  <option value="BTC">BTC</option>
                  <option value="ETH">ETH</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  {t('users.availableBalance')}
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={editData.available_balance}
                  onChange={(e) => setEditData({ ...editData, available_balance: e.target.value })}
                  className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-300 mb-2">
                  {t('users.lockedBalance')}
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={editData.locked_balance}
                  onChange={(e) => setEditData({ ...editData, locked_balance: e.target.value })}
                  className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-4 border-t border-gray-700">
              <button
                onClick={() => {
                  setEditModalOpen(false)
                  setSelectedUser(null)
                }}
                className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {t('common.cancel')}
              </button>
              <button
                onClick={handleSave}
                disabled={saving}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors"
              >
                {saving ? t('users.saving') : t('users.saveChanges')}
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
