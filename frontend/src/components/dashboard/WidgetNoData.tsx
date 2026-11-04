/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetNoData.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: i18n removed, themed muted text and optional icon.
 */
import type { ReactNode } from "react"
import { Box, Typography } from "@mui/material"
import InsightsOutlined from "@mui/icons-material/InsightsOutlined"

export function WidgetNoData({ message, icon }: { message?: ReactNode; icon?: ReactNode }) {
  return (
    <Box
      sx={{
        height: "100%",
        minHeight: 120,
        display: "flex",
        flexDirection: "column",
        gap: 1,
        alignItems: "center",
        justifyContent: "center",
        color: "text.tertiary",
        textAlign: "center",
      }}
    >
      {icon ?? <InsightsOutlined sx={{ fontSize: 28, opacity: 0.6 }} />}
      <Typography variant="body2" color="text.secondary">
        {message ?? "No data has been found."}
      </Typography>
    </Box>
  )
}
