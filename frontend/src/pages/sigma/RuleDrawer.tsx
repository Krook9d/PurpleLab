import { useState } from "react"
import { alpha, Box, Chip, LinearProgress, Skeleton, Stack, Typography } from "@mui/material"
import TransformOutlined from "@mui/icons-material/TransformOutlined"
import { Drawer } from "@/components/common/Drawer"
import { CodeBlock } from "@/components/common/CodeBlock"
import { useConvertSigma, useSigmaRule } from "@/hooks/useSigma"
import { PLUGIN_LABELS } from "./sigmaMeta"

export function RuleDrawer({ path, onClose }: { path: string | null; onClose: () => void }) {
  const rule = useSigmaRule(path)
  const convert = useConvertSigma()
  const [conversion, setConversion] = useState<{ plugin: string; output: string } | null>(null)
  const fileName = path?.split("/").pop() ?? ""

  function handleClose() {
    setConversion(null)
    convert.reset()
    onClose()
  }

  function handleConvert(plugin: string) {
    if (!path) return
    convert.mutate({ rule_path: path, plugin }, { onSuccess: (data) => setConversion({ plugin, output: data.output }) })
  }

  return (
    <Drawer open={!!path} onClose={handleClose} title={fileName} size="large">
      <Typography variant="body2" sx={(theme) => ({ fontFamily: theme.fontFamilyMono, color: "text.secondary", wordBreak: "break-all" })}>
        {path}
      </Typography>

      {rule.isLoading ? (
        <Skeleton variant="rounded" height={320} />
      ) : (
        <CodeBlock code={rule.data?.content ?? ""} language="yaml" showLineNumbers maxHeight="45vh" />
      )}

      <Box>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1, mb: 1 }}>
          <TransformOutlined sx={{ fontSize: 18, color: "primary.main" }} />
          <Typography variant="h4" sx={{ m: 0, color: "text.light" }}>
            Convert to
          </Typography>
        </Stack>
        <Stack direction="row" useFlexGap sx={{ gap: 0.75, flexWrap: "wrap" }}>
          {Object.entries(PLUGIN_LABELS).map(([plugin, label]) => {
            const active = conversion?.plugin === plugin || (convert.isPending && convert.variables?.plugin === plugin)
            return (
              <Chip
                key={plugin}
                label={label}
                clickable
                disabled={convert.isPending}
                onClick={() => handleConvert(plugin)}
                variant={active ? "filled" : "outlined"}
                color={active ? "primary" : "default"}
              />
            )
          })}
        </Stack>
      </Box>

      {convert.isPending && <LinearProgress sx={{ borderRadius: 1 }} />}
      {conversion && !convert.isPending && (
        <Box
          sx={(theme) => ({
            p: 1.5,
            borderRadius: 1,
            border: `1px solid ${alpha(theme.palette.primary.main, 0.3)}`,
            backgroundColor: alpha(theme.palette.primary.main, 0.05),
          })}
        >
          <Typography variant="h4" sx={{ color: "text.light" }}>
            {PLUGIN_LABELS[conversion.plugin]} query
          </Typography>
          <CodeBlock code={conversion.output} language="text" maxHeight={300} />
        </Box>
      )}
    </Drawer>
  )
}
