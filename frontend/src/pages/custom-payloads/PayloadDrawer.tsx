import { useState } from "react"
import { Button, Stack, Typography } from "@mui/material"
import PlayArrowOutlined from "@mui/icons-material/PlayArrowOutlined"
import DeleteOutlined from "@mui/icons-material/DeleteOutlined"
import { Drawer } from "@/components/common/Drawer"
import { CodeBlock } from "@/components/common/CodeBlock"
import { ConfirmDialog } from "@/components/common/ConfirmDialog"
import { UserAvatar } from "@/components/common/UserAvatar"
import { useDeletePayload, useExecutePayload } from "@/hooks/useCustomPayloads"
import { formatDateTime } from "@/lib/format"
import type { CustomPayload } from "@/lib/types"


interface PayloadDrawerProps {
  payload: CustomPayload | null
  currentUserId: number | undefined
  onClose: () => void
}

export function PayloadDrawer({ payload, currentUserId, onClose }: PayloadDrawerProps) {
  const executePayload = useExecutePayload()
  const deletePayload = useDeletePayload()
  const [confirmOpen, setConfirmOpen] = useState(false)
  const isOwner = !!payload && currentUserId === payload.author_id

  return (
    <>
      <Drawer
        open={!!payload}
        onClose={onClose}
        title={payload?.name ?? ""}
        size="medium"
        footer={
          payload && (
            <>
              {isOwner && (
                <Button color="error" startIcon={<DeleteOutlined />} onClick={() => setConfirmOpen(true)} sx={{ mr: "auto" }}>
                  Delete
                </Button>
              )}
              <Button
                variant="contained"
                startIcon={<PlayArrowOutlined />}
                loading={executePayload.isPending}
                loadingPosition="start"
                onClick={() => executePayload.mutate(payload.id)}
              >
                Execute on sandbox
              </Button>
            </>
          )
        }
      >
        {payload && (
          <>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
              <UserAvatar firstName={payload.author_first_name} lastName={payload.author_last_name} size={28} />
              <Typography variant="body2">
                {payload.author_first_name} {payload.author_last_name}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                · {formatDateTime(payload.created_at)}
              </Typography>
            </Stack>
            <CodeBlock code={payload.content} language="powershell" showLineNumbers />
          </>
        )}
      </Drawer>
      <ConfirmDialog
        open={confirmOpen}
        title="Delete this payload?"
        message={`"${payload?.name ?? ""}" will be removed from the shared catalog.`}
        confirmLabel="Delete"
        destructive
        loading={deletePayload.isPending}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() =>
          payload &&
          deletePayload.mutate(payload.id, {
            onSuccess: () => {
              setConfirmOpen(false)
              onClose()
            },
          })
        }
      />
    </>
  )
}
