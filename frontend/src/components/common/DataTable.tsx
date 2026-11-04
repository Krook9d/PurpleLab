import { useMemo, useState, type ReactNode } from "react"
import {
  alpha,
  keyframes,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TableSortLabel,
  Typography,
  type SxProps,
  type Theme,
} from "@mui/material"

export interface DataTableColumn<T> {
  id: string
  label: ReactNode
  render: (row: T) => ReactNode
  // Enables sorting on this column.
  sortValue?: (row: T) => string | number | null | undefined
  align?: "left" | "right" | "center"
  width?: number | string
  mono?: boolean
}

interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  rows: T[] | undefined
  getRowId: (row: T) => string | number
  loading?: boolean
  emptyMessage?: ReactNode
  onRowClick?: (row: T) => void
  maxHeight?: number | string
  defaultSort?: { columnId: string; direction: "asc" | "desc" }
  sx?: SxProps<Theme>
}

const rowEnter = keyframes`
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: none; }
`

function compare(a: string | number | null | undefined, b: string | number | null | undefined): number {
  if (a == null && b == null) return 0
  if (a == null) return 1
  if (b == null) return -1
  if (typeof a === "number" && typeof b === "number") return a - b
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: "base" })
}

// Generic list table: typed columns, client-side sorting, sticky header,
// hover rows, skeleton loading and empty state.
export function DataTable<T>({
  columns,
  rows,
  getRowId,
  loading = false,
  emptyMessage = "No data",
  onRowClick,
  maxHeight,
  defaultSort,
  sx,
}: DataTableProps<T>) {
  const [sort, setSort] = useState(defaultSort)

  const sortedRows = useMemo(() => {
    if (!rows || !sort) return rows
    const column = columns.find((c) => c.id === sort.columnId)
    if (!column?.sortValue) return rows
    const getValue = column.sortValue
    const sorted = [...rows].sort((a, b) => compare(getValue(a), getValue(b)))
    return sort.direction === "desc" ? sorted.reverse() : sorted
  }, [rows, sort, columns])

  const handleSort = (columnId: string) => {
    setSort((prev) =>
      prev?.columnId === columnId
        ? { columnId, direction: prev.direction === "asc" ? "desc" : "asc" }
        : { columnId, direction: "asc" },
    )
  }

  return (
    <TableContainer sx={[{ maxHeight }, ...(Array.isArray(sx) ? sx : [sx])]}>
      <Table size="small" stickyHeader={Boolean(maxHeight)}>
        <TableHead>
          <TableRow>
            {columns.map((column) => (
              <TableCell
                key={column.id}
                align={column.align}
                sx={(theme) => ({
                  width: column.width,
                  whiteSpace: "nowrap",
                  fontSize: 12,
                  fontWeight: 600,
                  color: "text.light",
                  textTransform: "uppercase",
                  letterSpacing: "0.04em",
                  backgroundColor: maxHeight ? theme.palette.background.paper : "transparent",
                })}
                sortDirection={sort?.columnId === column.id ? sort.direction : false}
              >
                {column.sortValue ? (
                  <TableSortLabel
                    active={sort?.columnId === column.id}
                    direction={sort?.columnId === column.id ? sort.direction : "asc"}
                    onClick={() => handleSort(column.id)}
                  >
                    {column.label}
                  </TableSortLabel>
                ) : (
                  column.label
                )}
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {loading &&
            Array.from({ length: 5 }, (_, i) => (
              <TableRow key={`skeleton-${i}`}>
                {columns.map((column) => (
                  <TableCell key={column.id}>
                    <Skeleton variant="text" />
                  </TableCell>
                ))}
              </TableRow>
            ))}
          {!loading && sortedRows?.length === 0 && (
            <TableRow>
              <TableCell colSpan={columns.length} sx={{ py: 5 }}>
                <Typography variant="body2" color="text.secondary" sx={{ textAlign: "center" }}>
                  {emptyMessage}
                </Typography>
              </TableCell>
            </TableRow>
          )}
          {!loading &&
            sortedRows?.map((row, index) => (
              <TableRow
                key={getRowId(row)}
                hover
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                sx={(theme) => ({
                  cursor: onRowClick ? "pointer" : undefined,
                  animation: `${rowEnter} 240ms ease both`,
                  animationDelay: `${Math.min(index, 15) * 20}ms`,
                  "@media (prefers-reduced-motion: reduce)": { animation: "none" },
                  "&.MuiTableRow-hover:hover": { backgroundColor: alpha(theme.palette.primary.main, 0.06) },
                })}
              >
                {columns.map((column) => (
                  <TableCell
                    key={column.id}
                    align={column.align}
                    sx={(theme) => ({ fontFamily: column.mono ? theme.fontFamilyMono : undefined, fontSize: column.mono ? 12.5 : undefined })}
                  >
                    {column.render(row)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
        </TableBody>
      </Table>
    </TableContainer>
  )
}
