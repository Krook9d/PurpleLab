import type { ReactNode } from "react"
import { Button, Typography } from "@mui/material"
import { Dialog } from "./Dialog"

interface ConfirmDialogProps {
  open: boolean
  title: string
  message: ReactNode
  confirmLabel?: string
  destructive?: boolean
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = "Confirm",
  destructive = false,
  loading = false,
  onConfirm,
  onClose,
}: ConfirmDialogProps) {
  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={title}
      size="small"
      actions={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="contained" color={destructive ? "error" : "primary"} loading={loading} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <Typography variant="body2" color="text.secondary">
        {message}
      </Typography>
    </Dialog>
  )
}
