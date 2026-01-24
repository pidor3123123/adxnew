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

  // ❌ не залогинен
  if (!session?.user?.email) {
    redirect("/")
  }

  // ❌ не админ
  const admins = getAdminEmails()
  if (admins.length === 0 || !admins.includes(session.user.email)) {
    redirect("/")
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
