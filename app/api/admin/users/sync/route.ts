import { NextRequest, NextResponse } from 'next/server'
import { getServerSession } from 'next-auth'
import { authOptions, isAdmin } from '@/lib/auth'
import { supabaseServer } from '@/lib/supabase-server'

export async function GET(request: NextRequest) {
  const session = await getServerSession(authOptions)

  if (!session?.user?.email || !(await isAdmin(session.user.email))) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
  }

  try {
    const supabase = supabaseServer()
    
    // Get all users from auth.users
    const { data: authUsers, error: listError } = await supabase.auth.admin.listUsers()
    
    if (listError) {
      return NextResponse.json({ error: listError.message }, { status: 500 })
    }

    if (!authUsers?.users || authUsers.users.length === 0) {
      return NextResponse.json({ 
        synced: 0, 
        total: 0,
        message: 'No users found in auth.users' 
      })
    }

    let syncedCount = 0
    let skippedCount = 0
    const errors: string[] = []
    const syncedUsers: string[] = []
    const skippedUsers: string[] = []

    // Process each user
    for (const authUser of authUsers.users) {
      try {
        // Check if user already exists in users table
        const { data: existingUser, error: checkError } = await supabase
          .from('users')
          .select('id')
          .eq('id', authUser.id)
          .maybeSingle()

        // If there's an error checking (other than "not found"), log it
        if (checkError && checkError.code !== 'PGRST116') {
          errors.push(`Error checking user ${authUser.email || authUser.id}: ${checkError.message}`)
          continue
        }

        if (existingUser) {
          // User already exists, skip
          skippedCount++
          skippedUsers.push(authUser.email || authUser.id)
          continue
        }

        // Extract metadata
        const metadata = authUser.user_metadata || {}
        const email = authUser.email || ''
        const firstName = metadata.first_name || metadata.name?.split(' ')[0] || email.split('@')[0] || 'User'
        const lastName = metadata.last_name || metadata.name?.split(' ').slice(1).join(' ') || 'User'
        const country = metadata.country || 'US'

        // Create user in users table
        const { error: userError } = await supabase
          .from('users')
          .insert({
            id: authUser.id,
            email: email,
            first_name: firstName,
            last_name: lastName,
            country: country,
            is_verified: false,
            kyc_status: 'pending',
            kyc_verified: false,
            created_at: authUser.created_at || new Date().toISOString(),
          })

        if (userError) {
          errors.push(`Failed to create user ${email}: ${userError.message}`)
          continue
        }

        // Create user_security entry
        const { error: securityError } = await supabase
          .from('user_security')
          .insert({
            user_id: authUser.id,
            two_fa_enabled: false,
            failed_login_attempts: 0,
            created_at: authUser.created_at || new Date().toISOString(),
          })

        if (securityError) {
          errors.push(`Failed to create security entry for ${email}: ${securityError.message}`)
          // Don't fail the whole sync, just log the error
        }

        syncedCount++
        syncedUsers.push(email || authUser.id)
      } catch (error: any) {
        errors.push(`Error processing user ${authUser.email || authUser.id}: ${error.message}`)
      }
    }

    return NextResponse.json({
      success: true,
      synced: syncedCount,
      skipped: skippedCount,
      total: authUsers.users.length,
      syncedUsers: syncedUsers.length > 0 ? syncedUsers : undefined,
      skippedUsers: skippedUsers.length > 0 ? skippedUsers.slice(0, 10) : undefined, // Limit to first 10 for response size
      errors: errors.length > 0 ? errors : undefined,
      message: `Synced ${syncedCount} out of ${authUsers.users.length} users (${skippedCount} already existed)`,
    })
  } catch (error: any) {
    return NextResponse.json({ error: error.message }, { status: 500 })
  }
}
