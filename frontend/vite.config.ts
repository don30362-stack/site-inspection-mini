import vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  return {
    plugins: [vue()],
    server: {
      proxy: {
        '/api': env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
        '/storage': env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
      },
    },
  }
})
