import { alpha, Box, Chip, Link, Stack, Typography } from "@mui/material"
import OpenInNew from "@mui/icons-material/OpenInNew"
import ReportProblemOutlined from "@mui/icons-material/ReportProblemOutlined"
import type { AlienvaultPulse } from "@/lib/types"

const dateFormat = new Intl.DateTimeFormat("en-US", { year: "numeric", month: "short", day: "numeric" })

export function RecentPulseList({ pulses }: { pulses: AlienvaultPulse[] }) {
  return (
    <Box sx={{ maxHeight: 400, overflowY: "auto", mx: -1 }}>
      {pulses.slice(0, 6).map((pulse) => (
        <Link
          key={pulse.id}
          href={`https://otx.alienvault.com/pulse/${pulse.id}`}
          target="_blank"
          rel="noreferrer"
          underline="none"
          color="inherit"
          sx={(theme) => ({
            display: "flex",
            gap: 1.5,
            px: 1,
            py: 1.5,
            borderRadius: 0.5,
            borderBottom: `1px solid ${theme.palette.divider}`,
            "&:last-of-type": { borderBottom: 0 },
            transition: "background-color 150ms ease",
            "&:hover": { backgroundColor: alpha(theme.palette.primary.main, 0.06) },
            "&:hover .pulse-name": { color: theme.palette.primary.main },
            "&:hover .pulse-open": { opacity: 1 },
          })}
        >
          <Box
            sx={(theme) => ({
              mt: 0.25,
              width: 26,
              height: 26,
              flexShrink: 0,
              borderRadius: 0.5,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              color: theme.palette.error.main,
              backgroundColor: alpha(theme.palette.error.main, 0.12),
            })}
          >
            <ReportProblemOutlined sx={{ fontSize: 15 }} />
          </Box>
          <Box sx={{ minWidth: 0, flex: 1 }}>
            <Stack direction="row" sx={{ gap: 1, alignItems: "flex-start", justifyContent: "space-between" }}>
              <Typography className="pulse-name" sx={{ fontWeight: 500, transition: "color 150ms ease" }}>
                {pulse.name}
              </Typography>
              <OpenInNew className="pulse-open" sx={{ fontSize: 14, mt: 0.25, opacity: 0, color: "text.secondary", transition: "opacity 150ms ease" }} />
            </Stack>
            <Typography variant="body2" sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 11, color: "text.tertiary" })}>
              {dateFormat.format(new Date(pulse.created))}
            </Typography>
            {pulse.tags.length > 0 && (
              <Stack direction="row" sx={{ gap: 0.5, flexWrap: "wrap", mt: 0.75 }}>
                {pulse.tags.slice(0, 3).map((tag) => (
                  <Chip key={tag} label={tag} size="small" variant="outlined" sx={{ height: 20, fontSize: 11 }} />
                ))}
              </Stack>
            )}
            {pulse.description && (
              <Typography
                variant="body2"
                color="text.secondary"
                sx={{ mt: 0.75, display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden" }}
              >
                {pulse.description}
              </Typography>
            )}
          </Box>
        </Link>
      ))}
    </Box>
  )
}
