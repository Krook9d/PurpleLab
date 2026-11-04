# Credits

Parts of the PurpleLab frontend user interface (theme, layout, common components
and chart configuration) are adapted from the Community Edition of
[OpenCTI](https://github.com/OpenCTI-Platform/opencti), Copyright (c) 2021-2026
Filigran SAS, licensed under the Apache License, Version 2.0 (full text in
[`LICENSES/Apache-2.0.txt`](LICENSES/Apache-2.0.txt)).

Only files without the Enterprise Edition license header were used. No logo,
icon or branding asset was reused. Every adapted file starts with a notice that
names its source file and summarizes the modifications.

The rest of PurpleLab remains under its own license ([`LICENSE`](LICENSE)).

## Adapted files

Source paths are relative to `opencti-platform/opencti-front/`. "pre-DS" means
the version before the design-system migration (`git show 5b9bcf6116^:<path>`).

| PurpleLab file (`frontend/`) | OpenCTI source | Modifications |
|---|---|---|
| `src/theme/tokens.ts` | `src/components/ThemeDark.ts`, `src/components/ThemeLight.ts` (pre-DS) | Colors extracted into per-mode tokens; violet PurpleLab primary/secondary; product-specific palettes removed; chart palette and brand gradient added |
| `src/theme/createAppTheme.ts` | `src/components/ThemeDark.ts`, `src/components/ThemeLight.ts` (pre-DS) | Dark/light builders merged into one factory; logo params and third-party editor/grid overrides removed; `<pre>`/`<code>` rules no longer `!important`; light-mode table borders fixed |
| `src/theme/theme.d.ts` | `src/components/Theme.d.ts` | Rewritten as a minimal MUI module augmentation |
| `src/components/layout/LeftBar.tsx` | `src/private/components/nav/LeftBar.jsx`, `LeftBarHeader.tsx` (pre-DS) | GraphQL settings, permissions, product switcher, sub-menus and vendor footer removed; PurpleLab items and logo |
| `src/components/layout/LeftBarItem.tsx` | `src/private/components/nav/LeftBarItem.tsx` (pre-DS) | Flat items only, external links, router NavLink, typed props |
| `src/components/layout/PageContainer.tsx` | `src/components/PageContainer.tsx` | Context and right-menu padding removed |
| `src/components/common/Loader.tsx` | `src/components/Loader.tsx` | Branded loader, settings context and rxjs removed |
| `src/components/common/Notifier.tsx` | `src/components/Message.tsx` | Local notify bus instead of rxjs, queued display, description, EE / AI variants removed |
| `src/components/common/Transition.tsx` | `src/components/Transition.tsx` | Typing only |
| `src/components/common/Dialog.tsx` | `src/components/common/dialog/Dialog.tsx` | Design-system layers removed, slide-up transition, actions slot |
| `src/components/common/Card.tsx`, `CardTitle.tsx` | `src/components/common/card/Card.tsx`, `CardTitle.tsx` | Theme colors instead of design-system variables, hover glow |
| `src/components/common/Drawer.tsx`, `DrawerHeader.tsx` | `src/private/components/common/drawer/Drawer.tsx`, `src/components/common/drawer/DrawerHeader.tsx` | Controlled component, subscriptions / banners / layers removed, footer slot |
| `src/components/charts/Chart.tsx` | `src/private/components/common/charts/Chart.tsx` | Local HTML escaping, export hook removed |
| `src/utils/chartOptions.ts` | `src/utils/Charts.jsx` | Typed port of bar / donut / polar-area builders, PurpleLab palette, animations, new radial gauge |
| `src/components/dashboard/WidgetContainer.tsx`, `WidgetNumber.tsx`, `WidgetNoData.tsx`, `WidgetHorizontalBars.tsx`, `WidgetVerticalBars.tsx`, `WidgetDonut.tsx`, `WidgetPolarArea.tsx` | `src/components/dashboard/*` (same names) | Plain `{label, value}` data, loading / empty states, count-up numbers; export, i18n, entity icons and redirection removed |
| `src/components/dashboard/WidgetDistributionList.tsx` | `src/components/dashboard/WidgetDistributionList.tsx` | Plain `{label, value, icon, href}` entries instead of entity representatives and router links; i18n formatter replaced |
| `src/pages/mitre/matrix/MatrixCellView.tsx` | `src/private/components/techniques/attack_patterns/attack_patterns_matrix/AttackPatternsMatrixColumsElement.tsx`, `AttackPatternsMatrixAccordion.tsx`, `AttackPatternsMatrixBadge.tsx` | Single cell component, coverage colors (Atomic / Sigma / executions) instead of security-coverage scores, typed props instead of Relay data, tooltip and failed-run marker |
| `src/pages/mitre/matrix/AttackMatrix.tsx` | `src/private/components/techniques/attack_patterns/attack_patterns_matrix/AttackPatternsMatrixColumns.tsx` | Columns from the `/mitre/matrix` API instead of Relay fragments, per-column coverage bar, filter dims instead of hiding |

## Third-party logos

The connector logos in `frontend/src/components/icons/SiemLogos.tsx` are used
only to identify each integration; they remain trademarks of their respective
owners.

- Elasticsearch, OpenSearch, Graylog, Splunk: SVG paths from
  [Simple Icons](https://simpleicons.org) 13.21.0 (CC0-1.0).
- Wazuh: wordmark from the official
  [wazuh-dashboard-plugins](https://github.com/wazuh/wazuh-dashboard-plugins)
  repository (`plugins/main/public/assets/images/themes/{dark,light}/logo.svg`).
