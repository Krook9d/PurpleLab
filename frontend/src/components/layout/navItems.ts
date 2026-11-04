import type { SvgIconComponent } from "@mui/icons-material"
import DashboardOutlined from "@mui/icons-material/DashboardOutlined"
import TrackChangesOutlined from "@mui/icons-material/TrackChangesOutlined"
import SecurityOutlined from "@mui/icons-material/SecurityOutlined"
import CodeOutlined from "@mui/icons-material/CodeOutlined"
import BugReportOutlined from "@mui/icons-material/BugReportOutlined"
import ShareOutlined from "@mui/icons-material/ShareOutlined"
import RuleOutlined from "@mui/icons-material/RuleOutlined"
import AccountTreeOutlined from "@mui/icons-material/AccountTreeOutlined"
import MonitorHeartOutlined from "@mui/icons-material/MonitorHeartOutlined"
import AdminPanelSettingsOutlined from "@mui/icons-material/AdminPanelSettingsOutlined"

export type NavGroup = "Overview" | "Threat intel" | "Purple ops" | "System"
export const NAV_GROUPS: NavGroup[] = ["Overview", "Threat intel", "Purple ops", "System"]

export interface NavItem {
  group: NavGroup
  to: string
  label: string
  icon: SvgIconComponent
  external?: boolean
  adminOnly?: boolean
}

// Hunting is OpenSearch Dashboards ("PurpleLab Hunting"), proxied by the
// frontend nginx under /hunting/ on the same origin; opened in a new tab.
const huntingUrl = "/hunting/"

export const NAV_ITEMS: NavItem[] = [
  { group: "Overview", to: "/", label: "Dashboard", icon: DashboardOutlined },
  { group: "Overview", to: huntingUrl, label: "Hunting", icon: TrackChangesOutlined, external: true },
  { group: "Threat intel", to: "/mitre", label: "MITRE ATT&CK", icon: SecurityOutlined },
  { group: "Threat intel", to: "/malware", label: "Malware", icon: BugReportOutlined },
  { group: "Threat intel", to: "/sigma", label: "Sigma Rules", icon: RuleOutlined },
  { group: "Purple ops", to: "/custom-payloads", label: "Custom Payloads", icon: CodeOutlined },
  { group: "Purple ops", to: "/rule-lifecycle", label: "Rule Lifecycle", icon: AccountTreeOutlined },
  { group: "Purple ops", to: "/sharing", label: "Sharing", icon: ShareOutlined },
  { group: "System", to: "/health", label: "Health", icon: MonitorHeartOutlined },
  { group: "System", to: "/admin", label: "Administration", icon: AdminPanelSettingsOutlined, adminOnly: true },
]

export function isNavItemSelected(item: NavItem, pathname: string): boolean {
  if (item.external) return false
  if (item.to === "/") return pathname === "/"
  return pathname === item.to || pathname.startsWith(`${item.to}/`)
}
