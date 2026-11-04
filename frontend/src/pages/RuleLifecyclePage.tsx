import { Box, Fade, Tab, Tabs } from "@mui/material"
import { useSearchParams } from "react-router-dom"
import { PageContainer } from "@/components/layout/PageContainer"
import { ConnectorsTab } from "./rule-lifecycle/ConnectorsTab"
import { RulesTab } from "./rule-lifecycle/RulesTab"
import { ExecutionTab } from "./rule-lifecycle/ExecutionTab"

const TABS = [
  { value: "connectors", label: "Connectors" },
  { value: "rules", label: "Rules & payloads" },
  { value: "execution", label: "Execution & results" },
] as const
type TabValue = (typeof TABS)[number]["value"]

export function RuleLifecyclePage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const requested = searchParams.get("tab")
  const tab: TabValue = TABS.some((t) => t.value === requested) ? (requested as TabValue) : "connectors"

  return (
    <PageContainer>
      <Box sx={{ borderBottom: 1, borderColor: "divider" }}>
        <Tabs value={tab} onChange={(_, v: TabValue) => setSearchParams({ tab: v }, { replace: true })}>
          {TABS.map((t) => (
            <Tab key={t.value} value={t.value} label={t.label} />
          ))}
        </Tabs>
      </Box>
      {/* Keyed on the tab so each switch fades the new content in. */}
      <Fade key={tab} in timeout={250}>
        <Box>
          {tab === "connectors" && <ConnectorsTab />}
          {tab === "rules" && <RulesTab />}
          {tab === "execution" && <ExecutionTab />}
        </Box>
      </Fade>
    </PageContainer>
  )
}
