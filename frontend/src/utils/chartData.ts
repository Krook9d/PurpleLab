export interface ChartDatum {
  label: string
  value: number
  // Optional per-entry color (bars are then colored individually).
  color?: string
}

// Slices past maxSlices are folded into "Other" (never a generated hue).
export function foldOther(data: ChartDatum[], maxSlices: number): ChartDatum[] {
  if (data.length <= maxSlices) return data
  const head = data.slice(0, maxSlices - 1)
  const rest = data.slice(maxSlices - 1).reduce((sum, d) => sum + d.value, 0)
  return [...head, { label: "Other", value: rest }]
}

// ~32px per bar keeps every bar thin (<= ~24px) whatever the data size.
export function horizontalBarsHeight(count: number): number {
  return Math.max(180, count * 32 + 40)
}
