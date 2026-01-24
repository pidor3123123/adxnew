interface AuditDiffViewerProps {
  oldData: Record<string, any> | null
  newData: Record<string, any> | null
}

export default function AuditDiffViewer({ oldData, newData }: AuditDiffViewerProps) {
  const allKeys = new Set([
    ...(oldData ? Object.keys(oldData) : []),
    ...(newData ? Object.keys(newData) : []),
  ])

  return (
    <div className="space-y-4">
      {Array.from(allKeys).map((key) => {
        const oldValue = oldData?.[key]
        const newValue = newData?.[key]
        const hasChanged = JSON.stringify(oldValue) !== JSON.stringify(newValue)

        if (!hasChanged && oldValue !== undefined) return null

        return (
          <div key={key} className="border-b border-gray-700 pb-2">
            <div className="font-medium text-gray-300 mb-1">{key}</div>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <div className="text-gray-500 text-xs mb-1">Old Value</div>
                <div className="bg-red-900/20 border border-red-800 rounded p-2 text-red-300">
                  {oldValue === null || oldValue === undefined 
                    ? <span className="italic">null</span>
                    : typeof oldValue === 'object'
                    ? <pre className="text-xs overflow-auto">{JSON.stringify(oldValue, null, 2)}</pre>
                    : String(oldValue)
                  }
                </div>
              </div>
              <div>
                <div className="text-gray-500 text-xs mb-1">New Value</div>
                <div className="bg-green-900/20 border border-green-800 rounded p-2 text-green-300">
                  {newValue === null || newValue === undefined
                    ? <span className="italic">null</span>
                    : typeof newValue === 'object'
                    ? <pre className="text-xs overflow-auto">{JSON.stringify(newValue, null, 2)}</pre>
                    : String(newValue)
                  }
                </div>
              </div>
            </div>
          </div>
        )
      })}
      {Array.from(allKeys).every(key => {
        const oldValue = oldData?.[key]
        const newValue = newData?.[key]
        return JSON.stringify(oldValue) === JSON.stringify(newValue)
      }) && (
        <div className="text-gray-400 text-sm italic">No changes detected</div>
      )}
    </div>
  )
}
