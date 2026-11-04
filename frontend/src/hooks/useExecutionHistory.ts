import { useQuery } from "@tanstack/react-query"
import { api } from "@/lib/apiClient"
import type { ExecutionHistoryEntry } from "@/lib/types"

export function useExecutionHistory(filters: { connector?: string; status?: string; source?: string } = {}) {
  const params = new URLSearchParams()
  if (filters.source) params.set("source", filters.source)
  if (filters.connector) params.set("connector", filters.connector)
  if (filters.status) params.set("status", filters.status)
  const qs = params.toString()

  return useQuery<ExecutionHistoryEntry[]>({
    queryKey: ["execution-history", filters.source ?? null, filters.connector ?? null, filters.status ?? null],
    queryFn: () => api.get<ExecutionHistoryEntry[]>(`/execution-history${qs ? `?${qs}` : ""}`),
  })
}
