import { useMemo } from "react"
import { Box, Typography, useTheme } from "@mui/material"
import { Chart } from "@/components/charts/Chart"
import { radialGaugeChartOptions, severityColor } from "@/utils/chartOptions"

interface WidgetRadialGaugeProps {
  label: string
  percent: number
  detail?: string
  height?: number
}

const STATUS_LABEL = (percent: number) => (percent >= 85 ? "Critical" : percent >= 60 ? "Warning" : "Normal")

// Resource meter (RAM / disk / CPU): the fill color carries the severity and
// the status is always spelled out under the gauge, never color alone.
export function WidgetRadialGauge({ label, percent, detail, height = 220 }: WidgetRadialGaugeProps) {
  const theme = useTheme()
  const clamped = Math.max(0, Math.min(100, percent))
  const color = severityColor(theme, clamped)
  const options = useMemo(() => radialGaugeChartOptions(theme, { label, color }), [theme, label, color])
  const series = useMemo(() => [clamped], [clamped])
  return (
    <Box sx={{ textAlign: "center" }}>
      <Chart type="radialBar" options={options} series={series} height={height} />
      <Box sx={{ display: "inline-flex", alignItems: "center", gap: 0.75 }}>
        <Box aria-hidden sx={{ width: 8, height: 8, borderRadius: "50%", backgroundColor: color }} />
        <Typography variant="body2" sx={{ fontWeight: 600 }}>
          {STATUS_LABEL(clamped)}
        </Typography>
      </Box>
      {detail && (
        <Typography variant="body2" color="text.secondary">
          {detail}
        </Typography>
      )}
    </Box>
  )
}
