import { useMemo, useState } from "react"
import { Button, LinearProgress, MenuItem, Stack, TextField, Typography } from "@mui/material"
import SyncOutlined from "@mui/icons-material/SyncOutlined"
import PlayArrowOutlined from "@mui/icons-material/PlayArrowOutlined"
import { Card } from "@/components/common/Card"
import { DataTable, type DataTableColumn } from "@/components/common/DataTable"
import { useRulePayloadMap, useSetRulePayloadMap, useSyncedRules, useSyncRules } from "@/hooks/useConnectors"
import { useExecuteRuleLifecyclePayload, useRuleLifecyclePayloads } from "@/hooks/useRuleLifecyclePayloads"
import { formatDateTime } from "@/lib/format"
import type { ConnectorType, SyncedRule } from "@/lib/types"
import { ConnectorSelect } from "./ConnectorSelect"
import { PayloadsPanel } from "./PayloadsPanel"
import { SeverityChip } from "./SeverityChip"

const ruleDisplayName = (rule: SyncedRule) => String(rule.rule_data.name ?? rule.rule_data.trigger_name ?? rule.rule_id)

export function RulesTab() {
  const [connectorType, setConnectorType] = useState<ConnectorType>("opensearch")
  const syncedRules = useSyncedRules(connectorType)
  const ruleMap = useRulePayloadMap()
  const payloads = useRuleLifecyclePayloads()
  const syncRules = useSyncRules()
  const setMapping = useSetRulePayloadMap()
  const executePayload = useExecuteRuleLifecyclePayload()

  const mapByRuleId = useMemo(() => new Map(ruleMap.data?.map((m) => [m.rule_id, m.payload_id]) ?? []), [ruleMap.data])

  const columns: DataTableColumn<SyncedRule>[] = [
    { id: "name", label: "Rule", render: (r) => <Typography sx={{ fontSize: 13, fontWeight: 500 }}>{ruleDisplayName(r)}</Typography>, sortValue: ruleDisplayName },
    { id: "severity", label: "Severity", width: 120, render: (r) => <SeverityChip value={r.rule_data.severity} />, sortValue: (r) => String(r.rule_data.severity ?? "") },
    {
      id: "payload",
      label: "Assigned payload",
      width: 240,
      render: (r) => {
        const assigned = mapByRuleId.get(r.rule_id) ?? null
        return (
          <TextField
            select
            size="small"
            value={assigned ? String(assigned) : "none"}
            onChange={(e) =>
              setMapping.mutate({ ruleId: r.rule_id, payloadId: e.target.value !== "none" ? parseInt(e.target.value, 10) : null })
            }
            sx={{ minWidth: 200 }}
            slotProps={{ htmlInput: { "aria-label": `Payload for ${ruleDisplayName(r)}` } }}
          >
            <MenuItem value="none">
              <Typography component="span" color="text.secondary" sx={{ fontSize: 13 }}>
                None
              </Typography>
            </MenuItem>
            {payloads.data?.map((p) => (
              <MenuItem key={p.id} value={String(p.id)}>
                {p.name}
              </MenuItem>
            ))}
          </TextField>
        )
      },
    },
    { id: "synced", label: "Synced", width: 170, render: (r) => formatDateTime(r.synced_at), sortValue: (r) => r.synced_at },
    {
      id: "action",
      label: "",
      width: 130,
      align: "right",
      render: (r) => {
        const assigned = mapByRuleId.get(r.rule_id) ?? null
        const running = executePayload.isPending && executePayload.variables?.ruleId === r.rule_id
        return (
          <Button
            size="small"
            variant="outlined"
            startIcon={<PlayArrowOutlined />}
            loading={running}
            loadingPosition="start"
            disabled={!assigned || executePayload.isPending}
            onClick={() => assigned && executePayload.mutate({ payloadId: assigned, ruleId: r.rule_id, connector: connectorType })}
          >
            Execute
          </Button>
        )
      },
    },
  ]

  return (
    <Stack spacing={3}>
      <Stack direction="row" useFlexGap sx={{ alignItems: "flex-end", gap: 2, flexWrap: "wrap" }}>
        <ConnectorSelect value={connectorType} onChange={setConnectorType} />
        <Button
          variant="outlined"
          startIcon={<SyncOutlined />}
          loading={syncRules.isPending}
          loadingPosition="start"
          onClick={() => syncRules.mutate(connectorType)}
        >
          Sync rules
        </Button>
        <Typography variant="body2" color="text.secondary" sx={{ flex: 1, minWidth: 240 }}>
          Map each detection rule to a payload, then execute it to check the rule fires.
        </Typography>
      </Stack>
      {syncRules.isPending && <LinearProgress sx={{ borderRadius: 1 }} />}

      <Card title={`Synced rules${syncedRules.data ? ` (${syncedRules.data.length})` : ""}`} padding="small" fullHeight={false}>
        <DataTable
          columns={columns}
          rows={syncedRules.data}
          loading={syncedRules.isLoading}
          getRowId={(r) => r.id}
          emptyMessage="No rule synced for this connector. Configure it, then click Sync rules."
          maxHeight={560}
        />
      </Card>

      <PayloadsPanel />
    </Stack>
  )
}
