import type { ReactNode } from "react"
import { alpha, IconButton, Stack, Tooltip, Typography } from "@mui/material"
import KeyOutlined from "@mui/icons-material/KeyOutlined"
import DeleteOutlined from "@mui/icons-material/DeleteOutlined"

// Current secret summary (masked key preview) with a remove action.
export function KeyPreview({ preview, extra, onDelete }: { preview: string | null; extra?: ReactNode; onDelete: () => void }) {
  return (
    <Stack
      direction="row"
      sx={(theme) => ({
        alignItems: "center",
        gap: 1.5,
        px: 2,
        py: 1.25,
        borderRadius: 1,
        border: `1px solid ${theme.palette.divider}`,
        backgroundColor: alpha(theme.palette.background.accent, 0.4),
      })}
    >
      <KeyOutlined sx={{ fontSize: 18, color: "text.secondary" }} />
      <Stack sx={{ flex: 1, minWidth: 0 }}>
        {extra}
        <Typography noWrap sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 12.5, color: "text.secondary" })}>
          {preview ?? "••••••••"}
        </Typography>
      </Stack>
      <Tooltip title="Remove key">
        <IconButton size="small" aria-label="Remove key" onClick={onDelete} sx={{ "&:hover": { color: "error.main" } }}>
          <DeleteOutlined fontSize="small" />
        </IconButton>
      </Tooltip>
    </Stack>
  )
}
