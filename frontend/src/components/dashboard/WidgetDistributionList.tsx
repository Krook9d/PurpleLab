/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetDistributionList.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: plain {label, value} entries with an optional icon and link
 * instead of entity representatives / router links; i18n formatter replaced
 * by Intl.NumberFormat.
 */
import type { ReactNode } from "react"
import { Box, List, ListItemButton, ListItemIcon, ListItemText } from "@mui/material"

const numberFormat = new Intl.NumberFormat("en-US")

export interface DistributionEntry {
  label: string
  value: number
  icon?: ReactNode
  href?: string
}

export function WidgetDistributionList({ data }: { data: DistributionEntry[] }) {
  return (
    <Box sx={{ width: "100%", height: "100%", overflow: "auto" }}>
      <List sx={{ mt: -1.25 }}>
        {data.map((entry) => {
          const linkProps = entry.href ? { component: "a" as const, href: entry.href, target: "_blank", rel: "noreferrer" } : {}
          return (
            <ListItemButton
              key={entry.label}
              dense
              divider
              disableRipple={!entry.href}
              {...linkProps}
              sx={{
                height: 50,
                minHeight: 50,
                maxHeight: 50,
                pr: 0,
                cursor: entry.href ? "pointer" : "default",
                ...(!entry.href && { "&.MuiListItemButton-root:hover": { backgroundColor: "transparent" } }),
              }}
            >
              {entry.icon && <ListItemIcon>{entry.icon}</ListItemIcon>}
              <ListItemText
                primary={
                  <Box component="span" sx={{ display: "block", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", pr: 1.25 }}>
                    {entry.label}
                  </Box>
                }
              />
              <Box sx={(theme) => ({ mr: 2.5, fontSize: 18, fontWeight: 600, color: theme.palette.primary.main, fontVariantNumeric: "tabular-nums" })}>
                {numberFormat.format(entry.value)}
              </Box>
            </ListItemButton>
          )
        })}
      </List>
    </Box>
  )
}
