// Database types for Supabase tables

export type KycStatus = 'pending' | 'approved' | 'rejected' | 'under_review'
export type DocumentType = 'passport' | 'id_card' | 'driver_license' | 'utility_bill' | 'bank_statement'
export type DocumentStatus = 'pending' | 'approved' | 'rejected'
export type TwoFaType = 'sms' | 'email' | 'app'
export type AdminRole = 'admin' | 'compliance'

export interface User {
  id: string
  first_name: string | null
  last_name: string | null
  email: string | null
  country: string | null
  is_verified: boolean | null
  kyc_status: KycStatus | null
  kyc_verified: boolean | null
  created_at: string
}

export interface UserSecurity {
  user_id: string
  two_fa_enabled: boolean | null
  two_fa_type: TwoFaType | null
  last_login_at: string | null
  last_login_ip: string | null
  failed_login_attempts: number | null
  account_locked_until: string | null
}

export interface UserDocument {
  id: string
  user_id: string
  type: DocumentType
  file_url: string
  status: DocumentStatus
  uploaded_at: string
}

export interface UserBalance {
  id: string
  user_id: string
  currency: string
  available_balance: number
  locked_balance: number
  updated_at: string
}

export interface Admin {
  id: string
  email: string
  role: AdminRole
  created_at: string
}

export interface AdminActionLog {
  id: string
  admin_id: string
  user_id: string | null
  action: string
  created_at: string
}

export interface AdminActionAuditLog {
  id: string
  admin_id: string
  action: string
  table_name: string
  record_id: string
  old_data: Record<string, any> | null
  new_data: Record<string, any> | null
  created_at: string
}

export interface UserWithSecurity extends User {
  user_security: UserSecurity | null
  user_balances?: UserBalance[]
}

export interface UserWithDetails extends User {
  user_security: UserSecurity | null
  user_documents: UserDocument[]
  user_balances: UserBalance[]
  admin_actions: AdminActionLog[]
}
