import { useState } from "react"
import { Button, IconButton, Stack, Tooltip, Typography } from "@mui/material"
import AddOutlined from "@mui/icons-material/AddOutlined"
import EditOutlined from "@mui/icons-material/EditOutlined"
import DeleteOutlined from "@mui/icons-material/DeleteOutlined"
import CodeOutlined from "@mui/icons-material/CodeOutlined"
import { Card } from "@/components/common/Card"
import { ConfirmDialog } from "@/components/common/ConfirmDialog"
import { DataTable, type DataTableColumn } from "@/components/common/DataTable"
import { useDeleteRuleLifecyclePayload, useRuleLifecyclePayloads } from "@/hooks/useRuleLifecyclePayloads"
import { formatDateTime } from "@/lib/format"
import type { RuleLifecyclePayloadItem } from "@/lib/types"
import { PayloadEditorDrawer } from "./PayloadEditorDrawer"

export function PayloadsPanel() {
  const payloads = useRuleLifecyclePayloads()
  const deletePayload = useDeleteRuleLifecyclePayload()
  const [editing, setEditing] = useState<RuleLifecyclePayloadItem | "new" | null>(null)
  const [toDelete, setToDelete] = useState<RuleLifecyclePayloadItem | null>(null)

  const columns: DataTableColumn<RuleLifecyclePayloadItem>[] = [
    {
      id: "name",
      label: "Name",
      render: (p) => (
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <CodeOutlined sx={{ fontSize: 16, color: "primary.main" }} />
          <Typography sx={{ fontWeight: 500, fontSize: 13 }}>{p.name}</Typography>
        </Stack>
      ),
      sortValue: (p) => p.name,
    },
    { id: "description", label: "Description", render: (p) => <Typography variant="body2" color="text.secondary">{p.description || "—"}</Typography> },
    { id: "updated", label: "Updated", width: 180, render: (p) => formatDateTime(p.updated_at), sortValue: (p) => p.updated_at },
    {
      id: "actions",
      label: "",
      width: 96,
      align: "right",
      render: (p) => (
        <Stack direction="row" sx={{ justifyContent: "flex-end" }}>
          <Tooltip title="Edit">
            <IconButton size="small" aria-label={`Edit ${p.name}`} onClick={() => setEditing(p)}>
              <EditOutlined fontSize="small" />
            </IconButton>
          </Tooltip>
          <Tooltip title="Delete">
            <IconButton size="small" aria-label={`Delete ${p.name}`} onClick={() => setToDelete(p)} sx={{ "&:hover": { color: "error.main" } }}>
              <DeleteOutlined fontSize="small" />
            </IconButton>
          </Tooltip>
        </Stack>
      ),
    },
  ]

  return (
    <>
      <Card
        title="Rule lifecycle payloads"
        padding="small"
        fullHeight={false}
        action={
          <Button size="small" variant="contained" startIcon={<AddOutlined />} onClick={() => setEditing("new")}>
            New payload
          </Button>
        }
        titleSx={{ minHeight: 32 }}
      >
        <DataTable
          columns={columns}
          rows={payloads.data}
          loading={payloads.isLoading}
          getRowId={(p) => p.id}
          onRowClick={setEditing}
          emptyMessage="No payload yet. Create one, then map it to synced rules above."
        />
      </Card>
      <PayloadEditorDrawer editing={editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={!!toDelete}
        title="Delete this payload?"
        message={`"${toDelete?.name ?? ""}" will be deleted and unmapped from its rules.`}
        confirmLabel="Delete"
        destructive
        loading={deletePayload.isPending}
        onClose={() => setToDelete(null)}
        onConfirm={() => toDelete && deletePayload.mutate(toDelete.id, { onSuccess: () => setToDelete(null) })}
      />
    </>
  )
}
