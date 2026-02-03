'use client'

import { useLocale } from 'next-intl'
import { useRouter, usePathname } from '@/lib/navigation'
import { routing } from '@/i18n/routing'

export default function LanguageSwitcher() {
  const locale = useLocale()
  const pathname = usePathname()
  const router = useRouter()

  function switchLocale(newLocale: string) {
    router.replace(pathname, { locale: newLocale })
  }

  return (
    <div className="flex items-center gap-1 p-2">
      {routing.locales.map((loc) => (
        <button
          key={loc}
          onClick={() => switchLocale(loc)}
          className={`px-2 py-1 rounded text-sm font-medium transition-colors ${
            locale === loc
              ? 'bg-blue-600 text-white'
              : 'text-gray-400 hover:text-white hover:bg-gray-800'
          }`}
          title={loc === 'ru' ? 'Русский' : 'Türkçe'}
        >
          {loc === 'ru' ? 'RU' : 'TR'}
        </button>
      ))}
    </div>
  )
}
