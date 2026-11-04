import { useMemo, useState } from "react"
import { alpha, Box, Typography, useTheme } from "@mui/material"
import { ComposableMap, Geographies, Geography } from "react-simple-maps"
import worldTopology from "world-atlas/countries-50m.json"

// OTX (and most geo-IP services) return long-form/ISO names that rarely
// match Natural Earth's map labels 1:1 — normalize the common mismatches
// seen on real data (and the usual long-form country-name suspects) rather
// than silently dropping them from the map. Unmatched names still degrade
// gracefully (that country just doesn't light up).
const NAME_ALIASES: Record<string, string> = {
  "united states": "United States of America",
  "russian federation": "Russia",
  "united kingdom of great britain and northern ireland": "United Kingdom",
  "korea, republic of": "South Korea",
  "korea, democratic people's republic of": "North Korea",
  "czech republic": "Czechia",
  "viet nam": "Vietnam",
  "iran, islamic republic of": "Iran",
  "syrian arab republic": "Syria",
  "tanzania, united republic of": "Tanzania",
  "bolivia, plurinational state of": "Bolivia",
  "venezuela, bolivarian republic of": "Venezuela",
  "moldova, republic of": "Moldova",
  "lao people's democratic republic": "Laos",
  "brunei darussalam": "Brunei",
  "congo, the democratic republic of the": "Dem. Rep. Congo",
  "north macedonia": "Macedonia",
  "swaziland": "eSwatini",
  "cape verde": "Cabo Verde",
  "taiwan, province of china": "Taiwan",
  "hong kong": "Hong Kong",
}

function normalize(name: string): string {
  return NAME_ALIASES[name.toLowerCase()] ?? name
}

// Sequential single-hue ramp (magnitude): the theme primary at increasing
// opacity, over a neutral land fill. 5 steps keep neighbours distinguishable.
const STEPS = [0.3, 0.45, 0.6, 0.78, 0.95]

function stepOpacity(intensity: number): number {
  return STEPS[Math.min(STEPS.length - 1, Math.floor(intensity * STEPS.length))]
}

export function WorldThreatMap({ data }: { data: Record<string, number> }) {
  const theme = useTheme()
  const [hovered, setHovered] = useState<{ name: string; count: number } | null>(null)
  const counts = useMemo(() => {
    const normalized: Record<string, number> = {}
    for (const [name, count] of Object.entries(data)) {
      const key = normalize(name)
      normalized[key] = (normalized[key] ?? 0) + count
    }
    return normalized
  }, [data])

  const maxCount = Math.max(1, ...Object.values(counts))
  const land = theme.palette.mode === "dark" ? alpha("#ffffff", 0.06) : alpha("#000000", 0.07)
  const border = theme.palette.mode === "dark" ? alpha("#ffffff", 0.1) : alpha("#000000", 0.12)
  const primary = theme.palette.primary.main

  return (
    <Box sx={{ position: "relative", width: "100%", height: "100%" }}>
      <ComposableMap
        // Explicit width/height set the SVG viewBox ratio to the wide card
        // this map lives in, so it fills the tile instead of letterboxing.
        width={980}
        height={420}
        projectionConfig={{ scale: 170, center: [10, 20] }}
        style={{ width: "100%", height: "100%" }}
      >
        <Geographies geography={worldTopology}>
          {({ geographies }) =>
            geographies.map((geo) => {
              const name = geo.properties.name as string
              const count = counts[name] ?? 0
              const active = count > 0
              const fill = active ? alpha(primary, stepOpacity(count / maxCount)) : land
              return (
                <Geography
                  key={geo.rsmKey}
                  geography={geo}
                  fill={fill}
                  stroke={border}
                  strokeWidth={0.5}
                  onMouseEnter={() => setHovered({ name, count })}
                  onMouseLeave={() => setHovered(null)}
                  style={{
                    default: { outline: "none", transition: "fill 250ms ease" },
                    hover: { outline: "none", fill: active ? theme.palette.primary.light : land, cursor: "default" },
                    pressed: { outline: "none" },
                  }}
                >
                  <title>{`${name}${active ? `: ${count}` : ""}`}</title>
                </Geography>
              )
            })
          }
        </Geographies>
      </ComposableMap>
      {hovered && (
        <Box
          sx={{
            position: "absolute",
            top: 8,
            left: 8,
            px: 1,
            py: 0.5,
            fontSize: 12,
            borderRadius: 0.5,
            pointerEvents: "none",
            backgroundColor: theme.palette.background.nav,
            border: `1px solid ${theme.palette.divider}`,
          }}
        >
          <Typography component="span" variant="body2" color="text.secondary">
            {hovered.name}
          </Typography>
          &nbsp;&nbsp;
          <Typography component="span" variant="body2" sx={{ fontWeight: 600 }}>
            {hovered.count > 0 ? hovered.count : "no activity"}
          </Typography>
        </Box>
      )}
      <Box sx={{ position: "absolute", right: 8, bottom: 4, display: "flex", alignItems: "center", gap: 0.75 }}>
        <Typography variant="body2" color="text.secondary" sx={{ fontSize: 11 }}>
          Less
        </Typography>
        {STEPS.map((o) => (
          <Box key={o} sx={{ width: 14, height: 8, borderRadius: 0.25, backgroundColor: alpha(primary, o) }} />
        ))}
        <Typography variant="body2" color="text.secondary" sx={{ fontSize: 11 }}>
          More
        </Typography>
      </Box>
    </Box>
  )
}
