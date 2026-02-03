'use client'

import StatsCard from '@/components/admin/StatsCard'
import DataTable from '@/components/admin/DataTable'
import { Link, useRouter } from '@/lib/navigation'
import { useTranslations } from 'next-intl'
import type { User } from '@/lib/types'

interface DashboardContentProps {
  stats: {
    totalUsers: number
    verifiedUsers: number
    kycPending: number
    blockedUsers: number
    balancesByCurrency: Record<string, { available: number; locked: number }>
    recentActions: any[]
    recentUsers: any[]
  }
}

export default function DashboardContent({ stats }: DashboardContentProps) {
  const t = useTranslations()
  const router = useRouter()

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">{t('dashboard.title')}</h1>
        <p className="text-gray-400">{t('dashboard.overview')}</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatsCard title={t('dashboard.totalUsers')} value={stats.totalUsers} icon="👥" />
        <StatsCard title={t('dashboard.verifiedUsers')} value={stats.verifiedUsers} icon="✅" />
        <StatsCard title={t('dashboard.kycPending')} value={stats.kycPending} icon="⏳" />
        <StatsCard title={t('dashboard.blockedAccounts')} value={stats.blockedUsers} icon="🔒" />
      </div>

      {Object.keys(stats.balancesByCurrency || {}).length > 0 && (
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">{t('dashboard.totalBalancesByCurrency')}</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {Object.entries(stats.balancesByCurrency).map(([currency, balances]: [string, any]) => (
              <div key={currency} className="bg-gray-900 rounded p-4">
                <div className="text-sm text-gray-400 mb-2">{currency}</div>
                <div className="text-lg font-semibold text-white">
                  {t('dashboard.available')}: {parseFloat(balances.available).toFixed(2)}
                </div>
                <div className="text-sm text-gray-400">
                  {t('dashboard.locked')}: {parseFloat(balances.locked).toFixed(2)}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-white">{t('dashboard.recentUsers')}</h2>
            <Link href="/admin/users" className="text-sm text-blue-400 hover:text-blue-300">
              {t('dashboard.viewAll')} →
            </Link>
          </div>
          {stats.recentUsers && stats.recentUsers.length > 0 ? (
            <DataTable
              data={stats.recentUsers}
              columns={[
                { key: 'first_name', label: t('common.name'), render: (_, row: User) => `${row.first_name || ''} ${row.last_name || ''}`.trim() || t('common.nA') },
                { key: 'email', label: t('common.email') },
                { key: 'created_at', label: t('dashboard.joined'), render: (date) => new Date(date).toLocaleDateString() },
              ]}
              onRowClick={(row: User) => router.push(`/admin/users/${row.id}`)}
            />
          ) : (
            <p className="text-gray-400 text-center py-4">{t('dashboard.noRecentUsers')}</p>
          )}
        </div>

        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-white">{t('dashboard.recentActions')}</h2>
            <Link href="/admin/audit" className="text-sm text-blue-400 hover:text-blue-300">
              {t('dashboard.viewAll')} →
            </Link>
          </div>
          {stats.recentActions && stats.recentActions.length > 0 ? (
            <DataTable
              data={stats.recentActions}
              columns={[
                { key: 'action', label: t('audit.action') },
                { key: 'created_at', label: t('common.date'), render: (date) => new Date(date).toLocaleString() },
              ]}
            />
          ) : (
            <p className="text-gray-400 text-center py-4">{t('dashboard.noRecentActions')}</p>
          )}
        </div>
      </div>
    </div>
  )
}
