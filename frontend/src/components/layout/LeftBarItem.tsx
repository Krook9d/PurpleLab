/*
 * Adapted from OpenCTI (opencti-front/src/private/components/nav/LeftBarItem.tsx,
 * pre design-system version), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: flat items only (no sub-menu collapse/popover), draft mode
 * removed, external-link support, router NavLink, typed props.
 */
import { alpha, ListItemButton, ListItemIcon, ListItemText, Tooltip, useTheme } from "@mui/material"
import OpenInNew from "@mui/icons-material/OpenInNew"
import type { SvgIconComponent } from "@mui/icons-material"
import { Link } from "react-router-dom"

interface LeftBarItemProps {
  icon: SvgIconComponent
  label: string
  to?: string
  external?: boolean
  selected?: boolean
  navOpen: boolean
  onClick?: () => void
}

export function LeftBarItem({ icon: Icon, label, to, external, selected = false, navOpen, onClick }: LeftBarItemProps) {
  const theme = useTheme()

  const linkProps = to
    ? external
      ? { component: "a" as const, href: to, target: "_blank", rel: "noreferrer" }
      : { component: Link, to }
    : {}

  return (
    <Tooltip title={navOpen ? "" : label} placement="right" disableInteractive>
      <ListItemButton
        {...linkProps}
        aria-label={label}
        aria-current={selected ? "page" : undefined}
        dense
        onClick={onClick}
        sx={{
          px: 2,
          pr: 1,
          py: 0,
          height: 40,
          flexGrow: 0,
          borderLeft: `2px solid ${selected ? theme.palette.primary.main : "transparent"}`,
          backgroundColor: selected ? alpha(theme.palette.primary.main, 0.1) : "transparent",
          transition: "background-color 150ms ease, border-color 150ms ease",
          "&:hover": {
            backgroundColor: selected ? theme.palette.action.selected : theme.palette.leftBar.hover,
          },
          "&:hover .left-bar-icon": { opacity: 1 },
        }}
      >
        <ListItemIcon
          className="left-bar-icon"
          sx={{
            minWidth: "0px !important",
            mr: 1,
            opacity: selected ? 1 : 0.5,
            color: selected ? theme.palette.primary.main : theme.palette.text.tertiary,
            transition: "opacity 150ms ease",
            "& svg": { fontSize: "18px !important" },
          }}
        >
          <Icon />
        </ListItemIcon>
        {navOpen && (
          <ListItemText
            primary={label}
            sx={{ pt: 0.1, whiteSpace: "nowrap" }}
            slotProps={{
              primary: {
                noWrap: true,
                sx: { fontSize: 14, fontWeight: selected ? 600 : 500, color: theme.palette.leftBar.text },
              },
            }}
          />
        )}
        {navOpen && external && <OpenInNew sx={{ fontSize: 12, opacity: 0.5 }} />}
      </ListItemButton>
    </Tooltip>
  )
}
