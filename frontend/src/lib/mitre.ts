const SMALL_WORDS = new Set(["and", "or", "of"])

// "command-and-control" -> "Command and Control" (ATT&CK display name).
export function tacticLabel(id: string): string {
  return id
    .trim()
    .split(/[-_\s]+/)
    .map((w, i) => (i > 0 && SMALL_WORDS.has(w.toLowerCase()) ? w.toLowerCase() : w.charAt(0).toUpperCase() + w.slice(1)))
    .join(" ")
}

// Comma-separated API fields (tactics, platforms, data sources) -> list.
export const splitList = (value: string | null | undefined): string[] =>
  value ? value.split(",").map((v) => v.trim()).filter(Boolean) : []

// ATT&CK descriptions embed raw <code> tags that markdown renders literally:
// turn them into markdown inline code.
export const mitreTextToMarkdown = (text: string): string =>
  text.replace(/<code>([\s\S]*?)<\/code>/gi, (_, code: string) => `\`${code.replace(/`/g, "'")}\``)
