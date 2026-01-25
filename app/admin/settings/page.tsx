'use client'

import { useEffect, useState } from 'react'
import DataTable from '@/components/admin/DataTable'
import Modal from '@/components/admin/Modal'
import type { Admin } from '@/lib/types'

export default function SettingsPage() {
  const [admins, setAdmins] = useState<Admin[]>([])
  const [loading, setLoading] = useState(true)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [newAdminEmail, setNewAdminEmail] = useState('')
  const [newAdminPassword, setNewAdminPassword] = useState('')
  const [newAdminRole, setNewAdminRole] = useState('admin')
  const [adminType, setAdminType] = useState<'password' | 'github'>('password')

  useEffect(() => {
    fetchAdmins()
  }, [])

  async function fetchAdmins() {
    setLoading(true)
    try {
      const res = await fetch('/api/admin/admins')
      const data = await res.json()
      setAdmins(data.data || [])
    } catch (error) {
      console.error('Failed to fetch admins:', error)
    } finally {
      setLoading(false)
    }
  }

  async function handleAddAdmin() {
    if (!newAdminEmail) {
      alert('Email is required')
      return
    }

    if (adminType === 'password' && !newAdminPassword) {
      alert('Password is required for email/password admin')
      return
    }

    if (adminType === 'password' && newAdminPassword.length < 8) {
      alert('Password must be at least 8 characters')
      return
    }

    try {
      const res = await fetch('/api/admin/admins', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: newAdminEmail,
          role: newAdminRole,
          password: adminType === 'password' ? newAdminPassword : undefined,
        }),
      })

      if (res.ok) {
        setAddModalOpen(false)
        setNewAdminEmail('')
        setNewAdminPassword('')
        setNewAdminRole('admin')
        setAdminType('password')
        fetchAdmins()
      } else {
        const error = await res.json()
        alert(error.error || 'Failed to add admin')
      }
    } catch (error) {
      console.error('Failed to add admin:', error)
      alert('Failed to add admin')
    }
  }

  async function handleDeleteAdmin(adminId: string) {
    if (!confirm('Are you sure you want to delete this admin?')) {
      return
    }

    try {
      const res = await fetch(`/api/admin/admins?id=${adminId}`, {
        method: 'DELETE',
      })

      if (res.ok) {
        fetchAdmins()
      } else {
        const error = await res.json()
        alert(error.error || 'Failed to delete admin')
      }
    } catch (error) {
      console.error('Failed to delete admin:', error)
      alert('Failed to delete admin')
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-white mb-2">Settings</h1>
          <p className="text-gray-400">Manage administrators and system settings</p>
        </div>
        <button
          onClick={() => setAddModalOpen(true)}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
        >
          Add Admin
        </button>
      </div>

      {/* Admins Table */}
      <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 className="text-xl font-semibold text-white mb-4">Administrators</h2>
        <DataTable
          data={admins}
          columns={[
            { key: 'email', label: 'Email' },
            {
              key: 'role',
              label: 'Role',
              render: (role) => (
                <span className="px-2 py-1 bg-blue-900 text-blue-300 rounded text-xs font-medium">
                  {role}
                </span>
              ),
            },
            {
              key: 'created_at',
              label: 'Created',
              render: (date) => new Date(date).toLocaleString(),
            },
            {
              key: 'id',
              label: 'Actions',
              render: (id) => (
                <button
                  onClick={() => handleDeleteAdmin(id)}
                  className="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded transition-colors"
                >
                  Delete
                </button>
              ),
            },
          ]}
          isLoading={loading}
          emptyMessage="No admins found"
        />
      </div>

      {/* System Settings Placeholder */}
      <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 className="text-xl font-semibold text-white mb-4">System Settings</h2>
        <p className="text-gray-400">System configuration options will be available here in the future.</p>
      </div>

      <Modal
        isOpen={addModalOpen}
        onClose={() => {
          setAddModalOpen(false)
          setNewAdminEmail('')
          setNewAdminPassword('')
          setNewAdminRole('admin')
          setAdminType('password')
        }}
        title="Add Administrator"
      >
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">
              Admin Type
            </label>
            <select
              value={adminType}
              onChange={(e) => setAdminType(e.target.value as 'password' | 'github')}
              className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="password">Email/Password</option>
              <option value="github">GitHub OAuth (no password)</option>
            </select>
            <p className="text-xs text-gray-400 mt-1">
              {adminType === 'password' 
                ? 'Admin will be able to login with email and password'
                : 'Admin will need to login via GitHub OAuth'}
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">
              Email
            </label>
            <input
              type="email"
              value={newAdminEmail}
              onChange={(e) => setNewAdminEmail(e.target.value)}
              className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="admin@example.com"
              required
            />
          </div>
          {adminType === 'password' && (
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Password
              </label>
              <input
                type="password"
                value={newAdminPassword}
                onChange={(e) => setNewAdminPassword(e.target.value)}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="••••••••"
                minLength={8}
                required
              />
              <p className="text-xs text-gray-400 mt-1">
                Minimum 8 characters
              </p>
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-300 mb-2">
              Role
            </label>
            <select
              value={newAdminRole}
              onChange={(e) => setNewAdminRole(e.target.value)}
              className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="admin">Admin</option>
              <option value="compliance">Compliance</option>
            </select>
          </div>
          <div className="flex justify-end gap-2">
            <button
              onClick={() => {
                setAddModalOpen(false)
                setNewAdminEmail('')
                setNewAdminPassword('')
                setNewAdminRole('admin')
                setAdminType('password')
              }}
              className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={handleAddAdmin}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            >
              Add Admin
            </button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
