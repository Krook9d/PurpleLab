import { useMemo, useState } from "react"
import { Box, keyframes, Skeleton, Stack, ToggleButton, ToggleButtonGroup, Typography } from "@mui/material"
import ForumOutlined from "@mui/icons-material/ForumOutlined"
import { PageContainer } from "@/components/layout/PageContainer"
import { Card } from "@/components/common/Card"
import { UserAvatar } from "@/components/common/UserAvatar"
import { WidgetNoData } from "@/components/dashboard/WidgetNoData"
import { useCurrentUser } from "@/hooks/useAuth"
import { useSharingPosts } from "@/hooks/useSharing"
import { PostComposer } from "./sharing/PostComposer"
import { PostCard } from "./sharing/PostCard"

type Filter = "all" | "text" | "code"

const enter = keyframes`
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: none; }
`

export function SharingPage() {
  const { data: posts, isLoading } = useSharingPosts()
  const { data: user } = useCurrentUser()
  const [filter, setFilter] = useState<Filter>("all")

  const filtered = useMemo(() => {
    if (!posts) return []
    if (filter === "code") return posts.filter((p) => !!p.language)
    if (filter === "text") return posts.filter((p) => !p.language)
    return posts
  }, [posts, filter])

  return (
    <PageContainer>
      <Box sx={{ display: "grid", gap: 3, alignItems: "start", gridTemplateColumns: { xs: "1fr", lg: "400px minmax(0, 1fr)" } }}>
        <Box sx={{ position: { lg: "sticky" }, top: { lg: 88 } }}>
          <PostComposer />
        </Box>

        <Box sx={{ minWidth: 0 }}>
          <Stack direction="row" sx={{ alignItems: "center", gap: 2, mb: 2 }}>
            <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
              Shared feed of notes and snippets between analysts
              {posts ? ` · ${filtered.length} post${filtered.length > 1 ? "s" : ""}` : ""}
            </Typography>
            <ToggleButtonGroup value={filter} exclusive onChange={(_, v: Filter | null) => v && setFilter(v)} aria-label="Filter posts">
              <ToggleButton value="all" sx={{ px: 1.5 }}>All</ToggleButton>
              <ToggleButton value="text" sx={{ px: 1.5 }}>Text</ToggleButton>
              <ToggleButton value="code" sx={{ px: 1.5 }}>Code</ToggleButton>
            </ToggleButtonGroup>
          </Stack>

          {isLoading ? (
            <Stack spacing={2}>
              {[0, 1, 2].map((i) => (
                <Skeleton key={i} variant="rounded" height={120} />
              ))}
            </Stack>
          ) : !filtered.length ? (
            <Card>
              <WidgetNoData
                icon={<ForumOutlined sx={{ fontSize: 32, color: "primary.main", opacity: 0.7 }} />}
                message={posts?.length ? "No post of this type." : "Nothing shared yet. Start the conversation!"}
              />
            </Card>
          ) : (
            // Timeline: avatar column with a connecting line, post card beside it.
            <Stack spacing={2}>
              {filtered.map((post, index) => (
                <Stack
                  key={post.id}
                  direction="row"
                  sx={{
                    gap: 1.5,
                    position: "relative",
                    animation: `${enter} 260ms ease both`,
                    animationDelay: `${Math.min(index, 10) * 40}ms`,
                    "@media (prefers-reduced-motion: reduce)": { animation: "none" },
                  }}
                >
                  <Box sx={{ position: "relative", display: "flex", justifyContent: "center", width: 32, flexShrink: 0 }}>
                    <Box sx={{ position: "relative", zIndex: 1, pt: 1.5 }}>
                      <UserAvatar firstName={post.author_first_name} lastName={post.author_last_name} size={32} />
                    </Box>
                    {index < filtered.length - 1 && (
                      <Box
                        aria-hidden
                        sx={(theme) => ({
                          position: "absolute",
                          top: 48,
                          bottom: -16,
                          width: "1px",
                          backgroundColor: theme.palette.border.primary,
                        })}
                      />
                    )}
                  </Box>
                  <Box sx={{ flex: 1, minWidth: 0 }}>
                    <PostCard post={post} canDelete={user?.id === post.author_id || user?.role === "admin"} />
                  </Box>
                </Stack>
              ))}
            </Stack>
          )}
        </Box>
      </Box>
    </PageContainer>
  )
}
