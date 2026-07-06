import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// PHP is the server of record; Vite is only the client asset pipeline.
// The build emits a manifest that examples/todo/public/index.php reads to
// inject hashed <script>/<link> tags. In dev, run `pnpm dev` alongside PHP.
export default defineConfig({
  plugins: [react()],
  base: '/dist/',
  // PHP owns the public/ dir; don't copy it into the build output.
  publicDir: false,
  build: {
    outDir: './public/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: { main: './src/client/main.tsx' },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
})
