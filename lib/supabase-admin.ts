import { supabaseServer } from "./supabase-server"
import type { 
  User, 
  UserSecurity, 
  UserDocument, 
  UserBalance, 
  Admin,
  AdminActionLog,
  AdminActionAuditLog,
  UserWithSecurity,
  UserWithDetails
} from "./types"

// Helper function to send webhook to main site
async function sendWebhook(type: string, payload: Record<string, any>): Promise<void> {
  const webhookUrl = process.env.WEBHOOK_URL
  const webhookSecret = process.env.WEBHOOK_SECRET
  
  if (!webhookUrl || !webhookSecret) {
    console.error('Webhook configuration missing: WEBHOOK_URL and WEBHOOK_SECRET must be set')
    return
  }
  
  try {
    const response = await fetch(webhookUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Webhook-Secret': webhookSecret,
      },
      body: JSON.stringify({
        type,
        payload,
      }),
    })

    if (!response.ok) {
      const errorText = await response.text()
      console.error(`Webhook failed: ${response.status} - ${errorText}`)
    }
  } catch (error: any) {
    // Don't throw - webhook failures shouldn't break the main operation
    console.error('Webhook error:', error.message)
  }
}

function getSupabase() {
  return supabaseServer()
}

// Helper function to get current admin ID from email
export async function getAdminIdByEmail(email: string): Promise<string | null> {
  const supabase = getSupabase()
  const { data, error } = await supabase
    .from('admins')
    .select('id')
    .eq('email', email)
    .single()
  
  if (error || !data) return null
  return data.id
}

// Helper function to get or create admin ID from email
export async function getOrCreateAdminIdByEmail(email: string): Promise<string> {
  let adminId = await getAdminIdByEmail(email)
  
  if (!adminId) {
    const supabase = getSupabase()
    const { data: newAdmin, error: createError } = await supabase
      .from('admins')
      .insert({
        email: email,
        role: 'admin',
      })
      .select()
      .single()
    
    if (createError || !newAdmin) {
      throw new Error(`Failed to create admin: ${createError?.message || 'Unknown error'}`)
    }
    
    return newAdmin.id
  }
  
  return adminId
}

// Helper function to log admin action
export async function logAdminAction(
  adminId: string,
  userId: string | null,
  action: string
): Promise<void> {
  const supabase = getSupabase()
  await supabase
    .from('admin_actions_log')
    .insert({
      admin_id: adminId,
      user_id: userId,
      action,
    })
}

// Helper function to log audit trail
export async function logAudit(
  adminId: string,
  action: string,
  tableName: string,
  recordId: string,
  oldData: Record<string, any> | null,
  newData: Record<string, any> | null
): Promise<void> {
  const supabase = getSupabase()
  await supabase
    .from('admin_actions_audit_log')
    .insert({
      admin_id: adminId,
      action,
      table_name: tableName,
      record_id: recordId,
      old_data: oldData,
      new_data: newData,
    })
}

// Users
export async function getUsers(limit = 50, offset = 0, search?: string) {
  const supabase = getSupabase()
  let query = supabase
    .from('users')
    .select(`
      *,
      user_security (*),
      user_balances (*)
    `)
    .order('created_at', { ascending: false })
    .range(offset, offset + limit - 1)

  if (search) {
    query = query.or(`first_name.ilike.%${search}%,last_name.ilike.%${search}%,email.ilike.%${search}%`)
  }

  return query
}

export async function getUserById(id: string): Promise<UserWithDetails | null> {
  const supabase = getSupabase()
  const { data: user, error: userError } = await supabase
    .from('users')
    .select('*')
    .eq('id', id)
    .single()

  if (userError || !user) return null

  const [security, documents, balances, actions] = await Promise.all([
    supabase.from('user_security').select('*').eq('user_id', id).single(),
    supabase.from('user_documents').select('*').eq('user_id', id).order('uploaded_at', { ascending: false }),
    supabase.from('user_balances').select('*').eq('user_id', id),
    supabase.from('admin_actions_log').select('*').eq('user_id', id).order('created_at', { ascending: false }).limit(50),
  ])

  return {
    ...user,
    user_security: security.data,
    user_documents: documents.data || [],
    user_balances: balances.data || [],
    admin_actions: actions.data || [],
  }
}

export async function updateUser(
  adminId: string,
  userId: string,
  updates: Partial<User>
): Promise<{ success: boolean; error?: string }> {
  const supabase = getSupabase()
  // Get old data
  const { data: oldData } = await supabase
    .from('users')
    .select('*')
    .eq('id', userId)
    .single()

  // Update user
  const { error } = await supabase
    .from('users')
    .update(updates)
    .eq('id', userId)

  if (error) {
    return { success: false, error: error.message }
  }

  // Get new data
  const { data: newData } = await supabase
    .from('users')
    .select('*')
    .eq('id', userId)
    .single()

  // Log audit
  await logAudit(adminId, 'update_user', 'users', userId, oldData, newData)
  await logAdminAction(adminId, userId, `Updated user: ${userId}`)

  // Send webhook to main site
  await sendWebhook('user_updated', {
    email: newData?.email || oldData?.email,
    updates: updates,
  })

  return { success: true }
}

export async function blockUser(
  adminId: string,
  userId: string,
  lockedUntil: string | null
): Promise<{ success: boolean; error?: string }> {
  const supabase = getSupabase()
  // Get old security data
  const { data: oldSecurity } = await supabase
    .from('user_security')
    .select('*')
    .eq('user_id', userId)
    .single()

  // Update or insert security
  const { error } = await supabase
    .from('user_security')
    .upsert({
      user_id: userId,
      account_locked_until: lockedUntil,
    }, {
      onConflict: 'user_id'
    })

  if (error) {
    return { success: false, error: error.message }
  }

  // Get new security data
  const { data: newSecurity } = await supabase
    .from('user_security')
    .select('*')
    .eq('user_id', userId)
    .single()

  // Log audit
  await logAudit(adminId, lockedUntil ? 'block_user' : 'unblock_user', 'user_security', userId, oldSecurity, newSecurity)
  await logAdminAction(adminId, userId, lockedUntil ? `Blocked user: ${userId}` : `Unblocked user: ${userId}`)

  // Get user email for webhook
  const { data: user } = await supabase
    .from('users')
    .select('email')
    .eq('id', userId)
    .single()

  // Send webhook to main site
  await sendWebhook(lockedUntil ? 'user_blocked' : 'user_unblocked', {
    email: user?.email,
    locked_until: lockedUntil,
  })

  return { success: true }
}

// Documents
export async function getDocuments(limit = 50, offset = 0, filters?: { status?: string; type?: string }) {
  const supabase = getSupabase()
  let query = supabase
    .from('user_documents')
    .select(`
      *,
      users!inner (id, first_name, last_name, email)
    `)
    .order('uploaded_at', { ascending: false })
    .range(offset, offset + limit - 1)

  if (filters?.status) {
    query = query.eq('status', filters.status)
  }
  if (filters?.type) {
    query = query.eq('type', filters.type)
  }

  return query
}

export async function approveDocument(
  adminId: string,
  documentId: string,
  approved: boolean
): Promise<{ success: boolean; error?: string }> {
  const supabase = getSupabase()
  // Get old document data
  const { data: oldDoc } = await supabase
    .from('user_documents')
    .select('*')
    .eq('id', documentId)
    .single()

  if (!oldDoc) {
    return { success: false, error: 'Document not found' }
  }

  const newStatus = approved ? 'approved' : 'rejected'

  // Update document
  const { error: docError } = await supabase
    .from('user_documents')
    .update({ status: newStatus })
    .eq('id', documentId)

  if (docError) {
    return { success: false, error: docError.message }
  }

  // If approved, update user KYC status
  if (approved) {
    await supabase
      .from('users')
      .update({ 
        kyc_status: 'approved',
        kyc_verified: true 
      })
      .eq('id', oldDoc.user_id)
  }

  // Get new document data
  const { data: newDoc } = await supabase
    .from('user_documents')
    .select('*')
    .eq('id', documentId)
    .single()

  // Log audit
  await logAudit(adminId, `document_${newStatus}`, 'user_documents', documentId, oldDoc, newDoc)
  await logAdminAction(adminId, oldDoc.user_id, `${approved ? 'Approved' : 'Rejected'} document: ${documentId}`)

  return { success: true }
}

// Balances
export async function getBalances(limit = 50, offset = 0, currency?: string) {
  const supabase = getSupabase()
  let query = supabase
    .from('user_balances')
    .select(`
      *,
      users!inner (id, first_name, last_name, email)
    `)
    .order('updated_at', { ascending: false })
    .range(offset, offset + limit - 1)

  if (currency) {
    query = query.eq('currency', currency)
  }

  return query
}

export async function updateBalance(
  adminId: string,
  balanceId: string,
  updates: { available_balance?: number; locked_balance?: number }
): Promise<{ success: boolean; error?: string }> {
  const supabase = getSupabase()
  // Get old balance data
  const { data: oldBalance } = await supabase
    .from('user_balances')
    .select('*')
    .eq('id', balanceId)
    .single()

  if (!oldBalance) {
    return { success: false, error: 'Balance not found' }
  }

  // Update balance
  const { error } = await supabase
    .from('user_balances')
    .update({
      ...updates,
      updated_at: new Date().toISOString(),
    })
    .eq('id', balanceId)

  if (error) {
    return { success: false, error: error.message }
  }

  // Get new balance data
  const { data: newBalance } = await supabase
    .from('user_balances')
    .select('*')
    .eq('id', balanceId)
    .single()

  // Log audit
  await logAudit(adminId, 'update_balance', 'user_balances', balanceId, oldBalance, newBalance)
  await logAdminAction(adminId, oldBalance.user_id, `Updated balance: ${balanceId}`)

  // Get user email for webhook
  const { data: user } = await supabase
    .from('users')
    .select('email')
    .eq('id', oldBalance.user_id)
    .single()

  // Send webhook to main site
  await sendWebhook('balance_updated', {
    user_id: oldBalance.user_id, // Supabase UUID
    email: user?.email,
    currency: oldBalance.currency,
    available_balance: updates.available_balance ?? oldBalance.available_balance,
    locked_balance: updates.locked_balance ?? oldBalance.locked_balance,
  })

  return { success: true }
}

// Audit Log
export async function getAuditLogs(limit = 50, offset = 0, filters?: { table_name?: string; admin_id?: string }) {
  const supabase = getSupabase()
  let query = supabase
    .from('admin_actions_audit_log')
    .select(`
      *,
      admins!inner (id, email, role)
    `)
    .order('created_at', { ascending: false })
    .range(offset, offset + limit - 1)

  if (filters?.table_name) {
    query = query.eq('table_name', filters.table_name)
  }
  if (filters?.admin_id) {
    query = query.eq('admin_id', filters.admin_id)
  }

  return query
}

// Admins
export async function getAdmins() {
  const supabase = getSupabase()
  return supabase
    .from('admins')
    .select('*')
    .order('created_at', { ascending: false })
}

export async function createAdmin(
  adminId: string,
  email: string,
  role: string
): Promise<{ success: boolean; error?: string; data?: Admin }> {
  const supabase = getSupabase()
  const { data, error } = await supabase
    .from('admins')
    .insert({ email, role })
    .select()
    .single()

  if (error) {
    return { success: false, error: error.message }
  }

  await logAudit(adminId, 'create_admin', 'admins', data.id, null, data)
  await logAdminAction(adminId, null, `Created admin: ${email}`)

  return { success: true, data }
}

// Create admin with password using Supabase Auth
export async function createAdminWithPassword(
  adminId: string,
  email: string,
  password: string,
  role: string
): Promise<{ success: boolean; error?: string; data?: Admin }> {
  const supabase = getSupabase()
  
  try {
    // Create user in Supabase Auth
    const { data: authUser, error: authError } = await supabase.auth.admin.createUser({
      email,
      password,
      email_confirm: true, // Auto-confirm email
    })

    if (authError) {
      return { success: false, error: authError.message }
    }

    if (!authUser.user) {
      return { success: false, error: 'Failed to create user in auth' }
    }

    // Create admin record in admins table
    const { data: admin, error: adminError } = await supabase
      .from('admins')
      .insert({ 
        id: authUser.user.id, // Use auth user ID
        email, 
        role 
      })
      .select()
      .single()

    if (adminError) {
      // If admin record creation fails, try to delete the auth user
      await supabase.auth.admin.deleteUser(authUser.user.id)
      return { success: false, error: adminError.message }
    }

    await logAudit(adminId, 'create_admin', 'admins', admin.id, null, admin)
    await logAdminAction(adminId, null, `Created admin with password: ${email}`)

    return { success: true, data: admin }
  } catch (error: any) {
    return { success: false, error: error.message || 'Unknown error' }
  }
}

// Sync users from auth.users to users table
export async function syncUsersFromAuth(): Promise<{
  success: boolean
  synced: number
  skipped: number
  total: number
  errors?: string[]
}> {
  const supabase = getSupabase()
  
  try {
    // Get all users from auth.users
    const { data: authUsers, error: listError } = await supabase.auth.admin.listUsers()
    
    if (listError) {
      return { success: false, synced: 0, skipped: 0, total: 0, errors: [listError.message] }
    }

    if (!authUsers?.users || authUsers.users.length === 0) {
      return { success: true, synced: 0, skipped: 0, total: 0 }
    }

    let syncedCount = 0
    let skippedCount = 0
    const errors: string[] = []

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
      } catch (error: any) {
        errors.push(`Error processing user ${authUser.email || authUser.id}: ${error.message}`)
      }
    }

    return {
      success: true,
      synced: syncedCount,
      skipped: skippedCount,
      total: authUsers.users.length,
      errors: errors.length > 0 ? errors : undefined,
    }
  } catch (error: any) {
    return { success: false, synced: 0, skipped: 0, total: 0, errors: [error.message] }
  }
}

export async function deleteAdmin(
  adminId: string,
  targetAdminId: string
): Promise<{ success: boolean; error?: string }> {
  const supabase = getSupabase()
  // Get old admin data
  const { data: oldAdmin } = await supabase
    .from('admins')
    .select('*')
    .eq('id', targetAdminId)
    .single()

  if (!oldAdmin) {
    return { success: false, error: 'Admin not found' }
  }

  const { error } = await supabase
    .from('admins')
    .delete()
    .eq('id', targetAdminId)

  if (error) {
    return { success: false, error: error.message }
  }

  await logAudit(adminId, 'delete_admin', 'admins', targetAdminId, oldAdmin, null)
  await logAdminAction(adminId, null, `Deleted admin: ${oldAdmin.email}`)

  return { success: true }
}

// Statistics
export async function getStats() {
  const supabase = getSupabase()
  const [
    totalUsers,
    verifiedUsers,
    kycPending,
    blockedUsers,
    totalBalances,
    recentActions,
    recentUsers,
  ] = await Promise.all([
    supabase.from('users').select('id', { count: 'exact', head: true }),
    supabase.from('users').select('id', { count: 'exact', head: true }).eq('is_verified', true),
    supabase.from('users').select('id', { count: 'exact', head: true }).eq('kyc_status', 'pending'),
    supabase.from('user_security').select('user_id', { count: 'exact', head: true }).not('account_locked_until', 'is', null),
    supabase.from('user_balances').select('currency, available_balance, locked_balance'),
    supabase.from('admin_actions_log').select('*').order('created_at', { ascending: false }).limit(10),
    supabase.from('users').select('*').order('created_at', { ascending: false }).limit(5),
  ])

  // Calculate total balances by currency
  const balancesByCurrency: Record<string, { available: number; locked: number }> = {}
  if (totalBalances.data) {
    totalBalances.data.forEach((balance: any) => {
      if (!balancesByCurrency[balance.currency]) {
        balancesByCurrency[balance.currency] = { available: 0, locked: 0 }
      }
      balancesByCurrency[balance.currency].available += parseFloat(balance.available_balance || 0)
      balancesByCurrency[balance.currency].locked += parseFloat(balance.locked_balance || 0)
    })
  }

  return {
    totalUsers: totalUsers.count || 0,
    verifiedUsers: verifiedUsers.count || 0,
    kycPending: kycPending.count || 0,
    blockedUsers: blockedUsers.count || 0,
    balancesByCurrency,
    recentActions: recentActions.data || [],
    recentUsers: recentUsers.data || [],
  }
}
