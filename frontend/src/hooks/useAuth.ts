import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useNavigate } from "react-router-dom"
import { api } from "@/lib/apiClient"
import { clearToken, getToken, setToken } from "@/lib/auth"
import type { LoginResponse, User } from "@/lib/types"

export function useCurrentUser() {
  return useQuery<User>({
    queryKey: ["auth", "me"],
    queryFn: () => api.get<User>("/auth/me"),
    enabled: !!getToken(),
    retry: false,
  })
}

export function useLogin() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (credentials: { email: string; password: string }) =>
      api.post<LoginResponse>("/auth/login", credentials),
    onSuccess: (data) => {
      setToken(data.access_token)
      queryClient.setQueryData(["auth", "me"], data.user)
      navigate("/")
    },
  })
}

export function useLogout() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  return () => {
    clearToken()
    queryClient.clear()
    navigate("/login")
  }
}
