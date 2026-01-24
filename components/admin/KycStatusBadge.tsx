import type { KycStatus } from '@/lib/types'

interface KycStatusBadgeProps {
  status: KycStatus | null | undefined
}

export default function KycStatusBadge({ status }: KycStatusBadgeProps) {
  if (!status) {
    return (
      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">
        Unknown
      </span>
    )
  }

  const statusConfig = {
    pending: { bg: 'bg-yellow-900', text: 'text-yellow-300', label: 'Pending' },
    approved: { bg: 'bg-green-900', text: 'text-green-300', label: 'Approved' },
    rejected: { bg: 'bg-red-900', text: 'text-red-300', label: 'Rejected' },
    under_review: { bg: 'bg-blue-900', text: 'text-blue-300', label: 'Under Review' },
  }

  const config = statusConfig[status] || statusConfig.pending

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
      {config.label}
    </span>
  )
}
