import { useState, type FormEvent } from "react"
import { Navigate } from "react-router-dom"
import {
  alpha,
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  Stack,
  TextField,
  Typography,
  useTheme,
} from "@mui/material"
import type { SvgIconComponent } from "@mui/icons-material"
import RadarOutlined from "@mui/icons-material/RadarOutlined"
import TerminalOutlined from "@mui/icons-material/TerminalOutlined"
import ShareOutlined from "@mui/icons-material/ShareOutlined"
import { useLogin } from "@/hooks/useAuth"
import { getToken } from "@/lib/auth"
import { ApiError } from "@/lib/apiClient"
import { AppBackground } from "@/components/layout/AppBackground"
import { BrandLogo } from "@/components/common/BrandLogo"

const FEATURES: { icon: SvgIconComponent; title: string; description: string }[] = [
  {
    icon: RadarOutlined,
    title: "Isolated sandbox VM",
    description: "Control a dedicated Windows VM: snapshots, EDR, one-click execution.",
  },
  {
    icon: TerminalOutlined,
    title: "Payload library",
    description: "Create, organize, and run your payloads directly on the sandbox.",
  },
  {
    icon: ShareOutlined,
    title: "Shared threat intel",
    description: "Publish and find samples, rules, and notes with the team.",
  },
]

export function LoginPage() {
  const theme = useTheme()
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const login = useLogin()

  if (getToken()) return <Navigate to="/" replace />

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    login.mutate({ email, password })
  }

  const brandGradient = `linear-gradient(135deg, ${theme.palette.brand.from}, ${theme.palette.brand.to})`

  return (
    <Box sx={{ minHeight: "100vh", display: "grid", gridTemplateColumns: { xs: "1fr", lg: "1fr 1fr" } }}>
      <AppBackground />

      <Box
        sx={{
          position: "relative",
          zIndex: 1,
          display: { xs: "none", lg: "flex" },
          flexDirection: "column",
          justifyContent: "space-between",
          p: 6,
          borderRight: `1px solid ${theme.palette.divider}`,
          backgroundColor: alpha(theme.palette.background.nav, 0.6),
        }}
      >
        <Stack direction="row" spacing={1.5} sx={{ alignItems: "center" }}>
          <BrandLogo size={44} glow />
          <Typography sx={{ fontFamily: '"Geologica", sans-serif', fontWeight: 600, fontSize: 20 }}>PurpleLab</Typography>
        </Stack>

        <Box sx={{ maxWidth: 460 }}>
          <Typography sx={{ fontFamily: '"Geologica", sans-serif', fontSize: 30, fontWeight: 600, lineHeight: 1.25 }}>
            Your purple team lab, ready in one click.
          </Typography>
          <Typography color="text.secondary" sx={{ mt: 1.5 }}>
            Sandbox, payloads, and threat intel brought together in a single interface, to train on realistic attack
            and detection scenarios.
          </Typography>
          <Stack spacing={3} sx={{ mt: 5 }}>
            {FEATURES.map(({ icon: Icon, title, description }) => (
              <Stack key={title} direction="row" spacing={1.75}>
                <Box
                  sx={{
                    width: 36,
                    height: 36,
                    flexShrink: 0,
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    borderRadius: 1,
                    border: `1px solid ${theme.palette.border.primary}`,
                    backgroundColor: alpha(theme.palette.primary.main, 0.08),
                    color: "primary.main",
                  }}
                >
                  <Icon sx={{ fontSize: 18 }} />
                </Box>
                <Box>
                  <Typography sx={{ fontWeight: 600 }}>{title}</Typography>
                  <Typography variant="body2" color="text.secondary">
                    {description}
                  </Typography>
                </Box>
              </Stack>
            ))}
          </Stack>
        </Box>

        <Typography variant="body2" sx={{ color: "text.tertiary" }}>
          Research environment — access restricted to the team.
        </Typography>
      </Box>

      <Box sx={{ position: "relative", zIndex: 1, display: "flex", alignItems: "center", justifyContent: "center", px: 2, py: 8 }}>
        <Paper
          variant="outlined"
          sx={{
            width: "100%",
            maxWidth: 380,
            p: 4,
            borderRadius: 1,
            backgroundColor: alpha(theme.palette.background.paper, 0.85),
            backdropFilter: "blur(12px)",
            boxShadow: `0 24px 48px -24px ${alpha(theme.palette.primary.main, 0.35)}`,
          }}
        >
          <Stack spacing={0.5} sx={{ alignItems: { xs: "center", lg: "flex-start" }, mb: 3 }}>
            <BrandLogo size={48} glow sx={{ display: { lg: "none" }, mb: 1 }} />
            <Typography variant="h1" sx={{ m: 0, fontSize: 24 }}>
              Welcome back
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Sign in to access the lab
            </Typography>
          </Stack>

          <Stack component="form" onSubmit={handleSubmit} spacing={2.5}>
            <TextField
              id="email"
              label="Email"
              type="email"
              autoComplete="email"
              placeholder="prenom@purplelab.local"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              fullWidth
              autoFocus
            />
            <TextField
              id="password"
              label="Password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              fullWidth
            />
            {login.isError && (
              <Alert severity="error" variant="outlined">
                {login.error instanceof ApiError && login.error.status === 401
                  ? "Incorrect email or password."
                  : "Unable to sign in at the moment."}
              </Alert>
            )}
            <Button
              type="submit"
              variant="contained"
              size="large"
              disabled={login.isPending}
              startIcon={login.isPending ? <CircularProgress size={16} thickness={4} color="inherit" /> : undefined}
              sx={{
                mt: 1,
                background: brandGradient,
                color: "#ffffff",
                transition: "filter 150ms ease, box-shadow 150ms ease",
                "&:hover": { filter: "brightness(1.1)", boxShadow: `0 8px 24px -8px ${theme.palette.brand.to}` },
              }}
            >
              {login.isPending ? "Signing in…" : "Sign in"}
            </Button>
          </Stack>
        </Paper>
      </Box>
    </Box>
  )
}
