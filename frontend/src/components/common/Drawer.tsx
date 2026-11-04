/*
 * Adapted from OpenCTI (opencti-front/src/private/components/common/drawer/Drawer.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: fully controlled (open/onClose), no children cloning or
 * controlled dial, subscription avatars / banners / design-system layers
 * removed; optional sticky footer for actions.
 */
import type { ReactNode } from "react"
import { Box, Drawer as MuiDrawer, useTheme } from "@mui/material"
import { DrawerHeader } from "./DrawerHeader"

export type DrawerSize = "small" | "medium" | "large" | "extraLarge"

const DRAWER_WIDTHS: Record<DrawerSize, string> = {
  small: "420px",
  medium: "640px",
  large: "960px",
  extraLarge: "90vw",
}

interface DrawerProps {
  title: ReactNode
  open: boolean
  onClose: () => void
  children?: ReactNode
  header?: ReactNode
  footer?: ReactNode
  size?: DrawerSize
  disableBackdropClose?: boolean
}

export function Drawer({
  title,
  open,
  onClose,
  children,
  header,
  footer,
  size = "medium",
  disableBackdropClose = false,
}: DrawerProps) {
  const theme = useTheme()
  return (
    <MuiDrawer
      open={open}
      anchor="right"
      variant="temporary"
      elevation={1}
      onClose={(_, reason) => {
        if (disableBackdropClose && reason === "backdropClick") return
        onClose()
      }}
      sx={{ zIndex: theme.zIndex.drawer + 2 }}
      slotProps={{
        paper: {
          "aria-labelledby": "drawer-title",
          role: "dialog",
          sx: {
            height: "100vh",
            width: DRAWER_WIDTHS[size],
            maxWidth: "100vw",
            overflow: "hidden",
            display: "flex",
            flexDirection: "column",
            backgroundColor: theme.palette.background.drawer,
            transition: theme.transitions.create("width", {
              easing: theme.transitions.easing.sharp,
              duration: theme.transitions.duration.enteringScreen,
            }),
          },
        },
      }}
    >
      <DrawerHeader title={title} endContent={header} onClose={onClose} />
      <Box sx={{ p: 3, flex: 1, minHeight: 0, overflowY: "auto", display: "flex", flexDirection: "column", gap: 2 }}>
        {children}
      </Box>
      {footer && (
        <Box
          sx={{
            px: 3,
            py: 2,
            display: "flex",
            justifyContent: "flex-end",
            gap: 1,
            borderTop: `1px solid ${theme.palette.divider}`,
          }}
        >
          {footer}
        </Box>
      )}
    </MuiDrawer>
  )
}
