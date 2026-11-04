import { Box, Stack, Typography } from "@mui/material"
import { formatDateTime } from "@/lib/format"
import type { MatrixTechnique } from "@/lib/types"

// Hover summary of a technique: the three coverage counters at once.
export function TechniqueTooltip({ technique, subCount }: { technique: MatrixTechnique; subCount: number }) {
  const rows: [string, number][] = [
    ["Atomic tests", technique.atomic_tests],
    ["Sigma rules", technique.sigma_rules],
    ["Executions", technique.executions],
  ]
  return (
    <Box sx={{ p: 0.5, minWidth: 180 }}>
      <Typography sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 11, opacity: 0.8 })}>{technique.id}</Typography>
      <Typography sx={{ fontSize: 12, fontWeight: 600, mb: 0.75, color: "#fff" }}>{technique.name}</Typography>
      {rows.map(([label, value]) => (
        <Stack key={label} direction="row" sx={{ justifyContent: "space-between", gap: 2, fontSize: 11.5 }}>
          <span>{label}</span>
          <b>{value}</b>
        </Stack>
      ))}
      {technique.last_executed_at && (
        <Typography sx={{ fontSize: 11, mt: 0.5, opacity: 0.8, color: "#fff" }}>
          Last run {formatDateTime(technique.last_executed_at)} · {technique.last_execution_status}
        </Typography>
      )}
      {subCount > 0 && <Typography sx={{ fontSize: 11, mt: 0.5, opacity: 0.8, color: "#fff" }}>{subCount} sub-techniques (own counts above)</Typography>}
    </Box>
  )
}
