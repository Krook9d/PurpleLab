import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { getToken } from "@/lib/auth"

export function RequireAuth({ children }: { children: ReactNode }) {
  if (!getToken()) {
    return <Navigate to="/login" replace />
  }
  return <>{children}</>
}
