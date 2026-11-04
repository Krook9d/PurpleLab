/*
 * Adapted from OpenCTI (opencti-front/src/components/ThemeDark.ts and
 * ThemeLight.ts, pre design-system version), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: colors extracted into plain per-mode token objects, primary /
 * secondary replaced by the PurpleLab violet accent, product-specific colors
 * (enterprise / hub / AI) removed, PurpleLab brand gradient and chart palette
 * added, text.secondary kept as a muted color.
 */
import type { PaletteMode } from "@mui/material"

export interface ModeTokens {
  background: string
  backgroundGradientEnd: string
  paper: string
  nav: string
  accent: string
  drawer: string
  dialog: string
  inputBackground: string
  disabled: string
  shadow: string
  primary: { main: string; light: string; dark: string }
  secondary: { main: string; light: string; dark: string }
  text: { primary: string; secondary: string; tertiary: string; light: string; disabled: string }
  leftBar: { hover: string; text: string; popoverItem: string }
  border: { main: string; secondary: string; paper: string; table: string }
  error: { main: string; dark: string }
  warning: { main: string }
  success: { main: string; dark: string }
  info: { main: string }
  // Fixed categorical order for every chart, never reassigned per filter.
  // Both modes validated with the dataviz palette validator against the card
  // surface (dark #09101e, light #ffffff): lightness band, chroma, adjacent CVD
  // (worst ΔE 8.4 dark / 9.1 light), normal-vision floor all pass. Light slots
  // 3-5 sit below 3:1 contrast, so charts always show values in a legend/label.
  chart: string[]
}

export const FONT_FAMILY = '"IBM Plex Sans", sans-serif'
export const FONT_FAMILY_HEADING = '"Geologica", sans-serif'
export const FONT_FAMILY_MONO = 'Consolas, monaco, "Cascadia Mono", monospace'

// PurpleLab brand gradient (logo glow, login, primary call-to-action accents).
export const BRAND_GRADIENT = { from: "#667eea", to: "#764ba2" }

export const SEVERITY = {
  critical: "#EE3838",
  high: "#E6700F",
  medium: "#E1B823",
  low: "#16AD34",
  info: "#1565c0",
  none: "#424242",
}

const dark: ModeTokens = {
  background: "#070d19",
  backgroundGradientEnd: "#0b0f22",
  paper: "#09101e",
  nav: "#070d19",
  accent: "#0f1e38",
  drawer: "#0f1d34",
  dialog: "#0f1d34",
  inputBackground: "#0c1524",
  disabled: "#363B46",
  shadow: "rgba(200, 200, 200, 0.15)",
  primary: { main: "#7c6cf0", light: "#b4abff", dark: "#5546c4" },
  secondary: { main: "#c792ff", light: "#e3c9ff", dark: "#8f55d6" },
  text: {
    primary: "#F2F2F3",
    secondary: "#AFB0B6",
    tertiary: "#848592",
    light: "#AFB0B6",
    disabled: "#75829A",
  },
  leftBar: { hover: "#1d2440", text: "#F2F2F3", popoverItem: "#070D19" },
  border: {
    main: "#252A35",
    secondary: "#424751",
    paper: "rgba(255, 255, 255, 0.12)",
    table: "rgba(255, 255, 255, 0.15)",
  },
  error: { main: "#F14337", dark: "#881106" },
  warning: { main: "#E6700F" },
  success: { main: "#17AB1F", dark: "#094E0B" },
  info: { main: "#4DCCFF" },
  chart: ["#7c6cf0", "#d95926", "#199e70", "#c98500", "#d55181", "#008300", "#3987e5", "#e66767"],
}

const light: ModeTokens = {
  background: "#ececf2",
  backgroundGradientEnd: "#f7f7fa",
  paper: "#ffffff",
  nav: "#ffffff",
  accent: "#e4e2f3",
  drawer: "#ffffff",
  dialog: "#ffffff",
  inputBackground: "#ffffff",
  disabled: "#DFDFDF",
  shadow: "rgba(0, 0, 0, 0.15)",
  primary: { main: "#5b4bd1", light: "#8f84ea", dark: "#3d2fa3" },
  secondary: { main: "#8b46d6", light: "#b88ae9", dark: "#5f2a9c" },
  text: {
    primary: "#18191B",
    secondary: "#494A50",
    tertiary: "#5F5F60",
    light: "#494A50",
    disabled: "#5F6B7A",
  },
  leftBar: { hover: "rgba(91, 75, 209, 0.1)", text: "#18191B", popoverItem: "#ECECF2" },
  border: {
    main: "#D2D2D2",
    secondary: "#C2C2C2",
    paper: "rgba(0, 0, 0, 0.12)",
    table: "rgba(0, 0, 0, 0.12)",
  },
  error: { main: "#D93025", dark: "#881106" },
  warning: { main: "#C75F0A" },
  success: { main: "#0D7E39", dark: "#075C28" },
  info: { main: "#0277bd" },
  chart: ["#5b4bd1", "#eb6834", "#1baf7a", "#eda100", "#e87ba4", "#008300", "#2a78d6", "#e34948"],
}

export const TOKENS: Record<PaletteMode, ModeTokens> = { dark, light }
