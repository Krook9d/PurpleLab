import path from "path"
import { fileURLToPath } from "url"
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

const dirname = path.dirname(fileURLToPath(import.meta.url))

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      "@": path.resolve(dirname, "./src"),
    },
  },
  server: {
    host: true,
    port: 5173,
    // Mirrors nginx.conf: /api/* -> backend with the prefix stripped, so dev
    // and prod share VITE_API_BASE_URL=/api and dev no longer relies on CORS.
    proxy: {
      "/api": {
        target: "http://127.0.0.1:8000",
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/api/, ""),
      },
      // Hunting (OpenSearch Dashboards), like nginx.conf; OSD_URL points at
      // the published Dashboards port (e.g. http://<host-ip>:5601). The theme
      // stylesheet is only injected by the production nginx.
      "/hunting": {
        target: process.env.OSD_URL ?? "http://127.0.0.1:5601",
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/hunting/, ""),
      },
    },
  },
})
