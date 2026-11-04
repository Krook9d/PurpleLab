import { alpha, Box, useTheme } from "@mui/material"
import { BrandLogo } from "@/components/common/BrandLogo"
import { NAV_TRANSITION } from "./navConstants"

interface AppBackgroundProps {
  // Left offset of the sidebar edge glow; omitted on full-screen pages (login).
  navWidth?: number
}

// PurpleLab's signature backdrop, layered over the theme body gradient:
// two soft violet radial glows, a faint dot grid, the oversized logo
// watermark in the bottom-right corner and a glow tracing the sidebar edge.
export function AppBackground({ navWidth }: AppBackgroundProps) {
  const theme = useTheme()
  const dark = theme.palette.mode === "dark"
  const glowA = alpha(theme.palette.primary.main, dark ? 0.12 : 0.08)
  const glowB = alpha(theme.palette.brand.to, dark ? 0.1 : 0.06)
  const dot = dark ? "rgba(255, 255, 255, 0.035)" : "rgba(0, 0, 0, 0.05)"

  return (
    <Box aria-hidden sx={{ position: "fixed", inset: 0, zIndex: 0, pointerEvents: "none", overflow: "hidden" }}>
      <Box
        sx={{
          position: "absolute",
          inset: 0,
          background: `radial-gradient(1200px 800px at 15% -10%, ${glowA}, transparent 60%), radial-gradient(1000px 700px at 100% 0%, ${glowB}, transparent 55%), radial-gradient(${dot} 1px, transparent 1px)`,
          backgroundSize: "auto, auto, 28px 28px",
        }}
      />
      <BrandLogo
        decorative
        size="42rem"
        color={dark ? "#ebebef" : theme.palette.primary.dark}
        sx={{ position: "absolute", right: "-10rem", bottom: "-10rem", opacity: dark ? 0.025 : 0.04 }}
      />
      {navWidth !== undefined && (
        <Box
          sx={{
            position: "absolute",
            top: 0,
            bottom: 0,
            left: navWidth,
            width: 96,
            transform: "translateX(-50%)",
            background: `linear-gradient(to bottom, transparent, ${alpha(theme.palette.brand.to, dark ? 0.15 : 0.08)}, transparent)`,
            filter: "blur(64px)",
            transition: `left ${NAV_TRANSITION}`,
          }}
        />
      )}
    </Box>
  )
}
