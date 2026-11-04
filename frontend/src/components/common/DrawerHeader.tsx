/*
 * Adapted from OpenCTI (opencti-front/src/components/common/drawer/DrawerHeader.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: design-system layer classes replaced by theme colors.
 */
import type { ReactNode } from "react"
import { IconButton, Stack, Typography } from "@mui/material"
import Close from "@mui/icons-material/Close"

interface DrawerHeaderProps {
  title: ReactNode
  onClose?: () => void
  endContent?: ReactNode
}

export function DrawerHeader({ title, onClose, endContent }: DrawerHeaderProps) {
  return (
    <Stack
      direction="row"
      sx={(theme) => ({
        backgroundColor: theme.palette.background.nav,
        borderBottom: `1px solid ${theme.palette.divider}`,
        px: 3,
        py: 2,
        alignItems: "center",
        justifyContent: "space-between",
        gap: 2,
      })}
    >
      <Typography
        id="drawer-title"
        variant="h5"
        noWrap
        // Drawer titles are entity names (files, payloads): keep their casing.
        sx={{ minWidth: 0, textTransform: "none", "&::first-letter": { textTransform: "none" } }}
      >
        {title}
      </Typography>
      <Stack direction="row" sx={{ alignItems: "center", gap: 1, flexShrink: 0 }}>
        {endContent}
        <IconButton aria-label="Close" onClick={onClose}>
          <Close />
        </IconButton>
      </Stack>
    </Stack>
  )
}
