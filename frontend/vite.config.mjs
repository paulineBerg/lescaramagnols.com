import { defineConfig } from 'vite';
import path from 'path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  },
  build: {
    outDir: path.resolve(__dirname, 'dist'),
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/js/main.ts'),
        style: path.resolve(__dirname, 'src/scss/style.scss'),
        private: path.resolve(__dirname, 'src/scss/private.scss')
      },
      output: {
        assetFileNames: 'assets/[name].[hash][extname]',
        entryFileNames: 'assets/[name].[hash].js'
      }
    }
  },
  css: {
    preprocessorOptions: {
      scss: {}
    }
  },
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',
    proxy: {
      '/core/': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true
      }
    }
  }
});
