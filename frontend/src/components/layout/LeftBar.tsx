/*
 * Adapted from OpenCTI (opencti-front/src/private/components/nav/LeftBar.jsx and
 * LeftBarHeader.tsx, pre design-system version), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: GraphQL settings query, permission checks, product switcher,
 * sub-menus and vendor footer removed; PurpleLab navigation items grouped in
 * titled sections and logo; state comes from the Shell nav context.
 */
import { Box, Divider, Drawer, List, ListSubheader, Typography, useTheme } from "@mui/material"
import ChevronLeft from "@mui/icons-material/ChevronLeft"
import ChevronRight from "@mui/icons-material/ChevronRight"
import { Link, useLocation } from "react-router-dom"
import { useCurrentUser } from "@/hooks/useAuth"
import { BrandLogo } from "@/components/common/BrandLogo"
import { LeftBarItem } from "./LeftBarItem"
import { NAV_GROUPS, NAV_ITEMS, isNavItemSelected } from "./navItems"
import { NAV_TRANSITION, TOP_BAR_HEIGHT } from "./navConstants"
import { useNav } from "./useNav"

export function LeftBar() {
  const theme = useTheme()
  const { pathname } = useLocation()
  const { navOpen, navWidth, toggleNav } = useNav()
  const { data: user } = useCurrentUser()

  const items = NAV_ITEMS.filter((item) => !item.adminOnly || user?.role === "admin")
  const background = `linear-gradient(100deg, ${theme.palette.background.gradient.start} 0%, ${theme.palette.background.gradient.end} 100%)`

  return (
    <Drawer
      variant="permanent"
      slotProps={{
        paper: {
          sx: {
            width: navWidth,
            minHeight: "100vh",
            overflowX: "hidden",
            display: "flex",
            flexDirection: "column",
            background,
            borderRight: `1px solid ${theme.palette.divider}`,
            transition: `width ${NAV_TRANSITION}`,
          },
        },
      }}
      sx={{
        width: navWidth,
        flexShrink: 0,
        zIndex: theme.zIndex.appBar + 1,
        transition: `width ${NAV_TRANSITION}`,
      }}
    >
      <Box
        component={Link}
        to="/"
        aria-label="PurpleLab home"
        sx={{
          height: TOP_BAR_HEIGHT,
          flexShrink: 0,
          display: "flex",
          alignItems: "center",
          gap: 1.25,
          px: navOpen ? 2 : "11px",
          textDecoration: "none",
          transition: `padding ${NAV_TRANSITION}`,
        }}
      >
        <BrandLogo size={32} glow decorative />
        {navOpen && (
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontFamily: '"Geologica", sans-serif', fontWeight: 600, fontSize: 15, lineHeight: 1.2 }} noWrap>
              PurpleLab
            </Typography>
            <Typography
              sx={{ fontSize: 10, letterSpacing: "0.08em", textTransform: "uppercase", color: "text.tertiary" }}
              noWrap
            >
              Security Lab
            </Typography>
          </Box>
        )}
      </Box>

      <Box
        component="nav"
        aria-label="Main navigation"
        sx={{ flex: 1, minHeight: 0, overflowY: "auto", overflowX: "hidden", pt: 1 }}
      >
        {NAV_GROUPS.map((group, index) => {
          const groupItems = items.filter((item) => item.group === group)
          if (!groupItems.length) return null
          return (
            <List
              key={group}
              disablePadding
              sx={{ mb: 1 }}
              subheader={
                navOpen ? (
                  <ListSubheader
                    disableSticky
                    sx={{
                      px: 2.25,
                      pt: index === 0 ? 0.5 : 1.5,
                      pb: 0.5,
                      lineHeight: "16px",
                      fontSize: 10,
                      fontWeight: 600,
                      letterSpacing: "0.1em",
                      textTransform: "uppercase",
                      color: "text.tertiary",
                      backgroundColor: "transparent",
                      whiteSpace: "nowrap",
                    }}
                  >
                    {group}
                  </ListSubheader>
                ) : index > 0 ? (
                  <Divider sx={{ mx: 1.5, my: 1 }} />
                ) : undefined
              }
            >
              {groupItems.map((item) => (
                <LeftBarItem
                  key={item.label}
                  icon={item.icon}
                  label={item.label}
                  to={item.to}
                  external={item.external}
                  selected={isNavItemSelected(item, pathname)}
                  navOpen={navOpen}
                />
              ))}
            </List>
          )
        })}
      </Box>

      <List disablePadding sx={{ flexShrink: 0, py: 1, borderTop: `1px solid ${theme.palette.divider}` }}>
        <LeftBarItem
          icon={navOpen ? ChevronLeft : ChevronRight}
          label={navOpen ? "Collapse" : "Expand"}
          navOpen={navOpen}
          onClick={toggleNav}
        />
      </List>
    </Drawer>
  )
}
