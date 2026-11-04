/*
 * Adapted from OpenCTI (opencti-front/src/components/Message.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: rxjs bus replaced by the local notify module, queued display
 * instead of latest-wins, optional description (e.g. execution output),
 * i18n, enterprise-edition access request and AI variants removed.
 */
import { useEffect, useState } from "react"
import { Alert, AlertTitle, Box, Slide, Snackbar, type SnackbarCloseReason } from "@mui/material"
import { notify, type Notification } from "@/lib/notify"

export function Notifier() {
  const [queue, setQueue] = useState<Notification[]>([])
  const [open, setOpen] = useState(false)
  const current = queue[0]

  useEffect(
    () =>
      notify.subscribe((notification) => {
        setQueue((q) => [...q, notification])
        setOpen(true)
      }),
    [],
  )

  const handleClose = (_: unknown, reason?: SnackbarCloseReason) => {
    if (reason === "clickaway") return
    setOpen(false)
  }

  // Once the exit transition is done, show the next queued notification.
  const handleExited = () => {
    setQueue((q) => {
      const rest = q.slice(1)
      if (rest.length > 0) setOpen(true)
      return rest
    })
  }

  return (
    <Snackbar
      key={current?.id}
      anchorOrigin={{ vertical: "top", horizontal: "center" }}
      open={open && Boolean(current)}
      onClose={handleClose}
      autoHideDuration={current?.severity === "error" ? 8000 : 4000}
      slots={{ transition: Slide }}
      slotProps={{ transition: { onExited: handleExited } }}
    >
      {current ? (
        <Alert severity={current.severity} variant="filled" onClose={() => handleClose(null)} sx={{ minWidth: 320, maxWidth: 560 }}>
          {current.description ? <AlertTitle sx={{ mb: 0.25 }}>{current.title}</AlertTitle> : current.title}
          {current.description && (
            <Box
              sx={{
                maxHeight: 160,
                overflowY: "auto",
                whiteSpace: "pre-wrap",
                wordBreak: "break-word",
                fontSize: 12,
                opacity: 0.9,
              }}
            >
              {current.description}
            </Box>
          )}
        </Alert>
      ) : undefined}
    </Snackbar>
  )
}
