'use client'

import { useEffect, useState } from 'react'
import DataTable from '@/components/admin/DataTable'
import type { UserDocument, DocumentStatus, DocumentType } from '@/lib/types'

interface DocumentWithUser extends UserDocument {
  users: {
    id: string
    first_name: string | null
    last_name: string | null
    email: string | null
  }
}

export default function DocumentsPage() {
  const [documents, setDocuments] = useState<DocumentWithUser[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [typeFilter, setTypeFilter] = useState<string>('all')

  useEffect(() => {
    fetchDocuments()
  }, [statusFilter, typeFilter])

  async function fetchDocuments() {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('limit', '100')
      if (statusFilter !== 'all') params.set('status', statusFilter)
      if (typeFilter !== 'all') params.set('type', typeFilter)
      
      const res = await fetch(`/api/admin/documents?${params}`)
      const data = await res.json()
      setDocuments(data.data || [])
    } catch (error) {
      console.error('Failed to fetch documents:', error)
    } finally {
      setLoading(false)
    }
  }

  async function handleApprove(documentId: string, approved: boolean) {
    try {
      const res = await fetch(`/api/admin/documents/${documentId}/approve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ approved }),
      })

      if (res.ok) {
        fetchDocuments()
      } else {
        const error = await res.json()
        alert(error.error || 'Failed to update document')
      }
    } catch (error) {
      console.error('Failed to update document:', error)
      alert('Failed to update document')
    }
  }

  const statuses: DocumentStatus[] = ['pending', 'approved', 'rejected']
  const types: DocumentType[] = ['passport', 'id_card', 'driver_license', 'utility_bill', 'bank_statement']

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Documents</h1>
        <p className="text-gray-400">Manage KYC documents</p>
      </div>

      {/* Filters */}
      <div className="flex gap-4">
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">All Statuses</option>
          {statuses.map(status => (
            <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>
          ))}
        </select>
        <select
          value={typeFilter}
          onChange={(e) => setTypeFilter(e.target.value)}
          className="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">All Types</option>
          {types.map(type => (
            <option key={type} value={type}>{type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>
          ))}
        </select>
      </div>

      {/* Documents Table */}
      <DataTable
        data={documents}
        columns={[
          {
            key: 'users',
            label: 'User',
            render: (user) => {
              const name = `${user?.first_name || ''} ${user?.last_name || ''}`.trim()
              return name || user?.email || 'N/A'
            },
          },
          {
            key: 'type',
            label: 'Type',
            render: (type) => type.replace('_', ' ').replace(/\b\w/g, (l: string) => l.toUpperCase()),
          },
          {
            key: 'status',
            label: 'Status',
            render: (status) => (
              <span className={`px-2 py-1 rounded text-xs font-medium ${
                status === 'approved' ? 'bg-green-900 text-green-300' :
                status === 'rejected' ? 'bg-red-900 text-red-300' :
                'bg-yellow-900 text-yellow-300'
              }`}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
              </span>
            ),
          },
          {
            key: 'uploaded_at',
            label: 'Uploaded',
            render: (date) => new Date(date).toLocaleString(),
          },
          {
            key: 'file_url',
            label: 'File',
            render: (url) => (
              <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-blue-400 hover:text-blue-300 underline"
              >
                View
              </a>
            ),
          },
          {
            key: 'id',
            label: 'Actions',
            render: (id, row: DocumentWithUser) => (
              <div className="flex gap-2">
                {row.status === 'pending' && (
                  <>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        handleApprove(id, true)
                      }}
                      className="px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded transition-colors"
                    >
                      Approve
                    </button>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        handleApprove(id, false)
                      }}
                      className="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded transition-colors"
                    >
                      Reject
                    </button>
                  </>
                )}
              </div>
            ),
          },
        ]}
        isLoading={loading}
        emptyMessage="No documents found"
      />
    </div>
  )
}
