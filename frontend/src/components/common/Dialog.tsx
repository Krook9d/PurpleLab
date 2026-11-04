/*
 * Adapted from OpenCTI (opencti-front/src/components/common/dialog/Dialog.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: design-system layer classes removed; slide-up transition by
 * default; optional actions slot rendered in DialogActions.
 */
import type { ReactNode } from "react"
import {
  Box,
  Dialog as MuiDialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  type DialogProps as MuiDialogProps,
} from "@mui/material"
import CloseOutlined from "@mui/icons-material/CloseOutlined"
import { Transition } from "./Transition"

export type DialogSize = "small" | "medium" | "large"

const DIALOG_SIZES: Record<DialogSize, string> = {
  small: "420px",
  medium: "640px",
  large: "960px",
}

type DialogProps = {
  title?: ReactNode
  actions?: ReactNode
  size?: DialogSize
  showCloseButton?: boolean
  onClose: () => void
} & Omit<MuiDialogProps, "title" | "onClose">

export function Dialog({
  title,
  actions,
  children,
  size = "medium",
  showCloseButton = false,
  onClose,
  fullScreen = false,
  sx,
  ...dialogProps
}: DialogProps) {
  return (
    <MuiDialog
      slots={{ transition: Transition }}
      {...dialogProps}
      fullScreen={fullScreen}
      onClose={onClose}
      sx={[
        !fullScreen && { "& .MuiDialog-paper": { maxWidth: DIALOG_SIZES[size], width: "100%", pt: 3, pb: actions ? 0 : 3 } },
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
    >
      {(title || showCloseButton) && (
        <DialogTitle
          sx={{
            py: 0,
            px: 3,
            mb: 2,
            display: "flex",
            alignItems: "center",
            justifyContent: showCloseButton && !title ? "flex-end" : "space-between",
          }}
        >
          {title && (
            <Box component="span" sx={{ width: "100%" }}>
              {title}
            </Box>
          )}
          {showCloseButton && (
            <IconButton aria-label="close" onClick={onClose}>
              <CloseOutlined />
            </IconButton>
          )}
        </DialogTitle>
      )}
      <DialogContent sx={{ px: 3, position: "relative", "&&": { py: "4px", my: "-4px" } }}>{children}</DialogContent>
      {actions && <DialogActions>{actions}</DialogActions>}
    </MuiDialog>
  )
}
