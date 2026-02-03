import { getLocale } from "next-intl/server";

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const locale = await getLocale();
  return (
    <html lang={locale || "ru"} suppressHydrationWarning>
      <body>{children}</body>
    </html>
  );
}
