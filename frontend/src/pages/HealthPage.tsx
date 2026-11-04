import type { ReactNode } from "react"
import { alpha, Box, Button, LinearProgress, Stack, Typography } from "@mui/material"
import StorageOutlined from "@mui/icons-material/StorageOutlined"
import DnsOutlined from "@mui/icons-material/DnsOutlined"
import MemoryOutlined from "@mui/icons-material/MemoryOutlined"
import SaveOutlined from "@mui/icons-material/SaveOutlined"
import PlayArrowOutlined from "@mui/icons-material/PlayArrowOutlined"
import PowerSettingsNewOutlined from "@mui/icons-material/PowerSettingsNewOutlined"
import RestoreOutlined from "@mui/icons-material/RestoreOutlined"
import GppBadOutlined from "@mui/icons-material/GppBadOutlined"
import GppGoodOutlined from "@mui/icons-material/GppGoodOutlined"
import ComputerOutlined from "@mui/icons-material/ComputerOutlined"
import FindInPageOutlined from "@mui/icons-material/FindInPageOutlined"
import { PageContainer } from "@/components/layout/PageContainer"
import { Card } from "@/components/common/Card"
import { StatusChip } from "@/components/common/StatusChip"
import { WidgetContainer } from "@/components/dashboard/WidgetContainer"
import { WidgetRadialGauge } from "@/components/dashboard/WidgetRadialGauge"
import { useHealthStatus, useSystemMetrics } from "@/hooks/useHealth"
import {
  useForensicAcquisition,
  useVmDisableAv,
  useVmEnableAv,
  useVmPoweroff,
  useVmRestore,
  useVmStart,
  useVmState,
} from "@/hooks/useVm"

function IconTile({ children }: { children: ReactNode }) {
  return (
    <Box
      sx={(theme) => ({
        width: 40,
        height: 40,
        flexShrink: 0,
        borderRadius: 1,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        color: theme.palette.primary.main,
        backgroundColor: alpha(theme.palette.primary.main, 0.12),
        border: `1px solid ${alpha(theme.palette.primary.main, 0.25)}`,
        "& svg": { fontSize: 20 },
      })}
    >
      {children}
    </Box>
  )
}

function ServiceCard({ icon, name, detail, ok, loading }: { icon: ReactNode; name: string; detail: string; ok: boolean; loading: boolean }) {
  return (
    <Card padding="medium" hoverable>
      <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
        <IconTile>{icon}</IconTile>
        <Box sx={{ flex: 1, minWidth: 0 }}>
          <Typography sx={{ fontWeight: 600 }}>{name}</Typography>
          <Typography variant="body2" color="text.secondary">
            {detail}
          </Typography>
        </Box>
        {loading ? (
          <StatusChip tone="neutral" label="Checking…" />
        ) : (
          <StatusChip tone={ok ? "success" : "error"} label={ok ? "Operational" : "Unavailable"} pulsing={ok} />
        )}
      </Stack>
    </Card>
  )
}

function InfoField({ label, value }: { label: string; value: ReactNode }) {
  return (
    <Box>
      <Typography variant="body2" color="text.secondary" sx={{ fontSize: 12 }}>
        {label}
      </Typography>
      <Typography sx={(theme) => ({ fontFamily: theme.fontFamilyMono, fontWeight: 500 })}>{value}</Typography>
    </Box>
  )
}

const formatGb = (used: number, total: number) => `${used.toFixed(1)} / ${total.toFixed(1)} GB`

export function HealthPage() {
  const health = useHealthStatus()
  const metrics = useSystemMetrics()
  const vmState = useVmState()
  const vm = vmState.data

  const poweroff = useVmPoweroff()
  const restore = useVmRestore()
  const start = useVmStart()
  const enableAv = useVmEnableAv()
  const disableAv = useVmDisableAv()
  const forensic = useForensicAcquisition()

  const vmRunning = vm?.state === "running"
  const anyVmActionPending =
    poweroff.isPending || restore.isPending || start.isPending || enableAv.isPending || disableAv.isPending
  const m = metrics.data

  return (
    <PageContainer>
      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" } }}>
        <ServiceCard
          icon={<StorageOutlined />}
          name="Database"
          detail="PostgreSQL"
          ok={health.data?.db === "ok"}
          loading={health.isLoading}
        />
        <ServiceCard
          icon={<DnsOutlined />}
          name="VM agent"
          detail="vmbridge-agent"
          ok={health.data?.vm_agent === "ok"}
          loading={health.isLoading}
        />
      </Box>

      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", md: "repeat(3, 1fr)" } }}>
        <WidgetContainer title="Memory" loading={metrics.isLoading} empty={!m} emptyMessage="Metrics unavailable.">
          {m && (
            <WidgetRadialGauge label="RAM" percent={m.memory.percent} detail={formatGb(m.memory.used_gb, m.memory.total_gb)} />
          )}
        </WidgetContainer>
        <WidgetContainer title="Disk" loading={metrics.isLoading} empty={!m} emptyMessage="Metrics unavailable.">
          {m && <WidgetRadialGauge label="Disk" percent={m.disk.percent} detail={formatGb(m.disk.used_gb, m.disk.total_gb)} />}
        </WidgetContainer>
        <WidgetContainer title="CPU" loading={metrics.isLoading} empty={!m} emptyMessage="Metrics unavailable.">
          {m && <WidgetRadialGauge label="CPU" percent={m.cpu.percent} detail="Instantaneous usage" />}
        </WidgetContainer>
      </Box>

      <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: { xs: "1fr", lg: "2fr 1fr" } }}>
        <Card title="Sandbox VM" hoverable>
          <Stack spacing={3}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
              <IconTile>
                <ComputerOutlined />
              </IconTile>
              <Typography variant="h2" sx={{ m: 0, flex: 1, textTransform: "none" }}>
                {vmState.isLoading ? "…" : (vm?.name ?? "sandbox")}
              </Typography>
              {vm && (
                <StatusChip
                  tone={vmRunning ? "success" : "neutral"}
                  label={vm.state.charAt(0).toUpperCase() + vm.state.slice(1)}
                  pulsing={vmRunning}
                />
              )}
            </Stack>

            <Box sx={{ display: "grid", gap: 2, gridTemplateColumns: "repeat(2, minmax(0, 200px))" }}>
              <InfoField label="IP address" value={vm?.ip ?? "—"} />
              <InfoField label="Snapshot" value={vm?.snapshot ?? "—"} />
            </Box>

            <Stack direction="row" useFlexGap sx={{ gap: 1, flexWrap: "wrap" }}>
              <Button
                variant="contained"
                startIcon={<PlayArrowOutlined />}
                loading={start.isPending}
                loadingPosition="start"
                disabled={vmRunning || anyVmActionPending}
                onClick={() => start.mutate()}
              >
                Start
              </Button>
              <Button
                variant="outlined"
                startIcon={<PowerSettingsNewOutlined />}
                loading={poweroff.isPending}
                loadingPosition="start"
                disabled={!vmRunning || anyVmActionPending}
                onClick={() => poweroff.mutate()}
              >
                Power off
              </Button>
              <Button
                variant="outlined"
                startIcon={<RestoreOutlined />}
                loading={restore.isPending}
                loadingPosition="start"
                disabled={anyVmActionPending}
                onClick={() => restore.mutate()}
              >
                Restore snapshot
              </Button>
              <Button
                variant="outlined"
                color="warning"
                startIcon={<GppBadOutlined />}
                loading={disableAv.isPending}
                loadingPosition="start"
                disabled={!vmRunning || anyVmActionPending}
                onClick={() => disableAv.mutate()}
              >
                Disable AV
              </Button>
              <Button
                variant="outlined"
                color="success"
                startIcon={<GppGoodOutlined />}
                loading={enableAv.isPending}
                loadingPosition="start"
                disabled={!vmRunning || anyVmActionPending}
                onClick={() => enableAv.mutate()}
              >
                Enable AV
              </Button>
            </Stack>
            {anyVmActionPending && <LinearProgress sx={{ borderRadius: 1 }} />}
          </Stack>
        </Card>

        <Card title="Forensic acquisition" hoverable>
          <Stack spacing={2} sx={{ height: "100%" }}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
              <IconTile>
                <FindInPageOutlined />
              </IconTile>
              <Box>
                <Typography sx={{ fontWeight: 600 }}>Memory dump / disk clone</Typography>
                <Typography variant="body2" color="text.secondary">
                  Captures the sandbox VM state for offline analysis.
                </Typography>
              </Box>
            </Stack>
            <Stack direction="row" useFlexGap sx={{ gap: 1, flexWrap: "wrap" }}>
              <Button
                variant="outlined"
                startIcon={<MemoryOutlined />}
                loading={forensic.isPending && forensic.variables === "memory"}
                loadingPosition="start"
                disabled={forensic.isPending}
                onClick={() => forensic.mutate("memory")}
              >
                Memory dump
              </Button>
              <Button
                variant="outlined"
                startIcon={<SaveOutlined />}
                loading={forensic.isPending && forensic.variables === "disk"}
                loadingPosition="start"
                disabled={forensic.isPending}
                onClick={() => forensic.mutate("disk")}
              >
                Clone disk
              </Button>
            </Stack>
            {forensic.isPending && (
              <Box>
                <LinearProgress sx={{ borderRadius: 1, mb: 1 }} />
                <Typography variant="body2" color="text.secondary">
                  Acquisition in progress — this can take up to 5 minutes.
                </Typography>
              </Box>
            )}
            {forensic.data?.output_path && !forensic.isPending && (
              <Typography variant="body2" color="text.secondary" sx={{ wordBreak: "break-all" }}>
                Last output: <code>{forensic.data.output_path}</code>
              </Typography>
            )}
          </Stack>
        </Card>
      </Box>
    </PageContainer>
  )
}
