/*
 * Adapted from OpenCTI (opencti-front/src/components/common/card/Card.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: design-system CSS variables replaced by theme colors; hover
 * lift + violet glow on interactive cards; react-router-dom Link.
 */
import type { CSSProperties, ReactNode } from "react"
import { alpha, CardActionArea, Card as MuiCard, Stack, type SxProps, type Theme } from "@mui/material"
import { Link } from "react-router-dom"
import { CardTitle } from "./CardTitle"

export type CardPadding = "none" | "small" | "medium" | "horizontal" | "default"

export interface CardProps {
  children?: ReactNode
  title?: ReactNode
  action?: ReactNode
  padding?: CardPadding
  sx?: SxProps<Theme>
  titleSx?: SxProps<Theme>
  titleAlignItems?: CSSProperties["alignItems"]
  fullHeight?: boolean
  // Hover glow without making the whole card clickable (e.g. cards with buttons).
  hoverable?: boolean
  onClick?: () => void
  to?: string
  disabled?: boolean
  "aria-label"?: string
}

const PADDINGS: Record<CardPadding, SxProps<Theme>> = {
  default: { p: 3 },
  horizontal: { px: 3, py: 1 },
  small: { p: 1 },
  medium: { px: 3, py: 2 },
  none: { p: 0 },
}

export function Card({
  title,
  children,
  action,
  padding = "default",
  sx,
  titleSx,
  titleAlignItems,
  fullHeight = true,
  hoverable = false,
  onClick,
  to,
  disabled,
  ...otherProps
}: CardProps) {
  const clickable = Boolean(onClick || to)
  const paddingSx = PADDINGS[padding]
  const extraSx = Array.isArray(sx) ? sx : [sx]

  const content = clickable ? (
    <CardActionArea
      disabled={disabled}
      onClick={onClick}
      {...(to ? { component: Link, to } : {})}
      sx={[{ height: "100%" }, paddingSx, ...extraSx]}
    >
      {children}
    </CardActionArea>
  ) : (
    children
  )

  return (
    <Stack sx={{ height: fullHeight ? "100%" : undefined }}>
      {(title || action) && (
        <CardTitle action={action} sx={titleSx} alignItems={titleAlignItems}>
          {title}
        </CardTitle>
      )}
      <MuiCard
        elevation={0}
        {...otherProps}
        sx={[
          (theme) => ({
            position: "relative",
            flexGrow: fullHeight ? 1 : 0,
            borderRadius: 0.5,
            backgroundColor: alpha(theme.palette.background.paper, theme.palette.mode === "dark" ? 0.85 : 0.9),
            backgroundImage: "none",
            border: `1px solid ${alpha(theme.palette.primary.main, theme.palette.mode === "dark" ? 0.14 : 0.16)}`,
            transition: "border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease",
          }),
          (clickable || hoverable) &&
            ((theme) => ({
              "&:hover, &:focus-within": {
                borderColor: alpha(theme.palette.primary.main, 0.45),
                boxShadow: `0 0 0 1px ${alpha(theme.palette.primary.main, 0.15)}, 0 8px 24px -8px ${alpha(theme.palette.primary.main, 0.35)}`,
              },
            })),
          clickable && !disabled && { "&:hover": { transform: "translateY(-2px)" } },
          ...(clickable ? [] : [paddingSx, ...extraSx]),
        ]}
      >
        {content}
      </MuiCard>
    </Stack>
  )
}
