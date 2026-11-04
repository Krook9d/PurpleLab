import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type { ExecuteResult, RuleLifecyclePayloadItem } from "@/lib/types"

// Table distinct from custom_payloads (RuleLifecyclePayload on the backend
// side): hook/query key names deliberately different so they never
// collide with useCustomPayloads.ts.

export function useRuleLifecyclePayloads() {
  return useQuery<RuleLifecyclePayloadItem[]>({
    queryKey: ["rule-lifecycle-payloads"],
    queryFn: () => api.get<RuleLifecyclePayloadItem[]>("/payloads"),
  })
}

export function useCreateRuleLifecyclePayload() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (body: { name: string; description?: string | null; code: string }) =>
      api.post<RuleLifecyclePayloadItem>("/payloads", body),
    onSuccess: () => {
      notify.success("Payload created")
      queryClient.invalidateQueries({ queryKey: ["rule-lifecycle-payloads"] })
    },
    onError: (err) => notify.error("Creation failed", { description: (err as Error).message }),
  })
}

export function useUpdateRuleLifecyclePayload() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...body }: { id: number; name: string; description?: string | null; code: string }) =>
      api.put<RuleLifecyclePayloadItem>(`/payloads/${id}`, body),
    onSuccess: () => {
      notify.success("Payload updated")
      queryClient.invalidateQueries({ queryKey: ["rule-lifecycle-payloads"] })
    },
    onError: (err) => notify.error("Update failed", { description: (err as Error).message }),
  })
}

export function useDeleteRuleLifecyclePayload() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/payloads/${id}`),
    onSuccess: () => {
      notify.success("Payload deleted")
      queryClient.invalidateQueries({ queryKey: ["rule-lifecycle-payloads"] })
    },
  })
}

export function useExecuteRuleLifecyclePayload() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      payloadId,
      ruleId,
      connector,
    }: {
      payloadId: number
      ruleId?: string
      connector?: string
    }) =>
      api.post<ExecuteResult>(`/payloads/${payloadId}/execute`, {
        rule_id: ruleId ?? null,
        connector: connector ?? null,
      }),
    onSuccess: (data) => {
      if (data.status === "success") {
        notify.success("Payload executed", { description: data.output ?? undefined })
      } else {
        notify.error("Execution failed", { description: data.error ?? undefined })
      }
      queryClient.invalidateQueries({ queryKey: ["execution-history"] })
    },
    onError: (err) => notify.error("Execution failed", { description: (err as Error).message }),
  })
}
