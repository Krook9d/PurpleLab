import { useState } from "react"
import { Box, Stack } from "@mui/material"
import SecurityOutlined from "@mui/icons-material/SecurityOutlined"
import { Card } from "@/components/common/Card"
import { SearchInput } from "@/components/common/SearchInput"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { useDebouncedValue } from "@/hooks/useDebouncedValue"
import { useSearchTechniques, useTechnique } from "@/hooks/useMitre"
import { TechniqueSearchList } from "./TechniqueSearchList"
import { TechniqueDetailCard } from "./TechniqueDetailCard"
import { AtomicTestsTable } from "./AtomicTestsTable"

export function SearchView({ selectedId, onSelect }: { selectedId: string | null; onSelect: (id: string) => void }) {
  const [query, setQuery] = useState("")
  const debouncedQuery = useDebouncedValue(query, 300)
  const search = useSearchTechniques(debouncedQuery)
  const technique = useTechnique(selectedId)

  return (
    <Box sx={{ display: "grid", gap: 2, alignItems: "start", gridTemplateColumns: { xs: "1fr", lg: "380px minmax(0, 1fr)" } }}>
      <Box sx={{ position: { lg: "sticky" }, top: { lg: 88 } }}>
        <Card padding="medium" fullHeight={false}>
          <SearchInput value={query} onChange={setQuery} placeholder="Search a technique (Phishing, T1566…)" autoFocus sx={{ width: "100%", mb: 1.5 }} />
          <Box sx={{ maxHeight: { lg: "calc(100vh - 300px)" }, overflowY: "auto", pr: 0.5 }}>
            <TechniqueSearchList
              query={debouncedQuery}
              results={search.data}
              loading={search.isLoading}
              selectedId={selectedId}
              onSelect={onSelect}
            />
          </Box>
        </Card>
      </Box>
      <Stack spacing={2} sx={{ minWidth: 0 }}>
        {selectedId ? (
          <>
            <TechniqueDetailCard technique={technique.data} loading={technique.isLoading} onSelect={onSelect} />
            <AtomicTestsTable techniqueId={selectedId} />
          </>
        ) : (
          <Card>
            <WidgetNoData
              icon={<SecurityOutlined sx={{ fontSize: 36, color: "primary.main", opacity: 0.7 }} />}
              message="Search for a technique to see its details, or ask the assistant to map an attack scenario."
            />
          </Card>
        )}
      </Stack>
    </Box>
  )
}
