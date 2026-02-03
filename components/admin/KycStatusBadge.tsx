'use client'

import type { KycStatus } from '@/lib/types'
import { useTranslations } from 'next-intl'

interface KycStatusBadgeProps {
  status: KycStatus | null | undefined
}

export default function KycStatusBadge({ status }: KycStatusBadgeProps) {
  const t = useTranslations('kycStatus')
  if (!status) {
    return (
      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">
        {t('unknown')}
      </span>
    )
  }

  const statusConfig = {
    pending: { bg: 'bg-yellow-900', text: 'text-yellow-300', labelKey: 'pending' as const },
    approved: { bg: 'bg-green-900', text: 'text-green-300', labelKey: 'approved' as const },
    rejected: { bg: 'bg-red-900', text: 'text-red-300', labelKey: 'rejected' as const },
    under_review: { bg: 'bg-blue-900', text: 'text-blue-300', labelKey: 'underReview' as const },
  }

  const config = statusConfig[status] || statusConfig.pending

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}>
      {t(config.labelKey)}
    </span>
  )
}
