import { createContext } from "react"

export interface NavContextValue {
  navOpen: boolean
  navWidth: number
  toggleNav: () => void
}

export const NavContext = createContext<NavContextValue | null>(null)
