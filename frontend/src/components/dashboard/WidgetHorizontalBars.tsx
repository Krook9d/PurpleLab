/*
 * Adapted from OpenCTI (opencti-front/src/components/dashboard/WidgetHorizontalBars.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: plain {label, value} data instead of entity distributions,
 * height derived from the number of bars, no entity redirection.
 */
import { useMemo } from "react"
import { useTheme } from "@mui/material"
import { Chart } from "@/components/charts/Chart"
import { horizontalBarsChartOptions } from "@/utils/chartOptions"
import { horizontalBarsHeight, type ChartDatum } from "@/utils/chartData"

interface WidgetHorizontalBarsProps {
  data: ChartDatum[]
  name?: string
  color?: string
  valueFormatter?: (v: number) => string
}

export function WidgetHorizontalBars({ data, name = "Count", color, valueFormatter }: WidgetHorizontalBarsProps) {
  const theme = useTheme()
  const options = useMemo(
    () =>
      horizontalBarsChartOptions(theme, {
        categories: data.map((d) => d.label),
        maxValue: Math.max(0, ...data.map((d) => d.value)),
        colors: data.some((d) => d.color) ? data.map((d) => d.color ?? theme.palette.primary.main) : undefined,
        color,
        valueFormatter,
      }),
    [theme, data, color, valueFormatter],
  )
  const series = useMemo(() => [{ name, data: data.map((d) => d.value) }], [name, data])
  return <Chart type="bar" options={options} series={series} height={horizontalBarsHeight(data.length)} />
}
