import { alpha, Box, useTheme } from "@mui/material"
import { tacticLabel } from "@/lib/mitre"
import { tacticColor } from "@/lib/tacticColors"

// Tactic tag: colored dot (identity) + neutral text label.
export function TacticChip({ tactic }: { tactic: string }) {
  const theme = useTheme()
  const color = tacticColor(theme, tactic)
  return (
    <Box
      component="span"
      sx={{
        display: "inline-flex",
        alignItems: "center",
        gap: 0.75,
        height: 22,
        px: 1,
        borderRadius: 0.5,
        fontSize: 12,
        whiteSpace: "nowrap",
        color: "text.primary",
        border: `1px solid ${alpha(color, 0.45)}`,
        backgroundColor: alpha(color, 0.1),
      }}
    >
      <Box component="span" aria-hidden sx={{ width: 7, height: 7, borderRadius: "50%", backgroundColor: color }} />
      {tacticLabel(tactic)}
    </Box>
  )
}
