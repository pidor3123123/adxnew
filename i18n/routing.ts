import { defineRouting } from 'next-intl/routing';

export const routing = defineRouting({
  locales: ['ru', 'tr'],
  defaultLocale: 'ru',
  localePrefix: 'always',
});
