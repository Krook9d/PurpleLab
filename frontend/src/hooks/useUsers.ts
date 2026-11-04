import { useQuery } from "@tanstack/react-query"
import { api } from "@/lib/apiClient"
import type { UserCount } from "@/lib/types"

export function useUsersCount() {
  return useQuery<UserCount>({
    queryKey: ["users", "count"],
    queryFn: () => api.get<UserCount>("/users/count"),
  })
}
