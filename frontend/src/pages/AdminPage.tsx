import { Box, Typography } from "@mui/material"
import { PageContainer } from "@/components/layout/PageContainer"
import { AlienvaultSection } from "./admin/AlienvaultSection"
import { MitreAiSection } from "./admin/MitreAiSection"

// Access is restricted by the RequireAdmin route guard (App.tsx).
export function AdminPage() {
  return (
    <PageContainer>
      <Typography variant="body2" color="text.secondary">
        Integrations restricted to administrators.
      </Typography>
      <Box sx={{ display: "grid", gap: 2, alignItems: "start", gridTemplateColumns: { xs: "1fr", xl: "1fr 1fr" } }}>
        <AlienvaultSection />
        <MitreAiSection />
      </Box>
    </PageContainer>
  )
}
