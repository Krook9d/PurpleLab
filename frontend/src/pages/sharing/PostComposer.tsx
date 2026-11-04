import { useState, type FormEvent } from "react"
import { Box, Button, MenuItem, Stack, Tab, Tabs, TextField, Typography } from "@mui/material"
import NotesOutlined from "@mui/icons-material/NotesOutlined"
import CodeOutlined from "@mui/icons-material/CodeOutlined"
import SendOutlined from "@mui/icons-material/SendOutlined"
import { Card } from "@/components/common/Card"
import { useCreatePost } from "@/hooks/useSharing"

const LANGUAGES = ["powershell", "python", "bash", "sql", "javascript", "yaml", "json"]
const MAX_LENGTH = 1000

export function PostComposer() {
  const createPost = useCreatePost()
  const [postType, setPostType] = useState<"text" | "code">("text")
  const [content, setContent] = useState("")
  const [title, setTitle] = useState("")
  const [description, setDescription] = useState("")
  const [language, setLanguage] = useState("powershell")
  const isCode = postType === "code"

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    createPost.mutate(
      isCode ? { content, title: title || undefined, description: description || undefined, language } : { content },
      {
        onSuccess: () => {
          setContent("")
          setTitle("")
          setDescription("")
        },
      },
    )
  }

  return (
    <Card title="New post" fullHeight={false}>
      <Tabs value={postType} onChange={(_, v: "text" | "code") => setPostType(v)} sx={{ mb: 2, minHeight: 36 }}>
        <Tab value="text" icon={<NotesOutlined fontSize="small" />} iconPosition="start" label="Text" sx={{ minHeight: 36, textTransform: "none" }} />
        <Tab value="code" icon={<CodeOutlined fontSize="small" />} iconPosition="start" label="Code" sx={{ minHeight: 36, textTransform: "none" }} />
      </Tabs>
      <Stack component="form" onSubmit={handleSubmit} spacing={2}>
        {isCode && (
          <>
            <TextField label="Title (optional)" value={title} onChange={(e) => setTitle(e.target.value)} fullWidth />
            <TextField label="Description (optional)" value={description} onChange={(e) => setDescription(e.target.value)} fullWidth />
            <TextField select label="Language" value={language} onChange={(e) => setLanguage(e.target.value)} fullWidth>
              {LANGUAGES.map((lang) => (
                <MenuItem key={lang} value={lang}>
                  {lang}
                </MenuItem>
              ))}
            </TextField>
          </>
        )}
        <TextField
          label={isCode ? "Code" : "Message"}
          placeholder={isCode ? "Paste your code here…" : "Share something with the team…"}
          value={content}
          onChange={(e) => setContent(e.target.value)}
          required
          fullWidth
          multiline
          minRows={isCode ? 7 : 4}
          slotProps={{
            htmlInput: { maxLength: MAX_LENGTH, spellCheck: !isCode },
            input: { sx: (theme) => (isCode ? { fontFamily: theme.fontFamilyMono, fontSize: 13 } : {}) },
          }}
        />
        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <Typography variant="body2" sx={{ color: content.length > MAX_LENGTH * 0.9 ? "warning.main" : "text.tertiary", fontSize: 12 }}>
            {content.length} / {MAX_LENGTH}
          </Typography>
          <Box>
            <Button type="submit" variant="contained" startIcon={<SendOutlined />} loading={createPost.isPending} loadingPosition="start">
              Publish
            </Button>
          </Box>
        </Stack>
      </Stack>
    </Card>
  )
}
