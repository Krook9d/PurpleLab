/*
 * Adapted from OpenCTI (opencti-front/src/private/components/nav/LeftBar.jsx,
 * pre design-system version), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: left bar widths (collapsed / expanded) plus PurpleLab top bar
 * height and shared transition timing.
 */
export const SMALL_BAR_WIDTH = 55
export const OPEN_BAR_WIDTH = 180
export const TOP_BAR_HEIGHT = 64

// Same timing as the left bar width transition (theme enteringScreen, easeInOut).
export const NAV_TRANSITION = "225ms cubic-bezier(0.4, 0, 0.2, 1)"
