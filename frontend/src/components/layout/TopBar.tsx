import { alpha, AppBar, Box, IconButton, Stack, Toolbar, Tooltip, Typography, useTheme } from "@mui/material"
import DarkModeOutlined from "@mui/icons-material/DarkModeOutlined"
import LightModeOutlined from "@mui/icons-material/LightModeOutlined"
import { useLocation } from "react-router-dom"
import { useThemeMode } from "@/theme/useThemeMode"
import { NAV_ITEMS, isNavItemSelected } from "./navItems"
import { NAV_TRANSITION, TOP_BAR_HEIGHT } from "./navConstants"
import { useNav } from "./useNav"
import { UserMenu } from "./UserMenu"

export function TopBar() {
  const theme = useTheme()
  const { pathname } = useLocation()
  const { navWidth } = useNav()
  const { mode, toggleMode } = useThemeMode()

  const current = NAV_ITEMS.find((item) => isNavItemSelected(item, pathname))
  const { start, end } = theme.palette.background.gradient
  const CurrentIcon = current?.icon

  return (
    <AppBar
      position="fixed"
      elevation={0}
      sx={{
        left: navWidth,
        width: `calc(100% - ${navWidth}px)`,
        height: TOP_BAR_HEIGHT,
        background: `linear-gradient(90deg, ${alpha(start, 0.9)} 0%, ${alpha(end, 0.9)} 100%)`,
        backdropFilter: "blur(6px)",
        borderBottom: `1px solid ${theme.palette.divider}`,
        color: "text.primary",
        transition: `left ${NAV_TRANSITION}, width ${NAV_TRANSITION}`,
      }}
    >
      <Toolbar sx={{ height: "100%", minHeight: `${TOP_BAR_HEIGHT}px !important`, px: { xs: 2, sm: 3 } }}>
        <Stack direction="row" spacing={1.25} sx={{ alignItems: "center", minWidth: 0, flex: 1 }}>
          {CurrentIcon && <CurrentIcon sx={{ fontSize: 18, color: "primary.main" }} />}
          {/* textTransform none: acronyms like "MITRE ATT&CK" must survive the heading lowercase trick. */}
          <Typography variant="h1" component="div" sx={{ m: 0, fontSize: 18, textTransform: "none" }} noWrap>
            {current?.label ?? "PurpleLab"}
          </Typography>
        </Stack>
        <Box sx={{ display: "flex", alignItems: "center", gap: 0.5 }}>
          <Tooltip title={mode === "dark" ? "Light mode" : "Dark mode"}>
            <IconButton onClick={toggleMode} aria-label="Toggle color mode" sx={{ color: "text.secondary" }}>
              {mode === "dark" ? <LightModeOutlined fontSize="small" /> : <DarkModeOutlined fontSize="small" />}
            </IconButton>
          </Tooltip>
          <UserMenu />
        </Box>
      </Toolbar>
    </AppBar>
  )
}
