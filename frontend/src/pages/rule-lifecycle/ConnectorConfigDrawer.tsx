import { useState, type FormEvent } from "react"
import { Button, FormControlLabel, Stack, Switch, TextField } from "@mui/material"
import NetworkCheckOutlined from "@mui/icons-material/NetworkCheckOutlined"
import SaveOutlined from "@mui/icons-material/SaveOutlined"
import { Drawer } from "@/components/common/Drawer"
import { useSaveConnector, useTestConnector } from "@/hooks/useConnectors"
import type { ConnectorConfig, ConnectorStatus } from "@/lib/types"
import { CONNECTOR_META } from "./connectorMeta"
import { ConnectorLabel } from "./ConnectorLabel"

const FORM_ID = "connector-config-form"

// Mounted with key={type} each time the drawer opens, so the fields are always
// initialized from the currently saved config.
function ConnectorForm({ status, onSubmit }: { status: ConnectorStatus; onSubmit: (config: ConnectorConfig) => void }) {
  const meta = CONNECTOR_META[status.type]
  const [host, setHost] = useState(status.config?.host ?? "")
  const [port, setPort] = useState(status.config?.port ? String(status.config.port) : "")
  const [username, setUsername] = useState(status.config?.username ?? "")
  const [password, setPassword] = useState("")
  const [sslEnabled, setSslEnabled] = useState(status.config?.ssl_enabled ?? true)
  const testConnector = useTestConnector()

  const config = (): ConnectorConfig => ({
    host,
    port: port ? parseInt(port, 10) : null,
    username,
    password,
    ssl_enabled: sslEnabled,
  })

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    onSubmit(config())
  }

  return (
    <Stack component="form" id={FORM_ID} onSubmit={handleSubmit} spacing={2.5}>
      <TextField label="Host" value={host} onChange={(e) => setHost(e.target.value)} required fullWidth helperText={meta.hint} />
      <Stack direction="row" sx={{ gap: 2, alignItems: "flex-end" }}>
        <TextField
          label="Port"
          value={port}
          onChange={(e) => setPort(e.target.value.replace(/\D/g, ""))}
          placeholder={String(meta.defaultPort)}
          slotProps={{ inputLabel: { shrink: true }, htmlInput: { inputMode: "numeric" } }}
          sx={{ width: 160 }}
        />
        <FormControlLabel control={<Switch checked={sslEnabled} onChange={(e) => setSslEnabled(e.target.checked)} />} label="TLS" />
      </Stack>
      <TextField label="Username" value={username} onChange={(e) => setUsername(e.target.value)} required fullWidth autoComplete="off" />
      <TextField
        label="Password"
        type="password"
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        fullWidth
        autoComplete="new-password"
        helperText={status.configured ? "Leave empty to keep the current password." : undefined}
      />
      <Button
        variant="outlined"
        startIcon={<NetworkCheckOutlined />}
        loading={testConnector.isPending}
        loadingPosition="start"
        onClick={() => testConnector.mutate({ type: status.type, config: config() })}
        sx={{ alignSelf: "flex-start" }}
      >
        Test connection
      </Button>
    </Stack>
  )
}

export function ConnectorConfigDrawer({ status, onClose }: { status: ConnectorStatus | null; onClose: () => void }) {
  const saveConnector = useSaveConnector()
  return (
    <Drawer
      open={!!status}
      onClose={onClose}
      size="small"
      title={status ? <ConnectorLabel type={status.type} size={20} /> : ""}
      footer={
        <Button
          type="submit"
          form={FORM_ID}
          variant="contained"
          startIcon={<SaveOutlined />}
          loading={saveConnector.isPending}
          loadingPosition="start"
        >
          Save
        </Button>
      }
    >
      {status && (
        <ConnectorForm
          key={status.type}
          status={status}
          onSubmit={(config) => saveConnector.mutate({ type: status.type, config }, { onSuccess: onClose })}
        />
      )}
    </Drawer>
  )
}
