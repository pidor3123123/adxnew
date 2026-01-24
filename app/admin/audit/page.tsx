'use client'

import { useEffect, useState } from 'react'
import DataTable from '@/components/admin/DataTable'
import Modal from '@/components/admin/Modal'
import AuditDiffViewer from '@/components/admin/AuditDiffViewer'
import type { AdminActionAuditLog } from '@/lib/types'

interface AuditLogWithAdmin extends AdminActionAuditLog {
  admins: {
    id: string
    email: string
    role: string
  }
}

export default function AuditPage() {
  const [logs, setLogs] = useState<AuditLogWithAdmin[]>([])
  const [loading, setLoading] = useState(true)
  const [tableFilter, setTableFilter] = useState<string>('all')
  const [selectedLog, setSelectedLog] = useState<AuditLogWithAdmin | null>(null)
  const [diffModalOpen, setDiffModalOpen] = useState(false)

  useEffect(() => {
    fetchLogs()
  }, [tableFilter])

  async function fetchLogs() {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('limit', '100')
      if (tableFilter !== 'all') params.set('table_name', tableFilter)
      
      const res = await fetch(`/api/admin/audit?${params}`)
      const data = await res.json()
      setLogs(data.data || [])
    } catch (error) {
      console.error('Failed to fetch audit logs:', error)
    } finally {
      setLoading(false)
    }
  }

  const tables = Array.from(new Set(logs.map(log => log.table_name)))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Audit Log</h1>
        <p className="text-gray-400">View all administrative changes and actions</p>
      </div>

      {/* Filter */}
      <div>
        <select
          value={tableFilter}
          onChange={(e) => setTableFilter(e.target.value)}
          className="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="all">All Tables</option>
          {tables.map(table => (
            <option key={table} value={table}>{table}</option>
          ))}
        </select>
      </div>

      {/* Audit Logs Table */}
      <DataTable
        data={logs}
        columns={[
          {
            key: 'admins',
            label: 'Admin',
            render: (admin) => admin?.email || 'N/A',
          },
          { key: 'action', label: 'Action' },
          { key: 'table_name', label: 'Table' },
          { key: 'record_id', label: 'Record ID' },
          {
            key: 'created_at',
            label: 'Date',
            render: (date) => new Date(date).toLocaleString(),
          },
          {
            key: 'id',
            label: 'View Changes',
            render: (id, row: AuditLogWithAdmin) => (
              <button
                onClick={(e) => {
                  e.stopPropagation()
                  setSelectedLog(row)
                  setDiffModalOpen(true)
                }}
                className="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded transition-colors"
              >
                View Diff
              </button>
            ),
          },
        ]}
        isLoading={loading}
        emptyMessage="No audit logs found"
      />

      <Modal
        isOpen={diffModalOpen}
        onClose={() => {
          setDiffModalOpen(false)
          setSelectedLog(null)
        }}
        title="Audit Log Details"
        size="xl"
      >
        {selectedLog && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span className="text-gray-400">Admin:</span>
                <p className="text-white">{selectedLog.admins?.email || 'N/A'}</p>
              </div>
              <div>
                <span className="text-gray-400">Action:</span>
                <p className="text-white">{selectedLog.action}</p>
              </div>
              <div>
                <span className="text-gray-400">Table:</span>
                <p className="text-white">{selectedLog.table_name}</p>
              </div>
              <div>
                <span className="text-gray-400">Record ID:</span>
                <p className="text-white">{selectedLog.record_id}</p>
              </div>
              <div>
                <span className="text-gray-400">Date:</span>
                <p className="text-white">{new Date(selectedLog.created_at).toLocaleString()}</p>
              </div>
            </div>
            <div className="border-t border-gray-700 pt-4">
              <h3 className="text-lg font-semibold text-white mb-4">Changes</h3>
              <AuditDiffViewer oldData={selectedLog.old_data} newData={selectedLog.new_data} />
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
