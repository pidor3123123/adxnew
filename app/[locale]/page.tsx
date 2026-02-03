import { getServerSession } from "next-auth";
import { authOptions, isAdmin } from "@/lib/auth";
import { redirect } from "next/navigation";
import AuthButtons from "@/components/AuthButtons";
import LanguageSwitcher from "@/components/LanguageSwitcher";
import { getTranslations } from "next-intl/server";

export default async function Home({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  const session = await getServerSession(authOptions);
  const userIsAdmin = session?.user?.email
    ? await isAdmin(session.user.email)
    : false;

  if (userIsAdmin) {
    redirect(`/${locale}/admin`);
  }

  const t = await getTranslations();

  return (
    <div className="flex min-h-screen items-center justify-center bg-zinc-50 font-sans dark:bg-black">
      <main className="flex min-h-screen w-full max-w-3xl flex-col items-center justify-between py-32 px-16 bg-white dark:bg-black sm:items-start">
        <div className="w-full flex justify-between items-start mb-8">
          <div className="flex items-center gap-4">
            <h1 className="text-3xl font-bold text-black dark:text-zinc-50">
              {t("home.title")}
            </h1>
            <LanguageSwitcher />
          </div>
          <AuthButtons session={session} isAdmin={userIsAdmin} />
        </div>

        <div className="flex flex-col items-center gap-6 text-center sm:items-start sm:text-left flex-1">
          <div className="space-y-4">
            {session ? (
              <>
                <h2 className="text-2xl font-semibold text-black dark:text-zinc-50">
                  {t("auth.welcome")}, {session.user?.name || session.user?.email}!
                </h2>
                <p className="text-lg text-zinc-600 dark:text-zinc-400">
                  {t("auth.noAdminAccess")}
                </p>
                {session.user?.email && (
                  <p className="text-sm text-zinc-500 dark:text-zinc-500">
                    {t("common.email")}: {session.user.email}
                  </p>
                )}
              </>
            ) : (
              <>
                <h2 className="text-2xl font-semibold text-black dark:text-zinc-50">
                  {t("auth.loginToAccess")}
                </h2>
                <p className="text-lg text-zinc-600 dark:text-zinc-400">
                  {t("auth.loginDescription")}
                </p>
              </>
            )}
          </div>
        </div>
      </main>
    </div>
  );
}
