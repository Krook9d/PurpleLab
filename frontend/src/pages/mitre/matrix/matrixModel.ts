import { alpha, type Theme } from "@mui/material"
import { splitList, tacticLabel } from "@/lib/mitre"
import { TACTIC_ORDER } from "@/lib/tacticColors"
import type { MatrixTechnique } from "@/lib/types"

export type MatrixMode = "atomic" | "sigma" | "executed"
export const MATRIX_MODES = ["atomic", "sigma", "executed"] as const

export const MODE_META: Record<MatrixMode, { label: string; unit: string; description: string }> = {
  atomic: { label: "Atomic tests", unit: "tests", description: "Atomic Red Team tests available to simulate the technique" },
  sigma: { label: "Sigma rules", unit: "rules", description: "Sigma rules tagged with the technique (attack.tXXXX)" },
  executed: { label: "Executed", unit: "runs", description: "Atomic tests already run on the sandbox VM" },
}

export const valueOf = (t: MatrixTechnique, mode: MatrixMode): number =>
  mode === "atomic" ? t.atomic_tests : mode === "sigma" ? t.sigma_rules : t.executions

export interface MatrixCell {
  technique: MatrixTechnique
  subs: MatrixTechnique[]
}

export interface MatrixColumn {
  tactic: string
  cells: MatrixCell[]
}

// Parent techniques grouped by kill-chain tactic (a technique appears under
// each of its tactics), sub-techniques attached to their parent. Tactics
// unknown to TACTIC_ORDER (future ATT&CK releases) get a column at the end
// instead of being dropped; empty columns are hidden.
export function buildColumns(techniques: MatrixTechnique[]): MatrixColumn[] {
  const subsByParent = new Map<string, MatrixTechnique[]>()
  for (const t of techniques) {
    if (!t.is_sub_technique) continue
    const parentId = t.id.split(".")[0]
    subsByParent.set(parentId, [...(subsByParent.get(parentId) ?? []), t])
  }
  const columns = new Map<string, MatrixCell[]>(TACTIC_ORDER.map((tactic) => [tactic, []]))
  for (const t of techniques) {
    if (t.is_sub_technique) continue
    const subs = (subsByParent.get(t.id) ?? []).sort((a, b) => (a.name ?? "").localeCompare(b.name ?? ""))
    for (const tactic of splitList(t.tactics).map(tacticLabel)) {
      if (!columns.has(tactic)) columns.set(tactic, [])
      columns.get(tactic)?.push({ technique: t, subs })
    }
  }
  return [...columns.entries()]
    .filter(([, cells]) => cells.length > 0)
    .map(([tactic, cells]) => ({
      tactic,
      cells: cells.sort((a, b) => (a.technique.name ?? "").localeCompare(b.technique.name ?? "")),
    }))
}

// Parent value includes its sub-techniques.
export const cellValue = (cell: MatrixCell, mode: MatrixMode): number =>
  valueOf(cell.technique, mode) + cell.subs.reduce((sum, s) => sum + valueOf(s, mode), 0)

export const coveredSubs = (cell: MatrixCell, mode: MatrixMode): number =>
  cell.subs.filter((s) => valueOf(s, mode) > 0).length

export const hasFailedRun = (cell: MatrixCell): boolean =>
  [cell.technique, ...cell.subs].some((t) => t.last_execution_status === "error")

export function modeColor(theme: Theme, mode: MatrixMode): string {
  if (mode === "sigma") return theme.palette.chart[2]
  if (mode === "executed") return theme.palette.success.main
  return theme.palette.primary.main
}

// Sequential single-hue scale on a log axis (a few techniques have many
// tests, most have a handful): 0 = uncovered, then 4 steps up to the max.
export const SCALE_STEPS = [0.14, 0.26, 0.4, 0.58] as const

export function scaleStep(value: number, max: number): number {
  if (value <= 0 || max <= 0) return -1
  const t = Math.log1p(value) / Math.log1p(max)
  return Math.min(SCALE_STEPS.length - 1, Math.max(0, Math.ceil(t * SCALE_STEPS.length) - 1))
}

export function cellColors(theme: Theme, mode: MatrixMode, value: number, max: number) {
  const step = scaleStep(value, max)
  if (step < 0) return { backgroundColor: "transparent", borderColor: theme.palette.background.accent }
  const color = modeColor(theme, mode)
  return { backgroundColor: alpha(color, SCALE_STEPS[step]), borderColor: alpha(color, 0.35 + step * 0.15) }
}
