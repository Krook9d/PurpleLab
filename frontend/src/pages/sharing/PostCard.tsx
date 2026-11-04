import { useState } from "react"
import { Box, IconButton, Stack, Tooltip, Typography } from "@mui/material"
import DeleteOutlined from "@mui/icons-material/DeleteOutlined"
import CodeOutlined from "@mui/icons-material/CodeOutlined"
import NotesOutlined from "@mui/icons-material/NotesOutlined"
import { Card } from "@/components/common/Card"
import { CodeBlock } from "@/components/common/CodeBlock"
import { ConfirmDialog } from "@/components/common/ConfirmDialog"
import { useDeletePost } from "@/hooks/useSharing"
import { formatDateTime } from "@/lib/format"
import type { SharingPost } from "@/lib/types"

export function PostCard({ post, canDelete }: { post: SharingPost; canDelete: boolean }) {
  const deletePost = useDeletePost()
  const [confirmOpen, setConfirmOpen] = useState(false)

  return (
    <Card padding="medium" hoverable fullHeight={false}>
      <Stack spacing={1.25}>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <Typography sx={{ fontWeight: 600 }}>
            {post.author_first_name} {post.author_last_name}
          </Typography>
          <Typography variant="body2" sx={{ color: "text.tertiary", fontSize: 12, flex: 1 }}>
            {formatDateTime(post.created_at)}
          </Typography>
          <Stack
            direction="row"
            sx={{ alignItems: "center", gap: 0.5, fontSize: 12, color: post.language ? "primary.main" : "text.secondary" }}
          >
            {post.language ? <CodeOutlined sx={{ fontSize: 15 }} /> : <NotesOutlined sx={{ fontSize: 15 }} />}
            <Box component="span" sx={(theme) => ({ fontFamily: post.language ? theme.fontFamilyMono : undefined })}>
              {post.language ?? "text"}
            </Box>
          </Stack>
          {canDelete && (
            <Tooltip title="Delete">
              <IconButton size="small" aria-label="Delete post" onClick={() => setConfirmOpen(true)} sx={{ "&:hover": { color: "error.main" } }}>
                <DeleteOutlined fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
        </Stack>
        {post.title && <Typography sx={{ fontWeight: 600, fontSize: 15 }}>{post.title}</Typography>}
        {post.description && (
          <Typography variant="body2" color="text.secondary">
            {post.description}
          </Typography>
        )}
        {post.language ? (
          <CodeBlock code={post.content} language={post.language} maxHeight={360} />
        ) : (
          <Typography sx={{ whiteSpace: "pre-wrap", wordBreak: "break-word", color: "text.primary" }}>{post.content}</Typography>
        )}
      </Stack>
      <ConfirmDialog
        open={confirmOpen}
        title="Delete this post?"
        message="This action cannot be undone."
        confirmLabel="Delete"
        destructive
        loading={deletePost.isPending}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => deletePost.mutate(post.id, { onSuccess: () => setConfirmOpen(false) })}
      />
    </Card>
  )
}
