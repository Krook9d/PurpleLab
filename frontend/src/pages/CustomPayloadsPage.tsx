import { useMemo, useState } from "react"
import { alpha, Box, Button, Skeleton, Stack, Typography } from "@mui/material"
import AddOutlined from "@mui/icons-material/AddOutlined"
import CodeOutlined from "@mui/icons-material/CodeOutlined"
import { PageContainer } from "@/components/layout/PageContainer"
import { Card } from "@/components/common/Card"
import { DataTable, type DataTableColumn } from "@/components/common/DataTable"
import { SearchInput } from "@/components/common/SearchInput"
import { UserAvatar } from "@/components/common/UserAvatar"
import { ViewToggle } from "@/components/common/ViewToggle"
import { VIEW_MODES } from "@/components/common/viewMode"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { useCurrentUser } from "@/hooks/useAuth"
import { useCustomPayloads } from "@/hooks/useCustomPayloads"
import { useLocalStorageState } from "@/hooks/useLocalStorageState"
import { formatDate } from "@/lib/format"
import type { CustomPayload } from "@/lib/types"
import { PayloadCreateDialog } from "./custom-payloads/PayloadCreateDialog"
import { PayloadDrawer } from "./custom-payloads/PayloadDrawer"

const authorName = (p: CustomPayload) => `${p.author_first_name ?? ""} ${p.author_last_name ?? ""}`.trim()

function PayloadCard({ payload, onOpen }: { payload: CustomPayload; onOpen: () => void }) {
  const preview = payload.content.split("\n").slice(0, 3).join("\n")
  return (
    <Card onClick={onOpen} padding="medium" aria-label={payload.name}>
      <Stack spacing={1.5}>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1.25 }}>
          <Box
            sx={(theme) => ({
              width: 32,
              height: 32,
              flexShrink: 0,
              borderRadius: 1,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              color: theme.palette.primary.main,
              backgroundColor: alpha(theme.palette.primary.main, 0.12),
            })}
          >
            <CodeOutlined sx={{ fontSize: 18 }} />
          </Box>
          <Typography noWrap sx={{ fontWeight: 600, flex: 1, minWidth: 0 }}>
            {payload.name}
          </Typography>
        </Stack>
        <Box
          component="pre"
          sx={(theme) => ({
            m: 0,
            p: 1,
            height: 58,
            overflow: "hidden",
            fontSize: 11.5,
            lineHeight: 1.45,
            color: theme.palette.text.secondary,
            background: `${alpha(theme.palette.background.accent, 0.6)} !important`,
            maskImage: "linear-gradient(to bottom, black 55%, transparent)",
          })}
        >
          {preview}
        </Box>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <UserAvatar firstName={payload.author_first_name} lastName={payload.author_last_name} size={22} />
          <Typography variant="body2" color="text.secondary" noWrap sx={{ flex: 1 }}>
            {authorName(payload)}
          </Typography>
          <Typography variant="body2" sx={{ color: "text.tertiary", fontSize: 12 }}>
            {formatDate(payload.created_at)}
          </Typography>
        </Stack>
      </Stack>
    </Card>
  )
}

export function CustomPayloadsPage() {
  const { data: payloads, isLoading } = useCustomPayloads()
  const { data: user } = useCurrentUser()
  const [view, setView] = useLocalStorageState("purplelab_view_custom_payloads", "cards", VIEW_MODES)
  const [filter, setFilter] = useState("")
  const [createOpen, setCreateOpen] = useState(false)
  const [selected, setSelected] = useState<CustomPayload | null>(null)

  const visible = useMemo(() => {
    const q = filter.trim().toLowerCase()
    return (payloads ?? []).filter((p) => !q || p.name.toLowerCase().includes(q) || authorName(p).toLowerCase().includes(q))
  }, [payloads, filter])

  const columns: DataTableColumn<CustomPayload>[] = [
    {
      id: "name",
      label: "Name",
      render: (p) => (
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <CodeOutlined sx={{ fontSize: 16, color: "primary.main" }} />
          {p.name}
        </Stack>
      ),
      sortValue: (p) => p.name,
    },
    {
      id: "author",
      label: "Author",
      render: (p) => (
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <UserAvatar firstName={p.author_first_name} lastName={p.author_last_name} size={22} />
          {authorName(p)}
        </Stack>
      ),
      sortValue: authorName,
    },
    {
      id: "created",
      label: "Created",
      width: 160,
      render: (p) => formatDate(p.created_at),
      sortValue: (p) => p.created_at,
    },
  ]

  return (
    <PageContainer>
      <Stack direction="row" useFlexGap sx={{ alignItems: "center", gap: 1.5, flexWrap: "wrap" }}>
        <SearchInput value={filter} onChange={setFilter} placeholder="Filter payloads" sx={{ width: 300 }} />
        <ViewToggle value={view} onChange={setView} />
        <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
          Shared catalog of PowerShell scripts executable on the sandbox VM.
        </Typography>
        <Button variant="contained" startIcon={<AddOutlined />} onClick={() => setCreateOpen(true)}>
          New payload
        </Button>
      </Stack>

      {isLoading ? (
        <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))" }}>
          {Array.from({ length: 6 }, (_, i) => (
            <Skeleton key={i} variant="rounded" height={170} />
          ))}
        </Box>
      ) : !visible.length ? (
        <Card>
          <WidgetNoData
            icon={<CodeOutlined sx={{ fontSize: 32, color: "primary.main", opacity: 0.7 }} />}
            message={payloads?.length ? "No payload matches this filter." : "No payload yet. Create the first one!"}
          />
        </Card>
      ) : view === "cards" ? (
        <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))" }}>
          {visible.map((p) => (
            <PayloadCard key={p.id} payload={p} onOpen={() => setSelected(p)} />
          ))}
        </Box>
      ) : (
        <Card padding="small">
          <DataTable
            columns={columns}
            rows={visible}
            getRowId={(p) => p.id}
            onRowClick={setSelected}
            defaultSort={{ columnId: "created", direction: "desc" }}
          />
        </Card>
      )}

      <PayloadCreateDialog open={createOpen} onClose={() => setCreateOpen(false)} />
      <PayloadDrawer payload={selected} currentUserId={user?.id} onClose={() => setSelected(null)} />
    </PageContainer>
  )
}
