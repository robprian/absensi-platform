import tailwindcss from '@tailwindcss/vite';
import { sveltekit } from '@sveltejs/kit/vite';
import { SvelteKitPWA } from '@vite-pwa/sveltekit';
import { defineConfig } from 'vite';

const API_BASE = process.env.VITE_API_BASE_URL ?? '/api';

export default defineConfig({
	plugins: [
		tailwindcss(),
		sveltekit(),
		SvelteKitPWA({
			registerType: 'prompt',
			injectRegister: 'auto',
			devOptions: { enabled: false },
			workbox: {
				navigateFallback: '/index.html',
				runtimeCaching: [
					{
						urlPattern: ({ url }) => url.pathname.startsWith('/api/v1/'),
						handler: 'NetworkFirst',
						method: 'GET',
						options: {
							cacheName: 'api-get-cache',
							expiration: { maxEntries: 100, maxAgeSeconds: 60 * 15 },
							networkTimeoutSeconds: 8
						}
					},
					{
						urlPattern: /^https:\/\/fonts\.(googleapis|gstatic)\.com\/.*/i,
						handler: 'CacheFirst',
						options: {
							cacheName: 'google-fonts',
							expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 30 }
						}
					}
				]
			},
			manifest: {
				name: 'Absensi Kerja',
				short_name: 'Absensi',
				description: 'Aplikasi absensi dan penggajian karyawan lapangan',
				start_url: '/',
				display: 'standalone',
				orientation: 'portrait',
				background_color: '#ffffff',
				theme_color: '#059669',
				lang: 'id',
				icons: [
					{ src: 'icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
					{ src: 'icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
					{
						src: 'icons/icon-maskable-192.png',
						sizes: '192x192',
						type: 'image/png',
						purpose: 'maskable'
					},
					{
						src: 'icons/icon-maskable-512.png',
						sizes: '512x512',
						type: 'image/png',
						purpose: 'maskable'
					}
				]
			}
		})
	],
	server: {
		port: 3000,
		proxy: {
			'/api': {
				target: API_BASE.startsWith('/') ? 'http://localhost:8000' : API_BASE,
				changeOrigin: true
			}
		}
	},
	preview: { port: 3000 },
	define: {
		'import.meta.env.VITE_API_BASE_URL': JSON.stringify(API_BASE)
	}
});
