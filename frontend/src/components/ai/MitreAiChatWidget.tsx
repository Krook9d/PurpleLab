import { useEffect, useRef, useState, type FormEvent, type PointerEvent } from "react"
import { alpha, Box, Fab, Grow, IconButton, Paper, Stack, TextField, Tooltip, Typography, useTheme } from "@mui/material"
import SmartToyOutlined from "@mui/icons-material/SmartToyOutlined"
import SendOutlined from "@mui/icons-material/SendOutlined"
import CloseOutlined from "@mui/icons-material/CloseOutlined"
import { Markdown } from "@/components/common/Markdown"
import { Loader } from "@/components/common/Loader"
import { TechniqueIdChip } from "@/components/common/TechniqueIdChip"
import { useMitreAiChat } from "@/hooks/useMitreAi"
import type { ChatMessage } from "@/lib/types"

const DEFAULT_POSITION = { x: 24, y: 24 } // distance from the bottom-right corner
const TECHNIQUE_ID = /(T\d{4}(?:\.\d{3})?)/g

type LocalMessage = ChatMessage & { related_techniques?: string[] }

export function MitreAiChatWidget({ onTechniqueClick }: { onTechniqueClick: (id: string) => void }) {
  const theme = useTheme()
  const [open, setOpen] = useState(false)
  const [messages, setMessages] = useState<LocalMessage[]>([])
  const [input, setInput] = useState("")
  const [position, setPosition] = useState(DEFAULT_POSITION)
  const chat = useMitreAiChat()
  const dragState = useRef<{ startX: number; startY: number; origin: typeof DEFAULT_POSITION } | null>(null)
  const scrollRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: "smooth" })
  }, [messages, chat.isPending])

  function handleDragStart(e: PointerEvent) {
    dragState.current = { startX: e.clientX, startY: e.clientY, origin: position }
    e.currentTarget.setPointerCapture(e.pointerId)
  }

  function handleDragMove(e: PointerEvent) {
    if (!dragState.current) return
    const dx = e.clientX - dragState.current.startX
    const dy = e.clientY - dragState.current.startY
    setPosition({
      x: Math.max(4, dragState.current.origin.x - dx),
      y: Math.max(4, dragState.current.origin.y - dy),
    })
  }

  function handleSend(e: FormEvent) {
    e.preventDefault()
    if (!input.trim() || chat.isPending) return
    const nextMessages: LocalMessage[] = [...messages, { role: "user", content: input }]
    setMessages(nextMessages)
    setInput("")
    chat.mutate(
      nextMessages.map(({ role, content }) => ({ role, content })),
      { onSuccess: (data) => setMessages((prev) => [...prev, { ...data.message, related_techniques: data.related_techniques }]) },
    )
  }

  // Raw technique IDs (T1234 / T1234.001) in answers become clickable chips.
  const renderTechniqueLinks = (text: string) =>
    text.split(TECHNIQUE_ID).map((part, i) =>
      /^T\d{4}(?:\.\d{3})?$/.test(part) ? <TechniqueIdChip key={i} id={part} onClick={() => onTechniqueClick(part)} /> : part,
    )

  return (
    <>
      <Grow in={!open} unmountOnExit>
        <Tooltip title="MITRE assistant" placement="left">
          <Fab
            aria-label="Open MITRE assistant"
            onClick={() => setOpen(true)}
            sx={{
              position: "fixed",
              right: 24,
              bottom: 24,
              zIndex: theme.zIndex.speedDial,
              color: "#ffffff",
              background: `linear-gradient(135deg, ${theme.palette.brand.from}, ${theme.palette.brand.to})`,
              boxShadow: `0 10px 30px -8px ${alpha(theme.palette.brand.to, 0.7)}`,
              transition: "transform 150ms ease",
              "&:hover": { transform: "scale(1.06)" },
            }}
          >
            <SmartToyOutlined />
          </Fab>
        </Tooltip>
      </Grow>

      <Grow in={open} unmountOnExit style={{ transformOrigin: "100% 100%" }}>
        <Paper
          elevation={8}
          sx={{
            position: "fixed",
            right: position.x,
            bottom: position.y,
            zIndex: theme.zIndex.speedDial,
            width: 400,
            height: 540,
            minWidth: 320,
            minHeight: 340,
            resize: "both",
            overflow: "hidden",
            display: "flex",
            flexDirection: "column",
            borderRadius: 1,
            border: `1px solid ${theme.palette.border.primary}`,
            backgroundColor: theme.palette.background.drawer,
            backgroundImage: "none",
          }}
        >
          <Stack
            direction="row"
            onPointerDown={handleDragStart}
            onPointerMove={handleDragMove}
            onPointerUp={() => (dragState.current = null)}
            sx={{
              alignItems: "center",
              gap: 1,
              px: 2,
              py: 1.25,
              cursor: "grab",
              userSelect: "none",
              "&:active": { cursor: "grabbing" },
              borderBottom: `1px solid ${theme.palette.divider}`,
              background: `linear-gradient(90deg, ${alpha(theme.palette.brand.from, 0.18)}, transparent)`,
            }}
          >
            <SmartToyOutlined sx={{ fontSize: 18, color: "primary.main" }} />
            <Typography sx={{ fontWeight: 600, flex: 1 }}>MITRE assistant</Typography>
            <IconButton size="small" aria-label="Close" onPointerDown={(e) => e.stopPropagation()} onClick={() => setOpen(false)}>
              <CloseOutlined fontSize="small" />
            </IconButton>
          </Stack>

          <Box ref={scrollRef} sx={{ flex: 1, overflowY: "auto", px: 2, py: 1.5, display: "flex", flexDirection: "column", gap: 1.25 }}>
            {!messages.length && (
              <Typography variant="body2" color="text.secondary">
                Describe an attack scenario and I'll suggest relevant MITRE ATT&CK techniques.
              </Typography>
            )}
            {messages.map((message, i) => {
              const fromUser = message.role === "user"
              return (
                <Box
                  key={i}
                  sx={{
                    alignSelf: fromUser ? "flex-end" : "flex-start",
                    maxWidth: "90%",
                    px: 1.5,
                    py: 1,
                    borderRadius: 1,
                    fontSize: 13,
                    backgroundColor: fromUser ? alpha(theme.palette.primary.main, 0.18) : theme.palette.background.paper,
                    border: `1px solid ${fromUser ? alpha(theme.palette.primary.main, 0.3) : theme.palette.divider}`,
                  }}
                >
                  {fromUser ? (
                    <Typography sx={{ fontSize: 13, whiteSpace: "pre-wrap" }}>{message.content}</Typography>
                  ) : (
                    <Markdown renderText={renderTechniqueLinks} sx={{ fontSize: 13 }}>
                      {message.content}
                    </Markdown>
                  )}
                  {!!message.related_techniques?.length && (
                    <Stack direction="row" useFlexGap sx={{ gap: 0.5, flexWrap: "wrap", mt: 1 }}>
                      {message.related_techniques.map((id) => (
                        <TechniqueIdChip key={id} id={id} onClick={() => onTechniqueClick(id)} />
                      ))}
                    </Stack>
                  )}
                </Box>
              )
            })}
            {chat.isPending && <Loader variant="inline" label="The assistant is thinking" />}
          </Box>

          <Stack
            component="form"
            direction="row"
            onSubmit={handleSend}
            sx={{ gap: 1, alignItems: "center", p: 1.5, borderTop: `1px solid ${theme.palette.divider}` }}
          >
            <TextField
              variant="outlined"
              size="small"
              fullWidth
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Describe a scenario…"
            />
            <IconButton type="submit" color="primary" aria-label="Send" disabled={chat.isPending || !input.trim()}>
              <SendOutlined fontSize="small" />
            </IconButton>
          </Stack>
        </Paper>
      </Grow>
    </>
  )
}
