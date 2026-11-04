import type { Theme } from "@mui/material"
import { tacticLabel } from "./mitre"

// Enterprise ATT&CK kill-chain order, mapped onto the theme's validated
// categorical chart palette in that fixed order, so a tactic keeps the same
// color everywhere it appears (search results, technique detail, charts).
export const TACTIC_ORDER = [
  "Reconnaissance",
  "Resource Development",
  "Initial Access",
  "Execution",
  "Persistence",
  "Privilege Escalation",
  // Recent ATT&CK releases split Defense Evasion into Stealth and Defense
  // Impairment; the legacy name is kept for older datasets.
  "Defense Evasion",
  "Stealth",
  "Defense Impairment",
  "Credential Access",
  "Discovery",
  "Lateral Movement",
  "Collection",
  "Command and Control",
  "Exfiltration",
  "Impact",
]

export function tacticColor(theme: Theme, tactic: string): string {
  const index = TACTIC_ORDER.indexOf(tacticLabel(tactic))
  if (index < 0) return theme.palette.text.secondary
  const palette = theme.palette.chart
  return palette[index % palette.length]
}
