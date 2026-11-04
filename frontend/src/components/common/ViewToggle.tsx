import { ToggleButton, ToggleButtonGroup } from "@mui/material"
import ViewModuleOutlined from "@mui/icons-material/ViewModuleOutlined"
import ViewListOutlined from "@mui/icons-material/ViewListOutlined"
import type { ViewMode } from "./viewMode"

// Cards / lines switch for list pages.
export function ViewToggle({ value, onChange }: { value: ViewMode; onChange: (v: ViewMode) => void }) {
  return (
    <ToggleButtonGroup value={value} exclusive onChange={(_, v: ViewMode | null) => v && onChange(v)} aria-label="View mode">
      <ToggleButton value="cards" aria-label="Cards view" title="Cards">
        <ViewModuleOutlined fontSize="small" />
      </ToggleButton>
      <ToggleButton value="lines" aria-label="Lines view" title="Lines">
        <ViewListOutlined fontSize="small" />
      </ToggleButton>
    </ToggleButtonGroup>
  )
}
