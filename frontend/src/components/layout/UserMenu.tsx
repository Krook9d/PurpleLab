import { useState } from "react"
import {
  Avatar,
  Box,
  Chip,
  Divider,
  IconButton,
  ListItemIcon,
  Menu,
  MenuItem,
  Tooltip,
  Typography,
} from "@mui/material"
import LogoutOutlined from "@mui/icons-material/LogoutOutlined"
import { useCurrentUser, useLogout } from "@/hooks/useAuth"

export function UserMenu() {
  const { data: user } = useCurrentUser()
  const logout = useLogout()
  const [anchor, setAnchor] = useState<HTMLElement | null>(null)

  const initials = user ? `${user.first_name[0] ?? ""}${user.last_name[0] ?? ""}`.toUpperCase() : "?"

  return (
    <>
      <Tooltip title="Account">
        <IconButton onClick={(e) => setAnchor(e.currentTarget)} size="small" aria-label="Account menu">
          <Avatar
            sx={(theme) => ({
              width: 30,
              height: 30,
              fontSize: 12,
              fontWeight: 600,
              color: "#ffffff",
              background: `linear-gradient(135deg, ${theme.palette.brand.from}, ${theme.palette.brand.to})`,
            })}
          >
            {initials}
          </Avatar>
        </IconButton>
      </Tooltip>
      <Menu
        anchorEl={anchor}
        open={Boolean(anchor)}
        onClose={() => setAnchor(null)}
        anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
        transformOrigin={{ vertical: "top", horizontal: "right" }}
        slotProps={{ paper: { sx: { minWidth: 220, mt: 1 } } }}
      >
        <Box sx={{ px: 2, py: 1 }}>
          <Typography sx={{ fontWeight: 600 }} noWrap>
            {user ? `${user.first_name} ${user.last_name}` : "…"}
          </Typography>
          <Typography variant="body2" color="text.secondary" noWrap>
            {user?.email}
          </Typography>
          {user && (
            <Chip
              label={user.role}
              size="small"
              color={user.role === "admin" ? "primary" : "default"}
              variant="outlined"
              sx={{ mt: 1, height: 20, fontSize: 11 }}
            />
          )}
        </Box>
        <Divider />
        <MenuItem
          onClick={() => {
            setAnchor(null)
            logout()
          }}
        >
          <ListItemIcon>
            <LogoutOutlined fontSize="small" />
          </ListItemIcon>
          Log out
        </MenuItem>
      </Menu>
    </>
  )
}
