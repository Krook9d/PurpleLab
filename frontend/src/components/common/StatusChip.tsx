import { alpha, Box, keyframes, type Theme } from "@mui/material"

export type StatusTone = "success" | "warning" | "error" | "neutral" | "info"

const pulse = keyframes`
  0% { box-shadow: 0 0 0 0 currentColor; }
  70% { box-shadow: 0 0 0 6px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
`

function toneColor(theme: Theme, tone: StatusTone): string {
  if (tone === "neutral") return theme.palette.text.secondary
  return theme.palette[tone].main
}

interface StatusChipProps {
  tone: StatusTone
  label: string
  // Animated halo on the dot for "live" states (running, operational).
  pulsing?: boolean
}

// Status indicator: a colored dot plus a text label, never color alone.
export function StatusChip({ tone, label, pulsing = false }: StatusChipProps) {
  return (
    <Box
      component="span"
      sx={(theme) => {
        const color = toneColor(theme, tone)
        return {
          display: "inline-flex",
          alignItems: "center",
          gap: 0.75,
          height: 24,
          px: 1.25,
          borderRadius: 0.5,
          fontSize: 12,
          fontWeight: 500,
          whiteSpace: "nowrap",
          color: theme.palette.text.primary,
          backgroundColor: alpha(color, 0.12),
          border: `1px solid ${alpha(color, 0.3)}`,
        }
      }}
    >
      <Box
        component="span"
        aria-hidden
        sx={(theme) => ({
          width: 7,
          height: 7,
          borderRadius: "50%",
          color: toneColor(theme, tone),
          backgroundColor: "currentColor",
          animation: pulsing ? `${pulse} 1.8s ease-out infinite` : undefined,
          "@media (prefers-reduced-motion: reduce)": { animation: "none" },
        })}
      />
      {label}
    </Box>
  )
}
