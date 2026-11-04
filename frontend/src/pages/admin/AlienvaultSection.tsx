import { useState, type FormEvent } from "react"
import { Box, Button, Divider, Stack, TextField, Typography } from "@mui/material"
import SatelliteAltOutlined from "@mui/icons-material/SatelliteAltOutlined"
import SyncOutlined from "@mui/icons-material/SyncOutlined"
import { Card } from "@/components/common/Card"
import { ConfirmDialog } from "@/components/common/ConfirmDialog"
import {
  useAlienvaultConfig,
  useAlienvaultDashboard,
  useDeleteAlienvaultConfig,
  useRefreshAlienvault,
  useSaveAlienvaultConfig,
} from "@/hooks/useAlienvault"
import { formatDateTime } from "@/lib/format"
import { IntegrationHeader } from "./IntegrationHeader"
import { KeyPreview } from "./KeyPreview"

export function AlienvaultSection() {
  const config = useAlienvaultConfig()
  const dashboard = useAlienvaultDashboard()
  const saveConfig = useSaveAlienvaultConfig()
  const deleteConfig = useDeleteAlienvaultConfig()
  const refresh = useRefreshAlienvault()
  const [apiKey, setApiKey] = useState("")
  const [confirmOpen, setConfirmOpen] = useState(false)

  function handleSave(e: FormEvent) {
    e.preventDefault()
    saveConfig.mutate(apiKey, { onSuccess: () => setApiKey("") })
  }

  return (
    <Card hoverable>
      <Stack spacing={2.5}>
        <IntegrationHeader
          icon={<SatelliteAltOutlined />}
          title="AlienVault OTX"
          description="Threat intelligence: pulses, IOCs, CVEs."
          configured={config.isLoading ? undefined : !!config.data?.configured}
        />

        {config.data?.configured && <KeyPreview preview={config.data.key_preview} onDelete={() => setConfirmOpen(true)} />}

        <Stack component="form" direction="row" onSubmit={handleSave} sx={{ gap: 1.5, alignItems: "flex-end" }}>
          <TextField
            label={config.data?.configured ? "Replace OTX API key" : "OTX API key"}
            type="password"
            value={apiKey}
            onChange={(e) => setApiKey(e.target.value)}
            required
            fullWidth
            autoComplete="off"
          />
          <Button type="submit" variant="contained" loading={saveConfig.isPending} sx={{ flexShrink: 0 }}>
            Save
          </Button>
        </Stack>

        <Divider />

        <Stack direction="row" sx={{ alignItems: "center", gap: 2 }}>
          <Box sx={{ flex: 1 }}>
            <Typography sx={{ fontWeight: 500 }}>Dashboard</Typography>
            <Typography variant="body2" color="text.secondary">
              {dashboard.data
                ? `${dashboard.data.data.summary.reports} reports · generated ${formatDateTime(dashboard.data.generated_at)}`
                : "Never generated"}
            </Typography>
          </Box>
          <Button
            variant="outlined"
            startIcon={<SyncOutlined />}
            loading={refresh.isPending}
            loadingPosition="start"
            disabled={!config.data?.configured}
            onClick={() => refresh.mutate()}
          >
            Refresh
          </Button>
        </Stack>
      </Stack>

      <ConfirmDialog
        open={confirmOpen}
        title="Remove the OTX key?"
        message="The dashboard will stop refreshing until a new key is saved."
        confirmLabel="Remove"
        destructive
        loading={deleteConfig.isPending}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => deleteConfig.mutate(undefined, { onSuccess: () => setConfirmOpen(false) })}
      />
    </Card>
  )
}
