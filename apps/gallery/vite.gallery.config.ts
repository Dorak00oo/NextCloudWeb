/*!
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Standalone gallery build that does NOT wipe the shared dist/ directory.
 */
import { createAppConfig } from '@nextcloud/vite-config'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '../..')

export default createAppConfig({
	'gallery-main': resolve(import.meta.dirname, 'src/main.js'),
	'gallery-files-sync': resolve(import.meta.dirname, 'src/files-sync.js'),
}, {
	createEmptyCSSEntryPoints: true,
	emptyOutputDirectory: false,
	extractLicenseInformation: {
		includeSourceMaps: true,
	},
	config: {
		root,
		resolve: {
			preserveSymlinks: true,
		},
		build: {
			outDir: 'dist',
			emptyOutDir: false,
			cssCodeSplit: false,
			rollupOptions: {
				output: {
					entryFileNames: '[name].mjs',
					chunkFileNames: '[name]-[hash].chunk.mjs',
					assetFileNames: 'gallery-[name]-[hash][extname]',
				},
			},
		},
		experimental: {
			renderBuiltUrl(filename, { hostType }) {
				if (hostType === 'css') {
					return `./${filename}`
				}
				return {
					runtime: `window.OC.filePath('', '', 'dist/${filename}')`,
				}
			},
		},
	},
})
