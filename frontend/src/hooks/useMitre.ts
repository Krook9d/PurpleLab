import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type {
  AtomicTestOut,
  ExecuteResult,
  MatrixTechnique,
  MitreStats,
  RefreshResult,
  TacticCount,
  TechniqueDetail,
  TechniqueSummary,
} from "@/lib/types"

export function useMitreStats() {
  return useQuery<MitreStats>({
    queryKey: ["mitre", "stats"],
    queryFn: () => api.get<MitreStats>("/mitre/stats"),
  })
}

export function useAtomicTestsByTactic() {
  return useQuery<TacticCount[]>({
    queryKey: ["mitre", "atomic-tests", "by-tactic"],
    queryFn: () => api.get<TacticCount[]>("/mitre/atomic-tests/by-tactic"),
  })
}

export function useMitreMatrix() {
  return useQuery<MatrixTechnique[]>({
    queryKey: ["mitre", "matrix"],
    queryFn: () => api.get<MatrixTechnique[]>("/mitre/matrix"),
    staleTime: 60_000,
  })
}

export function useSearchTechniques(query: string) {
  return useQuery<TechniqueSummary[]>({
    queryKey: ["mitre", "techniques", "search", query],
    queryFn: () => api.get<TechniqueSummary[]>(`/mitre/techniques?q=${encodeURIComponent(query)}`),
    enabled: query.length >= 2,
  })
}

export function useTechnique(id: string | null) {
  return useQuery<TechniqueDetail>({
    queryKey: ["mitre", "techniques", id],
    queryFn: () => api.get<TechniqueDetail>(`/mitre/techniques/${encodeURIComponent(id!)}`),
    enabled: !!id,
  })
}

export function useAtomicTests(techniqueId: string | null) {
  return useQuery<AtomicTestOut[]>({
    queryKey: ["mitre", "techniques", techniqueId, "atomic-tests"],
    queryFn: () => api.get<AtomicTestOut[]>(`/mitre/techniques/${encodeURIComponent(techniqueId!)}/atomic-tests`),
    enabled: !!techniqueId,
  })
}

export function useExecuteAtomicTest() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ techniqueId, testNumber }: { techniqueId: string; testNumber?: number }) =>
      api.post<ExecuteResult>(`/mitre/execute-atomic/${encodeURIComponent(techniqueId)}`, {
        test_number: testNumber,
      }),
    onSuccess: (data) => {
      if (data.status === "success") {
        notify.success("Atomic test executed", { description: data.output ?? undefined })
      } else {
        notify.error("Execution failed", { description: data.error ?? undefined })
      }
    },
    onError: (err) => notify.error("Execution failed", { description: (err as Error).message }),
    // Runs are recorded server-side: refresh the matrix "executed" coverage.
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["mitre", "matrix"] }),
  })
}

export function useRefreshMitreDatabase() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<RefreshResult>("/mitre/refresh"),
    onSuccess: (data) => {
      notify.success("MITRE database updated", {
        description: `${data.created} created, ${data.updated} updated`,
      })
      queryClient.invalidateQueries({ queryKey: ["mitre"] })
    },
    onError: (err) => notify.error("Update failed", { description: (err as Error).message }),
  })
}
