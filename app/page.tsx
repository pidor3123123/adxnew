import { getServerSession } from "next-auth";
import { authOptions, getAdminEmails } from "@/lib/auth";
import { redirect } from "next/navigation";
import AuthButtons from "@/components/AuthButtons";

export default async function Home() {
  const session = await getServerSession(authOptions);
  const admins = getAdminEmails();
  const isAdmin = !!(session?.user?.email && admins.length > 0 && admins.includes(session.user.email));

  // Если пользователь админ, редиректим на админ панель
  if (isAdmin) {
    redirect("/admin");
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-50 font-sans dark:bg-black">
      <main className="flex min-h-screen w-full max-w-3xl flex-col items-center justify-between py-32 px-16 bg-white dark:bg-black sm:items-start">
        <div className="w-full flex justify-between items-start mb-8">
          <h1 className="text-3xl font-bold text-black dark:text-zinc-50">
            ADX Finance Admin
          </h1>
          <AuthButtons session={session} isAdmin={isAdmin} />
        </div>

        <div className="flex flex-col items-center gap-6 text-center sm:items-start sm:text-left flex-1">
          <div className="space-y-4">
            {session ? (
              <>
                <h2 className="text-2xl font-semibold text-black dark:text-zinc-50">
                  Добро пожаловать, {session.user?.name || session.user?.email}!
                </h2>
                <p className="text-lg text-zinc-600 dark:text-zinc-400">
                  У вас нет доступа к админ панели.
                </p>
                {session.user?.email && (
                  <p className="text-sm text-zinc-500 dark:text-zinc-500">
                    Email: {session.user.email}
                  </p>
                )}
              </>
            ) : (
              <>
                <h2 className="text-2xl font-semibold text-black dark:text-zinc-50">
                  Войдите для доступа к админ панели
                </h2>
                <p className="text-lg text-zinc-600 dark:text-zinc-400">
                  Используйте GitHub для входа в систему
                </p>
              </>
            )}
          </div>
        </div>
      </main>
    </div>
  );
}
