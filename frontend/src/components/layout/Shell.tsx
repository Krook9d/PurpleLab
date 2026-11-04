import { Suspense, useCallback, useMemo, useState } from "react"
import { Box, keyframes } from "@mui/material"
import { Outlet, useLocation } from "react-router-dom"
import { Loader } from "@/components/common/Loader"
import { AppBackground } from "./AppBackground"
import { LeftBar } from "./LeftBar"
import { TopBar } from "./TopBar"
import { NavContext } from "./NavContext"
import { NAV_TRANSITION, OPEN_BAR_WIDTH, SMALL_BAR_WIDTH, TOP_BAR_HEIGHT } from "./navConstants"

const NAV_STORAGE_KEY = "purplelab_nav_open"

const pageEnter = keyframes`
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
`

function readNavOpen(): boolean {
  try {
    return localStorage.getItem(NAV_STORAGE_KEY) !== "false"
  } catch {
    return true
  }
}

export function Shell() {
  const { pathname } = useLocation()
  const [navOpen, setNavOpen] = useState(readNavOpen)

  const toggleNav = useCallback(() => {
    setNavOpen((prev) => {
      try {
        localStorage.setItem(NAV_STORAGE_KEY, String(!prev))
      } catch {
        // Storage unavailable: the state just isn't persisted.
      }
      return !prev
    })
  }, [])

  const navWidth = navOpen ? OPEN_BAR_WIDTH : SMALL_BAR_WIDTH
  const nav = useMemo(() => ({ navOpen, navWidth, toggleNav }), [navOpen, navWidth, toggleNav])

  return (
    <NavContext.Provider value={nav}>
      <AppBackground navWidth={navWidth} />
      <LeftBar />
      <TopBar />
      <Box
        component="main"
        sx={{
          position: "relative",
          zIndex: 1,
          minHeight: "100vh",
          ml: `${navWidth}px`,
          pt: `${TOP_BAR_HEIGHT + 24}px`,
          px: 3,
          pb: 4,
          transition: `margin-left ${NAV_TRANSITION}`,
        }}
      >
        {/* Keyed on the route so every navigation replays the enter animation. */}
        <Box
          key={pathname}
          sx={{
            animation: `${pageEnter} 280ms cubic-bezier(0.4, 0, 0.2, 1)`,
            "@media (prefers-reduced-motion: reduce)": { animation: "none" },
          }}
        >
          <Suspense fallback={<Loader />}>
            <Outlet />
          </Suspense>
        </Box>
      </Box>
    </NavContext.Provider>
  )
}
