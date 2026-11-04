import { useState, type FormEvent } from "react"
import { Box, Button, Chip, MenuItem, Stack, TextField, Typography } from "@mui/material"
import SmartToyOutlined from "@mui/icons-material/SmartToyOutlined"
import VerifiedOutlined from "@mui/icons-material/VerifiedOutlined"
import { Card } from "@/components/common/Card"
import { ConfirmDialog } from "@/components/common/ConfirmDialog"
import { useDeleteMitreAiConfig, useMitreAiConfig, useSaveMitreAiConfig } from "@/hooks/useMitreAi"
import { IntegrationHeader } from "./IntegrationHeader"
import { KeyPreview } from "./KeyPreview"

type Provider = "openai" | "gemini"

export function MitreAiSection() {
  const config = useMitreAiConfig()
  const saveConfig = useSaveMitreAiConfig()
  const deleteConfig = useDeleteMitreAiConfig()
  const [provider, setProvider] = useState<Provider>("openai")
  const [model, setModel] = useState("")
  const [apiBase, setApiBase] = useState("")
  const [apiKey, setApiKey] = useState("")
  const [confirmOpen, setConfirmOpen] = useState(false)

  function handleSave(e: FormEvent) {
    e.preventDefault()
    saveConfig.mutate({ provider, model, api_base: apiBase || null, api_key: apiKey }, { onSuccess: () => setApiKey("") })
  }

  return (
    <Card hoverable>
      <Stack spacing={2.5}>
        <IntegrationHeader
          icon={<SmartToyOutlined />}
          title="MITRE AI assistant"
          description="ATT&CK chatbot: OpenAI-compatible or Gemini."
          configured={config.isLoading ? undefined : !!config.data?.configured}
        />

        {config.data?.configured && (
          <KeyPreview
            preview={config.data.key_preview}
            onDelete={() => setConfirmOpen(true)}
            extra={
              <Stack direction="row" sx={{ alignItems: "center", gap: 1, mb: 0.25 }}>
                <Chip size="small" color="primary" variant="outlined" label={config.data.provider} sx={{ height: 20, fontSize: 11 }} />
                <Typography noWrap sx={{ fontSize: 13 }}>
                  {config.data.model}
                </Typography>
              </Stack>
            }
          />
        )}

        <Stack component="form" onSubmit={handleSave} spacing={2}>
          <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: "1fr 1fr" }}>
            <TextField select label="Provider" value={provider} onChange={(e) => setProvider(e.target.value as Provider)}>
              <MenuItem value="openai">OpenAI-compatible</MenuItem>
              <MenuItem value="gemini">Gemini</MenuItem>
            </TextField>
            <TextField label="Model" placeholder="gpt-4o-mini" value={model} onChange={(e) => setModel(e.target.value)} required />
          </Box>
          {provider === "openai" && (
            <TextField
              label="API base URL"
              placeholder="https://api.openai.com/v1"
              value={apiBase}
              onChange={(e) => setApiBase(e.target.value)}
              fullWidth
              slotProps={{ inputLabel: { shrink: true } }}
            />
          )}
          <TextField
            label="API key"
            type="password"
            value={apiKey}
            onChange={(e) => setApiKey(e.target.value)}
            required
            fullWidth
            autoComplete="off"
          />
          <Button type="submit" variant="contained" startIcon={<VerifiedOutlined />} loading={saveConfig.isPending} loadingPosition="start">
            Test and save
          </Button>
        </Stack>
      </Stack>

      <ConfirmDialog
        open={confirmOpen}
        title="Remove the AI configuration?"
        message="The MITRE assistant will be unavailable until a new configuration is saved."
        confirmLabel="Remove"
        destructive
        loading={deleteConfig.isPending}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => deleteConfig.mutate(undefined, { onSuccess: () => setConfirmOpen(false) })}
      />
    </Card>
  )
}
