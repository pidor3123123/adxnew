import { redirect } from "next/navigation"
import { getServerSession } from "next-auth"
import { getLocale } from "next-intl/server"
import { authOptions, isAdmin } from "@/lib/auth"
import Sidebar from "@/components/admin/Sidebar"

export default async function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const locale = await getLocale()
  const session = await getServerSession(authOptions)

  if (!session?.user?.email) {
    redirect(`/api/auth/signin?callbackUrl=/${locale}/admin`)
  }

  const userIsAdmin = await isAdmin(session.user.email)
  if (!userIsAdmin) {
    redirect(`/api/auth/signin?callbackUrl=/${locale}/admin`)
  }

  return (
    <div className="flex min-h-screen bg-gray-900 text-white">
      <Sidebar />
      <main className="flex-1 overflow-auto">
        <div className="p-8">
          {children}
        </div>
      </main>
    </div>
  )
}
