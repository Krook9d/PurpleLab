import type { SvgIconComponent } from "@mui/icons-material"
import AppsOutlined from "@mui/icons-material/AppsOutlined"
import LayersOutlined from "@mui/icons-material/LayersOutlined"
import CloudOutlined from "@mui/icons-material/CloudOutlined"
import VerifiedUserOutlined from "@mui/icons-material/VerifiedUserOutlined"
import TerminalOutlined from "@mui/icons-material/TerminalOutlined"
import LaptopMacOutlined from "@mui/icons-material/LaptopMacOutlined"
import LanOutlined from "@mui/icons-material/LanOutlined"
import LanguageOutlined from "@mui/icons-material/LanguageOutlined"
import DesktopWindowsOutlined from "@mui/icons-material/DesktopWindowsOutlined"
import BadgeOutlined from "@mui/icons-material/BadgeOutlined"

// Top-level Sigma folders, in a fixed order (index = chart palette slot).
export const CATEGORY_META: Record<string, { icon: SvgIconComponent; description: string; slot: number }> = {
  windows: { icon: DesktopWindowsOutlined, description: "Windows event logs", slot: 0 },
  linux: { icon: TerminalOutlined, description: "Linux / auditd logs", slot: 1 },
  cloud: { icon: CloudOutlined, description: "AWS, Azure, GCP, M365…", slot: 2 },
  macos: { icon: LaptopMacOutlined, description: "macOS logs", slot: 3 },
  network: { icon: LanOutlined, description: "Network traffic and devices", slot: 4 },
  web: { icon: LanguageOutlined, description: "Web server logs", slot: 5 },
  application: { icon: AppsOutlined, description: "Application events", slot: 6 },
  identity: { icon: BadgeOutlined, description: "Identity providers", slot: 7 },
  category: { icon: LayersOutlined, description: "Generic rules by category", slot: 0 },
  compliance: { icon: VerifiedUserOutlined, description: "Compliance rules", slot: 1 },
}

// qradar/cortexxdr/clickhouse/panther deliberately excluded: verified broken
// or unusable without extra setup (see backend/Dockerfile).
export const PLUGIN_LABELS: Record<string, string> = {
  splunk: "Splunk",
  lucene: "Lucene",
  kusto: "Kusto",
  carbon_black: "Carbon Black",
  sentinel_one: "SentinelOne",
  sentinel_one_pq: "SentinelOne PQ",
  opensearch_lucene: "OpenSearch",
  log_scale: "CrowdStrike LogScale",
  sumologic_cse: "Sumo Logic",
  loki: "Loki",
  logpoint: "Logpoint",
  secops: "Google SecOps",
  quickwit: "Quickwit",
  sqlite: "SQLite",
  net_witness: "NetWitness",
  signals: "Tanium Signals",
  golang_expr: "Golang Expr",
}
