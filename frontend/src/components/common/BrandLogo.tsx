import { Box, type SxProps, type Theme } from "@mui/material"

interface BrandLogoProps {
  size: number | string
  // Defaults to the theme's logo color (original light grey in dark mode,
  // deep violet in light mode).
  color?: string
  glow?: boolean
  decorative?: boolean
  sx?: SxProps<Theme>
}

// The PurpleLab mark is a single-color PNG with transparency: it is used as a
// CSS mask so the original file stays untouched while its color follows the
// theme.
export function BrandLogo({ size, color, glow = false, decorative = false, sx }: BrandLogoProps) {
  return (
    <Box
      role={decorative ? undefined : "img"}
      aria-label={decorative ? undefined : "PurpleLab"}
      aria-hidden={decorative || undefined}
      sx={[
        (theme) => ({
          width: size,
          height: size,
          flexShrink: 0,
          backgroundColor: color ?? (theme.palette.mode === "dark" ? "#ebebef" : theme.palette.primary.dark),
          maskImage: "url(/brand/logo-mark.png)",
          maskSize: "contain",
          maskRepeat: "no-repeat",
          maskPosition: "center",
          filter: glow ? `drop-shadow(0 0 10px ${theme.palette.primary.main}8c)` : undefined,
        }),
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
    />
  )
}
