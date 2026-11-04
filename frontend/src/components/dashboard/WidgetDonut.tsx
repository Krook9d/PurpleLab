/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetDonut.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: plain {label, value} data, "Other" folding past the palette
 * size, centered total.
 */
import { useMemo } from "react"
import { useTheme } from "@mui/material"
import { Chart } from "@/components/charts/Chart"
import { donutChartOptions } from "@/utils/chartOptions"
import { foldOther, type ChartDatum } from "@/utils/chartData"

interface WidgetDonutProps {
  data: ChartDatum[]
  height?: number
  maxSlices?: number
  totalLabel?: string
  legendPosition?: "bottom" | "right"
}

export function WidgetDonut({ data, height = 320, maxSlices = 6, totalLabel, legendPosition = "bottom" }: WidgetDonutProps) {
  const theme = useTheme()
  const folded = useMemo(() => foldOther(data, Math.min(maxSlices, theme.palette.chart.length)), [data, maxSlices, theme])
  const options = useMemo(
    () => donutChartOptions(theme, { labels: folded.map((d) => d.label), totalLabel, legendPosition }),
    [theme, folded, totalLabel, legendPosition],
  )
  const series = useMemo(() => folded.map((d) => d.value), [folded])
  return <Chart type="donut" options={options} series={series} height={height} />
}
