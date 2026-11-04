import { useState, type FormEvent } from "react"
import { Button, Stack, TextField } from "@mui/material"
import SaveOutlined from "@mui/icons-material/SaveOutlined"
import { Drawer } from "@/components/common/Drawer"
import { useCreateRuleLifecyclePayload, useUpdateRuleLifecyclePayload } from "@/hooks/useRuleLifecyclePayloads"
import type { RuleLifecyclePayloadItem } from "@/lib/types"

const FORM_ID = "rlc-payload-form"

function PayloadForm({ payload, onSubmit }: { payload: RuleLifecyclePayloadItem | null; onSubmit: (v: { name: string; description: string; code: string }) => void }) {
  const [name, setName] = useState(payload?.name ?? "")
  const [description, setDescription] = useState(payload?.description ?? "")
  const [code, setCode] = useState(payload?.code ?? "")

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    onSubmit({ name, description, code })
  }

  return (
    <Stack component="form" id={FORM_ID} onSubmit={handleSubmit} spacing={2.5}>
      <TextField label="Name" value={name} onChange={(e) => setName(e.target.value)} required fullWidth autoFocus />
      <TextField label="Description" value={description} onChange={(e) => setDescription(e.target.value)} fullWidth />
      <TextField
        label="PowerShell code"
        helperText="Executed on the sandbox VM to trigger the mapped detection rules."
        value={code}
        onChange={(e) => setCode(e.target.value)}
        required
        fullWidth
        multiline
        minRows={12}
        slotProps={{ htmlInput: { spellCheck: false }, input: { sx: (theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 13 }) } }}
      />
    </Stack>
  )
}

interface PayloadEditorDrawerProps {
  // "new" to create, an item to edit, null when closed.
  editing: RuleLifecyclePayloadItem | "new" | null
  onClose: () => void
}

export function PayloadEditorDrawer({ editing, onClose }: PayloadEditorDrawerProps) {
  const createPayload = useCreateRuleLifecyclePayload()
  const updatePayload = useUpdateRuleLifecyclePayload()
  const item = editing === "new" ? null : editing

  return (
    <Drawer
      open={!!editing}
      onClose={onClose}
      size="medium"
      title={item ? `Edit ${item.name}` : "Create a payload"}
      footer={
        <Button
          type="submit"
          form={FORM_ID}
          variant="contained"
          startIcon={<SaveOutlined />}
          loading={createPayload.isPending || updatePayload.isPending}
          loadingPosition="start"
        >
          Save
        </Button>
      }
    >
      {editing && (
        <PayloadForm
          key={item?.id ?? "new"}
          payload={item}
          onSubmit={(values) =>
            item
              ? updatePayload.mutate({ id: item.id, ...values }, { onSuccess: onClose })
              : createPayload.mutate(values, { onSuccess: onClose })
          }
        />
      )}
    </Drawer>
  )
}
