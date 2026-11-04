/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetNumber.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: wrapped in a themed card, count-up animation, auto-compact
 * number formatting, category icon as a large animated watermark instead of
 * entity icons; difference indicator and i18n removed.
 */
import type { ReactNode } from "react"
import { alpha, Box, Skeleton, Stack, Typography } from "@mui/material"
import { Card } from "@/components/common/Card"
import { useCountUp } from "@/hooks/useCountUp"

const full = new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 })
const compact = new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 })

function formatValue(v: number): string {
  return Math.abs(v) >= 10000 ? compact.format(v) : full.format(v)
}

interface WidgetNumberProps {
  label: string
  value: number | undefined
  // Category icon, rendered as a large watermark in the tile corner.
  icon?: ReactNode
  // Tint of the watermark; defaults to white (dark mode) / violet (light mode).
  color?: string
  loading?: boolean
  action?: ReactNode
  // Makes the whole tile a link (e.g. to the page behind the number).
  to?: string
}

export function WidgetNumber({ label, value, icon, color, loading = false, action, to }: WidgetNumberProps) {
  const animated = useCountUp(loading ? undefined : value)

  return (
    <Card padding="medium" hoverable to={to} aria-label={to ? label : undefined}>
      <Box sx={{ position: "relative", height: "100%", "&:hover .widget-watermark": { transform: "scale(1.08) rotate(-6deg)", opacity: 1 } }}>
        {icon && (
          <Box
            className="widget-watermark"
            aria-hidden
            sx={(theme) => {
              const tint = color ?? (theme.palette.mode === "dark" ? "#ffffff" : theme.palette.primary.dark)
              return {
                position: "absolute",
                right: -18,
                bottom: -30,
                lineHeight: 0,
                pointerEvents: "none",
                color: alpha(tint, theme.palette.mode === "dark" ? 0.07 : 0.09),
                opacity: 0.85,
                transformOrigin: "70% 70%",
                transition: "transform 300ms cubic-bezier(0.4, 0, 0.2, 1), opacity 300ms ease",
                "& svg": { fontSize: 104 },
              }
            }}
          >
            {icon}
          </Box>
        )}
        <Stack sx={{ position: "relative", height: "100%", justifyContent: "space-between", gap: 1.5 }}>
          <Stack direction="row" sx={{ alignItems: "flex-start", gap: 1 }}>
            <Typography variant="body2" sx={{ color: "text.light", flex: 1 }}>
              {label}
            </Typography>
            {action}
          </Stack>
          {loading || value === undefined ? (
            <Skeleton variant="text" width={80} sx={{ fontSize: 32 }} />
          ) : (
            <Box
              data-testid={`card-number-${label}`}
              sx={{ fontSize: 32, lineHeight: 1, fontWeight: 600, fontVariantNumeric: "tabular-nums" }}
            >
              {formatValue(Math.round(animated ?? 0))}
            </Box>
          )}
        </Stack>
      </Box>
    </Card>
  )
}
