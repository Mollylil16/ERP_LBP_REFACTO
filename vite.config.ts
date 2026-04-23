import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const isProd = mode === 'production'

  return {
    plugins: [
      react(),

      VitePWA({
        registerType: 'autoUpdate',
        // Aligné avec `index.html` : <link rel="manifest" href="/manifest.json" />
        // (sinon VitePWA génère par défaut `manifest.webmanifest` → 404 côté navigateur + chargements bizarres)
        manifestFilename: 'manifest.json',
        includeAssets: ['favicon.ico', 'robots.txt', 'logo_lbp.png'],
        manifest: {
          name: 'LA BELLE PORTE - Gestion de Colis',
          short_name: 'LBP',
          description: 'Application de gestion de colis pour La Belle Porte',
          theme_color: '#667eea',
          background_color: '#ffffff',
          display: 'standalone',
          start_url: '/',
          icons: [
            {
              src: '/logo_lbp.png',
              sizes: 'any',
              type: 'image/png',
              purpose: 'any maskable',
            },
            {
              src: '/logo_lbp.png',
              sizes: '192x192',
              type: 'image/png',
              purpose: 'any',
            },
            {
              src: '/logo_lbp.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'any',
            },
          ],
        },
        workbox: {
          globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
          navigateFallback: '/index.html',
          navigateFallbackDenylist: [/^\/api/, /^\/_/, /^\/sw\.js/],
          // Taille max des assets mis en cache par le SW (5 MB par fichier)
          maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
          runtimeCaching: [
            {
              urlPattern: /^https:\/\/api\.lbp\.com\/.*/i,
              handler: 'NetworkFirst',
              options: {
                cacheName: 'api-cache',
                expiration: {
                  maxEntries: 100,
                  maxAgeSeconds: 60 * 60 * 24, // 24h
                },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
            {
              // Tiles OpenStreetMap
              urlPattern: /^https:\/\/[a-c]\.tile\.openstreetmap\.org\/.*/i,
              handler: 'CacheFirst',
              options: {
                cacheName: 'osm-tiles-cache',
                expiration: {
                  maxEntries: 200,
                  maxAgeSeconds: 60 * 60 * 24 * 30, // 30 jours
                },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
            {
              urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
              handler: 'CacheFirst',
              options: {
                cacheName: 'images-cache',
                expiration: {
                  maxEntries: 100,
                  maxAgeSeconds: 60 * 60 * 24 * 7, // 7 jours
                },
              },
            },
            {
              // Google Fonts
              urlPattern: /^https:\/\/fonts\.(googleapis|gstatic)\.com\/.*/i,
              handler: 'CacheFirst',
              options: {
                cacheName: 'google-fonts-cache',
                expiration: {
                  maxEntries: 20,
                  maxAgeSeconds: 60 * 60 * 24 * 365, // 1 an
                },
                cacheableResponse: { statuses: [0, 200] },
              },
            },
          ],
        },
        // N'activer le SW en dev que si besoin de tester le cache offline
        devOptions: {
          enabled: false,
          type: 'module',
        },
      }),
    ],

    /* ═══════════════════════════════════════
       ALIAS
    ═══════════════════════════════════════ */
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
        '@components': path.resolve(__dirname, './src/components'),
        '@pages': path.resolve(__dirname, './src/pages'),
        '@hooks': path.resolve(__dirname, './src/hooks'),
        '@services': path.resolve(__dirname, './src/services'),
        '@utils': path.resolve(__dirname, './src/utils'),
        '@contexts': path.resolve(__dirname, './src/contexts'),
        '@constants': path.resolve(__dirname, './src/constants'),
        '@config': path.resolve(__dirname, './src/config'),
        '@types': path.resolve(__dirname, './src/types'),
      },
    },

    /* ═══════════════════════════════════════
       PRÉ-BUNDLING (Optimise le démarrage dev)
    ═══════════════════════════════════════ */
    optimizeDeps: {
      include: [
        'react',
        'react-dom',
        'react-router-dom',
        'antd',
        '@ant-design/icons',
        '@tanstack/react-query',
        'axios',
        'dayjs',
        'recharts',
        'zustand',
        'react-hot-toast',
      ],
      // Exclure les modules qui ne doivent pas être pré-bundlés
      exclude: ['@vite/client', '@vite/env'],
    },

    /* ═══════════════════════════════════════
       BUILD PRODUCTION
    ═══════════════════════════════════════ */
    build: {
      // Target moderne pour réduire la taille du polyfill
      target: 'es2020',

      // Minification esbuild (plus rapide que terser)
      minify: 'esbuild',

      // Supprimer console.log et debugger en production
      ...(isProd && {
        esbuild: {
          drop: ['console', 'debugger'],
          legalComments: 'none',
        },
      }),

      // Taille des chunks CSS inline
      cssCodeSplit: true,

      // Afficher la taille compressée dans le rapport
      reportCompressedSize: true,

      // Limite d'avertissement chunk (par défaut 500kB, on monte à 800kB)
      chunkSizeWarningLimit: 800,

      // Sourcemaps en développement seulement
      sourcemap: !isProd,

      rollupOptions: {
        output: {
          // Nommage des chunks avec hash pour cache busting
          chunkFileNames: 'assets/js/[name]-[hash].js',
          entryFileNames: 'assets/js/[name]-[hash].js',
          assetFileNames: (assetInfo) => {
            const ext = assetInfo.name?.split('.').pop() || ''
            if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'].includes(ext)) {
              return 'assets/images/[name]-[hash][extname]'
            }
            if (['woff', 'woff2', 'ttf', 'eot'].includes(ext)) {
              return 'assets/fonts/[name]-[hash][extname]'
            }
            return 'assets/[name]-[hash][extname]'
          },
        },
        // Avertir si des modules sont importés de manière circulaire
        onwarn(warning, warn) {
          if (warning.code === 'CIRCULAR_DEPENDENCY') return
          warn(warning)
        },
      },
    },

    /* ═══════════════════════════════════════
       SERVEUR DE DÉVELOPPEMENT
    ═══════════════════════════════════════ */
    server: {
      port: 5173,
      host: '0.0.0.0', // Écoute sur toutes les interfaces (Wi-Fi inclus) pour scanner QR depuis un téléphone
      strictPort: false,
      // Proxy API en dev pour éviter les CORS
      proxy: {
        '/api': {
          target: 'http://localhost:3000',
          changeOrigin: true,
          rewrite: (path) => path.replace(/^\/api/, ''),
        },
      },
      // Hot Module Replacement optimisé
      hmr: {
        overlay: true,
      },
    },

    /* ═══════════════════════════════════════
       APERÇU (vite preview)
    ═══════════════════════════════════════ */
    preview: {
      port: 4173,
      strictPort: false,
    },

    /* ═══════════════════════════════════════
       ESBUILD (transform)
    ═══════════════════════════════════════ */
    esbuild: {
      // Supprimer les console.* en production seulement
      drop: isProd ? ['console', 'debugger'] : [],
      // Cible JS moderne
      target: 'es2020',
    },
  }
})
