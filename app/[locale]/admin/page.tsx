import DashboardContent from '@/components/admin/DashboardContent'

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
  return <DashboardContent stats={stats} />
}
