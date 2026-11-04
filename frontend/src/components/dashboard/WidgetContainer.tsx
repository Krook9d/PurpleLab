/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetContainer.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: export popover, preview tag, error boundary and in-line
 * variants removed; built-in loading and empty states; vertically centered
 * content.
 */
import type { CSSProperties, ReactNode } from "react"
import { Box } from "@mui/material"
import { Card, type CardPadding } from "@/components/common/Card"
import { Loader } from "@/components/common/Loader"
import { WidgetNoData } from "./WidgetNoData"

interface WidgetContainerProps {
  children?: ReactNode
  title?: ReactNode
  action?: ReactNode
  height?: CSSProperties["height"]
  padding?: CardPadding
  loading?: boolean
  empty?: boolean
  emptyMessage?: ReactNode
  hoverable?: boolean
  // Vertically center the content when the card is taller than it
  // (cards in a grid row stretch to the tallest one).
  centered?: boolean
}

export function WidgetContainer({
  children,
  title,
  action,
  height,
  padding = "medium",
  loading = false,
  empty = false,
  emptyMessage,
  hoverable = true,
  centered = true,
}: WidgetContainerProps) {
  let content = children
  if (loading) content = <Loader variant="inElement" />
  else if (empty) content = <WidgetNoData message={emptyMessage} />
  return (
    <Box sx={{ height: height ?? "100%" }}>
      <Card
        title={title}
        action={action}
        padding={padding}
        hoverable={hoverable}
        sx={centered ? { display: "flex", flexDirection: "column", justifyContent: "center" } : undefined}
      >
        {content}
      </Card>
    </Box>
  )
}
