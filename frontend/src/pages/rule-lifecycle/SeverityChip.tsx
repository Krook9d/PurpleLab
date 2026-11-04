import { alpha, Box, useTheme } from "@mui/material"

// Severity tag: dot in the severity color + neutral text.
export function SeverityChip({ value }: { value: unknown }) {
  const theme = useTheme()
  if (value === undefined || value === null || value === "") return <>—</>
  const label = String(value)
  const key = label.toLowerCase() as keyof typeof theme.palette.severity
  const color = theme.palette.severity[key] ?? theme.palette.text.secondary
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
        textTransform: "capitalize",
        border: `1px solid ${alpha(color, 0.4)}`,
        backgroundColor: alpha(color, 0.1),
      }}
    >
      <Box component="span" aria-hidden sx={{ width: 7, height: 7, borderRadius: "50%", backgroundColor: color }} />
      {label}
    </Box>
  )
}
