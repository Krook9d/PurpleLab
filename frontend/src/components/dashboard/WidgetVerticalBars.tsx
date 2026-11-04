/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetVerticalBars.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: plain {label, value} data instead of time series.
 */
import { useMemo } from "react"
import { useTheme } from "@mui/material"
import { Chart } from "@/components/charts/Chart"
import { verticalBarsChartOptions } from "@/utils/chartOptions"
import type { ChartDatum } from "@/utils/chartData"

interface WidgetVerticalBarsProps {
  data: ChartDatum[]
  name?: string
  color?: string
  height?: number
  valueFormatter?: (v: number) => string
}

export function WidgetVerticalBars({ data, name = "Count", color, height = 300, valueFormatter }: WidgetVerticalBarsProps) {
  const theme = useTheme()
  const options = useMemo(
    () =>
      verticalBarsChartOptions(theme, {
        categories: data.map((d) => d.label),
        maxValue: Math.max(0, ...data.map((d) => d.value)),
        colors: data.some((d) => d.color) ? data.map((d) => d.color ?? theme.palette.primary.main) : undefined,
        color,
        valueFormatter,
      }),
    [theme, data, color, valueFormatter],
  )
  const series = useMemo(() => [{ name, data: data.map((d) => d.value) }], [name, data])
  return <Chart type="bar" options={options} series={series} height={height} />
}
