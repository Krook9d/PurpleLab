import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type {
  ConnectorConfig,
  ConnectorStatus,
  ConnectorTestResult,
  ConnectorType,
  RulePayloadMapEntry,
  RulesSyncResult,
  SyncedRule,
} from "@/lib/types"

export function useConnectors() {
  return useQuery<ConnectorStatus[]>({
    queryKey: ["connectors"],
    queryFn: () => api.get<ConnectorStatus[]>("/connectors"),
  })
}

export function useTestConnector() {
  return useMutation({
    mutationFn: ({ type, config }: { type: ConnectorType; config: ConnectorConfig }) =>
      api.post<ConnectorTestResult>(`/connectors/${type}/test`, config),
    onSuccess: (data) => {
      if (data.ok) notify.success("Connection successful")
      else notify.error("Connection failed")
    },
    onError: (err) => notify.error("Connection failed", { description: (err as Error).message }),
  })
}

export function useSaveConnector() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ type, config }: { type: ConnectorType; config: ConnectorConfig }) =>
      api.put<ConnectorStatus>(`/connectors/${type}`, config),
    onSuccess: () => {
      notify.success("Connector saved")
      queryClient.invalidateQueries({ queryKey: ["connectors"] })
    },
    onError: (err) => notify.error("Save failed", { description: (err as Error).message }),
  })
}

export function useDeleteConnector() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (type: ConnectorType) => api.delete(`/connectors/${type}`),
    onSuccess: () => {
      notify.success("Connector deleted")
      queryClient.invalidateQueries({ queryKey: ["connectors"] })
    },
  })
}

export function useSyncedRules(type: ConnectorType) {
  return useQuery<SyncedRule[]>({
    queryKey: ["connectors", type, "rules"],
    queryFn: () => api.get<SyncedRule[]>(`/connectors/${type}/rules`),
  })
}

export function useSyncRules() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (type: ConnectorType) => api.post<RulesSyncResult>(`/connectors/${type}/rules/sync`),
    onSuccess: (data, type) => {
      notify.success(`${data.synced_count} rule(s) synced`)
      queryClient.invalidateQueries({ queryKey: ["connectors", type, "rules"] })
    },
    onError: (err) => notify.error("Sync failed", { description: (err as Error).message }),
  })
}

export function useRulePayloadMap() {
  return useQuery<RulePayloadMapEntry[]>({
    queryKey: ["rule-payload-map"],
    queryFn: () => api.get<RulePayloadMapEntry[]>("/rule-payload-map"),
  })
}

export function useSetRulePayloadMap() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ ruleId, payloadId }: { ruleId: string; payloadId: number | null }) =>
      api.put<RulePayloadMapEntry>(`/rule-payload-map/${encodeURIComponent(ruleId)}`, { payload_id: payloadId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["rule-payload-map"] })
    },
    onError: (err) => notify.error("Association failed", { description: (err as Error).message }),
  })
}
