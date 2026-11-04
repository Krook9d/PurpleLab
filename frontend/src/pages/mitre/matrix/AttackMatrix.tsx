/*
 * Adapted from OpenCTI (opencti-front/src/private/components/techniques/attack_patterns/
 * attack_patterns_matrix/AttackPatternsMatrixColumns.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: columns built from the PurpleLab /mitre/matrix payload instead
 * of Relay fragments, per-column coverage bar, text filter dims instead of
 * hiding, layout sizing and banner logic removed.
 */
import { alpha, Box, Stack, Typography, useTheme } from "@mui/material"
import { cellValue, modeColor, type MatrixColumn, type MatrixMode } from "./matrixModel"
import { MatrixCellView } from "./MatrixCellView"

interface AttackMatrixProps {
  columns: MatrixColumn[]
  mode: MatrixMode
  max: number
  filter: string
  coveredOnly: boolean
  expandAll: boolean
  onSelect: (id: string) => void
}

export function AttackMatrix({ columns, mode, max, filter, coveredOnly, expandAll, onSelect }: AttackMatrixProps) {
  const theme = useTheme()
  const color = modeColor(theme, mode)
  const q = filter.trim().toLowerCase()

  return (
    <Box sx={{ overflowX: "auto", pb: 2 }}>
      <Box sx={{ display: "inline-flex", alignItems: "flex-start", gap: 1.5, minWidth: "100%" }}>
        {columns.map((column) => {
          const covered = column.cells.filter((c) => cellValue(c, mode) > 0).length
          const cells = coveredOnly ? column.cells.filter((c) => cellValue(c, mode) > 0) : column.cells
          return (
            <Stack key={column.tactic} sx={{ flex: "1 0 158px", maxWidth: 210, gap: 0.5 }}>
              <Box sx={{ textAlign: "center", mb: 0.75 }}>
                <Typography noWrap sx={{ fontSize: 13, fontWeight: 600 }} title={column.tactic}>
                  {column.tactic}
                </Typography>
                <Typography variant="caption" color="text.secondary">
                  {covered}/{column.cells.length} techniques
                </Typography>
                <Box sx={{ mt: 0.5, height: 3, borderRadius: 2, backgroundColor: alpha(color, 0.15), overflow: "hidden" }}>
                  <Box
                    sx={{
                      height: "100%",
                      width: `${column.cells.length ? (covered / column.cells.length) * 100 : 0}%`,
                      backgroundColor: color,
                      transition: "width 400ms cubic-bezier(0.4, 0, 0.2, 1), background-color 200ms ease",
                    }}
                  />
                </Box>
              </Box>
              {cells.map((cell) => {
                const matches =
                  !q ||
                  [cell.technique, ...cell.subs].some(
                    (t) => t.id.toLowerCase().includes(q) || (t.name ?? "").toLowerCase().includes(q),
                  )
                return (
                  <MatrixCellView
                    key={`${cell.technique.id}-${expandAll ? "open" : "closed"}`}
                    cell={cell}
                    mode={mode}
                    max={max}
                    dimmed={!matches}
                    defaultExpanded={expandAll}
                    onSelect={onSelect}
                  />
                )
              })}
            </Stack>
          )
        })}
      </Box>
    </Box>
  )
}
