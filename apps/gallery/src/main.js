/*!
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import GalleryApp from './GalleryApp.vue'

function forceFullBleed(el) {
	const content = document.getElementById('content')
	if (content) {
		// Keep core position:fixed; only switch flex → block so absolute child can fill.
		content.style.setProperty('display', 'block', 'important')
		content.style.setProperty('background-color', 'var(--color-main-background)', 'important')
	}
	el.style.setProperty('position', 'absolute', 'important')
	el.style.setProperty('top', '0', 'important')
	el.style.setProperty('right', '0', 'important')
	el.style.setProperty('bottom', '0', 'important')
	el.style.setProperty('left', '0', 'important')
	el.style.setProperty('width', '100%', 'important')
	el.style.setProperty('height', '100%', 'important')
	el.style.setProperty('max-width', 'none', 'important')
	el.style.setProperty('overflow', 'auto', 'important')
	el.style.setProperty('background-color', 'var(--color-main-background)', 'important')
	el.style.setProperty('z-index', '1', 'important')
}

const el = document.getElementById('app-content-vue')
if (el) {
	forceFullBleed(el)
	createApp(GalleryApp).mount(el)
} else {
	console.error('[gallery] #app-content-vue not found')
}
