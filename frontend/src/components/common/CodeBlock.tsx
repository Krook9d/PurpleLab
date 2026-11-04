import { useState } from "react"
import { alpha, Box, IconButton, Tooltip, useTheme, type SxProps, type Theme } from "@mui/material"
import ContentCopyOutlined from "@mui/icons-material/ContentCopyOutlined"
import CheckOutlined from "@mui/icons-material/CheckOutlined"
import { Prism as SyntaxHighlighter } from "react-syntax-highlighter"
import { oneDark, oneLight } from "react-syntax-highlighter/dist/esm/styles/prism"

interface CodeBlockProps {
  code: string
  language?: string
  showLineNumbers?: boolean
  copyable?: boolean
  maxHeight?: number | string
  sx?: SxProps<Theme>
}

// Syntax-highlighted code that follows the color mode, with a copy button.
export function CodeBlock({
  code,
  language = "text",
  showLineNumbers = false,
  copyable = true,
  maxHeight,
  sx,
}: CodeBlockProps) {
  const theme = useTheme()
  const [copied, setCopied] = useState(false)

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(code)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 1500)
    } catch {
      // Clipboard unavailable (non-secure context): nothing to do.
    }
  }

  return (
    <Box
      sx={[
        {
          position: "relative",
          borderRadius: 0.5,
          border: `1px solid ${theme.palette.divider}`,
          backgroundColor: theme.palette.mode === "dark" ? alpha("#000000", 0.25) : theme.palette.background.accent,
          overflow: "hidden",
          "&:hover .code-copy": { opacity: 1 },
        },
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
    >
      {copyable && (
        <Tooltip title={copied ? "Copied" : "Copy"}>
          <IconButton
            className="code-copy"
            size="small"
            onClick={handleCopy}
            aria-label="Copy code"
            sx={{
              position: "absolute",
              top: 6,
              right: 6,
              zIndex: 1,
              opacity: 0.6,
              transition: "opacity 150ms ease",
              backgroundColor: alpha(theme.palette.background.paper, 0.8),
              "&:hover": { backgroundColor: theme.palette.background.paper },
            }}
          >
            {copied ? <CheckOutlined sx={{ fontSize: 16 }} color="success" /> : <ContentCopyOutlined sx={{ fontSize: 16 }} />}
          </IconButton>
        </Tooltip>
      )}
      <SyntaxHighlighter
        language={language}
        style={theme.palette.mode === "dark" ? oneDark : oneLight}
        showLineNumbers={showLineNumbers}
        wrapLongLines
        customStyle={{
          margin: 0,
          padding: "14px 16px",
          background: "transparent",
          fontSize: 12.5,
          maxHeight,
          overflow: "auto",
        }}
        codeTagProps={{ style: { fontFamily: theme.fontFamilyMono, background: "transparent" } }}
      >
        {code}
      </SyntaxHighlighter>
    </Box>
  )
}
