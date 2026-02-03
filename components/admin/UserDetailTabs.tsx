'use client'

import { useState } from 'react'
import type { UserWithDetails, KycStatus } from '@/lib/types'
import KycStatusBadge from './KycStatusBadge'
import DataTable from './DataTable'
import AuthInfo from './AuthInfo'
import { useTranslations } from 'next-intl'

interface UserDetailTabsProps {
  user: UserWithDetails
  onUserUpdate?: () => void
}

export default function UserDetailTabs({ user: initialUser, onUserUpdate }: UserDetailTabsProps) {
  const t = useTranslations()
  const [activeTab, setActiveTab] = useState<'info' | 'security' | 'documents' | 'balances' | 'history'>('info')
  const [user, setUser] = useState<UserWithDetails>(initialUser)
  const [isEditing, setIsEditing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [editData, setEditData] = useState({
    is_verified: initialUser.is_verified || false,
    kyc_status: initialUser.kyc_status || 'pending' as KycStatus,
    kyc_verified: initialUser.kyc_verified || false,
  })

  const tabs = [
    { id: 'info' as const, label: t('userTabs.information') },
    { id: 'security' as const, label: t('userTabs.security') },
    { id: 'documents' as const, label: t('userTabs.documents') },
    { id: 'balances' as const, label: t('userTabs.balances') },
    { id: 'history' as const, label: t('userTabs.history') },
  ]

  return (
    <div>
      <div className="border-b border-gray-700">
        <nav className="flex space-x-8">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-400'
                  : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      <div className="mt-6">
        {activeTab === 'info' && (
          <div className="space-y-4">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold text-white">{t('userTabs.information')}</h3>
              {!isEditing ? (
                <button
                  onClick={() => setIsEditing(true)}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium"
                >
                  {t('userTabs.editStatuses')}
                </button>
              ) : (
                <div className="flex gap-2">
                  <button
                    onClick={async () => {
                      setSaving(true)
                      try {
                        const res = await fetch(`/api/admin/users/${user.id}`, {
                          method: 'PATCH',
                          headers: { 'Content-Type': 'application/json' },
                          body: JSON.stringify(editData),
                        })
                        
                        if (res.ok) {
                          setUser({ ...user, ...editData })
                          setIsEditing(false)
                          onUserUpdate?.()
                        } else {
                          const error = await res.json()
                          alert(error.error || 'Failed to update user')
                        }
                      } catch (error) {
                        console.error('Failed to update user:', error)
                        alert('Failed to update user')
                      } finally {
                        setSaving(false)
                      }
                    }}
                    disabled={saving}
                    className="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white rounded-lg transition-colors text-sm font-medium"
                  >
                    {saving ? t('users.saving') : t('common.save')}
                  </button>
                  <button
                    onClick={() => {
                      setIsEditing(false)
                      setEditData({
                        is_verified: user.is_verified || false,
                        kyc_status: user.kyc_status || 'pending',
                        kyc_verified: user.kyc_verified || false,
                      })
                    }}
                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors text-sm font-medium"
                  >
                    {t('common.cancel')}
                  </button>
                </div>
              )}
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.firstName')}</label>
                <p className="mt-1 text-white">{user.first_name || t('common.nA')}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.lastName')}</label>
                <p className="mt-1 text-white">{user.last_name || t('common.nA')}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('common.email')}</label>
                <p className="mt-1 text-white">{user.email || t('common.nA')}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.country')}</label>
                <p className="mt-1 text-white">{user.country || t('common.nA')}</p>
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.verified')}</label>
                {isEditing ? (
                  <div className="mt-1">
                    <label className="flex items-center space-x-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={editData.is_verified}
                        onChange={(e) => setEditData({ ...editData, is_verified: e.target.checked })}
                        className="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                      />
                      <span className="text-white">{editData.is_verified ? t('common.yes') : t('common.no')}</span>
                    </label>
                  </div>
                ) : (
                  <p className="mt-1 text-white">{user.is_verified ? t('common.yes') : t('common.no')}</p>
                )}
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.kycStatus')}</label>
                {isEditing ? (
                  <div className="mt-1">
                    <select
                      value={editData.kyc_status || 'pending'}
                      onChange={(e) => setEditData({ ...editData, kyc_status: e.target.value as KycStatus })}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="pending">{t('kycStatus.pending')}</option>
                      <option value="approved">{t('kycStatus.approved')}</option>
                      <option value="rejected">{t('kycStatus.rejected')}</option>
                      <option value="under_review">{t('kycStatus.underReview')}</option>
                    </select>
                  </div>
                ) : (
                  <div className="mt-1">
                    <KycStatusBadge status={user.kyc_status} />
                  </div>
                )}
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.kycVerified')}</label>
                {isEditing ? (
                  <div className="mt-1">
                    <label className="flex items-center space-x-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={editData.kyc_verified}
                        onChange={(e) => setEditData({ ...editData, kyc_verified: e.target.checked })}
                        className="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                      />
                      <span className="text-white">{editData.kyc_verified ? t('common.yes') : t('common.no')}</span>
                    </label>
                  </div>
                ) : (
                  <p className="mt-1 text-white">{user.kyc_verified ? t('common.yes') : t('common.no')}</p>
                )}
              </div>
              <div>
                <label className="text-sm font-medium text-gray-400">{t('userTabs.createdAt')}</label>
                <p className="mt-1 text-white">{new Date(user.created_at).toLocaleString()}</p>
              </div>
            </div>
          </div>
        )}

        {activeTab === 'security' && (
          <div className="space-y-4">
            <AuthInfo userId={user.id} />
            {user.user_security ? (
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium text-gray-400">2FA Enabled</label>
                  <p className="mt-1 text-white">{user.user_security.two_fa_enabled ? 'Yes' : 'No'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-400">2FA Type</label>
                  <p className="mt-1 text-white">{user.user_security.two_fa_type || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-400">Last Login</label>
                  <p className="mt-1 text-white">
                    {user.user_security.last_login_at 
                      ? new Date(user.user_security.last_login_at).toLocaleString()
                      : 'Never'}
                  </p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-400">Last Login IP</label>
                  <p className="mt-1 text-white">{user.user_security.last_login_ip || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-400">Failed Login Attempts</label>
                  <p className="mt-1 text-white">{user.user_security.failed_login_attempts || 0}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-400">Account Locked Until</label>
                  <p className="mt-1 text-white">
                    {user.user_security.account_locked_until
                      ? new Date(user.user_security.account_locked_until).toLocaleString()
                      : 'Not locked'}
                  </p>
                </div>
              </div>
            ) : (
              <p className="text-gray-400">No security data available</p>
            )}
          </div>
        )}

        {activeTab === 'documents' && (
          <div>
            {user.user_documents.length > 0 ? (
              <DataTable
                data={user.user_documents}
                columns={[
                  { key: 'type', label: t('common.type') },
                  { key: 'status', label: 'Status', render: (status) => (
                    <span className={`px-2 py-1 rounded text-xs ${
                      status === 'approved' ? 'bg-green-900 text-green-300' :
                      status === 'rejected' ? 'bg-red-900 text-red-300' :
                      'bg-yellow-900 text-yellow-300'
                    }`}>
                      {status}
                    </span>
                  )},
                  { key: 'uploaded_at', label: t('documents.uploaded'), render: (date) => new Date(date).toLocaleString() },
                  { key: 'file_url', label: t('common.file'), render: (url) => (
                    <a href={url} target="_blank" rel="noopener noreferrer" className="text-blue-400 hover:underline">
                      {t('common.view')}
                    </a>
                  )},
                ]}
              />
            ) : (
              <p className="text-gray-400">{t('userTabs.noDocumentsUploaded')}</p>
            )}
          </div>
        )}

        {activeTab === 'balances' && (
          <div>
            {user.user_balances.length > 0 ? (
              <DataTable
                data={user.user_balances}
                columns={[
                  { key: 'currency', label: 'Currency' },
                  { key: 'available_balance', label: 'Available', render: (val) => parseFloat(val).toFixed(2) },
                  { key: 'locked_balance', label: 'Locked', render: (val) => parseFloat(val).toFixed(2) },
                  { key: 'updated_at', label: 'Updated', render: (date) => new Date(date).toLocaleString() },
                ]}
              />
            ) : (
              <p className="text-gray-400">No balances found</p>
            )}
          </div>
        )}

        {activeTab === 'history' && (
          <div>
            {user.admin_actions.length > 0 ? (
              <DataTable
                data={user.admin_actions}
                columns={[
                  { key: 'action', label: t('audit.action') },
                  { key: 'created_at', label: t('common.date'), render: (date) => new Date(date).toLocaleString() },
                ]}
              />
            ) : (
              <p className="text-gray-400">{t('userTabs.noAdminActions')}</p>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
