import type { ReactNode } from "react"
import { alpha, Box, Skeleton, Stack, Typography } from "@mui/material"
import { StatusChip } from "@/components/common/StatusChip"

interface IntegrationHeaderProps {
  icon: ReactNode
  title: string
  description: string
  configured: boolean | undefined
}

export function IntegrationHeader({ icon, title, description, configured }: IntegrationHeaderProps) {
  return (
    <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
      <Box
        sx={(theme) => ({
          width: 40,
          height: 40,
          flexShrink: 0,
          borderRadius: 1,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          color: theme.palette.primary.main,
          backgroundColor: alpha(theme.palette.primary.main, 0.12),
          border: `1px solid ${alpha(theme.palette.primary.main, 0.25)}`,
          "& svg": { fontSize: 20 },
        })}
      >
        {icon}
      </Box>
      <Box sx={{ flex: 1, minWidth: 0 }}>
        <Typography sx={{ fontWeight: 600 }}>{title}</Typography>
        <Typography variant="body2" color="text.secondary">
          {description}
        </Typography>
      </Box>
      {configured === undefined ? (
        <Skeleton variant="rounded" width={100} height={24} />
      ) : (
        <StatusChip tone={configured ? "success" : "neutral"} label={configured ? "Configured" : "Not configured"} />
      )}
    </Stack>
  )
}
