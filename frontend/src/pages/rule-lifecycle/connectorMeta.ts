import type { ComponentType } from "react"
import { ElasticsearchLogo, GraylogLogo, OpenSearchLogo, SplunkLogo, WazuhLogo } from "@/components/icons/SiemLogos"
import type { ConnectorType } from "@/lib/types"

export const CONNECTOR_META: Record<
  ConnectorType,
  {
    label: string
    logo: ComponentType<{ height?: number }>
    // Wordmark logos already spell the product name.
    wordmark?: boolean
    // Logo height inside the 64x40 connector card tile (wordmarks differ in width).
    tileHeight: number
    // Inline (labels, menus) height relative to the text size, so wordmarks
    // with different proportions read at the same visual size.
    inlineScale?: number
    color: string
    defaultPort: number
    hint?: string
  }
> = {
  opensearch: { label: "OpenSearch", logo: OpenSearchLogo, tileHeight: 24, color: "#00A3E0", defaultPort: 9200 },
  splunk: { label: "Splunk", logo: SplunkLogo, wordmark: true, tileHeight: 15, inlineScale: 1.15, color: "#65A637", defaultPort: 8089 },
  wazuh: { label: "Wazuh", logo: WazuhLogo, wordmark: true, tileHeight: 11, inlineScale: 0.72, color: "#3585F9", defaultPort: 55000 },
  elasticsearch: {
    label: "Elasticsearch",
    logo: ElasticsearchLogo,
    tileHeight: 24,
    color: "#00BFB3",
    defaultPort: 5601,
    hint: "Points at Kibana, not the Elasticsearch cluster: detection rules are managed through Kibana's Detection Engine API.",
  },
  graylog: {
    label: "Graylog",
    logo: GraylogLogo,
    tileHeight: 24,
    color: "#FF3633",
    defaultPort: 9000,
    hint: "Rules here are Graylog's Event Definitions (search/aggregation conditions), its detection-rule mechanism.",
  },
}

export const CONNECTOR_TYPES = Object.keys(CONNECTOR_META) as ConnectorType[]
