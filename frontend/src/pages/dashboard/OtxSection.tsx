import { Box, Button, Chip, Stack, Typography } from "@mui/material"
import SatelliteAltOutlined from "@mui/icons-material/SatelliteAltOutlined"
import GroupsOutlined from "@mui/icons-material/GroupsOutlined"
import CoronavirusOutlined from "@mui/icons-material/CoronavirusOutlined"
import DescriptionOutlined from "@mui/icons-material/DescriptionOutlined"
import FingerprintOutlined from "@mui/icons-material/FingerprintOutlined"
import SettingsOutlined from "@mui/icons-material/SettingsOutlined"
import BugReportOutlined from "@mui/icons-material/BugReportOutlined"
import { Link as RouterLink } from "react-router-dom"
import { Card } from "@/components/common/Card"
import { WidgetContainer } from "@/components/dashboard/WidgetContainer"
import { WidgetNumber } from "@/components/dashboard/WidgetNumber"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { WidgetDonut } from "@/components/dashboard/WidgetDonut"
import { WidgetPolarArea } from "@/components/dashboard/WidgetPolarArea"
import { WorldThreatMap } from "@/components/charts/WorldThreatMap"
import { WidgetDistributionList } from "@/components/dashboard/WidgetDistributionList"
import { useAlienvaultDashboard } from "@/hooks/useAlienvault"
import { useCurrentUser } from "@/hooks/useAuth"
import { RecentPulseList } from "./RecentPulseList"

// Vulnerability accent ("arsenal" amber), darkened in light mode for contrast.
const vulnerabilityColor = (mode: "light" | "dark") => (mode === "dark" ? "#F0B60A" : "#A87B00")

const dateTimeFormat = new Intl.DateTimeFormat("en-US", { dateStyle: "medium", timeStyle: "short" })

export function OtxSection() {
  const alienvault = useAlienvaultDashboard()
  const { data: user } = useCurrentUser()
  const otx = alienvault.data?.data

  const header = (
    <Stack direction="row" sx={{ alignItems: "center", gap: 1, mb: 1.5 }}>
      <SatelliteAltOutlined sx={{ fontSize: 18, color: "primary.main" }} />
      <Typography variant="h2" sx={{ m: 0, textTransform: "none" }}>
        AlienVault OTX
      </Typography>
      {alienvault.data && (
        <Chip
          size="small"
          variant="outlined"
          label={`Updated ${dateTimeFormat.format(new Date(alienvault.data.generated_at))}`}
          sx={{ ml: 1, height: 22, fontSize: 11 }}
        />
      )}
    </Stack>
  )

  if (alienvault.isLoading) {
    return (
      <Box>
        {header}
        <WidgetContainer loading height={200} />
      </Box>
    )
  }

  if (!otx) {
    return (
      <Box>
        {header}
        <Card>
          <WidgetNoData
            icon={<SatelliteAltOutlined sx={{ fontSize: 32, color: "primary.main", opacity: 0.7 }} />}
            message={
              <>
                The threat intelligence dashboard has not been generated yet.
                <br />
                Configure the AlienVault OTX key and run a refresh from Administration.
              </>
            }
          />
          {user?.role === "admin" && (
            <Box sx={{ display: "flex", justifyContent: "center", mt: 1 }}>
              <Button component={RouterLink} to="/admin" variant="outlined" startIcon={<SettingsOutlined />}>
                Open administration
              </Button>
            </Box>
          )}
        </Card>
      </Box>
    )
  }

  const hasGeo = Object.keys(otx.geo_data).length > 0

  return (
    <Box>
      {header}
      <Stack spacing={2}>
        <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", sm: "repeat(2, 1fr)", lg: "repeat(4, 1fr)" } }}>
          <WidgetNumber label="Intrusion sets" value={otx.summary.intrusion_sets} icon={<GroupsOutlined />} />
          <WidgetNumber label="Malware" value={otx.summary.malware} icon={<CoronavirusOutlined />} />
          <WidgetNumber label="Reports" value={otx.summary.reports} icon={<DescriptionOutlined />} />
          <WidgetNumber label="Indicators" value={otx.summary.indicators} icon={<FingerprintOutlined />} />
        </Box>

        <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", lg: "repeat(3, 1fr)" } }}>
          <WidgetContainer title="Main threat types" empty={otx.top_threats.length === 0}>
            <WidgetDonut data={otx.top_threats.map((t) => ({ label: t.name, value: t.count }))} totalLabel="Mentions" height={380} />
          </WidgetContainer>
          <WidgetContainer title="Most targeted industries" empty={otx.most_targeted.length === 0}>
            <WidgetPolarArea data={otx.most_targeted.map((t) => ({ label: t.name, value: t.count }))} height={420} />
          </WidgetContainer>
          <WidgetContainer
            title="Most exploited CVEs"
            empty={otx.top_cves.length === 0}
            emptyMessage="No CVE mentioned in current pulses."
          >
            <WidgetDistributionList
              data={otx.top_cves.map((c) => ({
                label: c.cve,
                value: c.count,
                href: `https://nvd.nist.gov/vuln/detail/${encodeURIComponent(c.cve)}`,
                icon: <BugReportOutlined sx={(theme) => ({ color: vulnerabilityColor(theme.palette.mode) })} />,
              }))}
            />
          </WidgetContainer>
        </Box>

        <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", lg: "2fr 1fr" } }}>
          <WidgetContainer
            title="Global threat map"
            height={480}
            empty={!hasGeo}
            emptyMessage="No geolocated indicators (needs resolvable IPv4 indicators in recent pulses)."
          >
            <WorldThreatMap data={otx.geo_data} />
          </WidgetContainer>
          <WidgetContainer
            title="Latest detected threats"
            height={480}
            empty={otx.recent_pulses.length === 0}
            emptyMessage="No recent threats available."
          >
            <RecentPulseList pulses={otx.recent_pulses} />
          </WidgetContainer>
        </Box>
      </Stack>
    </Box>
  )
}
