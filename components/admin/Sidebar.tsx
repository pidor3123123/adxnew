'use client'

import { Link, usePathname } from '@/lib/navigation'
import { useTranslations, useLocale } from 'next-intl'
import { signOut } from 'next-auth/react'
import LanguageSwitcher from '@/components/LanguageSwitcher'

const navigation = [
  { nameKey: 'nav.dashboard', href: '/admin', icon: '📊' },
  { nameKey: 'nav.users', href: '/admin/users', icon: '👥' },
  { nameKey: 'nav.deposits', href: '/admin/deposits', icon: '💵' },
  { nameKey: 'nav.balances', href: '/admin/balances', icon: '💰' },
  { nameKey: 'nav.documents', href: '/admin/documents', icon: '📄' },
  { nameKey: 'nav.auditLog', href: '/admin/audit', icon: '📝' },
  { nameKey: 'nav.analytics', href: '/admin/analytics', icon: '📈' },
  { nameKey: 'nav.testData', href: '/admin/test-data', icon: '🧪' },
  { nameKey: 'nav.settings', href: '/admin/settings', icon: '⚙️' },
]

export default function Sidebar() {
  const pathname = usePathname()
  const locale = useLocale()
  const t = useTranslations()

  return (
    <div className="w-64 bg-gray-900 border-r border-gray-800 flex flex-col h-screen">
      <div className="p-6 border-b border-gray-800">
        <h1 className="text-xl font-bold text-white">{t('nav.adminPanel')}</h1>
        <div className="mt-3">
          <LanguageSwitcher />
        </div>
      </div>

      <nav className="flex-1 p-4 space-y-2">
        {navigation.map((item) => {
          const isActive =
            pathname === item.href ||
            (item.href !== '/admin' && pathname?.startsWith(item.href))
          return (
            <Link
              key={item.nameKey}
              href={item.href}
              className={`flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors ${
                isActive
                  ? 'bg-gray-800 text-white'
                  : 'text-gray-400 hover:bg-gray-800 hover:text-white'
              }`}
            >
              <span className="text-lg">{item.icon}</span>
              <span className="font-medium">{t(item.nameKey as any)}</span>
            </Link>
          )
        })}
      </nav>

      <div className="p-4 border-t border-gray-800">
        <button
          onClick={() => signOut({ callbackUrl: `/${locale}` })}
          className="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white transition-colors"
        >
          <span className="text-lg">🚪</span>
          <span className="font-medium">{t('nav.signOut')}</span>
        </button>
      </div>
    </div>
  )
}
