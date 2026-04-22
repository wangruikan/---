import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import { resolve } from 'path'

export default defineConfig({
  plugins: [
    vue(),
    AutoImport({
      resolvers: [ElementPlusResolver()],
    }),
    Components({
      resolvers: [ElementPlusResolver()],
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  optimizeDeps: {
    include: ['pdfjs-dist'],
    exclude: ['pdfjs-dist/build/pdf.worker.min.js']
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/storage': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/payment_requests': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'public',
    emptyOutDir: false,
    assetsDir: 'assets',
    target: 'esnext',
    commonjsOptions: {
      include: [/pdfjs-dist/, /node_modules/],
      transformMixedEsModules: true
    },
    rollupOptions: {
      output: {
        format: 'es',
        // 使用哈希值，每次构建文件名都会变化，避免缓存问题
        entryFileNames: 'assets/app-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
        // 手动分包，将 pdfjs-dist 单独打包
        manualChunks: {
          'pdf': ['pdfjs-dist']
        }
      }
    }
  },
  publicDir: false  // 禁用 public 目录复制，避免冲突
})
