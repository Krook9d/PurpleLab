import { useCallback, useMemo, useState, type ReactNode } from "react"
import { CssBaseline, ThemeProvider, type PaletteMode } from "@mui/material"
import { createAppTheme } from "./createAppTheme"
import { ThemeModeContext } from "./ThemeModeContext"

const STORAGE_KEY = "purplelab_theme"

function readStoredMode(): PaletteMode {
  try {
    return localStorage.getItem(STORAGE_KEY) === "light" ? "light" : "dark"
  } catch {
    return "dark"
  }
}

export function ThemeModeProvider({ children }: { children: ReactNode }) {
  const [mode, setMode] = useState<PaletteMode>(readStoredMode)

  const toggleMode = useCallback(() => {
    setMode((prev) => {
      const next = prev === "dark" ? "light" : "dark"
      try {
        localStorage.setItem(STORAGE_KEY, next)
      } catch {
        // Storage unavailable (private mode): the choice just isn't persisted.
      }
      return next
    })
  }, [])

  const theme = useMemo(() => createAppTheme(mode), [mode])
  const value = useMemo(() => ({ mode, toggleMode }), [mode, toggleMode])

  return (
    <ThemeModeContext.Provider value={value}>
      <ThemeProvider theme={theme}>
        <CssBaseline enableColorScheme />
        {children}
      </ThemeProvider>
    </ThemeModeContext.Provider>
  )
}
