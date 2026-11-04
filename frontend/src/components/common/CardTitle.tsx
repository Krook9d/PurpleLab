/*
 * Adapted from OpenCTI (opencti-front/src/components/common/card/CardTitle.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: sx merge helper inlined; uppercase small-caps label style.
 */
import type { CSSProperties, ReactNode } from "react"
import { Stack, Typography, type SxProps, type Theme } from "@mui/material"

interface CardTitleProps {
  children?: ReactNode
  action?: ReactNode
  alignItems?: CSSProperties["alignItems"]
  sx?: SxProps<Theme>
}

export function CardTitle({ children, alignItems = "center", action, sx }: CardTitleProps) {
  return (
    <Stack
      direction="row"
      sx={[
        { justifyContent: "space-between", alignItems, minHeight: 19, mb: 1, flex: 0 },
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
    >
      <Typography
        variant="h5"
        sx={{
          mb: 0,
          color: "text.light",
          lineHeight: "19px",
          fontSize: 12,
          fontWeight: 500,
          letterSpacing: "0.04em",
          textTransform: "uppercase",
          "&::first-letter": { textTransform: "uppercase" },
          fontFamily: '"IBM Plex Sans", sans-serif',
        }}
      >
        {children}
      </Typography>
      <Stack direction="row" sx={{ gap: 1, alignItems: "center" }}>
        {action}
      </Stack>
    </Stack>
  )
}
