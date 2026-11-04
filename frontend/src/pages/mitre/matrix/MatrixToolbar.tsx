import { alpha, Box, FormControlLabel, Stack, Switch, ToggleButton, ToggleButtonGroup, Typography, useTheme } from "@mui/material"
import ScienceOutlined from "@mui/icons-material/ScienceOutlined"
import RuleOutlined from "@mui/icons-material/RuleOutlined"
import PlayCircleOutlined from "@mui/icons-material/PlayCircleOutlined"
import { Card } from "@/components/common/Card"
import { SearchInput } from "@/components/common/SearchInput"
import { MODE_META, SCALE_STEPS, modeColor, type MatrixMode } from "./matrixModel"

const MODE_ICONS = { atomic: ScienceOutlined, sigma: RuleOutlined, executed: PlayCircleOutlined } as const

interface MatrixToolbarProps {
  mode: MatrixMode
  onModeChange: (mode: MatrixMode) => void
  covered: number
  total: number
  filter: string
  onFilterChange: (v: string) => void
  coveredOnly: boolean
  onCoveredOnlyChange: (v: boolean) => void
  expandAll: boolean
  onExpandAllChange: (v: boolean) => void
}

export function MatrixToolbar(props: MatrixToolbarProps) {
  const theme = useTheme()
  const color = modeColor(theme, props.mode)
  const pct = props.total ? Math.round((props.covered / props.total) * 100) : 0

  return (
    <Card padding="medium" fullHeight={false}>
      <Stack direction="row" useFlexGap sx={{ gap: 3, alignItems: "center", flexWrap: "wrap" }}>
        <Box>
          <Typography variant="body2" color="text.secondary" sx={{ fontSize: 11, mb: 0.5 }}>
            Coverage by
          </Typography>
          <ToggleButtonGroup
            value={props.mode}
            exclusive
            onChange={(_, v: MatrixMode | null) => v && props.onModeChange(v)}
            aria-label="Coverage mode"
          >
            {(Object.keys(MODE_META) as MatrixMode[]).map((m) => {
              const Icon = MODE_ICONS[m]
              return (
                <ToggleButton key={m} value={m} sx={{ gap: 0.75, px: 1.5, textTransform: "none" }}>
                  <Icon sx={{ fontSize: 16 }} />
                  {MODE_META[m].label}
                </ToggleButton>
              )
            })}
          </ToggleButtonGroup>
        </Box>

        <Box sx={{ minWidth: 220, flex: "0 1 280px" }}>
          <Stack direction="row" sx={{ alignItems: "baseline", gap: 1 }}>
            <Typography sx={{ fontSize: 24, fontWeight: 600, color, fontVariantNumeric: "tabular-nums" }}>{pct}%</Typography>
            <Typography variant="body2" color="text.secondary">
              {props.covered} / {props.total} techniques covered
            </Typography>
          </Stack>
          <Box sx={{ mt: 0.5, height: 6, borderRadius: 3, backgroundColor: alpha(color, 0.15), overflow: "hidden" }}>
            <Box sx={{ height: "100%", width: `${pct}%`, backgroundColor: color, borderRadius: 3, transition: "width 500ms cubic-bezier(0.4, 0, 0.2, 1)" }} />
          </Box>
          <Typography variant="body2" sx={{ fontSize: 11, color: "text.tertiary", mt: 0.5 }}>
            {MODE_META[props.mode].description}
          </Typography>
        </Box>

        <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }} aria-label="Color scale">
          <Typography variant="body2" color="text.secondary" sx={{ fontSize: 11 }}>
            None
          </Typography>
          <Box sx={{ width: 16, height: 10, border: `1px solid ${theme.palette.background.accent}`, borderRadius: 0.25 }} />
          {SCALE_STEPS.map((a) => (
            <Box key={a} sx={{ width: 16, height: 10, borderRadius: 0.25, backgroundColor: alpha(color, a), border: `1px solid ${alpha(color, 0.5)}` }} />
          ))}
          <Typography variant="body2" color="text.secondary" sx={{ fontSize: 11 }}>
            More {MODE_META[props.mode].unit}
          </Typography>
        </Stack>

        <Box sx={{ flex: 1 }} />

        <SearchInput value={props.filter} onChange={props.onFilterChange} placeholder="Highlight technique…" sx={{ width: 240 }} />
        <Stack>
          <FormControlLabel
            control={<Switch size="small" checked={props.coveredOnly} onChange={(e) => props.onCoveredOnlyChange(e.target.checked)} />}
            label={<Typography variant="body2">Covered only</Typography>}
          />
          <FormControlLabel
            control={<Switch size="small" checked={props.expandAll} onChange={(e) => props.onExpandAllChange(e.target.checked)} />}
            label={<Typography variant="body2">Expand sub-techniques</Typography>}
          />
        </Stack>
      </Stack>
    </Card>
  )
}
