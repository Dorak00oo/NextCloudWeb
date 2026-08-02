<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
?>
<style>
	/* Critical: fill the fixed #content shell completely */
	#content.app-gallery {
		display: block !important;
		background-color: var(--color-main-background) !important;
	}
	#content.app-gallery > #app-content-vue,
	#content.app-gallery > .gallery-root {
		position: absolute !important;
		top: 0 !important;
		right: 0 !important;
		bottom: 0 !important;
		left: 0 !important;
		width: 100% !important;
		height: 100% !important;
		max-width: none !important;
		overflow: auto !important;
		background-color: var(--color-main-background) !important;
		z-index: 1;
	}
</style>
<div id="app-content-vue" class="gallery-root"></div>
