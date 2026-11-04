import { Avatar } from "@mui/material"

// Initials avatar on the brand gradient.
export function UserAvatar({ firstName, lastName, size = 24 }: { firstName?: string | null; lastName?: string | null; size?: number }) {
  const initials = `${firstName?.[0] ?? ""}${lastName?.[0] ?? ""}`.toUpperCase() || "?"
  return (
    <Avatar
      sx={(theme) => ({
        width: size,
        height: size,
        fontSize: size * 0.42,
        fontWeight: 600,
        color: "#ffffff",
        background: `linear-gradient(135deg, ${theme.palette.brand.from}, ${theme.palette.brand.to})`,
      })}
    >
      {initials}
    </Avatar>
  )
}
