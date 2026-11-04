import { useState, type FormEvent } from "react"
import { Button, Stack, TextField } from "@mui/material"
import { Dialog } from "@/components/common/Dialog"
import { useCreatePayload } from "@/hooks/useCustomPayloads"

export function PayloadCreateDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const createPayload = useCreatePayload()
  const [name, setName] = useState("")
  const [content, setContent] = useState("")

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    createPayload.mutate(
      { name, content },
      {
        onSuccess: () => {
          setName("")
          setContent("")
          onClose()
        },
      },
    )
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Create a payload"
      actions={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="submit" form="payload-create-form" variant="contained" loading={createPayload.isPending}>
            Create
          </Button>
        </>
      }
    >
      <Stack component="form" id="payload-create-form" onSubmit={handleSubmit} spacing={2.5} sx={{ pt: 1 }}>
        <TextField label="Name" value={name} onChange={(e) => setName(e.target.value)} required fullWidth autoFocus />
        <TextField
          label="PowerShell content"
          helperText="Executed as-is on the sandbox VM."
          value={content}
          onChange={(e) => setContent(e.target.value)}
          required
          fullWidth
          multiline
          minRows={8}
          slotProps={{ htmlInput: { spellCheck: false }, input: { sx: (theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 13 }) } }}
        />
      </Stack>
    </Dialog>
  )
}
