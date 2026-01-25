import { redirect } from "next/navigation"
import { getServerSession } from "next-auth"
import { authOptions, getAdminEmails } from "@/lib/auth"
import Sidebar from "@/components/admin/Sidebar"

export default async function AdminLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const session = await getServerSession(authOptions)

  // ❌ не залогинен - редиректим на страницу входа
  if (!session?.user?.email) {
    redirect("/api/auth/signin")
  }

  // ❌ не админ - редиректим на страницу входа
  const admins = getAdminEmails()
  if (admins.length === 0 || !admins.includes(session.user.email)) {
    redirect("/api/auth/signin")
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
