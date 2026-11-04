import { Breadcrumbs as MuiBreadcrumbs, Link, Typography } from "@mui/material"
import NavigateNext from "@mui/icons-material/NavigateNext"

export interface BreadcrumbElement {
  label: string
  onClick?: () => void
}

// In-page breadcrumbs: every element but the last is clickable.
export function Breadcrumbs({ elements }: { elements: BreadcrumbElement[] }) {
  return (
    <MuiBreadcrumbs separator={<NavigateNext sx={{ fontSize: 14 }} />} aria-label="breadcrumb">
      {elements.map((el, i) => {
        const last = i === elements.length - 1
        if (last || !el.onClick) {
          return (
            <Typography key={`${el.label}-${i}`} variant="body2" color={last ? "text.primary" : "text.secondary"}>
              {el.label}
            </Typography>
          )
        }
        return (
          <Link
            key={`${el.label}-${i}`}
            component="button"
            variant="body2"
            underline="hover"
            color="text.secondary"
            onClick={el.onClick}
          >
            {el.label}
          </Link>
        )
      })}
    </MuiBreadcrumbs>
  )
}
