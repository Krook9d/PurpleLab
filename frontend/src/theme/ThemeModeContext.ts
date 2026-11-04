import { createContext } from "react"
import type { PaletteMode } from "@mui/material"

export interface ThemeModeContextValue {
  mode: PaletteMode
  toggleMode: () => void
}

export const ThemeModeContext = createContext<ThemeModeContextValue | null>(null)
