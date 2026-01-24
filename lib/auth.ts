import { type NextAuthOptions } from "next-auth"
import GitHubProvider from "next-auth/providers/github"

/**
 * Получение списка админов из переменной окружения
 */
export function getAdminEmails(): string[] {
  const admins = process.env.ADMIN_EMAILS
  if (!admins) {
    return []
  }
  return admins.split(',').map(email => email.trim()).filter(email => email.length > 0)
}

export const authOptions: NextAuthOptions = {
  providers: [
    GitHubProvider({
      clientId: process.env.GITHUB_CLIENT_ID!,
      clientSecret: process.env.GITHUB_CLIENT_SECRET!,
    }),
  ],
  session: {
    strategy: "jwt",
  },
  secret: process.env.NEXTAUTH_SECRET,
}
