import { alpha, Box } from "@mui/material"

interface TechniqueIdChipProps {
  id: string
  onClick?: () => void
  size?: "small" | "medium"
}

// Monospace ATT&CK technique ID (T1059.001) tag, clickable when onClick is set.
export function TechniqueIdChip({ id, onClick, size = "small" }: TechniqueIdChipProps) {
  return (
    <Box
      component={onClick ? "button" : "span"}
      type={onClick ? "button" : undefined}
      onClick={onClick}
      sx={(theme) => ({
        display: "inline-flex",
        alignItems: "center",
        flexShrink: 0,
        px: size === "small" ? 0.75 : 1,
        py: size === "small" ? 0.1 : 0.25,
        borderRadius: 0.5,
        fontFamily: theme.fontFamilyMono,
        fontSize: size === "small" ? 12 : 14,
        fontWeight: 600,
        lineHeight: 1.6,
        color: theme.palette.primary.main,
        backgroundColor: alpha(theme.palette.primary.main, 0.12),
        border: `1px solid ${alpha(theme.palette.primary.main, 0.3)}`,
        cursor: onClick ? "pointer" : "default",
        transition: "background-color 150ms ease",
        "&:hover": onClick ? { backgroundColor: alpha(theme.palette.primary.main, 0.22) } : undefined,
      })}
    >
      {id}
    </Box>
  )
}
