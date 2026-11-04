/*
 * Adapted from OpenCTI (opencti-front/src/private/components/techniques/attack_patterns/
 * attack_patterns_matrix/AttackPatternsMatrixColumsElement.tsx, AttackPatternsMatrixAccordion.tsx
 * and AttackPatternsMatrixBadge.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: one component for plain and sub-technique cells, colors from
 * a sequential coverage scale (Atomic tests / Sigma rules / executions)
 * instead of security-coverage scores, typed props instead of Relay data,
 * hover tooltip with all counters, failed-run marker; context menu removed.
 */
import { useState, type ReactNode } from "react"
import { Badge, Box, Collapse, IconButton, Tooltip, useTheme } from "@mui/material"
import ChevronRight from "@mui/icons-material/ChevronRight"
import type { MatrixTechnique } from "@/lib/types"
import { cellColors, cellValue, coveredSubs, hasFailedRun, modeColor, valueOf, type MatrixCell, type MatrixMode } from "./matrixModel"
import { TechniqueTooltip } from "./TechniqueTooltip"

interface MatrixCellViewProps {
  cell: MatrixCell
  mode: MatrixMode
  max: number
  dimmed: boolean
  defaultExpanded: boolean
  onSelect: (id: string) => void
}

function CellBox({
  technique,
  value,
  mode,
  max,
  sub = false,
  failed = false,
  leading,
  subCount = 0,
  onSelect,
}: {
  technique: MatrixTechnique
  value: number
  mode: MatrixMode
  max: number
  sub?: boolean
  failed?: boolean
  leading?: ReactNode
  subCount?: number
  onSelect: (id: string) => void
}) {
  const theme = useTheme()
  const colors = cellColors(theme, mode, value, max)
  return (
    <Tooltip
      title={<TechniqueTooltip technique={technique} subCount={subCount} />}
      placement="right"
      enterDelay={450}
      enterNextDelay={300}
      disableInteractive
    >
      <Box
        role="button"
        tabIndex={0}
        aria-label={`${technique.id} ${technique.name ?? ""}: ${value}`}
        onClick={() => onSelect(technique.id)}
        onKeyDown={(e) => (e.key === "Enter" || e.key === " ") && onSelect(technique.id)}
        style={{ backgroundColor: colors.backgroundColor, borderColor: colors.borderColor }}
        sx={{
          position: "relative",
          display: "flex",
          alignItems: "center",
          gap: 0.5,
          width: "100%",
          minHeight: sub ? 30 : 36,
          pl: leading ? 0.25 : sub ? 2.5 : 1.25,
          pr: 1,
          py: 0.5,
          border: "1px solid",
          cursor: "pointer",
          whiteSpace: "normal",
          transition: "filter 120ms ease, box-shadow 120ms ease",
          "&:hover, &:focus-visible": {
            filter: "brightness(1.25)",
            boxShadow: `inset 0 0 0 1px ${theme.palette.primary.main}`,
            outline: "none",
          },
        }}
      >
        {leading}
        <Box component="span" sx={{ flex: 1, fontSize: sub ? 10 : 10.5, lineHeight: 1.3, color: "text.primary" }}>
          {/* "Parent: Sub" names: the parent prefix is redundant under the parent cell. */}
          {sub ? (technique.name ?? "").split(": ").slice(1).join(": ") || technique.name : technique.name}
        </Box>
        {failed && (
          <Box component="span" aria-label="last run failed" sx={{ width: 6, height: 6, borderRadius: "50%", flexShrink: 0, bgcolor: "error.main" }} />
        )}
        {value > 0 && (
          <Box component="span" sx={{ fontSize: 10.5, fontWeight: 700, flexShrink: 0, color: modeColor(theme, mode), fontVariantNumeric: "tabular-nums" }}>
            {value}
          </Box>
        )}
      </Box>
    </Tooltip>
  )
}

export function MatrixCellView({ cell, mode, max, dimmed, defaultExpanded, onSelect }: MatrixCellViewProps) {
  const theme = useTheme()
  const [expanded, setExpanded] = useState(defaultExpanded)
  const value = cellValue(cell, mode)
  const hasSubs = cell.subs.length > 0
  const covered = (valueOf(cell.technique, mode) > 0 ? 1 : 0) + coveredSubs(cell, mode)
  const failed = mode === "executed" && hasFailedRun(cell)

  const parent = (
    <CellBox
      technique={cell.technique}
      value={value}
      mode={mode}
      max={max}
      failed={failed}
      subCount={cell.subs.length}
      onSelect={onSelect}
      leading={
        hasSubs ? (
          <IconButton
            size="small"
            aria-label={expanded ? "Collapse sub-techniques" : "Expand sub-techniques"}
            aria-expanded={expanded}
            onClick={(e) => {
              e.stopPropagation()
              setExpanded((v) => !v)
            }}
            sx={{ p: 0.25, color: "text.secondary" }}
          >
            <ChevronRight sx={{ fontSize: 14, transition: "transform 150ms ease", transform: expanded ? "rotate(90deg)" : "none" }} />
          </IconButton>
        ) : undefined
      }
    />
  )

  return (
    <Box sx={{ opacity: dimmed ? 0.22 : 1, transition: "opacity 150ms ease" }}>
      {hasSubs ? (
        <Badge
          invisible={covered === 0}
          badgeContent={`${covered}/${cell.subs.length + 1}`}
          overlap="rectangular"
          anchorOrigin={{ vertical: "top", horizontal: "right" }}
          sx={{
            display: "block",
            "& .MuiBadge-badge": {
              backgroundColor: modeColor(theme, mode),
              color: "#fff",
              height: 14,
              minWidth: 14,
              fontSize: 9.5,
              px: 0.5,
              top: 2,
              right: 6,
            },
          }}
        >
          {parent}
        </Badge>
      ) : (
        parent
      )}
      {hasSubs && (
        <Collapse in={expanded} timeout="auto" unmountOnExit>
          <Box sx={{ borderLeft: `2px solid ${theme.palette.background.accent}`, ml: 1 }}>
            {cell.subs.map((sub) => (
              <CellBox key={sub.id} technique={sub} value={valueOf(sub, mode)} mode={mode} max={max} sub onSelect={onSelect} />
            ))}
          </Box>
        </Collapse>
      )}
    </Box>
  )
}
