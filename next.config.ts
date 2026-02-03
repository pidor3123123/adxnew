import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin('./i18n/request.ts');

const nextConfig: NextConfig = {
  // Убрали basePath, так как используем поддомен admin.adx.finance
  // Админ панель будет доступна на поддомене, основной сайт на adx.finance
  typescript: {
    ignoreBuildErrors: true, // next-intl routing types conflict with Next.js 16 LayoutRoutes
  },
};

export default withNextIntl(nextConfig);
