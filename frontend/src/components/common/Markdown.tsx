import { Children, type ReactNode } from "react"
import { Box, type SxProps, type Theme } from "@mui/material"
import ReactMarkdown from "react-markdown"
import remarkGfm from "remark-gfm"

interface MarkdownProps {
  children: string
  // Optional transform applied to plain-text runs of paragraphs and list items
  // (e.g. turning technique IDs into clickable chips).
  renderText?: (text: string) => ReactNode
  sx?: SxProps<Theme>
}

function transform(children: ReactNode, renderText?: (text: string) => ReactNode): ReactNode {
  if (!renderText) return children
  return Children.map(children, (child) => (typeof child === "string" ? renderText(child) : child))
}

// Markdown rendered with theme typography (links, inline code, lists, tables).
export function Markdown({ children, renderText, sx }: MarkdownProps) {
  return (
    <Box
      sx={[
        (theme) => ({
          fontSize: "0.9rem",
          lineHeight: 1.6,
          color: theme.palette.text.primary,
          "& p": { m: 0, mb: 1.25 },
          "& p:last-child": { mb: 0 },
          "& a": { color: theme.palette.primary.main, textDecoration: "underline", textUnderlineOffset: 2 },
          "& ul, & ol": { pl: 2.5, my: 1 },
          "& li": { mb: 0.5 },
          "& strong": { fontWeight: 600 },
          "& table": { borderCollapse: "collapse", my: 1 },
          "& th, & td": { border: `1px solid ${theme.palette.divider}`, px: 1, py: 0.5 },
          "& pre": { p: 1.5, overflowX: "auto" },
        }),
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
    >
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        components={{
          p: ({ children: c }) => <p>{transform(c, renderText)}</p>,
          li: ({ children: c }) => <li>{transform(c, renderText)}</li>,
          a: ({ href, children: c }) => (
            <a href={href} target="_blank" rel="noreferrer">
              {c}
            </a>
          ),
        }}
      >
        {children}
      </ReactMarkdown>
    </Box>
  )
}
