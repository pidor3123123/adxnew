'use client'

import { useEffect, useState } from 'react'
import { useTranslations } from 'next-intl'

interface DepositRequest {
  id: number
  user_id: number
  amount: number
  method: string
  status: string
  created_at: string
  processed_at: string | null
  email: string | null
  first_name: string | null
  last_name: string | null
}

export default function DepositsPage() {
  const t = useTranslations()
  const [requests, setRequests] = useState<DepositRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    fetchDeposits()
  }, [])

  async function fetchDeposits() {
    setLoading(true)
    setError(null)
    try {
      const res = await fetch('/api/admin/deposits?status=PENDING')
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || 'Failed to fetch')
      setRequests(data.deposit_requests || [])
    } catch (err: any) {
      setError(err.message || 'Failed to fetch deposits')
    } finally {
      setLoading(false)
    }
  }

  async function handleApprove(id: number) {
    try {
      const res = await fetch('/api/admin/deposits', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'approve', id }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || 'Failed to approve')
      alert(t('deposits.approveSuccess'))
      fetchDeposits()
    } catch (err: any) {
      alert(err.message || 'Failed to approve')
    }
  }

  async function handleReject(id: number, notes?: string) {
    try {
      const res = await fetch('/api/admin/deposits', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reject', id, request_id: id, notes }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || 'Failed to reject')
      alert(t('deposits.rejectSuccess'))
      fetchDeposits()
    } catch (err: any) {
      alert(err.message || 'Failed to reject')
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">{t('deposits.title')}</h1>
        <p className="text-gray-400">{t('deposits.subtitle')}</p>
      </div>

      {error && (
        <div className="p-4 bg-red-900/30 border border-red-700 rounded-lg text-red-200">
          {error}
        </div>
      )}

      {loading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500" />
        </div>
      ) : requests.length === 0 ? (
        <div className="p-12 text-center text-gray-400 bg-gray-800/50 rounded-lg">
          {t('deposits.noDeposits')}
        </div>
      ) : (
        <div className="space-y-4">
          {requests.map((req) => (
            <div
              key={req.id}
              className="p-6 bg-gray-800 border border-gray-700 rounded-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-2">
                  <span className="text-xl font-bold text-white">
                    ${Number(req.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                  </span>
                  <span className="text-gray-400">•</span>
                  <span className="text-gray-300">{req.method}</span>
                </div>
                <div className="text-sm text-gray-400">
                  {req.email}
                  {req.first_name || req.last_name
                    ? ` (${[req.first_name, req.last_name].filter(Boolean).join(' ')})`
                    : ''}
                </div>
                <div className="text-xs text-gray-500 mt-1">
                  {new Date(req.created_at).toLocaleString()}
                </div>
              </div>
              <div className="flex gap-3">
                <button
                  onClick={() => handleApprove(req.id)}
                  className="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg font-medium transition-colors"
                >
                  {t('deposits.approve')}
                </button>
                <button
                  onClick={() => handleReject(req.id)}
                  className="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg font-medium transition-colors"
                >
                  {t('deposits.reject')}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
