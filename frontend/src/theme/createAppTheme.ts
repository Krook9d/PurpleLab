/*
 * Adapted from OpenCTI (opencti-front/src/components/ThemeDark.ts and
 * ThemeLight.ts, pre design-system version), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: the two near-identical dark/light builders are merged into one
 * factory fed by per-mode tokens (./tokens.ts); logo parameters, product
 * specific palettes and third-party editor/grid overrides removed; inline
 * <code> and <pre> styling no longer uses !important so syntax-highlighted
 * blocks keep their own theme; table borders fixed for light mode; chips keep
 * their original casing (they display data, not UI labels).
 */
import { alpha, createTheme, type PaletteMode, type Theme } from "@mui/material"
import { buttonClasses } from "@mui/material/Button"
import {
  BRAND_GRADIENT,
  FONT_FAMILY,
  FONT_FAMILY_HEADING,
  FONT_FAMILY_MONO,
  SEVERITY,
  TOKENS,
} from "./tokens"

// Signature typography trick: headings, tabs, chips and tooltips are
// rendered lowercase with only the first letter capitalized.
const firstLetterUppercase = {
  textTransform: "lowercase",
  "&::first-letter": { textTransform: "uppercase" },
} as const

export function createAppTheme(mode: PaletteMode): Theme {
  const t = TOKENS[mode]
  const isDark = mode === "dark"
  const textColor = t.text.primary
  const bodyGradient = `linear-gradient(100deg, ${t.background} 0%, ${t.backgroundGradientEnd} 100%)`
  const scrollbarColor = isDark ? `${t.accent} ${t.background}` : `${t.accent} ${t.paper}`

  return createTheme({
    fontFamilyMono: FONT_FAMILY_MONO,
    shape: { borderRadius: 4 },
    palette: {
      mode,
      primary: t.primary,
      secondary: t.secondary,
      error: t.error,
      warning: t.warning,
      success: t.success,
      info: t.info,
      text: t.text,
      divider: t.border.paper,
      background: {
        default: t.background,
        paper: t.paper,
        nav: t.nav,
        accent: t.accent,
        drawer: t.drawer,
        dialog: t.dialog,
        input: t.inputBackground,
        disabled: t.disabled,
        shadow: t.shadow,
        gradient: { start: t.background, end: t.backgroundGradientEnd },
      },
      leftBar: t.leftBar,
      border: { ...t.border, primary: alpha(t.primary.main, 0.3) },
      brand: BRAND_GRADIENT,
      severity: SEVERITY,
      chart: t.chart,
    },
    typography: {
      fontFamily: FONT_FAMILY,
      body2: { fontSize: "0.8rem", lineHeight: "1.2rem", color: textColor },
      body1: { fontSize: "0.9rem", color: textColor },
      overline: { fontWeight: 500, color: textColor },
      h1: {
        margin: "0 0 10px 0",
        padding: 0,
        fontWeight: 400,
        fontSize: 22,
        fontFamily: FONT_FAMILY_HEADING,
        color: textColor,
        ...firstLetterUppercase,
      },
      h2: {
        margin: "0 0 10px 0",
        padding: 0,
        fontWeight: 500,
        fontSize: 16,
        fontFamily: FONT_FAMILY_HEADING,
        color: textColor,
        ...firstLetterUppercase,
      },
      h3: {
        margin: "0 0 10px 0",
        padding: 0,
        fontWeight: 400,
        fontSize: 13,
        fontFamily: FONT_FAMILY_HEADING,
        color: textColor,
        ...firstLetterUppercase,
      },
      h4: {
        height: 15,
        margin: "0 0 10px 0",
        padding: 0,
        fontSize: 12,
        fontWeight: 500,
        color: textColor,
        ...firstLetterUppercase,
      },
      h5: { fontWeight: 700, fontSize: 16, fontFamily: FONT_FAMILY_HEADING, color: textColor, ...firstLetterUppercase },
      h6: { fontWeight: 600, fontSize: 14, fontFamily: FONT_FAMILY_HEADING, color: textColor, ...firstLetterUppercase },
      subtitle2: { fontWeight: 400, fontSize: 18, color: textColor, ...firstLetterUppercase },
    },
    components: {
      MuiAccordion: {
        defaultProps: { slotProps: { transition: { unmountOnExit: true } } },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            [`&.${buttonClasses.outlined}.${buttonClasses.sizeSmall}`]: { padding: "4px 9px" },
            "&.icon-outlined": {
              borderColor: alpha(isDark ? "#ffffff" : "#000000", 0.15),
              padding: 7,
              minWidth: 0,
              "&:hover": {
                borderColor: alpha(isDark ? "#ffffff" : "#000000", 0.15),
                backgroundColor: alpha(isDark ? "#ffffff" : "#000000", 0.05),
              },
            },
          },
        },
      },
      MuiDialog: {
        styleOverrides: {
          paper: { backgroundImage: "none", backgroundColor: t.dialog, borderRadius: 4 },
        },
      },
      MuiDialogTitle: { defaultProps: { variant: "h5" } },
      MuiDialogActions: {
        styleOverrides: {
          root: ({ theme }) => ({
            gap: theme.spacing(1),
            padding: theme.spacing(0, 3, 3, 3),
            marginTop: theme.spacing(2),
            "& .MuiButton-root": { textTransform: "none" },
            "& > :not(style) ~ :not(style)": { marginLeft: 0 },
          }),
        },
      },
      MuiDrawer: {
        styleOverrides: { paper: { backgroundImage: "none" } },
      },
      MuiToggleButtonGroup: {
        defaultProps: { size: "small" },
        styleOverrides: {
          root: {
            height: 36,
            "& .MuiTouchRipple-root": { display: "none" },
            "& .MuiToggleButton-root": {
              border: `1px solid ${t.border.main}`,
              color: t.primary.main,
              "&:focus-visible": { outline: "none", boxShadow: `0 0 0 2px ${t.primary.light}` },
              "&.Mui-selected": { backgroundColor: alpha(t.primary.main, 0.25) },
              "&:hover:not(.Mui-selected)": { backgroundColor: alpha(t.primary.main, 0.15) },
            },
          },
        },
      },
      MuiTooltip: {
        styleOverrides: {
          tooltip: { backgroundColor: "rgba(0,0,0,0.7)" },
          arrow: { color: "rgba(0,0,0,0.7)" },
          popper: firstLetterUppercase,
        },
      },
      MuiFormControl: {
        defaultProps: { variant: "standard" },
        styleOverrides: { root: { color: textColor } },
      },
      MuiTextField: {
        defaultProps: { variant: "standard" },
        styleOverrides: {
          root: {
            color: textColor,
            "& .MuiFormLabel-root:not(.MuiInputLabel-shrink):not(.Mui-error)": { color: t.text.light },
          },
        },
      },
      MuiSelect: {
        defaultProps: { variant: "standard" },
        styleOverrides: {
          root: { color: textColor, "& fieldset": { border: "none" } },
          outlined: { backgroundColor: t.inputBackground },
        },
      },
      MuiPaper: {
        styleOverrides: { root: { color: textColor } },
      },
      MuiCssBaseline: {
        styleOverrides: {
          html: {
            scrollbarColor,
            scrollbarWidth: "thin",
            background: bodyGradient,
            backgroundAttachment: "fixed",
            backgroundColor: t.background,
            WebkitFontSmoothing: "auto",
          },
          body: {
            background: bodyGradient,
            backgroundAttachment: "fixed",
            scrollbarColor,
            scrollbarWidth: "thin",
            minHeight: "100vh",
            a: { color: t.primary.main },
            "input:-webkit-autofill": {
              WebkitAnimation: "autofill 0s forwards",
              animation: "autofill 0s forwards",
              WebkitTextFillColor: `${textColor} !important`,
              caretColor: "transparent !important",
              WebkitBoxShadow: `0 0 0 1000px ${isDark ? "rgba(4, 8, 17, 0.88)" : t.paper} inset !important`,
              borderTopLeftRadius: "inherit",
              borderTopRightRadius: "inherit",
            },
            pre: {
              fontFamily: FONT_FAMILY_MONO,
              color: textColor,
              background: t.accent,
              borderRadius: 4,
            },
            ":not(pre) > code": {
              fontFamily: FONT_FAMILY_MONO,
              color: textColor,
              background: t.accent,
              padding: 3,
              fontSize: 12,
              fontWeight: 400,
              borderRadius: 4,
            },
          },
        },
      },
      MuiTableCell: {
        styleOverrides: {
          head: { borderBottom: `1px solid ${t.border.table}` },
          body: { borderTop: `1px solid ${t.border.table}`, borderBottom: `1px solid ${t.border.table}` },
        },
      },
      MuiMenuItem: {
        styleOverrides: {
          root: {
            "&.Mui-selected": {
              boxShadow: `2px 0 ${t.primary.main} inset`,
              backgroundColor: alpha(t.primary.main, 0.24),
            },
            "&.Mui-selected:hover": {
              boxShadow: `2px 0 ${t.primary.main} inset`,
              backgroundColor: alpha(t.primary.main, 0.32),
            },
          },
        },
      },
      MuiTypography: {
        styleOverrides: { root: { color: textColor, textTransform: "none" } },
      },
      MuiInputBase: {
        styleOverrides: { root: { color: textColor } },
      },
      // No lowercase trick on chips: they carry data (names, tags, IDs).
      MuiChip: {
        styleOverrides: { root: { color: textColor } },
      },
      MuiTab: {
        styleOverrides: {
          root: { display: "inline-block", ...firstLetterUppercase },
        },
      },
      MuiListItemIcon: {
        styleOverrides: { root: { minWidth: 36 } },
      },
      MuiFab: {
        styleOverrides: { root: { textTransform: "none" } },
      },
      MuiAutocomplete: {
        styleOverrides: {
          root: {
            "& .MuiFormLabel-root:not(.MuiInputLabel-shrink):not(.Mui-error)": { color: t.text.light },
            "& .MuiOutlinedInput-root": {
              backgroundColor: t.inputBackground,
              "& fieldset": { borderColor: "transparent" },
            },
          },
        },
      },
    },
  })
}
