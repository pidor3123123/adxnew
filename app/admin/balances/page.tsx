'use client'

import { useEffect, useState } from 'react'
import DataTable from '@/components/admin/DataTable'
import Modal from '@/components/admin/Modal'
import type { UserBalance } from '@/lib/types'

interface BalanceWithUser extends UserBalance {
  users: {
    id: string
    first_name: string | null
    last_name: string | null
    email: string | null
  }
}

export default function BalancesPage() {
  const [balances, setBalances] = useState<BalanceWithUser[]>([])
  const [loading, setLoading] = useState(true)
  const [currencyFilter, setCurrencyFilter] = useState('')
  const [editModalOpen, setEditModalOpen] = useState(false)
  const [selectedBalance, setSelectedBalance] = useState<BalanceWithUser | null>(null)
  const [availableBalance, setAvailableBalance] = useState('')
  const [lockedBalance, setLockedBalance] = useState('')

  useEffect(() => {
    fetchBalances()
  }, [currencyFilter])

  async function fetchBalances() {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('limit', '100')
      if (currencyFilter) params.set('currency', currencyFilter)
      
      const res = await fetch(`/api/admin/balances?${params}`)
      const data = await res.json()
      setBalances(data.data || [])
    } catch (error) {
      console.error('Failed to fetch balances:', error)
    } finally {
      setLoading(false)
    }
  }

  function openEditModal(balance: BalanceWithUser) {
    setSelectedBalance(balance)
    setAvailableBalance(balance.available_balance.toString())
    setLockedBalance(balance.locked_balance.toString())
    setEditModalOpen(true)
  }

  async function handleSave() {
    if (!selectedBalance) return

    try {
      const res = await fetch('/api/admin/balances', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          balanceId: selectedBalance.id,
          available_balance: parseFloat(availableBalance),
          locked_balance: parseFloat(lockedBalance),
        }),
      })

      if (res.ok) {
        setEditModalOpen(false)
        setSelectedBalance(null)
        fetchBalances()
      } else {
        const error = await res.json()
        alert(error.error || 'Failed to update balance')
      }
    } catch (error) {
      console.error('Failed to update balance:', error)
      alert('Failed to update balance')
    }
  }

  const currencies = Array.from(new Set(balances.map(b => b.currency)))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Balances</h1>
        <p className="text-gray-400">Manage user balances</p>
      </div>

      {/* Filter */}
      <div>
        <select
          value={currencyFilter}
          onChange={(e) => setCurrencyFilter(e.target.value)}
          className="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Currencies</option>
          {currencies.map(currency => (
            <option key={currency} value={currency}>{currency}</option>
          ))}
        </select>
      </div>

      {/* Balances Table */}
      <DataTable
        data={balances}
        columns={[
          {
            key: 'users',
            label: 'User',
            render: (user) => {
              const name = `${user?.first_name || ''} ${user?.last_name || ''}`.trim()
              return name || user?.email || 'N/A'
            },
          },
          { key: 'currency', label: 'Currency' },
          {
            key: 'available_balance',
            label: 'Available',
            render: (val) => parseFloat(val).toFixed(2),
          },
          {
            key: 'locked_balance',
            label: 'Locked',
            render: (val) => parseFloat(val).toFixed(2),
          },
          {
            key: 'updated_at',
            label: 'Updated',
            render: (date) => new Date(date).toLocaleString(),
          },
        ]}
        onRowClick={openEditModal}
        isLoading={loading}
        emptyMessage="No balances found"
      />

      <Modal
        isOpen={editModalOpen}
        onClose={() => {
          setEditModalOpen(false)
          setSelectedBalance(null)
        }}
        title="Edit Balance"
      >
        {selectedBalance && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                User
              </label>
              <p className="text-white">
                {selectedBalance.users?.first_name} {selectedBalance.users?.last_name} ({selectedBalance.users?.email})
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Currency
              </label>
              <p className="text-white">{selectedBalance.currency}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Available Balance
              </label>
              <input
                type="number"
                step="0.01"
                value={availableBalance}
                onChange={(e) => setAvailableBalance(e.target.value)}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Locked Balance
              </label>
              <input
                type="number"
                step="0.01"
                value={lockedBalance}
                onChange={(e) => setLockedBalance(e.target.value)}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => {
                  setEditModalOpen(false)
                  setSelectedBalance(null)
                }}
                className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleSave}
                className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
              >
                Save
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
