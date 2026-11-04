/*
 * Adapted from OpenCTI (opencti-front/src/utils/Charts.jsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: typed port of the bar / donut / polar-area builders without
 * entity redirection; colors come from the PurpleLab chart palette; tuned
 * entrance animations; solid hairline grid, bars capped near 24px with values
 * at the tip, 2px surface gaps between slices (dataviz mark specs); new
 * radial gauge builder.
 */
import { alpha, type Theme } from "@mui/material"
import type { ApexOptions } from "apexcharts"
import { escapeHtml } from "@/utils/escapeHtml"

const numberFormat = new Intl.NumberFormat("en-US")
export const formatNumber = (value: number) => numberFormat.format(value)

const fontFamily = '"IBM Plex Sans", sans-serif'

function gridColor(theme: Theme) {
  return theme.palette.mode === "dark" ? "rgba(255, 255, 255, 0.08)" : "rgba(0, 0, 0, 0.08)"
}

// Shared base: transparent background (the card is the surface), hidden
// toolbar, theme text colors, gentle entrance + update animations.
function baseOptions(theme: Theme): ApexOptions {
  return {
    chart: {
      background: "transparent",
      toolbar: { show: false },
      foreColor: theme.palette.text.secondary,
      fontFamily,
      parentHeightOffset: 0,
      animations: {
        enabled: true,
        speed: 800,
        animateGradually: { enabled: true, delay: 60 },
        dynamicAnimation: { enabled: true, speed: 350 },
      },
    },
    theme: { mode: theme.palette.mode },
    states: { hover: { filter: { type: "lighten" } }, active: { filter: { type: "none" } } },
    tooltip: { theme: theme.palette.mode, style: { fontFamily, fontSize: "12px" } },
    dataLabels: { enabled: false },
  }
}

// Tooltip showing only "label: value", styled with theme colors (ApexCharts'
// own tooltip cannot be recolored through its API).
export function labelValueTooltip(theme: Theme, valueFormatter: (v: number) => string = formatNumber) {
  return ({ series, seriesIndex, w }: { series: number[]; seriesIndex: number; w: { config: { labels: string[] } } }) => {
    const label = escapeHtml(w.config.labels[seriesIndex])
    const value = escapeHtml(valueFormatter(series[seriesIndex]))
    return `<div style="background:${theme.palette.background.nav};color:${theme.palette.text.primary};padding:4px 8px;font-size:12px;border:1px solid ${theme.palette.divider}"><span style="color:${theme.palette.text.secondary}">${label}</span>&nbsp;&nbsp;<b>${value}</b></div>`
  }
}

// Round value-axis scale from 0 with ~15% headroom above the largest value, so
// the tip label of the longest bar always fits: the step is the 1/2/2.5/5 x 10^n
// value closest to a 5-tick split, then as many ticks as needed are added.
export function niceValueScale(maxValue: number): { min: number; max: number; tickAmount: number } {
  const top = Math.max(1, maxValue * 1.15)
  const raw = top / 5
  const magnitude = Math.pow(10, Math.floor(Math.log10(raw)))
  const step = [1, 2, 2.5, 5, 10]
    .map((m) => m * magnitude)
    .reduce((best, st) => (Math.abs(st - raw) < Math.abs(best - raw) ? st : best))
  const tickAmount = Math.max(1, Math.ceil(top / step))
  return { min: 0, max: step * tickAmount, tickAmount }
}

interface BarsOptions {
  categories: string[]
  // One color per bar (same order as categories); otherwise a single color.
  colors?: string[]
  // Largest value, used to build a round value-axis scale (niceValueScale).
  maxValue?: number
  color?: string
  valueFormatter?: (v: number) => string
  // Value printed at the bar tip.
  showValues?: boolean
}

export function horizontalBarsChartOptions(theme: Theme, opts: BarsOptions): ApexOptions {
  const base = baseOptions(theme)
  const valueFormatter = opts.valueFormatter ?? formatNumber
  return {
    ...base,
    chart: { ...base.chart, type: "bar" },
    colors: opts.colors ?? [opts.color ?? theme.palette.primary.main],
    grid: {
      borderColor: gridColor(theme),
      strokeDashArray: 0,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: false } },
      // Left padding: ApexCharts slightly under-measures long y-axis labels.
      padding: { left: 16, right: opts.showValues === false ? 8 : 24 },
    },
    xaxis: {
      categories: opts.categories,
      ...(opts.maxValue !== undefined ? niceValueScale(opts.maxValue) : {}),
      labels: { formatter: (v: string) => valueFormatter(Number(v)), style: { fontFamily } },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      labels: { maxWidth: 220, style: { fontFamily, fontSize: "12px", colors: theme.palette.text.primary } },
      axisBorder: { show: false },
    },
    plotOptions: {
      bar: {
        horizontal: true,
        distributed: !!opts.colors,
        barHeight: "62%",
        borderRadius: 4,
        borderRadiusApplication: "end",
        dataLabels: { position: "top" },
      },
    },
    dataLabels: {
      enabled: opts.showValues !== false,
      formatter: (v: number) => valueFormatter(v),
      offsetX: 28,
      style: { fontFamily, fontSize: "11px", fontWeight: 600, colors: [theme.palette.text.primary] },
      dropShadow: { enabled: false },
    },
    tooltip: { ...base.tooltip, y: { formatter: (v: number) => valueFormatter(v) } },
    legend: { show: false },
  }
}

export function verticalBarsChartOptions(theme: Theme, opts: BarsOptions): ApexOptions {
  const base = baseOptions(theme)
  const valueFormatter = opts.valueFormatter ?? formatNumber
  return {
    ...base,
    chart: { ...base.chart, type: "bar" },
    colors: opts.colors ?? [opts.color ?? theme.palette.primary.main],
    grid: { borderColor: gridColor(theme), strokeDashArray: 0 },
    xaxis: {
      categories: opts.categories,
      labels: { style: { fontFamily, fontSize: "12px" }, rotate: -35, hideOverlappingLabels: true, trim: true },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      ...(opts.maxValue !== undefined ? niceValueScale(opts.maxValue) : {}),
      labels: { formatter: (v: number) => valueFormatter(v), style: { fontFamily } },
    },
    plotOptions: {
      bar: { horizontal: false, distributed: !!opts.colors, columnWidth: "45%", borderRadius: 4, borderRadiusApplication: "end", dataLabels: { position: "top" } },
    },
    dataLabels: {
      enabled: opts.showValues !== false,
      formatter: (v: number) => valueFormatter(v),
      offsetY: -18,
      style: { fontFamily, fontSize: "11px", fontWeight: 600, colors: [theme.palette.text.primary] },
      dropShadow: { enabled: false },
    },
    tooltip: { ...base.tooltip, y: { formatter: (v: number) => valueFormatter(v) } },
    legend: { show: false },
  }
}

interface PartsOptions {
  labels: string[]
  colors?: string[]
  legendPosition?: "bottom" | "right" | "left" | "top"
  valueFormatter?: (v: number) => string
}

// Legend entries carry the value next to the label (identity never relies on
// color alone; also the contrast relief for light-mode slots).
function partsLegend(opts: PartsOptions): ApexOptions["legend"] {
  const valueFormatter = opts.valueFormatter ?? formatNumber
  return {
    show: true,
    position: opts.legendPosition ?? "bottom",
    fontFamily,
    fontSize: "12px",
    markers: { strokeWidth: 0, size: 6, shape: "circle" },
    itemMargin: { horizontal: 6, vertical: 3 },
    formatter: (label: string, { seriesIndex, w }: { seriesIndex: number; w: { globals: { series: number[] } } }) =>
      `${escapeHtml(label)}&nbsp;&nbsp;<b>${escapeHtml(valueFormatter(w.globals.series[seriesIndex]))}</b>`,
  }
}

export function donutChartOptions(theme: Theme, opts: PartsOptions & { size?: number; totalLabel?: string }): ApexOptions {
  const base = baseOptions(theme)
  const valueFormatter = opts.valueFormatter ?? formatNumber
  return {
    ...base,
    chart: { ...base.chart, type: "donut" },
    labels: opts.labels,
    colors: opts.colors ?? theme.palette.chart,
    fill: { opacity: 1 },
    // 2px gap in the surface color between slices.
    stroke: { width: 2, colors: [theme.palette.background.paper] },
    legend: partsLegend(opts),
    tooltip: { ...base.tooltip, custom: labelValueTooltip(theme, valueFormatter) },
    plotOptions: {
      pie: {
        expandOnClick: false,
        donut: {
          size: `${opts.size ?? 70}%`,
          labels: {
            show: true,
            name: { show: true, fontFamily, fontSize: "12px", color: theme.palette.text.secondary, offsetY: 18 },
            value: {
              show: true,
              fontFamily: '"Geologica", sans-serif',
              fontSize: "26px",
              fontWeight: 600,
              color: theme.palette.text.primary,
              offsetY: -14,
              formatter: (v: string) => valueFormatter(Number(v)),
            },
            total: {
              show: true,
              showAlways: false,
              label: opts.totalLabel ?? "Total",
              fontFamily,
              fontSize: "12px",
              color: theme.palette.text.secondary,
              formatter: (w: { globals: { seriesTotals: number[] } }) =>
                valueFormatter(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
            },
          },
        },
      },
    },
  }
}

// Same look as the reference polar area: translucent wedges outlined in
// their own color, visible ring scale, plain dot legend (exact values are in
// the tooltip).
export function polarAreaChartOptions(theme: Theme, opts: PartsOptions): ApexOptions {
  const base = baseOptions(theme)
  const ring = gridColor(theme)
  const colors = opts.colors ?? theme.palette.chart
  const valueFormatter = opts.valueFormatter ?? formatNumber
  return {
    ...base,
    chart: { ...base.chart, type: "polarArea" },
    labels: opts.labels,
    colors,
    fill: { opacity: 0.65 },
    stroke: { width: 1.5, colors },
    legend: {
      show: true,
      position: opts.legendPosition ?? "bottom",
      fontFamily,
      fontSize: "12px",
      markers: { strokeWidth: 0, size: 6, shape: "circle" },
      itemMargin: { horizontal: 6, vertical: 3 },
    },
    tooltip: { ...base.tooltip, custom: labelValueTooltip(theme, valueFormatter) },
    yaxis: {
      labels: {
        formatter: (v: number) => valueFormatter(Math.round(v)),
        style: { fontFamily, fontSize: "11px", colors: theme.palette.text.secondary },
      },
    },
    plotOptions: {
      polarArea: { rings: { strokeWidth: 1, strokeColor: ring }, spokes: { strokeWidth: 1, connectorColors: ring } },
    },
  }
}

// Meter: the fill carries severity (accent -> warning -> error), the track is
// a light step of the same hue so the state reads across the whole ring.
export function severityColor(theme: Theme, percent: number): string {
  if (percent >= 85) return theme.palette.error.main
  if (percent >= 60) return theme.palette.warning.main
  return theme.palette.primary.main
}

export function radialGaugeChartOptions(theme: Theme, opts: { label: string; color: string }): ApexOptions {
  const base = baseOptions(theme)
  return {
    ...base,
    chart: { ...base.chart, type: "radialBar", sparkline: { enabled: true } },
    labels: [opts.label],
    colors: [opts.color],
    fill: {
      type: "gradient",
      gradient: { shade: theme.palette.mode, type: "horizontal", shadeIntensity: 0.35, opacityFrom: 1, opacityTo: 1, stops: [0, 100] },
    },
    stroke: { lineCap: "round" },
    plotOptions: {
      radialBar: {
        startAngle: -135,
        endAngle: 135,
        hollow: { size: "64%" },
        track: { background: alpha(opts.color, 0.15), strokeWidth: "100%", margin: 0 },
        dataLabels: {
          name: { show: true, offsetY: 22, fontFamily, fontSize: "12px", color: theme.palette.text.secondary },
          value: {
            show: true,
            offsetY: -12,
            fontFamily: '"Geologica", sans-serif',
            fontSize: "26px",
            fontWeight: 600,
            color: theme.palette.text.primary,
            formatter: (v: number) => `${Math.round(v)}%`,
          },
        },
      },
    },
  }
}
