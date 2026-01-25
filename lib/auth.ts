import { type NextAuthOptions } from "next-auth"
import GitHubProvider from "next-auth/providers/github"
import CredentialsProvider from "next-auth/providers/credentials"
import { supabaseServer } from "./supabase-server"

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
    CredentialsProvider({
      name: "Email",
      credentials: {
        email: { label: "Email", type: "email" },
        password: { label: "Password", type: "password" },
      },
      async authorize(credentials) {
        if (!credentials?.email || !credentials?.password) {
          return null
        }

        try {
          // Используем Supabase Auth для проверки пароля
          const supabaseUrl = process.env.NEXT_PUBLIC_SUPABASE_URL
          if (!supabaseUrl) {
            throw new Error("NEXT_PUBLIC_SUPABASE_URL is not set")
          }

          // Проверяем пароль через Supabase Auth REST API
          // Используем правильный формат для Supabase Auth
          const response = await fetch(`${supabaseUrl}/auth/v1/token?grant_type=password`, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "apikey": process.env.SUPABASE_SERVICE_ROLE_KEY!,
              "Authorization": `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY!}`,
            },
            body: JSON.stringify({
              email: credentials.email,
              password: credentials.password,
            }),
          })

          if (!response.ok) {
            const errorData = await response.json().catch(() => ({}))
            console.error("Supabase Auth error:", errorData)
            return null
          }

          const data = await response.json()

          if (!data.user) {
            return null
          }

          // Проверяем, что пользователь является админом
          const supabase = supabaseServer()
          const { data: admin } = await supabase
            .from("admins")
            .select("*")
            .eq("email", credentials.email)
            .single()

          if (!admin) {
            // Проверяем также переменную окружения ADMIN_EMAILS
            const adminEmails = getAdminEmails()
            if (!adminEmails.includes(credentials.email)) {
              return null
            }
          }

          return {
            id: data.user.id,
            email: data.user.email,
            name: data.user.email,
          }
        } catch (error) {
          console.error("Auth error:", error)
          return null
        }
      },
    }),
  ],
  session: {
    strategy: "jwt",
  },
  secret: process.env.NEXTAUTH_SECRET,
  pages: {
    signIn: "/",
  },
}
