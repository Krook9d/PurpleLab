import { useContext } from "react"
import { ThemeModeContext, type ThemeModeContextValue } from "./ThemeModeContext"

export function useThemeMode(): ThemeModeContextValue {
  const ctx = useContext(ThemeModeContext)
  if (!ctx) throw new Error("useThemeMode must be used inside <ThemeModeProvider>")
  return ctx
}
