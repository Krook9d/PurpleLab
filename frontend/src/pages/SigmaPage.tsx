import { useMemo, useState } from "react"
import { alpha, Box, Button, LinearProgress, List, ListItemButton, Skeleton, Stack, Typography, useTheme } from "@mui/material"
import SyncOutlined from "@mui/icons-material/SyncOutlined"
import FolderOutlined from "@mui/icons-material/FolderOutlined"
import DescriptionOutlined from "@mui/icons-material/DescriptionOutlined"
import RuleOutlined from "@mui/icons-material/RuleOutlined"
import { PageContainer } from "@/components/layout/PageContainer"
import { Breadcrumbs } from "@/components/layout/Breadcrumbs"
import { Card } from "@/components/common/Card"
import { SearchInput } from "@/components/common/SearchInput"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { useDebouncedValue } from "@/hooks/useDebouncedValue"
import { useBrowseSigma, useSearchSigma, useSigmaStats, useUpdateSigmaDatabase } from "@/hooks/useSigma"
import { CATEGORY_META } from "./sigma/sigmaMeta"
import { RuleDrawer } from "./sigma/RuleDrawer"

const numberFormat = new Intl.NumberFormat("en-US")

function FileList({ paths, onSelect, showFullPath }: { paths: string[]; onSelect: (path: string) => void; showFullPath?: boolean }) {
  return (
    <List disablePadding dense>
      {paths.map((path) => (
        <ListItemButton
          key={path}
          onClick={() => onSelect(path)}
          sx={(theme) => ({
            gap: 1.25,
            borderRadius: 0.5,
            "&:hover": { backgroundColor: alpha(theme.palette.primary.main, 0.08) },
          })}
        >
          <DescriptionOutlined sx={{ fontSize: 16, color: "warning.main" }} />
          <Typography noWrap sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontSize: 13 })}>
            {showFullPath ? path : path.split("/").pop()}
          </Typography>
        </ListItemButton>
      ))}
    </List>
  )
}

export function SigmaPage() {
  const theme = useTheme()
  const [folder, setFolder] = useState("")
  const [search, setSearch] = useState("")
  const [fileFilter, setFileFilter] = useState("")
  const [selectedFile, setSelectedFile] = useState<string | null>(null)
  const debouncedSearch = useDebouncedValue(search.trim(), 300)
  const searching = debouncedSearch.length >= 2

  const browse = useBrowseSigma(folder)
  const stats = useSigmaStats()
  const searchResults = useSearchSigma(debouncedSearch)
  const updateDatabase = useUpdateSigmaDatabase()

  const segments = folder ? folder.split("/") : []
  const counts = useMemo(() => new Map((stats.data?.by_category ?? []).map((c) => [c.folder, c.count])), [stats.data])
  const maxCount = Math.max(1, ...counts.values())
  const files = useMemo(() => {
    const q = fileFilter.trim().toLowerCase()
    return (browse.data?.files ?? []).filter((f) => !q || f.toLowerCase().includes(q)).map((f) => (folder ? `${folder}/${f}` : f))
  }, [browse.data, fileFilter, folder])

  const openFolder = (path: string) => {
    setFolder(path)
    setFileFilter("")
  }

  return (
    <PageContainer>
      <Stack direction="row" useFlexGap sx={{ alignItems: "center", gap: 1.5, flexWrap: "wrap" }}>
        <SearchInput value={search} onChange={setSearch} placeholder="Search in rule content…" sx={{ width: 360 }} />
        <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
          Browse, search and convert Sigma detection rules
          {browse.data && !folder ? ` · ${numberFormat.format(browse.data.rule_count)} rules` : ""}.
        </Typography>
        <Button
          variant="outlined"
          startIcon={<SyncOutlined />}
          loading={updateDatabase.isPending}
          loadingPosition="start"
          onClick={() => updateDatabase.mutate()}
        >
          Update database
        </Button>
      </Stack>
      {updateDatabase.isPending && <LinearProgress sx={{ borderRadius: 1 }} />}

      {searching ? (
        <Card title={`Search results${searchResults.data ? ` (${searchResults.data.matches.length})` : ""}`} padding="small">
          {searchResults.isLoading ? (
            <Stack spacing={1} sx={{ p: 1 }}>
              {[0, 1, 2, 3].map((i) => (
                <Skeleton key={i} variant="rounded" height={32} />
              ))}
            </Stack>
          ) : searchResults.data?.matches.length ? (
            <FileList paths={searchResults.data.matches} onSelect={setSelectedFile} showFullPath />
          ) : (
            <WidgetNoData message="No rule matches this search." />
          )}
        </Card>
      ) : (
        <>
          <Breadcrumbs
            elements={[
              { label: "Sigma", onClick: () => openFolder("") },
              ...segments.map((seg, i) => ({ label: seg, onClick: () => openFolder(segments.slice(0, i + 1).join("/")) })),
            ]}
          />

          {browse.isLoading ? (
            <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))" }}>
              {Array.from({ length: 6 }, (_, i) => (
                <Skeleton key={i} variant="rounded" height={folder ? 56 : 110} />
              ))}
            </Box>
          ) : (
            <>
              {!!browse.data?.folders.length && (
                <Box
                  sx={{
                    display: "grid",
                    gap: 2,
                    gridTemplateColumns: folder ? "repeat(auto-fill, minmax(240px, 1fr))" : "repeat(auto-fill, minmax(280px, 1fr))",
                  }}
                >
                  {browse.data.folders.map((name) => {
                    const meta = !folder ? CATEGORY_META[name] : undefined
                    const Icon = meta?.icon ?? FolderOutlined
                    const color = meta ? theme.palette.chart[meta.slot] : theme.palette.primary.main
                    const count = !folder ? counts.get(name) : undefined
                    const path = folder ? `${folder}/${name}` : name
                    return (
                      <Card key={name} onClick={() => openFolder(path)} padding="medium" aria-label={name}>
                        <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
                          <Box
                            sx={{
                              width: 36,
                              height: 36,
                              flexShrink: 0,
                              borderRadius: 1,
                              display: "flex",
                              alignItems: "center",
                              justifyContent: "center",
                              color,
                              backgroundColor: alpha(color, 0.14),
                            }}
                          >
                            <Icon sx={{ fontSize: 19 }} />
                          </Box>
                          <Box sx={{ minWidth: 0, flex: 1 }}>
                            <Typography noWrap sx={{ fontWeight: 600, textTransform: meta ? "capitalize" : "none" }}>
                              {name}
                            </Typography>
                            {meta && (
                              <Typography variant="body2" color="text.secondary" noWrap>
                                {meta.description}
                              </Typography>
                            )}
                          </Box>
                          {count !== undefined && (
                            <Typography sx={{ fontWeight: 600, fontVariantNumeric: "tabular-nums" }}>{numberFormat.format(count)}</Typography>
                          )}
                        </Stack>
                        {count !== undefined && (
                          <Box sx={{ mt: 1.5, height: 4, borderRadius: 2, backgroundColor: alpha(color, 0.15), overflow: "hidden" }}>
                            <Box sx={{ height: "100%", width: `${(count / maxCount) * 100}%`, backgroundColor: color, borderRadius: 2 }} />
                          </Box>
                        )}
                      </Card>
                    )
                  })}
                </Box>
              )}

              {!!browse.data?.files.length && (
                <Card
                  title={`Rules (${files.length})`}
                  padding="small"
                  action={<SearchInput value={fileFilter} onChange={setFileFilter} placeholder="Filter files" sx={{ width: 240 }} />}
                  titleSx={{ minHeight: 40 }}
                >
                  {files.length ? <FileList paths={files} onSelect={setSelectedFile} /> : <WidgetNoData message="No file matches this filter." />}
                </Card>
              )}

              {!browse.data?.folders.length && !browse.data?.files.length && (
                <Card>
                  <WidgetNoData icon={<RuleOutlined sx={{ fontSize: 28, opacity: 0.6 }} />} message="Empty folder." />
                </Card>
              )}
            </>
          )}
        </>
      )}

      <RuleDrawer path={selectedFile} onClose={() => setSelectedFile(null)} />
    </PageContainer>
  )
}
