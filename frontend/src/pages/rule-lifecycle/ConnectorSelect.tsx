import { MenuItem, TextField } from "@mui/material"
import { CONNECTOR_TYPES } from "./connectorMeta"
import { ConnectorLabel } from "./ConnectorLabel"

interface ConnectorSelectProps<T extends string> {
  value: T
  onChange: (value: T) => void
  label?: string
  allOption?: string
}

export function ConnectorSelect<T extends string>({ value, onChange, label = "Connector", allOption }: ConnectorSelectProps<T>) {
  return (
    <TextField select label={label} value={value} onChange={(e) => onChange(e.target.value as T)} sx={{ minWidth: 200 }}>
      {allOption && <MenuItem value="all">{allOption}</MenuItem>}
      {CONNECTOR_TYPES.map((type) => (
        <MenuItem key={type} value={type}>
          <ConnectorLabel type={type} />
        </MenuItem>
      ))}
    </TextField>
  )
}
