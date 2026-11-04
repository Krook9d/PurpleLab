import { useMemo } from "react"
import { Box, useTheme } from "@mui/material"
import ReceiptLongOutlined from "@mui/icons-material/ReceiptLongOutlined"
import GroupOutlined from "@mui/icons-material/GroupOutlined"
import RuleOutlined from "@mui/icons-material/RuleOutlined"
import SecurityOutlined from "@mui/icons-material/SecurityOutlined"
import AccountTreeOutlined from "@mui/icons-material/AccountTreeOutlined"
import { PageContainer } from "@/components/layout/PageContainer"
import { WidgetContainer } from "@/components/dashboard/WidgetContainer"
import { WidgetNumber } from "@/components/dashboard/WidgetNumber"
import { WidgetHorizontalBars } from "@/components/dashboard/WidgetHorizontalBars"
import { useEventCount } from "@/hooks/useHealth"
import { useUsersCount } from "@/hooks/useUsers"
import { useSigmaStats } from "@/hooks/useSigma"
import { useAtomicTestsByTactic, useMitreStats } from "@/hooks/useMitre"
import { useRuleLifecyclePayloads } from "@/hooks/useRuleLifecyclePayloads"
import { tacticLabel } from "@/lib/mitre"
import { tacticColor } from "@/lib/tacticColors"
import { CATEGORY_META } from "./sigma/sigmaMeta"
import type { ChartDatum } from "@/utils/chartData"
import { OtxSection } from "./dashboard/OtxSection"

const byValueDesc = (a: ChartDatum, b: ChartDatum) => b.value - a.value

export function IndexPage() {
  const theme = useTheme()
  const eventCount = useEventCount()
  const usersCount = useUsersCount()
  const sigmaStats = useSigmaStats()
  const mitreStats = useMitreStats()
  const byTactic = useAtomicTestsByTactic()
  const ruleLifecyclePayloads = useRuleLifecyclePayloads()

  const sigmaData = useMemo(
    // Same color as the category card on the Sigma page.
    () =>
      (sigmaStats.data?.by_category ?? [])
        .map((c) => {
          const slot = CATEGORY_META[c.folder]?.slot
          return { label: c.folder, value: c.count, color: slot !== undefined ? theme.palette.chart[slot] : theme.palette.text.secondary }
        })
        .sort(byValueDesc),
    [sigmaStats.data, theme],
  )
  const tacticData = useMemo(
    // Same color as the tactic tags on the MITRE page.
    () =>
      (byTactic.data ?? [])
        .map((t) => ({ label: tacticLabel(t.tactic), value: t.count, color: tacticColor(theme, t.tactic) }))
        .sort(byValueDesc),
    [byTactic.data, theme],
  )

  return (
    <PageContainer>
      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", sm: "repeat(2, 1fr)", lg: "repeat(5, 1fr)" } }}>
        <WidgetNumber
          label="Winlogbeat events"
          value={eventCount.data?.count}
          loading={eventCount.isLoading}
          icon={<ReceiptLongOutlined />}
        />
        <WidgetNumber label="Users" value={usersCount.data?.count} loading={usersCount.isLoading} icon={<GroupOutlined />} />
        <WidgetNumber
          label="Sigma rules"
          value={sigmaStats.data?.total}
          loading={sigmaStats.isLoading}
          icon={<RuleOutlined />}
          to="/sigma"
        />
        <WidgetNumber
          label="MITRE techniques"
          value={mitreStats.data?.technique_count}
          loading={mitreStats.isLoading}
          icon={<SecurityOutlined />}
          to="/mitre"
        />
        <WidgetNumber
          label="Rule lifecycle payloads"
          value={ruleLifecyclePayloads.data?.length}
          loading={ruleLifecyclePayloads.isLoading}
          icon={<AccountTreeOutlined />}
          to="/rule-lifecycle"
        />
      </Box>

      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", lg: "1fr 1fr" } }}>
        <WidgetContainer
          title="Sigma rules by category"
          loading={sigmaStats.isLoading}
          empty={sigmaData.length === 0}
        >
          <WidgetHorizontalBars data={sigmaData} name="Rules" />
        </WidgetContainer>
        <WidgetContainer
          title="Atomic tests by tactic"
          loading={byTactic.isLoading}
          empty={tacticData.length === 0}
          emptyMessage="No Atomic tests indexed."
        >
          <WidgetHorizontalBars data={tacticData} name="Tests" />
        </WidgetContainer>
      </Box>

      <OtxSection />
    </PageContainer>
  )
}
