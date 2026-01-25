import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Убрали basePath, так как используем поддомен admin.adx.finance
  // Админ панель будет доступна на поддомене, основной сайт на adx.finance
};

export default nextConfig;
