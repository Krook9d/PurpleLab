import { alpha, Box, Stack, Typography, useTheme } from "@mui/material"
import { Drawer } from "@/components/common/Drawer"
import { useTechnique } from "@/hooks/useMitre"
import type { MatrixTechnique } from "@/lib/types"
import { AtomicTestsTable } from "../AtomicTestsTable"
import { TechniqueDetailCard } from "../TechniqueDetailCard"
import { MATRIX_MODES, MODE_META, modeColor, valueOf } from "./matrixModel"

interface TechniqueDrawerProps {
  techniqueId: string | null
  coverage: MatrixTechnique | undefined
  onSelect: (id: string) => void
  onClose: () => void
}

export function TechniqueDrawer({ techniqueId, coverage, onSelect, onClose }: TechniqueDrawerProps) {
  const theme = useTheme()
  const technique = useTechnique(techniqueId)

  return (
    <Drawer open={!!techniqueId} onClose={onClose} title={techniqueId ?? ""} size="large">
      {coverage && (
        <Box sx={{ display: "grid", gap: 1.5, gridTemplateColumns: "repeat(3, 1fr)" }}>
          {MATRIX_MODES.map((m) => {
            const color = modeColor(theme, m)
            return (
              <Stack
                key={m}
                sx={{ p: 1.5, borderRadius: 1, border: `1px solid ${alpha(color, 0.35)}`, backgroundColor: alpha(color, 0.08) }}
              >
                <Typography variant="body2" color="text.secondary">
                  {MODE_META[m].label}
                </Typography>
                <Typography sx={{ fontSize: 22, fontWeight: 600, color, fontVariantNumeric: "tabular-nums" }}>{valueOf(coverage, m)}</Typography>
              </Stack>
            )
          })}
        </Box>
      )}
      {techniqueId && (
        <>
          <TechniqueDetailCard technique={technique.data} loading={technique.isLoading} onSelect={onSelect} />
          <AtomicTestsTable techniqueId={techniqueId} />
        </>
      )}
    </Drawer>
  )
}
