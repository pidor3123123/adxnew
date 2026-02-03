'use client'

import { useEffect, useState } from 'react'
import StatsCard from '@/components/admin/StatsCard'
import { useTranslations } from 'next-intl'

interface AnalyticsData {
  totalUsers: number
  verifiedUsers: number
  kycPending: number
  blockedUsers: number
  balancesByCurrency: Record<string, { available: number; locked: number }>
  recentActions: any[]
  recentUsers: any[]
}

export default function AnalyticsPage() {
  const t = useTranslations()
  const [data, setData] = useState<AnalyticsData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchAnalytics()
  }, [])

  async function fetchAnalytics() {
    setLoading(true)
    try {
      const res = await fetch('/api/admin/stats')
      const stats = await res.json()
      setData(stats)
    } catch (error) {
      console.error('Failed to fetch analytics:', error)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-gray-400">{t('analytics.loadingAnalytics')}</div>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-gray-400">{t('analytics.loadError')}</div>
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">{t('analytics.title')}</h1>
        <p className="text-gray-400">{t('analytics.subtitle')}</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatsCard
          title={t('analytics.totalUsers')}
          value={data.totalUsers}
          icon="👥"
        />
        <StatsCard
          title={t('analytics.verified')}
          value={data.verifiedUsers}
          subtitle={`${data.totalUsers > 0 ? ((data.verifiedUsers / data.totalUsers) * 100).toFixed(1) : 0}% ${t('analytics.ofTotal')}`}
          icon="✅"
        />
        <StatsCard
          title={t('dashboard.kycPending')}
          value={data.kycPending}
          icon="⏳"
        />
        <StatsCard
          title={t('dashboard.blockedAccounts')}
          value={data.blockedUsers}
          icon="🔒"
        />
      </div>

      {/* Balances Summary */}
      {Object.keys(data.balancesByCurrency || {}).length > 0 && (
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">{t('analytics.totalBalancesByCurrency')}</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {Object.entries(data.balancesByCurrency).map(([currency, balances]) => (
              <div key={currency} className="bg-gray-900 rounded p-4">
                <div className="text-sm text-gray-400 mb-2">{currency}</div>
                <div className="text-lg font-semibold text-white">
                  {t('analytics.available')}: {parseFloat(balances.available.toString()).toFixed(2)}
                </div>
                <div className="text-sm text-gray-400">
                  {t('analytics.locked')}: {parseFloat(balances.locked.toString()).toFixed(2)}
                </div>
                <div className="text-sm text-gray-500 mt-2">
                  {t('analytics.total')}: {parseFloat((balances.available + balances.locked).toString()).toFixed(2)}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Additional Analytics Sections */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">{t('analytics.userVerificationRate')}</h2>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-400">{t('analytics.verified')}</span>
              <span className="text-white">{data.verifiedUsers} / {data.totalUsers}</span>
            </div>
            <div className="w-full bg-gray-700 rounded-full h-2">
              <div
                className="bg-green-500 h-2 rounded-full"
                style={{ width: `${data.totalUsers > 0 ? (data.verifiedUsers / data.totalUsers) * 100 : 0}%` }}
              />
            </div>
          </div>
        </div>

        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">{t('analytics.kycStatusDistribution')}</h2>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-gray-400">{t('analytics.pending')}</span>
              <span className="text-white">{data.kycPending}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-gray-400">{t('analytics.totalUsers')}</span>
              <span className="text-white">{data.totalUsers}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Export Section */}
      <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 className="text-xl font-semibold text-white mb-4">{t('analytics.exportData')}</h2>
        <p className="text-gray-400 mb-4">{t('analytics.exportDescription')}</p>
        <button
          onClick={() => {
            const csv = `Metric,Value\nTotal Users,${data.totalUsers}\nVerified Users,${data.verifiedUsers}\nKYC Pending,${data.kycPending}\nBlocked Users,${data.blockedUsers}`
            const blob = new Blob([csv], { type: 'text/csv' })
            const url = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = `analytics-${new Date().toISOString().split('T')[0]}.csv`
            a.click()
          }}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
        >
          {t('analytics.exportCsv')}
        </button>
      </div>
    </div>
  )
}
