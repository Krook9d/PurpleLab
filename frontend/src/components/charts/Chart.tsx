/*
 * Adapted from OpenCTI (opencti-front/src/private/components/common/charts/Chart.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: local HTML escaping helper; raw labels escaped, custom
 * formatters trusted to escape themselves; export "mounted" hook and category
 * formatter resolution removed.
 */
import { useEffect, useMemo, useState } from "react"
import ApexChart from "react-apexcharts"
import type { ApexOptions } from "apexcharts"
import { escapeHtml } from "@/utils/escapeHtml"

type ChartType = "bar" | "donut" | "pie" | "polarArea" | "radialBar" | "area" | "line" | "radar" | "heatmap"

interface ChartProps {
  type: ChartType
  options: ApexOptions
  series: ApexOptions["series"]
  width?: string | number
  height?: string | number
}

// Legend items and tooltips are the only parts ApexCharts renders through
// innerHTML: raw labels coming from external data (OTX pulses, SIEM rule
// names...) are escaped here. A builder that supplies its own legend or
// tooltip formatter (utils/chartOptions.ts) returns markup and is responsible
// for escaping the data it interpolates, so it is left untouched.
const escapeRaw = (value: unknown) => escapeHtml(value)

const fontsLoaded = () => typeof document === "undefined" || !document.fonts || document.fonts.status === "loaded"

export function Chart({ type, options, series, width = "100%", height = "100%" }: ChartProps) {
  // ApexCharts measures label widths once at render: wait for the web fonts,
  // otherwise axis labels are measured with the fallback font and get clipped.
  const [fontsReady, setFontsReady] = useState(fontsLoaded)
  useEffect(() => {
    if (fontsReady) return
    let active = true
    document.fonts.ready.then(() => active && setFontsReady(true))
    return () => {
      active = false
    }
  }, [fontsReady])

  const safeOptions = useMemo<ApexOptions>(() => {
    const tooltipY = Array.isArray(options.tooltip?.y) ? options.tooltip?.y[0] : options.tooltip?.y
    return {
      ...options,
      legend: { ...options.legend, formatter: options.legend?.formatter ?? escapeRaw },
      tooltip: {
        ...options.tooltip,
        x: { ...options.tooltip?.x, formatter: options.tooltip?.x?.formatter ?? escapeRaw },
        y: {
          ...tooltipY,
          title: {
            ...tooltipY?.title,
            formatter: tooltipY?.title?.formatter ?? ((v: string) => (v ? `${escapeHtml(v)}: ` : "")),
          },
        },
      },
    }
  }, [options])

  if (!fontsReady) return <div style={{ width, height }} />
  return <ApexChart type={type} options={safeOptions} series={series} width={width} height={height} />
}
