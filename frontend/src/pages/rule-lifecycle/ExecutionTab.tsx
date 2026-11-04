import { useMemo, useState } from "react"
import { Box, Stack, ToggleButton, ToggleButtonGroup, Typography, useTheme } from "@mui/material"
import PlayCircleOutlined from "@mui/icons-material/PlayCircleOutlined"
import CheckCircleOutlined from "@mui/icons-material/CheckCircleOutlined"
import ErrorOutlineOutlined from "@mui/icons-material/ErrorOutlineOutlined"
import { Card } from "@/components/common/Card"
import { CodeBlock } from "@/components/common/CodeBlock"
import { DataTable, type DataTableColumn } from "@/components/common/DataTable"
import { Drawer } from "@/components/common/Drawer"
import { StatusChip } from "@/components/common/StatusChip"
import { WidgetNumber } from "@/components/dashboard/WidgetNumber"
import { useExecutionHistory } from "@/hooks/useExecutionHistory"
import { formatDateTime } from "@/lib/format"
import type { ConnectorType, ExecutionHistoryEntry } from "@/lib/types"
import { ConnectorLabel } from "./ConnectorLabel"
import { ConnectorSelect } from "./ConnectorSelect"

type StatusFilter = "all" | "success" | "error"

export function ExecutionTab() {
  const theme = useTheme()
  const [connector, setConnector] = useState<ConnectorType | "all">("all")
  const [status, setStatus] = useState<StatusFilter>("all")
  const [selected, setSelected] = useState<ExecutionHistoryEntry | null>(null)
  // Rule payload runs only (Atomic runs from the MITRE page share the table).
  const history = useExecutionHistory({
    source: "rule_payload",
    connector: connector !== "all" ? connector : undefined,
    status: status !== "all" ? status : undefined,
  })

  const counts = useMemo(() => {
    const rows = history.data ?? []
    return { total: rows.length, success: rows.filter((r) => r.status === "success").length, error: rows.filter((r) => r.status !== "success").length }
  }, [history.data])

  const columns: DataTableColumn<ExecutionHistoryEntry>[] = [
    { id: "rule", label: "Rule", render: (e) => e.rule_id ?? "—", sortValue: (e) => e.rule_id, mono: true },
    { id: "connector", label: "Connector", width: 160, render: (e) => <ConnectorLabel type={e.connector} />, sortValue: (e) => e.connector },
    {
      id: "status",
      label: "Status",
      width: 120,
      render: (e) => <StatusChip tone={e.status === "success" ? "success" : "error"} label={e.status} />,
      sortValue: (e) => e.status,
    },
    {
      id: "output",
      label: "Output",
      render: (e) => (
        <Typography noWrap sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 12, color: "text.secondary", maxWidth: 420 })}>
          {e.output ?? e.error ?? "—"}
        </Typography>
      ),
    },
    { id: "executed", label: "Executed", width: 180, render: (e) => formatDateTime(e.executed_at), sortValue: (e) => e.executed_at },
  ]

  return (
    <Stack spacing={3}>
      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", md: "repeat(3, 1fr)" } }}>
        <WidgetNumber label="Executions" value={counts.total} loading={history.isLoading} icon={<PlayCircleOutlined />} />
        <WidgetNumber label="Succeeded" value={counts.success} loading={history.isLoading} icon={<CheckCircleOutlined />} color={theme.palette.success.main} />
        <WidgetNumber label="Failed" value={counts.error} loading={history.isLoading} icon={<ErrorOutlineOutlined />} color={theme.palette.error.main} />
      </Box>

      <Stack direction="row" useFlexGap sx={{ alignItems: "flex-end", gap: 2, flexWrap: "wrap" }}>
        <ConnectorSelect value={connector} onChange={setConnector} allOption="All connectors" />
        <ToggleButtonGroup value={status} exclusive onChange={(_, v: StatusFilter | null) => v && setStatus(v)} aria-label="Status filter">
          <ToggleButton value="all" sx={{ px: 1.5 }}>All</ToggleButton>
          <ToggleButton value="success" sx={{ px: 1.5 }}>Success</ToggleButton>
          <ToggleButton value="error" sx={{ px: 1.5 }}>Error</ToggleButton>
        </ToggleButtonGroup>
      </Stack>

      <Card title="Execution history" padding="small" fullHeight={false}>
        <DataTable
          columns={columns}
          rows={history.data}
          loading={history.isLoading}
          getRowId={(e) => e.id}
          onRowClick={setSelected}
          defaultSort={{ columnId: "executed", direction: "desc" }}
          emptyMessage="No execution recorded."
          maxHeight={600}
        />
      </Card>

      <Drawer open={!!selected} onClose={() => setSelected(null)} title={selected?.rule_id ?? "Execution"} size="medium">
        {selected && (
          <>
            <Stack direction="row" useFlexGap sx={{ gap: 2, alignItems: "center", flexWrap: "wrap" }}>
              <StatusChip tone={selected.status === "success" ? "success" : "error"} label={selected.status} />
              <ConnectorLabel type={selected.connector} />
              <Typography variant="body2" color="text.secondary">
                {formatDateTime(selected.executed_at)} · source {selected.source}
              </Typography>
            </Stack>
            {selected.output && (
              <Box>
                <Typography variant="h4" sx={{ color: "text.light" }}>
                  Output
                </Typography>
                <CodeBlock code={selected.output} language="text" maxHeight="50vh" />
              </Box>
            )}
            {selected.error && (
              <Box>
                <Typography variant="h4" sx={{ color: "error.main" }}>
                  Error
                </Typography>
                <CodeBlock code={selected.error} language="text" maxHeight="30vh" />
              </Box>
            )}
          </>
        )}
      </Drawer>
    </Stack>
  )
}
