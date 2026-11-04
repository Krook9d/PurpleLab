import type { ReactNode } from "react"
import { Box, Chip, Divider, Link, Skeleton, Stack, Typography } from "@mui/material"
import OpenInNew from "@mui/icons-material/OpenInNew"
import ArrowBackOutlined from "@mui/icons-material/ArrowBackOutlined"
import { Card } from "@/components/common/Card"
import { Markdown } from "@/components/common/Markdown"
import { TechniqueIdChip } from "@/components/common/TechniqueIdChip"
import { mitreTextToMarkdown, splitList } from "@/lib/mitre"
import type { TechniqueDetail } from "@/lib/types"
import { TacticChip } from "./TacticChip"

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <Box>
      <Typography variant="h4" sx={{ color: "text.light", mb: 0.75 }}>
        {label}
      </Typography>
      {children}
    </Box>
  )
}

function ChipList({ values }: { values: string[] }) {
  return (
    <Stack direction="row" useFlexGap sx={{ gap: 0.75, flexWrap: "wrap" }}>
      {values.map((v) => (
        <Chip key={v} label={v} size="small" variant="outlined" sx={{ height: 22, fontSize: 12 }} />
      ))}
    </Stack>
  )
}

const dateFormat = new Intl.DateTimeFormat("en-US", { dateStyle: "medium" })

interface TechniqueDetailCardProps {
  technique: TechniqueDetail | undefined
  loading: boolean
  onSelect: (id: string) => void
}

export function TechniqueDetailCard({ technique, loading, onSelect }: TechniqueDetailCardProps) {
  if (loading || !technique) {
    return (
      <Card>
        <Stack spacing={1.5}>
          <Skeleton variant="text" width="40%" sx={{ fontSize: 24 }} />
          <Skeleton variant="rounded" height={120} />
          <Skeleton variant="rounded" height={60} />
        </Stack>
      </Card>
    )
  }

  const platforms = splitList(technique.platforms)
  const dataSources = splitList(technique.data_sources)
  const permissions = splitList(technique.permissions_required)

  return (
    <Card>
      <Stack spacing={2.5}>
        <Box>
          {technique.is_sub_technique && technique.sub_technique_of && (
            <Link
              component="button"
              variant="body2"
              underline="hover"
              color="text.secondary"
              onClick={() => onSelect(technique.sub_technique_of!)}
              sx={{ display: "inline-flex", alignItems: "center", gap: 0.5, mb: 1 }}
            >
              <ArrowBackOutlined sx={{ fontSize: 14 }} /> Parent technique {technique.sub_technique_of}
            </Link>
          )}
          <Stack direction="row" useFlexGap sx={{ alignItems: "center", gap: 1.5, flexWrap: "wrap" }}>
            <TechniqueIdChip id={technique.id} size="medium" />
            <Typography variant="h1" sx={{ m: 0, textTransform: "none", flex: 1, minWidth: 200 }}>
              {technique.name}
            </Typography>
            {technique.url && (
              <Link
                href={technique.url}
                target="_blank"
                rel="noreferrer"
                variant="body2"
                underline="hover"
                sx={{ display: "inline-flex", alignItems: "center", gap: 0.5 }}
              >
                attack.mitre.org <OpenInNew sx={{ fontSize: 14 }} />
              </Link>
            )}
          </Stack>
          <Stack direction="row" useFlexGap sx={{ gap: 0.75, flexWrap: "wrap", mt: 1.5 }}>
            {splitList(technique.tactics).map((tactic) => (
              <TacticChip key={tactic} tactic={tactic} />
            ))}
          </Stack>
        </Box>

        <Box sx={{ display: "grid", gap: 3, gridTemplateColumns: { xs: "1fr", xl: "minmax(0, 2fr) minmax(220px, 1fr)" } }}>
          <Field label="Description">
            <Markdown sx={{ color: "text.secondary" }}>{mitreTextToMarkdown(technique.description ?? "No description.")}</Markdown>
          </Field>
          <Stack spacing={2}>
            {platforms.length > 0 && (
              <Field label="Platforms">
                <ChipList values={platforms} />
              </Field>
            )}
            {permissions.length > 0 && (
              <Field label="Permissions required">
                <ChipList values={permissions} />
              </Field>
            )}
            {(technique.version || technique.last_modified) && (
              <Field label="Version">
                <Typography variant="body2" color="text.secondary">
                  {technique.version ? `v${technique.version}` : "—"}
                  {technique.last_modified ? ` · modified ${dateFormat.format(new Date(technique.last_modified))}` : ""}
                </Typography>
              </Field>
            )}
          </Stack>
        </Box>

        {(dataSources.length > 0 || technique.detection) && <Divider />}
        {dataSources.length > 0 && (
          <Field label="Data sources">
            <ChipList values={dataSources} />
          </Field>
        )}
        {technique.detection && (
          <Field label="Detection">
            <Markdown sx={{ color: "text.secondary" }}>{mitreTextToMarkdown(technique.detection)}</Markdown>
          </Field>
        )}
      </Stack>
    </Card>
  )
}
