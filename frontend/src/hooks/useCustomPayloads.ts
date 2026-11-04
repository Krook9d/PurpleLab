import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type { CustomPayload } from "@/lib/types"

export function useCustomPayloads() {
  return useQuery<CustomPayload[]>({
    queryKey: ["custom-payloads"],
    queryFn: () => api.get<CustomPayload[]>("/custom-payloads"),
  })
}

export function useCreatePayload() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (body: { name: string; content: string }) =>
      api.post<CustomPayload>("/custom-payloads", body),
    onSuccess: () => {
      notify.success("Payload created")
      queryClient.invalidateQueries({ queryKey: ["custom-payloads"] })
    },
    onError: (err) => notify.error("Creation failed", { description: (err as Error).message }),
  })
}

export function useDeletePayload() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/custom-payloads/${id}`),
    onSuccess: () => {
      notify.success("Payload deleted")
      queryClient.invalidateQueries({ queryKey: ["custom-payloads"] })
    },
  })
}

export function useExecutePayload() {
  return useMutation({
    mutationFn: (id: number) =>
      api.post<{ status: string; output: string | null; error: string | null }>(
        `/custom-payloads/${id}/execute`
      ),
    onSuccess: (data) => {
      if (data.status === "success") {
        notify.success("Payload executed", { description: data.output ?? undefined })
      } else {
        notify.error("Execution failed", { description: data.error ?? undefined })
      }
    },
    onError: (err) => notify.error("Execution failed", { description: (err as Error).message }),
  })
}
