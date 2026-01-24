'use client'

import { useEffect, useState } from 'react'
import { useRouter, useParams } from 'next/navigation'
import UserDetailTabs from '@/components/admin/UserDetailTabs'
import Modal from '@/components/admin/Modal'
import type { UserWithDetails } from '@/lib/types'

export default function UserDetailPage() {
  const params = useParams()
  const router = useRouter()
  const [user, setUser] = useState<UserWithDetails | null>(null)
  const [loading, setLoading] = useState(true)
  const [blockModalOpen, setBlockModalOpen] = useState(false)
  const [blockUntil, setBlockUntil] = useState('')

  useEffect(() => {
    if (params.id) {
      fetchUser()
    }
  }, [params.id])

  async function fetchUser() {
    setLoading(true)
    try {
      const res = await fetch(`/api/admin/users/${params.id}`)
      if (res.ok) {
        const data = await res.json()
        setUser(data)
      } else {
        router.push('/admin/users')
      }
    } catch (error) {
      console.error('Failed to fetch user:', error)
      router.push('/admin/users')
    } finally {
      setLoading(false)
    }
  }

  async function handleBlock() {
    try {
      const lockedUntil = blockUntil ? new Date(blockUntil).toISOString() : null
      const res = await fetch(`/api/admin/users/${params.id}/block`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lockedUntil }),
      })

      if (res.ok) {
        setBlockModalOpen(false)
        setBlockUntil('')
        fetchUser()
      } else {
        alert('Failed to update user block status')
      }
    } catch (error) {
      console.error('Failed to block user:', error)
      alert('Failed to update user block status')
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-gray-400">Loading...</div>
      </div>
    )
  }

  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-gray-400">User not found</div>
      </div>
    )
  }

  const isBlocked = user.user_security?.account_locked_until 
    ? new Date(user.user_security.account_locked_until) > new Date()
    : false

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <button
            onClick={() => router.back()}
            className="text-gray-400 hover:text-white mb-4"
          >
            ← Back
          </button>
          <h1 className="text-3xl font-bold text-white">
            {user.first_name} {user.last_name}
          </h1>
          <p className="text-gray-400 mt-1">{user.email}</p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => setBlockModalOpen(true)}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              isBlocked
                ? 'bg-green-600 hover:bg-green-700 text-white'
                : 'bg-red-600 hover:bg-red-700 text-white'
            }`}
          >
            {isBlocked ? 'Unblock User' : 'Block User'}
          </button>
        </div>
      </div>

      <div className="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <UserDetailTabs user={user} onUserUpdate={fetchUser} />
      </div>

      <Modal
        isOpen={blockModalOpen}
        onClose={() => {
          setBlockModalOpen(false)
          setBlockUntil('')
        }}
        title={isBlocked ? 'Unblock User' : 'Block User'}
      >
        <div className="space-y-4">
          {!isBlocked && (
            <div>
              <label className="block text-sm font-medium text-gray-300 mb-2">
                Block until (leave empty to block indefinitely)
              </label>
              <input
                type="datetime-local"
                value={blockUntil}
                onChange={(e) => setBlockUntil(e.target.value)}
                className="w-full px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          )}
          <div className="flex justify-end gap-2">
            <button
              onClick={() => {
                setBlockModalOpen(false)
                setBlockUntil('')
              }}
              className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={handleBlock}
              className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                isBlocked
                  ? 'bg-green-600 hover:bg-green-700 text-white'
                  : 'bg-red-600 hover:bg-red-700 text-white'
              }`}
            >
              {isBlocked ? 'Unblock' : 'Block'}
            </button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
