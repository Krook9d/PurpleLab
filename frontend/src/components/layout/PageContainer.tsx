/*
 * Adapted from OpenCTI (opencti-front/src/components/PageContainer.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: context and right-menu padding removed; gap enabled by default.
 */
import type { ReactNode } from "react"
import { Box, type SxProps, type Theme } from "@mui/material"

interface PageContainerProps {
  children: ReactNode
  withGap?: boolean
  sx?: SxProps<Theme>
}

export function PageContainer({ children, withGap = true, sx }: PageContainerProps) {
  return (
    <Box
      sx={[
        { m: 0, p: 0, display: "flex", flexDirection: "column", gap: withGap ? 3 : undefined },
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
    >
      {children}
    </Box>
  )
}
