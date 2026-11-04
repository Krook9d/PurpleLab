import { Box, Button, Stack, Tab, Tabs, Typography } from "@mui/material"
import SyncOutlined from "@mui/icons-material/SyncOutlined"
import GridOnOutlined from "@mui/icons-material/GridOnOutlined"
import SearchOutlined from "@mui/icons-material/SearchOutlined"
import { useSearchParams } from "react-router-dom"
import { PageContainer } from "@/components/layout/PageContainer"
import { MitreAiChatWidget } from "@/components/ai/MitreAiChatWidget"
import { useMitreStats, useRefreshMitreDatabase } from "@/hooks/useMitre"
import { MatrixView } from "./mitre/matrix/MatrixView"
import { SearchView } from "./mitre/SearchView"

type View = "matrix" | "search"

export function MitrePage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const view: View = searchParams.get("view") === "search" ? "search" : "matrix"
  const selectedId = searchParams.get("technique")
  const stats = useMitreStats()
  const refreshDb = useRefreshMitreDatabase()

  const update = (changes: Record<string, string | null>) => {
    const next = new URLSearchParams(searchParams)
    for (const [key, value] of Object.entries(changes)) {
      if (value === null) next.delete(key)
      else next.set(key, value)
    }
    setSearchParams(next)
  }
  // In the matrix the technique opens in a side panel, in search it shows inline.
  const selectTechnique = (id: string) => update({ technique: id })

  return (
    <PageContainer>
      <Stack direction="row" useFlexGap sx={{ alignItems: "center", gap: 2, flexWrap: "wrap", borderBottom: 1, borderColor: "divider" }}>
        <Tabs value={view} onChange={(_, v: View) => update({ view: v, technique: null })}>
          <Tab value="matrix" icon={<GridOnOutlined fontSize="small" />} iconPosition="start" label="Matrix" sx={{ minHeight: 48, textTransform: "none" }} />
          <Tab value="search" icon={<SearchOutlined fontSize="small" />} iconPosition="start" label="Search" sx={{ minHeight: 48, textTransform: "none" }} />
        </Tabs>
        <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
          {stats.data ? `${stats.data.technique_count} techniques indexed` : ""}
        </Typography>
        <Box sx={{ pb: 0.5 }}>
          <Button
            variant="outlined"
            startIcon={<SyncOutlined />}
            loading={refreshDb.isPending}
            loadingPosition="start"
            onClick={() => refreshDb.mutate()}
          >
            Update database
          </Button>
        </Box>
      </Stack>

      {view === "matrix" ? (
        <MatrixView selectedId={selectedId} onSelect={selectTechnique} onClose={() => update({ technique: null })} />
      ) : (
        <SearchView selectedId={selectedId} onSelect={selectTechnique} />
      )}

      <MitreAiChatWidget onTechniqueClick={selectTechnique} />
    </PageContainer>
  )
}
