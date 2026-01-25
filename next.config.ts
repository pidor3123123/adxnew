import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  basePath: '/admin',
  // Для работы на подпути /admin
  // Все маршруты будут автоматически иметь префикс /admin
};

export default nextConfig;
