import { useState } from "react"
import { alpha, Box, Button, IconButton, Skeleton, Stack, Tooltip, Typography } from "@mui/material"
import SettingsOutlined from "@mui/icons-material/SettingsOutlined"
import DeleteOutlined from "@mui/icons-material/DeleteOutlined"
import { Card } from "@/components/common/Card"
import { ConfirmDialog } from "@/components/common/ConfirmDialog"
import { StatusChip } from "@/components/common/StatusChip"
import { useConnectors, useDeleteConnector } from "@/hooks/useConnectors"
import type { ConnectorStatus, ConnectorType } from "@/lib/types"
import { CONNECTOR_META } from "./connectorMeta"
import { ConnectorConfigDrawer } from "./ConnectorConfigDrawer"

function ConnectorCard({
  status,
  onConfigure,
  onDelete,
}: {
  status: ConnectorStatus
  onConfigure: () => void
  onDelete: () => void
}) {
  const meta = CONNECTOR_META[status.type]
  const Logo = meta.logo
  const endpoint = status.config?.host ? `${status.config.host}:${status.config.port ?? meta.defaultPort}` : null

  return (
    <Card padding="medium" hoverable>
      <Stack spacing={2} sx={{ height: "100%" }}>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
          <Box
            sx={{
              width: 64,
              height: 40,
              borderRadius: 1,
              flexShrink: 0,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              backgroundColor: alpha(meta.color, 0.14),
              border: `1px solid ${alpha(meta.color, 0.3)}`,
            }}
          >
            <Logo height={meta.tileHeight} />
          </Box>
          <Box sx={{ flex: 1, minWidth: 0 }}>
            <Typography sx={{ fontWeight: 600 }}>{meta.label}</Typography>
            <Typography noWrap variant="body2" sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 12, color: "text.tertiary" })}>
              {endpoint ?? "—"}
            </Typography>
          </Box>
        </Stack>
        <Box>
          <StatusChip tone={status.configured ? "success" : "neutral"} label={status.configured ? "Configured" : "Not configured"} />
        </Box>
        <Stack direction="row" sx={{ gap: 1, mt: "auto" }}>
          <Button size="small" variant={status.configured ? "outlined" : "contained"} startIcon={<SettingsOutlined />} onClick={onConfigure}>
            {status.configured ? "Configure" : "Set up"}
          </Button>
          {status.configured && (
            <Tooltip title="Remove configuration">
              <IconButton size="small" aria-label={`Remove ${meta.label} configuration`} onClick={onDelete} sx={{ "&:hover": { color: "error.main" } }}>
                <DeleteOutlined fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
        </Stack>
      </Stack>
    </Card>
  )
}

export function ConnectorsTab() {
  const connectors = useConnectors()
  const deleteConnector = useDeleteConnector()
  const [editingType, setEditingType] = useState<ConnectorType | null>(null)
  const [deletingType, setDeletingType] = useState<ConnectorType | null>(null)
  // Always read the live status so the drawer reflects freshly saved data.
  const editing = connectors.data?.find((c) => c.type === editingType) ?? null

  return (
    <>
      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: "repeat(auto-fill, minmax(250px, 1fr))" }}>
        {connectors.isLoading
          ? Array.from({ length: 5 }, (_, i) => <Skeleton key={i} variant="rounded" height={170} />)
          : connectors.data?.map((c) => (
              <ConnectorCard key={c.type} status={c} onConfigure={() => setEditingType(c.type)} onDelete={() => setDeletingType(c.type)} />
            ))}
      </Box>
      <ConnectorConfigDrawer status={editing} onClose={() => setEditingType(null)} />
      <ConfirmDialog
        open={!!deletingType}
        title="Remove this connector?"
        message={`The ${deletingType ? CONNECTOR_META[deletingType].label : ""} credentials will be deleted.`}
        confirmLabel="Remove"
        destructive
        loading={deleteConnector.isPending}
        onClose={() => setDeletingType(null)}
        onConfirm={() => deletingType && deleteConnector.mutate(deletingType, { onSuccess: () => setDeletingType(null) })}
      />
    </>
  )
}
