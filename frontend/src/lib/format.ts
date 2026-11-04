const dateFormat = new Intl.DateTimeFormat("en-US", { dateStyle: "medium" })
const dateTimeFormat = new Intl.DateTimeFormat("en-US", { dateStyle: "medium", timeStyle: "short" })

function toDate(value: string | null | undefined): Date | null {
  if (!value) return null
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

export const formatDate = (value: string | null | undefined) => {
  const d = toDate(value)
  return d ? dateFormat.format(d) : "—"
}

export const formatDateTime = (value: string | null | undefined) => {
  const d = toDate(value)
  return d ? dateTimeFormat.format(d) : "—"
}

const units = ["B", "KB", "MB", "GB"]
export function formatBytes(bytes: number): string {
  let value = bytes
  let i = 0
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024
    i++
  }
  return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}
