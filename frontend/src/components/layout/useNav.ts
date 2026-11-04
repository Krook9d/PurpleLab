import { useContext } from "react"
import { NavContext, type NavContextValue } from "./NavContext"

export function useNav(): NavContextValue {
  const ctx = useContext(NavContext)
  if (!ctx) throw new Error("useNav must be used inside <Shell>")
  return ctx
}
