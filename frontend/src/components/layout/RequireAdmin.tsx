import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { useCurrentUser } from "@/hooks/useAuth"
import { Loader } from "@/components/common/Loader"

export function RequireAdmin({ children }: { children: ReactNode }) {
  const { data: user, isLoading } = useCurrentUser()
  if (isLoading) return <Loader />
  if (user?.role !== "admin") return <Navigate to="/" replace />
  return <>{children}</>
}
