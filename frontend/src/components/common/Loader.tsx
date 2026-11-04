/*
 * Adapted from OpenCTI (opencti-front/src/components/Loader.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: branded animated loader, user settings context and rxjs
 * rotating texts removed; plain CircularProgress only; optional label.
 */
import { Box, CircularProgress, Typography } from "@mui/material"

export type LoaderVariant = "container" | "inElement" | "inline"

interface LoaderProps {
  variant?: LoaderVariant
  label?: string
}

export function Loader({ variant = "container", label }: LoaderProps) {
  if (variant === "inline") {
    return (
      <Box
        role="progressbar"
        aria-label={label ?? "Loading..."}
        sx={{ display: "inline-flex", gap: 1, height: 35, alignItems: "center", justifyContent: "center" }}
      >
        <CircularProgress aria-hidden size={24} thickness={1} />
        {label && <Typography variant="body2">{label}</Typography>}
      </Box>
    )
  }
  const inElement = variant === "inElement"
  return (
    <Box
      sx={{
        width: "100%",
        height: "100%",
        minHeight: inElement ? 120 : "calc(100vh - 180px)",
        display: "flex",
        flexDirection: "column",
        gap: 2,
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      <CircularProgress role="progressbar" aria-label={label ?? "Loading..."} size={inElement ? 40 : 80} thickness={1} />
      {label && (
        <Typography variant="body2" color="text.secondary">
          {label}
        </Typography>
      )}
    </Box>
  )
}
