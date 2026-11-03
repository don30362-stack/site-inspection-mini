import vue from '@vitejs/plugin-vue'
import { defineConfig, loadEnv, type ProxyOptions } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiProxy: ProxyOptions = {
    target: env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
    configure(proxy) {
      proxy.on('proxyReq', (outgoing, incoming) => {
        // Replace client-supplied forwarding claims with the socket peer.
        outgoing.setHeader('X-Forwarded-For', incoming.socket.remoteAddress || '')
        outgoing.removeHeader('Forwarded')
      })
    },
  }
  return {
    plugins: [vue()],
    server: {
      proxy: {
        '/api': apiProxy,
        '/storage': env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
      },
    },
  }
})
