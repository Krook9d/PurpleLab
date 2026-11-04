import { useMemo, useState } from "react"
import { Skeleton, Stack } from "@mui/material"
import { useSearchParams } from "react-router-dom"
import { Card } from "@/components/common/Card"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { useMitreMatrix } from "@/hooks/useMitre"
import { AttackMatrix } from "./AttackMatrix"
import { MatrixToolbar } from "./MatrixToolbar"
import { TechniqueDrawer } from "./TechniqueDrawer"
import { MATRIX_MODES, buildColumns, cellValue, type MatrixMode } from "./matrixModel"

export function MatrixView({ selectedId, onSelect, onClose }: { selectedId: string | null; onSelect: (id: string) => void; onClose: () => void }) {
  const matrix = useMitreMatrix()
  const [searchParams, setSearchParams] = useSearchParams()
  const requested = searchParams.get("coverage")
  const mode: MatrixMode = (MATRIX_MODES as readonly string[]).includes(requested ?? "") ? (requested as MatrixMode) : "atomic"
  const [filter, setFilter] = useState("")
  const [coveredOnly, setCoveredOnly] = useState(false)
  const [expandAll, setExpandAll] = useState(false)

  const columns = useMemo(() => buildColumns(matrix.data ?? []), [matrix.data])
  const byId = useMemo(() => new Map((matrix.data ?? []).map((t) => [t.id, t])), [matrix.data])

  const { max, covered, total } = useMemo(() => {
    // Unique parent techniques (a technique can sit under several tactics).
    const parents = new Map(columns.flatMap((c) => c.cells).map((cell) => [cell.technique.id, cell]))
    const values = [...parents.values()].map((cell) => cellValue(cell, mode))
    return { max: Math.max(0, ...values), covered: values.filter((v) => v > 0).length, total: parents.size }
  }, [columns, mode])

  const setMode = (m: MatrixMode) => {
    const next = new URLSearchParams(searchParams)
    next.set("coverage", m)
    setSearchParams(next, { replace: true })
  }

  return (
    <Stack spacing={2}>
      <MatrixToolbar
        mode={mode}
        onModeChange={setMode}
        covered={covered}
        total={total}
        filter={filter}
        onFilterChange={setFilter}
        coveredOnly={coveredOnly}
        onCoveredOnlyChange={setCoveredOnly}
        expandAll={expandAll}
        onExpandAllChange={setExpandAll}
      />
      {matrix.isLoading ? (
        <Stack direction="row" spacing={1.5} sx={{ overflow: "hidden" }}>
          {Array.from({ length: 8 }, (_, i) => (
            <Stack key={i} spacing={0.5} sx={{ flex: "1 0 158px" }}>
              {Array.from({ length: 12 }, (__, j) => (
                <Skeleton key={j} variant="rectangular" height={36} />
              ))}
            </Stack>
          ))}
        </Stack>
      ) : !matrix.data?.length ? (
        <Card>
          <WidgetNoData message="No ATT&CK technique in the database. Use Update database to import them." />
        </Card>
      ) : (
        <AttackMatrix
          columns={columns}
          mode={mode}
          max={max}
          filter={filter}
          coveredOnly={coveredOnly}
          expandAll={expandAll}
          onSelect={onSelect}
        />
      )}
      <TechniqueDrawer techniqueId={selectedId} coverage={selectedId ? byId.get(selectedId) : undefined} onSelect={onSelect} onClose={onClose} />
    </Stack>
  )
}
