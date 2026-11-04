import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type {
  SigmaBrowse,
  SigmaConvertResult,
  SigmaRule,
  SigmaSearch,
  SigmaStats,
  SigmaUpdateResult,
} from "@/lib/types"

export function useSigmaStats() {
  return useQuery<SigmaStats>({
    queryKey: ["sigma", "stats"],
    queryFn: () => api.get<SigmaStats>("/sigma/stats"),
  })
}

export function useBrowseSigma(folder: string) {
  return useQuery<SigmaBrowse>({
    queryKey: ["sigma", "browse", folder],
    queryFn: () => api.get<SigmaBrowse>(`/sigma/browse?folder=${encodeURIComponent(folder)}`),
  })
}

export function useSearchSigma(query: string) {
  return useQuery<SigmaSearch>({
    queryKey: ["sigma", "search", query],
    queryFn: () => api.get<SigmaSearch>(`/sigma/search?q=${encodeURIComponent(query)}`),
    enabled: query.length >= 2,
  })
}

export function useSigmaRule(path: string | null) {
  return useQuery<SigmaRule>({
    queryKey: ["sigma", "rule", path],
    queryFn: () => api.get<SigmaRule>(`/sigma/rule?path=${encodeURIComponent(path!)}`),
    enabled: !!path,
  })
}

export function useConvertSigma() {
  return useMutation({
    mutationFn: (body: { rule_path: string; plugin: string }) =>
      api.post<SigmaConvertResult>("/sigma/convert", body),
    onError: (err) => notify.error("Conversion failed", { description: (err as Error).message }),
  })
}

export function useUpdateSigmaDatabase() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<SigmaUpdateResult>("/sigma/update"),
    onSuccess: (data) => {
      notify.success(`Sigma database updated`, { description: `${data.rule_count} rules` })
      queryClient.invalidateQueries({ queryKey: ["sigma", "browse"] })
      queryClient.invalidateQueries({ queryKey: ["sigma", "stats"] })
      queryClient.invalidateQueries({ queryKey: ["mitre", "matrix"] })
    },
    onError: (err) => notify.error("Update failed", { description: (err as Error).message }),
  })
}
