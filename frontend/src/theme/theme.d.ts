/*
 * Adapted from OpenCTI (opencti-front/src/components/Theme.d.ts), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: rewritten as a MUI module augmentation limited to the palette
 * keys PurpleLab actually uses.
 */
import "@mui/material/styles"

declare module "@mui/material/styles" {
  interface TypeBackground {
    nav: string
    accent: string
    drawer: string
    dialog: string
    input: string
    disabled: string
    shadow: string
    gradient: { start: string; end: string }
  }

  interface TypeText {
    tertiary: string
    light: string
  }

  interface Palette {
    leftBar: { hover: string; text: string; popoverItem: string }
    border: { main: string; secondary: string; paper: string; table: string; primary: string }
    brand: { from: string; to: string }
    severity: { critical: string; high: string; medium: string; low: string; info: string; none: string }
    chart: string[]
  }

  interface PaletteOptions {
    leftBar?: Palette["leftBar"]
    border?: Palette["border"]
    brand?: Palette["brand"]
    severity?: Palette["severity"]
    chart?: string[]
  }

  interface Theme {
    fontFamilyMono: string
  }

  interface ThemeOptions {
    fontFamilyMono?: string
  }
}
