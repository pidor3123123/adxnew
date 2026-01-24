import StatsCard from '@/components/admin/StatsCard'
import DataTable from '@/components/admin/DataTable'
import Link from 'next/link'
import type { User, AdminActionLog } from '@/lib/types'

async function getDashboardData() {
  const baseUrl = process.env.NEXTAUTH_URL || 'http://localhost:3000'
  const res = await fetch(`${baseUrl}/api/admin/stats`, {
    cache: 'no-store',
  })
  
  if (!res.ok) {
    return {
      totalUsers: 0,
      verifiedUsers: 0,
      kycPending: 0,
      blockedUsers: 0,
      balancesByCurrency: {},
      recentActions: [],
      recentUsers: [],
    }
  }
  
  return res.json()
}

export default async function AdminPage() {
  const stats = await getDashboardData()

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Dashboard</h1>
        <p className="text-gray-400">Overview of your system</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatsCard
          title="Total Users"
          value={stats.totalUsers}
          icon="👥"
        />
        <StatsCard
          title="Verified Users"
          value={stats.verifiedUsers}
          icon="✅"
        />
        <StatsCard
          title="KYC Pending"
          value={stats.kycPending}
          icon="⏳"
        />
        <StatsCard
          title="Blocked Accounts"
          value={stats.blockedUsers}
          icon="🔒"
        />
      </div>

      {/* Balances Summary */}
      {Object.keys(stats.balancesByCurrency || {}).length > 0 && (
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <h2 className="text-xl font-semibold text-white mb-4">Total Balances by Currency</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {Object.entries(stats.balancesByCurrency).map(([currency, balances]: [string, any]) => (
              <div key={currency} className="bg-gray-900 rounded p-4">
                <div className="text-sm text-gray-400 mb-2">{currency}</div>
                <div className="text-lg font-semibold text-white">
                  Available: {parseFloat(balances.available).toFixed(2)}
                </div>
                <div className="text-sm text-gray-400">
                  Locked: {parseFloat(balances.locked).toFixed(2)}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Users */}
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-white">Recent Users</h2>
            <Link href="/admin/users" className="text-sm text-blue-400 hover:text-blue-300">
              View all →
            </Link>
          </div>
          {stats.recentUsers && stats.recentUsers.length > 0 ? (
            <DataTable
              data={stats.recentUsers}
              columns={[
                { key: 'first_name', label: 'Name', render: (_, row: User) => `${row.first_name || ''} ${row.last_name || ''}`.trim() || 'N/A' },
                { key: 'email', label: 'Email' },
                { key: 'created_at', label: 'Joined', render: (date) => new Date(date).toLocaleDateString() },
              ]}
              onRowClick={(row: User) => {
                window.location.href = `/admin/users/${row.id}`
              }}
            />
          ) : (
            <p className="text-gray-400 text-center py-4">No recent users</p>
          )}
        </div>

        {/* Recent Actions */}
        <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-white">Recent Actions</h2>
            <Link href="/admin/audit" className="text-sm text-blue-400 hover:text-blue-300">
              View all →
            </Link>
          </div>
          {stats.recentActions && stats.recentActions.length > 0 ? (
            <DataTable
              data={stats.recentActions}
              columns={[
                { key: 'action', label: 'Action' },
                { key: 'created_at', label: 'Date', render: (date) => new Date(date).toLocaleString() },
              ]}
            />
          ) : (
            <p className="text-gray-400 text-center py-4">No recent actions</p>
          )}
        </div>
      </div>
    </div>
  )
}
