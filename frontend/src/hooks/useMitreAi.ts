import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type { ChatMessage, ChatResponse, MitreAiConfigStatus } from "@/lib/types"

export type MitreAiConfigSave = {
  provider: "openai" | "gemini"
  model: string
  api_base?: string | null
  api_key: string
}

export function useMitreAiConfig() {
  return useQuery<MitreAiConfigStatus>({
    queryKey: ["mitre-ai", "config"],
    queryFn: () => api.get<MitreAiConfigStatus>("/mitre-ai/config"),
  })
}

export function useSaveMitreAiConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (body: MitreAiConfigSave) => api.put<MitreAiConfigStatus>("/mitre-ai/config", body),
    onSuccess: () => {
      notify.success("AI configuration saved")
      queryClient.invalidateQueries({ queryKey: ["mitre-ai", "config"] })
    },
    onError: (err) => notify.error("Connection failed", { description: (err as Error).message }),
  })
}

export function useDeleteMitreAiConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete("/mitre-ai/config"),
    onSuccess: () => {
      notify.success("AI configuration deleted")
      queryClient.invalidateQueries({ queryKey: ["mitre-ai", "config"] })
    },
  })
}

export function useMitreAiChat() {
  return useMutation({
    mutationFn: (messages: ChatMessage[]) => api.post<ChatResponse>("/mitre-ai/chat", { messages }),
    onError: (err) => notify.error("The chatbot did not respond", { description: (err as Error).message }),
  })
}
