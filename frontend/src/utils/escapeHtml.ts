const HTML_ESCAPES: Record<string, string> = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }

export function escapeHtml(value: unknown): string {
  const text = Array.isArray(value) ? value.join(" ") : String(value ?? "")
  return text.replace(/[&<>"']/g, (c) => HTML_ESCAPES[c] ?? c)
}
