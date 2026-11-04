import { alpha, Box, List, ListItemButton, Skeleton, Stack, Typography, useTheme } from "@mui/material"
import SubdirectoryArrowRightOutlined from "@mui/icons-material/SubdirectoryArrowRightOutlined"
import { TechniqueIdChip } from "@/components/common/TechniqueIdChip"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { splitList, tacticLabel } from "@/lib/mitre"
import { tacticColor } from "@/lib/tacticColors"
import type { TechniqueSummary } from "@/lib/types"

interface TechniqueSearchListProps {
  query: string
  results: TechniqueSummary[] | undefined
  loading: boolean
  selectedId: string | null
  onSelect: (id: string) => void
}

export function TechniqueSearchList({ query, results, loading, selectedId, onSelect }: TechniqueSearchListProps) {
  const theme = useTheme()

  if (query.length < 2) {
    return <WidgetNoData message="Type at least 2 characters: a name (Phishing) or an ID (T1566)." />
  }
  if (loading) {
    return (
      <Stack spacing={1}>
        {Array.from({ length: 6 }, (_, i) => (
          <Skeleton key={i} variant="rounded" height={44} />
        ))}
      </Stack>
    )
  }
  if (!results?.length) return <WidgetNoData message="No technique found." />

  return (
    <List disablePadding sx={{ mx: -1 }}>
      {results.map((t) => {
        const primaryTactic = splitList(t.tactics)[0]
        const selected = t.id === selectedId
        return (
          <ListItemButton
            key={t.id}
            selected={selected}
            onClick={() => onSelect(t.id)}
            sx={{
              gap: 1,
              py: 0.75,
              pl: t.is_sub_technique ? 3 : 1,
              borderRadius: 0.5,
              borderLeft: `2px solid ${selected ? theme.palette.primary.main : "transparent"}`,
              "&.Mui-selected": { backgroundColor: alpha(theme.palette.primary.main, 0.12) },
            }}
          >
            {t.is_sub_technique && <SubdirectoryArrowRightOutlined sx={{ fontSize: 14, color: "text.tertiary" }} />}
            <TechniqueIdChip id={t.id} />
            <Typography noWrap sx={{ flex: 1, minWidth: 0, fontSize: 13 }}>
              {t.name}
            </Typography>
            {primaryTactic && (
              <Box
                title={tacticLabel(primaryTactic)}
                sx={{ width: 8, height: 8, flexShrink: 0, borderRadius: "50%", backgroundColor: tacticColor(theme, primaryTactic) }}
              />
            )}
          </ListItemButton>
        )
      })}
    </List>
  )
}
