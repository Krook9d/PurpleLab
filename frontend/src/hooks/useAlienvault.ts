import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api, ApiError } from "@/lib/apiClient"
import type { AlienvaultConfigStatus, AlienvaultDashboard } from "@/lib/types"

export function useAlienvaultDashboard() {
  return useQuery<AlienvaultDashboard>({
    queryKey: ["alienvault", "dashboard"],
    queryFn: () => api.get<AlienvaultDashboard>("/alienvault/dashboard"),
    retry: (failureCount, err) => err instanceof ApiError && err.status === 404 ? false : failureCount < 1,
  })
}

export function useAlienvaultConfig() {
  return useQuery<AlienvaultConfigStatus>({
    queryKey: ["alienvault", "config"],
    queryFn: () => api.get<AlienvaultConfigStatus>("/alienvault/config"),
  })
}

export function useSaveAlienvaultConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (apiKey: string) => api.put<AlienvaultConfigStatus>("/alienvault/config", { api_key: apiKey }),
    onSuccess: () => {
      notify.success("AlienVault OTX key saved")
      queryClient.invalidateQueries({ queryKey: ["alienvault", "config"] })
    },
    onError: (err) => notify.error("Invalid key", { description: (err as Error).message }),
  })
}

export function useDeleteAlienvaultConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete("/alienvault/config"),
    onSuccess: () => {
      notify.success("AlienVault configuration deleted")
      queryClient.invalidateQueries({ queryKey: ["alienvault", "config"] })
    },
  })
}

export function useRefreshAlienvault() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<{ message: string }>("/alienvault/refresh"),
    onSuccess: () => {
      notify.success("AlienVault dashboard refresh started")
      setTimeout(() => queryClient.invalidateQueries({ queryKey: ["alienvault", "dashboard"] }), 5000)
    },
    onError: (err) => notify.error("Refresh failed", { description: (err as Error).message }),
  })
}
