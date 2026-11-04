/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetPolarArea.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: plain {label, value} data, capped to the palette size
 * (extra entries folded into "Other").
 */
import { useMemo } from "react"
import { useTheme } from "@mui/material"
import { Chart } from "@/components/charts/Chart"
import { polarAreaChartOptions } from "@/utils/chartOptions"
import { foldOther, type ChartDatum } from "@/utils/chartData"

interface WidgetPolarAreaProps {
  data: ChartDatum[]
  height?: number
  maxSlices?: number
}

export function WidgetPolarArea({ data, height = 380, maxSlices = 8 }: WidgetPolarAreaProps) {
  const theme = useTheme()
  const folded = useMemo(() => foldOther(data, Math.min(maxSlices, theme.palette.chart.length)), [data, maxSlices, theme])
  const options = useMemo(() => polarAreaChartOptions(theme, { labels: folded.map((d) => d.label) }), [theme, folded])
  const series = useMemo(() => folded.map((d) => d.value), [folded])
  return <Chart type="polarArea" options={options} series={series} height={height} />
}
