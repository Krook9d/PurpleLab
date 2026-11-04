import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type { SharingPost } from "@/lib/types"

export function useSharingPosts() {
  return useQuery<SharingPost[]>({
    queryKey: ["sharing"],
    queryFn: () => api.get<SharingPost[]>("/sharing"),
  })
}

export function useCreatePost() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (body: {
      content: string
      title?: string
      description?: string
      language?: string
    }) => api.post<SharingPost>("/sharing", body),
    onSuccess: () => {
      notify.success("Post published")
      queryClient.invalidateQueries({ queryKey: ["sharing"] })
    },
    onError: (err) => notify.error("Publish failed", { description: (err as Error).message }),
  })
}

export function useDeletePost() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/sharing/${id}`),
    onSuccess: () => {
      notify.success("Post deleted")
      queryClient.invalidateQueries({ queryKey: ["sharing"] })
    },
    onError: (err) => notify.error("Deletion failed", { description: (err as Error).message }),
  })
}
