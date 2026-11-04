export type User = {
  id: number
  email: string
  first_name: string
  last_name: string
  role: string
}

export type LoginResponse = {
  access_token: string
  token_type: string
  user: User
}

export type VmState = {
  name: string
  state: string
  snapshot: string | null
  ip: string | null
}

export type SystemMetrics = {
  memory: { percent: number; total_gb: number; used_gb: number }
  disk: { percent: number; total_gb: number; used_gb: number }
  cpu: { percent: number }
}

export type HealthStatus = {
  db: "ok" | "down"
  vm_agent: "ok" | "down"
}

export type CustomPayload = {
  id: number
  name: string
  content: string
  author_id: number
  author_first_name: string
  author_last_name: string
  created_at: string | null
}

export type SharingPost = {
  id: number
  content: string
  title: string | null
  description: string | null
  language: string | null
  author_id: number
  author_first_name: string
  author_last_name: string
  created_at: string
}

export type SigmaBrowse = {
  folders: string[]
  files: string[]
  rule_count: number
}

export type SigmaSearch = {
  matches: string[]
}

export type SigmaRule = {
  path: string
  content: string
}

export type SigmaConvertResult = {
  output: string
}

export type SigmaUpdateResult = {
  rule_count: number
}

export type MalwareRetrieveStarted = {
  job_id: string
  status: string
}

export type MalwareJobStatus = {
  job_id: string
  status: "running" | "done" | "error"
  message: string | null
}

export type MalwareResultRow = {
  md5_hash?: string
  first_seen?: string
  last_seen?: string
  file_name?: string
  file_size?: number
  file_type_mime?: string
  file_type?: string
  reporter?: string
}

export type MalwareUploadedFile = {
  filename: string
  size: number
}

export type ExecuteResult = {
  status: string
  output: string | null
  error: string | null
}

export type UserCount = {
  count: number
}

export type EventCount = {
  count: number
}

export type SigmaCategoryCount = {
  folder: string
  count: number
}

export type SigmaStats = {
  total: number
  by_category: SigmaCategoryCount[]
}

export type TacticCount = {
  tactic: string
  count: number
}

export type MitreStats = {
  technique_count: number
}

export type AlienvaultPulse = {
  id: string
  name: string
  created: string
  description: string
  tags: string[]
  author_name: string
  references: string[]
}

export type AlienvaultDashboardData = {
  summary: { intrusion_sets: number; malware: number; reports: number; indicators: number }
  top_threats: { name: string; count: number }[]
  most_targeted: { name: string; count: number }[]
  top_cves: { cve: string; count: number }[]
  geo_data: Record<string, number>
  recent_pulses: AlienvaultPulse[]
  time_metadata: {
    oldest_pulse: string | null
    newest_pulse: string | null
    pulse_count: number
    generated_at: string
  }
}

export type AlienvaultDashboard = {
  data: AlienvaultDashboardData
  generated_at: string
}

export type AlienvaultConfigStatus = {
  configured: boolean
  key_preview: string | null
  updated_at: string | null
}

export type TechniqueSummary = {
  id: string
  name: string | null
  tactics: string | null
  is_sub_technique: boolean | null
}

export type MatrixTechnique = {
  id: string
  name: string | null
  tactics: string | null
  is_sub_technique: boolean | null
  atomic_tests: number
  sigma_rules: number
  // Atomic Red Team runs recorded in the execution history.
  executions: number
  last_execution_status: string | null
  last_executed_at: string | null
}

export type TechniqueDetail = {
  id: string
  stix_id: string | null
  name: string | null
  description: string | null
  url: string | null
  created: string | null
  last_modified: string | null
  domain: string | null
  version: string | null
  tactics: string | null
  detection: string | null
  platforms: string | null
  data_sources: string | null
  is_sub_technique: boolean | null
  sub_technique_of: string | null
  defenses_bypassed: string | null
  contributors: string | null
  permissions_required: string | null
  supports_remote: boolean | null
  system_requirements: string | null
  impact_type: string | null
  effective_permissions: string | null
  relationship_citations: string | null
}

export type AtomicTestOut = {
  id: number
  tactic: string | null
  technique_id: string | null
  technique_name: string | null
  test: string | null
  test_name: string | null
  test_guid: string | null
  executor_name: string | null
}

export type RefreshResult = {
  created: number
  updated: number
  total: number
}

export type ChatMessage = {
  role: "user" | "assistant"
  content: string
}

export type ChatResponse = {
  message: ChatMessage
  related_techniques: string[]
}

export type ConnectorType = "splunk" | "opensearch" | "wazuh" | "elasticsearch" | "graylog"

export type ConnectorConfig = {
  host: string
  port?: number | null
  username: string
  password: string
  ssl_enabled: boolean
}

export type ConnectorStatus = {
  type: ConnectorType
  configured: boolean
  config: (Partial<ConnectorConfig> & { password?: string }) | null
}

export type ConnectorTestResult = {
  ok: boolean
}

export type SyncedRule = {
  id: number
  connector: ConnectorType
  rule_id: string
  rule_data: Record<string, unknown>
  synced_at: string | null
}

export type RulesSyncResult = {
  synced_count: number
}

export type RulePayloadMapEntry = {
  rule_id: string
  payload_id: number | null
}

export type RuleLifecyclePayloadItem = {
  id: number
  name: string
  description: string | null
  code: string
  created_at: string | null
  updated_at: string | null
}

export type ExecutionHistoryEntry = {
  id: number
  source: string
  payload_id: number | null
  rule_id: string | null
  connector: string | null
  status: string
  output: string | null
  error: string | null
  executed_by: number | null
  executed_at: string
}

export type MitreAiConfigStatus = {
  configured: boolean
  provider: "openai" | "gemini" | null
  model: string | null
  api_base: string | null
  key_preview: string | null
  updated_at: string | null
}

// /malware/execute and /malware/upload don't follow the
// {status,output,error} pattern of the other routers: they relay
// the vmbridge-agent response directly (or raise an HTTP 502 on
// failure, cf. malware.py:93-98) rather than returning an "error"
// status inside a 200.
export type MalwareExecuteResult = {
  message: string
  output?: string
}
