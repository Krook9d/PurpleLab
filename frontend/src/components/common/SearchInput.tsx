import { IconButton, InputAdornment, TextField, type SxProps, type Theme } from "@mui/material"
import SearchOutlined from "@mui/icons-material/SearchOutlined"
import CloseOutlined from "@mui/icons-material/CloseOutlined"

interface SearchInputProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  autoFocus?: boolean
  sx?: SxProps<Theme>
}

// Compact outlined search field with a clear button.
export function SearchInput({ value, onChange, placeholder = "Search…", autoFocus, sx }: SearchInputProps) {
  return (
    <TextField
      variant="outlined"
      size="small"
      value={value}
      autoFocus={autoFocus}
      placeholder={placeholder}
      onChange={(e) => onChange(e.target.value)}
      sx={[
        (theme) => ({
          "& .MuiOutlinedInput-root": {
            backgroundColor: theme.palette.background.paper,
            transition: "box-shadow 150ms ease",
            "&.Mui-focused": { boxShadow: `0 0 0 3px ${theme.palette.primary.main}26` },
          },
        }),
        ...(Array.isArray(sx) ? sx : [sx]),
      ]}
      slotProps={{
        input: {
          startAdornment: (
            <InputAdornment position="start">
              <SearchOutlined fontSize="small" />
            </InputAdornment>
          ),
          endAdornment: value ? (
            <InputAdornment position="end">
              <IconButton size="small" aria-label="Clear search" onClick={() => onChange("")} edge="end">
                <CloseOutlined fontSize="small" />
              </IconButton>
            </InputAdornment>
          ) : undefined,
        },
      }}
    />
  )
}
