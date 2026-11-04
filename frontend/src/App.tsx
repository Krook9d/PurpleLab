import { lazy } from "react"
import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom"
import { QueryClient, QueryClientProvider } from "@tanstack/react-query"
import { ThemeModeProvider } from "@/theme/ThemeModeProvider"
import { Notifier } from "@/components/common/Notifier"
import { Shell } from "@/components/layout/Shell"
import { RequireAuth } from "@/components/layout/RequireAuth"
import { RequireAdmin } from "@/components/layout/RequireAdmin"
import { LoginPage } from "@/pages/LoginPage"

// Pages are code-split: each one (and its charts / map) loads on first visit,
// while the Shell layout stays on screen (Suspense boundary in Shell).
const IndexPage = lazy(() => import("@/pages/IndexPage").then((m) => ({ default: m.IndexPage })))
const HealthPage = lazy(() => import("@/pages/HealthPage").then((m) => ({ default: m.HealthPage })))
const CustomPayloadsPage = lazy(() => import("@/pages/CustomPayloadsPage").then((m) => ({ default: m.CustomPayloadsPage })))
const SharingPage = lazy(() => import("@/pages/SharingPage").then((m) => ({ default: m.SharingPage })))
const SigmaPage = lazy(() => import("@/pages/SigmaPage").then((m) => ({ default: m.SigmaPage })))
const MalwarePage = lazy(() => import("@/pages/MalwarePage").then((m) => ({ default: m.MalwarePage })))
const MitrePage = lazy(() => import("@/pages/MitrePage").then((m) => ({ default: m.MitrePage })))
const RuleLifecyclePage = lazy(() => import("@/pages/RuleLifecyclePage").then((m) => ({ default: m.RuleLifecyclePage })))
const AdminPage = lazy(() => import("@/pages/AdminPage").then((m) => ({ default: m.AdminPage })))

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 5000,
    },
  },
})

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeModeProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              element={
                <RequireAuth>
                  <Shell />
                </RequireAuth>
              }
            >
              <Route path="/" element={<IndexPage />} />
              <Route path="/health" element={<HealthPage />} />
              <Route path="/custom-payloads" element={<CustomPayloadsPage />} />
              <Route path="/sharing" element={<SharingPage />} />
              <Route path="/sigma" element={<SigmaPage />} />
              <Route path="/malware" element={<MalwarePage />} />
              <Route path="/mitre" element={<MitrePage />} />
              <Route path="/rule-lifecycle" element={<RuleLifecyclePage />} />
              <Route
                path="/admin"
                element={
                  <RequireAdmin>
                    <AdminPage />
                  </RequireAdmin>
                }
              />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Route>
          </Routes>
        </BrowserRouter>
        <Notifier />
      </ThemeModeProvider>
    </QueryClientProvider>
  )
}
