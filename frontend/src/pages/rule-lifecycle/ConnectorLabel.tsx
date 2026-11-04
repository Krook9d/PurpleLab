import { Stack } from "@mui/material"
import type { ConnectorType } from "@/lib/types"
import { CONNECTOR_META } from "./connectorMeta"

export function ConnectorLabel({ type, size = 16 }: { type: string | null; size?: number }) {
  const meta = type ? CONNECTOR_META[type as ConnectorType] : undefined
  if (!meta) return <>{type ?? "—"}</>
  const Logo = meta.logo
  // Wordmarks already spell the name: shown alone, at a per-logo scale.
  if (meta.wordmark) {
    return (
      <Stack component="span" role="img" aria-label={meta.label} direction="row" sx={{ alignItems: "center", display: "inline-flex" }}>
        <Logo height={Math.round(size * (meta.inlineScale ?? 1))} />
      </Stack>
    )
  }
  return (
    <Stack component="span" direction="row" sx={{ alignItems: "center", gap: 0.75, display: "inline-flex" }}>
      <Logo height={size} />
      {meta.label}
    </Stack>
  )
}
