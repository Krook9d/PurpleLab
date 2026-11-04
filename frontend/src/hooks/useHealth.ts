import { useQuery } from "@tanstack/react-query"
import { api } from "@/lib/apiClient"
import type { EventCount, HealthStatus, SystemMetrics } from "@/lib/types"

export function useHealthStatus() {
  return useQuery<HealthStatus>({
    queryKey: ["health"],
    queryFn: () => api.get<HealthStatus>("/health"),
    refetchInterval: 10000,
  })
}

export function useSystemMetrics() {
  return useQuery<SystemMetrics>({
    queryKey: ["health", "system"],
    queryFn: () => api.get<SystemMetrics>("/health/system"),
    refetchInterval: 5000,
  })
}

export function useEventCount() {
  return useQuery<EventCount>({
    queryKey: ["health", "event-count"],
    queryFn: () => api.get<EventCount>("/health/event-count"),
  })
}
