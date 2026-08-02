<!--
  - SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="gallery-app">
		<header class="gallery-header">
			<div class="gallery-header__left">
				<button v-if="showBack"
					type="button"
					class="btn"
					aria-label="Back"
					@click="goBack">
					←
				</button>
				<div>
					<h1>{{ title }}</h1>
					<p v-if="subtitle" class="gallery-subtitle">
						{{ subtitle }}
					</p>
				</div>
			</div>

			<div class="gallery-header__right">
				<template v-if="folderPickerOpen">
					<span class="gallery-refreshing" :class="{ 'is-active': refreshing }" aria-live="polite">Updating…</span>
					<button type="button" class="btn" @click="closeFolderPicker">Cancel</button>
					<button type="button" class="btn btn--primary" @click="confirmCreateAlbumHere">
						Create album here
					</button>
				</template>
				<template v-else-if="!currentAlbum && !pickCoverMode">
					<span class="gallery-refreshing" :class="{ 'is-active': refreshing }" aria-live="polite">Updating…</span>
					<div class="density">
						<button type="button" class="btn" :disabled="columns <= 6" @click="bumpColumns(-2)">−</button>
						<span>{{ columns }}×</span>
						<button type="button" class="btn" :disabled="columns >= 12" @click="bumpColumns(2)">+</button>
					</div>
					<select v-model="sortMode" class="btn" aria-label="Sort albums" @change="onSortChange">
						<option value="custom">Custom</option>
						<option value="newest">Newest</option>
						<option value="name">Name</option>
					</select>
					<button type="button" class="btn" @click="promptCreateAlbum">New album</button>
					<button type="button" class="btn" @click="promptCreateGroup">New group</button>
					<button type="button" class="btn" :class="{ active: showHidden }" @click="toggleShowHidden">
						{{ showHidden ? 'Hidden: on' : 'Hidden' }}
					</button>
					<button type="button" class="btn" :class="{ active: mergeSameName }" @click="toggleMerge">
						{{ mergeSameName ? 'Merge: on' : 'Merge' }}
					</button>
				</template>
				<template v-else-if="currentAlbum && !pickCoverMode">
					<span class="gallery-refreshing" :class="{ 'is-active': refreshing }" aria-live="polite">Updating…</span>
					<div class="density">
						<button type="button" class="btn" :disabled="columns <= 6" @click="bumpColumns(-2)">−</button>
						<span>{{ columns }}×</span>
						<button type="button" class="btn" :disabled="columns >= 12" @click="bumpColumns(2)">+</button>
					</div>
					<select v-model="mediaSortOrder" class="btn" aria-label="Sort photos" @change="onMediaSortChange">
						<option value="sort_new_to_old">Newest first</option>
						<option value="sort_old_to_new">Oldest first</option>
						<option value="sort_a_to_z">Name A–Z</option>
						<option value="sort_z_to_a">Name Z–A</option>
						<option value="sort_big_to_small">Biggest first</option>
						<option value="sort_small_to_big">Smallest first</option>
					</select>
					<button type="button" class="btn" :class="{ active: selectMode }" @click="toggleSelectMode">
						{{ selectMode ? 'Selecting' : 'Select' }}
					</button>
					<button v-if="canChangeCover"
						type="button"
						class="btn"
						@click="startPickCover">
						Change cover
					</button>
					<button v-if="canHideCurrent"
						type="button"
						class="btn"
						@click="hideCurrentAlbum">
						Hide album
					</button>
				</template>
				<template v-else-if="pickCoverMode">
					<span class="gallery-refreshing" :class="{ 'is-active': refreshing }" aria-live="polite">Updating…</span>
					<span class="hint">Click a photo or video to set as cover</span>
				</template>
			</div>
		</header>

		<div v-if="selectedAlbumIds.length && !currentAlbum" class="selection-bar">
			<span>{{ selectedAlbumIds.length }} selected</span>
			<button type="button" class="btn" @click="hideSelected(true)">Hide</button>
			<button type="button" class="btn" @click="hideSelected(false)">Unhide</button>
			<button type="button" class="btn" @click="promptAddSelectedToGroup">Add to group</button>
			<button type="button" class="btn" @click="selectedAlbumIds = []">Clear</button>
		</div>

		<div v-if="selectedMediaIds.length && currentAlbum && !pickCoverMode" class="selection-bar">
			<span>{{ selectedMediaIds.length }} selected</span>
			<button type="button" class="btn" @click="promptMoveSelected">Move</button>
			<button type="button" class="btn" @click="favoriteSelected(true)">Favorite</button>
			<button type="button" class="btn" @click="favoriteSelected(false)">Unfavorite</button>
			<button type="button" class="btn" @click="deleteSelected">Delete</button>
			<button type="button" class="btn" @click="clearMediaSelection">Clear</button>
		</div>

		<div v-if="error" class="gallery-error">{{ error }}</div>
		<div v-if="showBootLoading" class="gallery-empty">Loading…</div>

		<!-- Create-album folder tree (APK FolderPicker) -->
		<section v-else-if="folderPickerOpen" class="folder-picker" :class="{ 'is-refreshing': refreshing }">
			<nav class="folder-picker__crumbs" aria-label="Path">
				<button type="button"
					class="folder-picker__crumb"
					:class="{ active: folderPickerPath === '' }"
					@click="openFolderPickerPath('')">
					Files
				</button>
				<template v-for="(seg, i) in folderPickerCrumbs" :key="seg.path">
					<span class="folder-picker__sep">/</span>
					<button type="button"
						class="folder-picker__crumb"
						:class="{ active: i === folderPickerCrumbs.length - 1 }"
						@click="openFolderPickerPath(seg.path)">
						{{ seg.name }}
					</button>
				</template>
			</nav>
			<ul class="folder-picker__list">
				<li v-for="folder in folderPickerFolders" :key="folder.path">
					<button type="button" class="folder-picker__row" @click="openFolderPickerPath(folder.path)">
						<span class="folder-picker__icon" aria-hidden="true" />
						<span>{{ folder.name }}</span>
						<span class="folder-picker__chevron">›</span>
					</button>
				</li>
				<li v-if="!folderPickerFolders.length" class="folder-picker__empty">
					No folders here. Use “Create album here” to create one in this location.
				</li>
			</ul>
		</section>

		<!-- Albums home / group -->
		<section v-else-if="!currentAlbum"
			class="album-grid"
			:class="{ 'is-refreshing': refreshing }"
			:style="{ '--cols': columns }">
			<div v-for="(album, index) in albums"
				:key="album.id"
				class="album-tile"
				:class="{
					'album-tile--hidden': album.isHidden,
					'album-tile--selected': selectedAlbumIds.includes(album.id),
					'album-tile--drop': dragOverId === album.id && album.isGroup,
				}"
				draggable="true"
				@click="onAlbumClick(album, $event)"
				@contextmenu.prevent="toggleSelect(album)"
				@dragstart="onDragStart(album, index, $event)"
				@dragover.prevent="onDragOver(album, $event)"
				@dragleave="onDragLeave(album)"
				@drop.prevent="onDrop(album, index)">
				<div class="album-tile__cover" :class="collageClass(album)" :style="coverStyle(album)">
					<template v-if="album.isGroup && collageUrls(album).length >= 2">
						<span
							v-for="(url, i) in collageUrls(album)"
							:key="i"
							class="collage-cell"
							:style="{ backgroundImage: `url('${url}')` }" />
					</template>
					<span v-if="album.isGroup" class="badge">Group</span>
					<span v-if="album.isFavorites" class="badge badge--fav">★</span>
					<span v-if="album.isHidden" class="badge">Hidden</span>
					<span v-if="selectedAlbumIds.includes(album.id)" class="badge badge--sel">✓</span>
				</div>
				<div class="album-tile__meta">
					<strong>{{ album.name }}</strong>
					<span>
						<template v-if="album.showParent && album.parentName">in {{ album.parentName }} · </template>
						{{ album.count }}
					</span>
				</div>
			</div>
			<div v-if="!albums.length" class="gallery-empty gallery-empty--full">
				No albums yet. Create one or upload photos/videos into folders in Files.
			</div>
		</section>

		<!-- Album media / cover picker -->
		<section v-else class="media-grid" :class="{ 'is-refreshing': refreshing }" :style="{ '--cols': columns }">
			<button v-for="item in media"
				:key="item.id"
				type="button"
				class="media-tile"
				:class="{ 'media-tile--selected': selectedMediaIds.includes(item.id) }"
				@click="onMediaClick(item, $event)"
				@contextmenu.prevent="toggleMediaSelect(item)">
				<img v-if="item.previewUrl" :src="item.previewUrl" :alt="item.name" loading="lazy">
				<span v-if="item.isFavorite" class="media-tile__fav">★</span>
				<span v-if="item.isVideo" class="media-tile__video">▶</span>
				<span v-if="selectedMediaIds.includes(item.id)" class="badge badge--sel">✓</span>
			</button>
			<div v-if="!media.length" class="gallery-empty gallery-empty--full">
				This album is empty.
			</div>
		</section>

		<!-- Teleport above #header (z-index 2000) stacking context -->
		<Teleport to="body">
			<div v-if="viewerItem" class="lightbox" @click.self="closeViewer">
				<button type="button" class="btn lightbox__close" @click="closeViewer">✕</button>
				<button v-if="viewerIndex > 0" type="button" class="btn lightbox__nav lightbox__nav--prev" @click="viewerStep(-1)">‹</button>
				<button v-if="viewerIndex < media.length - 1" type="button" class="btn lightbox__nav lightbox__nav--next" @click="viewerStep(1)">›</button>
				<div class="lightbox__stage">
					<video v-if="viewerItem.isVideo"
						:key="'v' + viewerItem.id"
						:src="viewerItem.sourceUrl"
						controls
						autoplay
						playsinline />
					<img v-else
						:key="'i' + viewerItem.id"
						:src="viewerItem.sourceUrl || viewerItem.previewUrl"
						:alt="viewerItem.name">
				</div>
				<div class="lightbox__caption">{{ viewerItem.name }}</div>
			</div>

			<div v-if="nameDialog.open"
				class="name-dialog"
				role="dialog"
				aria-modal="true"
				:aria-labelledby="'gallery-name-dialog-title'"
				@keydown.esc.prevent="cancelNameDialog">
				<div class="name-dialog__backdrop" @click="cancelNameDialog" />
				<form class="name-dialog__panel" @submit.prevent="submitNameDialog">
					<h2 id="gallery-name-dialog-title">{{ nameDialog.title }}</h2>
					<p v-if="nameDialog.hint" class="name-dialog__hint">{{ nameDialog.hint }}</p>
					<label class="name-dialog__label">
						<span>{{ nameDialog.label }}</span>
						<input ref="nameDialogInput"
							v-model="nameDialog.value"
							type="text"
							class="name-dialog__input"
							autocomplete="off"
							maxlength="200"
							required>
					</label>
					<div class="name-dialog__actions">
						<button type="button" class="btn" @click="cancelNameDialog">Cancel</button>
						<button type="submit" class="btn btn--primary">{{ nameDialog.confirmLabel }}</button>
					</div>
				</form>
			</div>
		</Teleport>
	</div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { Folder, Permission } from '@nextcloud/files'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'

const FILES_SYNC_CHANNEL = 'nc-gallery-files-sync'
const FILES_SYNC_STORAGE_KEY = 'nc-gallery-files-sync'

const DENSITY_KEY = 'gallery-desktop-columns'
const ALLOWED_COLS = [6, 8, 10, 12]
const VIEW_CACHE_PREFIX = 'gallery-view-cache-v1:'

const loading = ref(false)
const refreshing = ref(false)
const albumsReady = ref(false)
const mediaReadyFor = ref(null)
const foldersReadyFor = ref(null)
const error = ref('')
const albums = ref([])
const metadata = ref(null)
const currentAlbum = ref(null)
const media = ref([])
const groupStack = ref([])
const columns = ref(readColumns())
const sortMode = ref('custom')
const mediaSortOrder = ref('sort_new_to_old')
const showHidden = ref(false)
const mergeSameName = ref(false)
const selectedAlbumIds = ref([])
const selectedMediaIds = ref([])
const selectMode = ref(false)
const pickCoverMode = ref(false)
const dragAlbumId = ref(null)
const dragFromIndex = ref(-1)
const dragOverId = ref(null)
const viewerIndex = ref(null)
const moveTargets = ref([])
const folderPickerOpen = ref(false)
const folderPickerPath = ref('')
const folderPickerFolders = ref([])
const nameDialogInput = ref(null)
const nameDialog = ref({
	open: false,
	title: '',
	label: '',
	hint: '',
	confirmLabel: 'Create',
	value: '',
	resolve: null,
})

const parentGroupId = computed(() => (
	groupStack.value.length ? groupStack.value[groupStack.value.length - 1].id : null
))

const viewerItem = computed(() => (
	viewerIndex.value === null ? null : media.value[viewerIndex.value] || null
))

const folderPickerCrumbs = computed(() => {
	const path = folderPickerPath.value.replace(/^\/+|\/+$/g, '')
	if (!path) return []
	const parts = path.split('/')
	return parts.map((name, i) => ({
		name,
		path: parts.slice(0, i + 1).join('/'),
	}))
})

const showBack = computed(() => (
	viewerIndex.value !== null
	|| folderPickerOpen.value
	|| pickCoverMode.value
	|| !!currentAlbum.value
	|| groupStack.value.length > 0
	|| selectedMediaIds.value.length > 0
	|| selectMode.value
))

const showBootLoading = computed(() => {
	if (error.value) return false
	if (folderPickerOpen.value) {
		return !albumsReady.value && foldersReadyFor.value === null && loading.value
	}
	if (currentAlbum.value) {
		return mediaReadyFor.value !== currentAlbum.value.id && loading.value
	}
	return !albumsReady.value && loading.value
})

const title = computed(() => {
	if (viewerItem.value) return viewerItem.value.name
	if (folderPickerOpen.value) return 'Create album'
	if (pickCoverMode.value) return 'Choose cover'
	if (currentAlbum.value) return currentAlbum.value.name
	if (groupStack.value.length) return groupStack.value[groupStack.value.length - 1].name
	return 'Gallery'
})

const subtitle = computed(() => {
	if (viewerItem.value) {
		return `${(viewerIndex.value || 0) + 1} / ${media.value.length}`
	}
	if (folderPickerOpen.value) {
		return folderPickerPath.value
			? `Location: /${folderPickerPath.value}`
			: 'Choose a folder in your files, then create the album here'
	}
	if (pickCoverMode.value) return 'Select a photo or video from this album'
	if (!currentAlbum.value) {
		return sortMode.value === 'custom' ? 'Drag to reorder · Drop on a group to add' : null
	}
	const photos = media.value.filter((m) => !m.isVideo).length
	const videos = media.value.filter((m) => m.isVideo).length
	const parts = []
	if (photos) parts.push(`${photos} photo${photos === 1 ? '' : 's'}`)
	if (videos) parts.push(`${videos} video${videos === 1 ? '' : 's'}`)
	return parts.join(' · ') || '0 items'
})

const canChangeCover = computed(() => (
	currentAlbum.value
	&& !currentAlbum.value.isFavorites
	&& !currentAlbum.value.isGroup
	&& !String(currentAlbum.value.id).startsWith('merged:')
))

const canHideCurrent = computed(() => (
	currentAlbum.value
	&& !currentAlbum.value.isFavorites
	&& !currentAlbum.value.isGroup
))

function readColumns() {
	const n = Number(localStorage.getItem(DENSITY_KEY) || 6)
	return ALLOWED_COLS.includes(n) ? n : 6
}

function bumpColumns(delta) {
	const next = columns.value + delta
	if (!ALLOWED_COLS.includes(next)) return
	columns.value = next
	localStorage.setItem(DENSITY_KEY, String(next))
}

function collageUrls(album) {
	const covers = (album.memberCovers || []).map((c) => c.coverUrl).filter(Boolean)
	return covers.slice(0, 4)
}

function collageClass(album) {
	if (!album.isGroup) return ''
	const n = collageUrls(album).length
	if (n >= 4) return 'collage collage--4'
	if (n === 3) return 'collage collage--3'
	if (n === 2) return 'collage collage--2'
	return ''
}

function coverStyle(album) {
	if (album.isGroup && collageUrls(album).length >= 2) return {}
	if (album.coverUrl) return { backgroundImage: `url("${album.coverUrl}")` }
	return { background: 'var(--color-background-dark)' }
}

function applyListPayload(data) {
	albums.value = data.albums || []
	metadata.value = data.metadata || null
	if (metadata.value?.albumsSortMode) sortMode.value = metadata.value.albumsSortMode
	if (metadata.value?.mediaSortOrder) mediaSortOrder.value = metadata.value.mediaSortOrder
	if (typeof metadata.value?.mergeSameName === 'boolean') mergeSameName.value = metadata.value.mergeSameName
	albumsReady.value = true
	persistAlbumsCache()
}

function noCacheParams(extra = {}) {
	return { ...extra, _t: Date.now() }
}

function albumsCacheKey() {
	return `${VIEW_CACHE_PREFIX}albums:${parentGroupId.value || 'root'}:h${showHidden.value ? 1 : 0}`
}

function mediaCacheKey(albumId) {
	return `${VIEW_CACHE_PREFIX}media:${albumId}:${mediaSortOrder.value}`
}

function foldersCacheKey(path) {
	return `${VIEW_CACHE_PREFIX}folders:${path || 'root'}`
}

function readViewCache(key) {
	try {
		const raw = sessionStorage.getItem(key)
		if (!raw) return null
		return JSON.parse(raw)
	} catch {
		return null
	}
}

function writeViewCache(key, value) {
	try {
		sessionStorage.setItem(key, JSON.stringify({ ...value, at: Date.now() }))
	} catch {
		// ignore quota
	}
}

function persistAlbumsCache() {
	writeViewCache(albumsCacheKey(), {
		albums: albums.value,
		metadata: metadata.value,
		sortMode: sortMode.value,
		mediaSortOrder: mediaSortOrder.value,
		mergeSameName: mergeSameName.value,
	})
}

const albumsContextKey = ref('')

function hydrateAlbumsCache() {
	const key = albumsCacheKey()
	const cached = readViewCache(key)
	if (!cached?.albums) return false
	albums.value = cached.albums
	metadata.value = cached.metadata || null
	if (cached.sortMode) sortMode.value = cached.sortMode
	if (cached.mediaSortOrder) mediaSortOrder.value = cached.mediaSortOrder
	if (typeof cached.mergeSameName === 'boolean') mergeSameName.value = cached.mergeSameName
	albumsReady.value = true
	albumsContextKey.value = key
	return true
}

function hydrateMediaCache(albumId) {
	const cached = readViewCache(mediaCacheKey(albumId))
	if (!cached?.items) return false
	media.value = cached.items
	mediaReadyFor.value = albumId
	return true
}

const albumsAbort = { controller: null, seq: 0 }
const mediaAbort = { controller: null, seq: 0 }
const foldersAbort = { controller: null, seq: 0 }

function beginFetch(hasCache) {
	error.value = ''
	if (hasCache) {
		refreshing.value = true
		loading.value = false
	} else {
		loading.value = true
		refreshing.value = false
	}
}

function endFetch(isLatest = true) {
	if (!isLatest) return
	loading.value = false
	refreshing.value = false
}

function isAbortError(e) {
	return e?.code === 'ERR_CANCELED' || e?.name === 'CanceledError' || e?.name === 'AbortError'
}

function startRequest(slot) {
	slot.controller?.abort()
	slot.seq += 1
	const seq = slot.seq
	slot.controller = new AbortController()
	return {
		seq,
		signal: slot.controller.signal,
		isLatest: () => slot.seq === seq,
	}
}

async function loadAlbums() {
	const key = albumsCacheKey()
	if (albumsContextKey.value !== key) {
		const hydrated = hydrateAlbumsCache()
		if (!hydrated) {
			albumsReady.value = false
			albums.value = []
		}
		albumsContextKey.value = key
	}
	const hasCache = albumsReady.value
	const req = startRequest(albumsAbort)
	beginFetch(hasCache)
	try {
		const { data } = await axios.get(generateUrl('/apps/gallery/api/albums'), {
			params: noCacheParams({
				...(parentGroupId.value ? { parentGroupId: parentGroupId.value } : {}),
				showHidden: showHidden.value ? '1' : '0',
			}),
			headers: { 'Cache-Control': 'no-cache' },
			signal: req.signal,
		})
		if (!req.isLatest() || albumsCacheKey() !== key) return
		applyListPayload(data)
		albumsContextKey.value = key
	} catch (e) {
		if (isAbortError(e) || !req.isLatest()) return
		if (!hasCache) {
			error.value = e?.response?.data?.message || e.message || 'Failed to load albums'
		}
	} finally {
		endFetch(req.isLatest())
	}
}

async function loadMedia(album) {
	const albumId = album.id
	const hasCache = mediaReadyFor.value === albumId || hydrateMediaCache(albumId)
	if (mediaReadyFor.value !== albumId) {
		// different album: keep previous tiles only if cache hydrated for this album
		if (!hasCache) media.value = []
	}
	const req = startRequest(mediaAbort)
	beginFetch(hasCache)
	try {
		const { data } = await axios.get(generateUrl('/apps/gallery/api/media'), {
			params: noCacheParams({ albumId }),
			headers: { 'Cache-Control': 'no-cache' },
			signal: req.signal,
		})
		if (!req.isLatest() || currentAlbum.value?.id !== albumId) return
		media.value = data.items || []
		mediaReadyFor.value = albumId
		writeViewCache(mediaCacheKey(albumId), { items: media.value })
	} catch (e) {
		if (isAbortError(e) || !req.isLatest()) return
		if (!hasCache) {
			error.value = e?.response?.data?.message || e.message || 'Failed to load media'
		}
	} finally {
		endFetch(req.isLatest())
	}
}

let refreshTimer = null
function scheduleFilesystemRefresh() {
	if (refreshTimer) clearTimeout(refreshTimer)
	refreshTimer = setTimeout(() => {
		refreshTimer = null
		refreshFromFilesystem()
	}, 200)
}

async function refreshFromFilesystem() {
	if (viewerIndex.value !== null || pickCoverMode.value) return
	if (folderPickerOpen.value) {
		await openFolderPickerPath(folderPickerPath.value)
		return
	}
	if (currentAlbum.value) {
		await loadMedia(currentAlbum.value)
		return
	}
	await loadAlbums()
}

function notifyFilesApp(created) {
	if (!created?.fileId) return
	const uid = getCurrentUser()?.uid
	if (!uid) return
	const relative = String(created.relativePath || '').replace(/^\/+/, '')
	const source = generateRemoteUrl(`dav/files/${uid}/${relative}`)
	try {
		const folder = new Folder({
			source,
			id: created.fileId,
			mtime: new Date(),
			owner: uid,
			permissions: Permission.ALL,
			root: `/files/${uid}`,
		})
		emit('files:node:created', folder)
	} catch {
		// Folder node construction can fail if dav helpers are unavailable
	}
	const payload = {
		type: 'created',
		fileId: created.fileId,
		path: relative,
		at: Date.now(),
	}
	try {
		localStorage.setItem(FILES_SYNC_STORAGE_KEY, JSON.stringify(payload))
		const bc = new BroadcastChannel(FILES_SYNC_CHANNEL)
		bc.postMessage(payload)
		bc.close()
	} catch {
		// ignore storage / BroadcastChannel failures
	}
}

function ctx() {
	return {
		showHidden: showHidden.value,
		parentGroupId: parentGroupId.value,
	}
}

async function post(path, body) {
	const { data } = await axios.post(generateUrl(path), { ...ctx(), ...body })
	applyListPayload(data)
	return data
}

function onAlbumClick(album, event) {
	if (event.ctrlKey || event.metaKey || event.shiftKey) {
		toggleSelect(album)
		return
	}
	if (selectedAlbumIds.value.length) {
		toggleSelect(album)
		return
	}
	openAlbum(album)
}

function toggleSelect(album) {
	if (album.isFavorites) return
	const id = album.id
	if (selectedAlbumIds.value.includes(id)) {
		selectedAlbumIds.value = selectedAlbumIds.value.filter((x) => x !== id)
	} else {
		selectedAlbumIds.value = [...selectedAlbumIds.value, id]
	}
}

async function openAlbum(album) {
	selectedAlbumIds.value = []
	clearMediaSelection()
	if (album.isGroup) {
		groupStack.value = [...groupStack.value, album]
		currentAlbum.value = null
		await loadAlbums()
		return
	}
	currentAlbum.value = album
	await loadMedia(album)
}

function clearMediaSelection() {
	selectedMediaIds.value = []
	selectMode.value = false
}

function toggleSelectMode() {
	selectMode.value = !selectMode.value
	if (!selectMode.value) selectedMediaIds.value = []
}

function toggleMediaSelect(item) {
	const id = item.id
	if (selectedMediaIds.value.includes(id)) {
		selectedMediaIds.value = selectedMediaIds.value.filter((x) => x !== id)
	} else {
		selectedMediaIds.value = [...selectedMediaIds.value, id]
	}
	if (selectedMediaIds.value.length) selectMode.value = true
}

async function goBack() {
	if (viewerIndex.value !== null) {
		closeViewer()
		return
	}
	if (folderPickerOpen.value) {
		if (folderPickerPath.value) {
			const parts = folderPickerPath.value.split('/').filter(Boolean)
			parts.pop()
			await openFolderPickerPath(parts.join('/'))
			return
		}
		closeFolderPicker()
		return
	}
	if (pickCoverMode.value) {
		pickCoverMode.value = false
		return
	}
	if (selectedMediaIds.value.length || selectMode.value) {
		clearMediaSelection()
		return
	}
	if (currentAlbum.value) {
		currentAlbum.value = null
		media.value = []
		await loadAlbums()
		return
	}
	if (groupStack.value.length) {
		groupStack.value = groupStack.value.slice(0, -1)
		await loadAlbums()
	}
}

async function onSortChange() {
	try {
		const next = { ...(metadata.value || {}), albumsSortMode: sortMode.value }
		const { data } = await axios.put(generateUrl('/apps/gallery/api/metadata'), next)
		metadata.value = data
		await loadAlbums()
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to save sort'
	}
}

async function onMediaSortChange() {
	try {
		const next = { ...(metadata.value || {}), mediaSortOrder: mediaSortOrder.value }
		const { data } = await axios.put(generateUrl('/apps/gallery/api/metadata'), next)
		metadata.value = data
		if (currentAlbum.value) {
			mediaReadyFor.value = null
			await loadMedia(currentAlbum.value)
		}
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to save sort'
	}
}

let foldersRequestPath = ''

async function openFolderPickerPath(path) {
	const targetPath = path || ''
	foldersRequestPath = targetPath
	const key = foldersCacheKey(targetPath)
	const cached = readViewCache(key)
	const hasCache = !!cached?.folders
	if (hasCache) {
		folderPickerPath.value = cached.path || targetPath
		folderPickerFolders.value = cached.folders
		foldersReadyFor.value = targetPath
	} else {
		folderPickerPath.value = targetPath
		folderPickerFolders.value = []
		foldersReadyFor.value = null
	}
	const req = startRequest(foldersAbort)
	beginFetch(hasCache)
	try {
		const { data } = await axios.get(generateUrl('/apps/gallery/api/folders'), {
			params: noCacheParams({ path: targetPath }),
			headers: { 'Cache-Control': 'no-cache' },
			signal: req.signal,
		})
		if (!req.isLatest() || !folderPickerOpen.value || foldersRequestPath !== targetPath) return
		folderPickerPath.value = data.path || ''
		folderPickerFolders.value = data.folders || []
		foldersReadyFor.value = folderPickerPath.value
		writeViewCache(key, {
			path: folderPickerPath.value,
			folders: folderPickerFolders.value,
		})
	} catch (e) {
		if (isAbortError(e) || !req.isLatest() || foldersRequestPath !== targetPath) return
		if (!hasCache) {
			error.value = e?.response?.data?.message || e.message || 'Failed to load folders'
		}
	} finally {
		endFetch(req.isLatest() && foldersRequestPath === targetPath)
	}
}

async function promptCreateAlbum() {
	error.value = ''
	folderPickerOpen.value = true
	selectedAlbumIds.value = []
	await openFolderPickerPath('')
}

function closeFolderPicker() {
	folderPickerOpen.value = false
	folderPickerPath.value = ''
	folderPickerFolders.value = []
}

function askName({ title, label = 'Name', hint = '', confirmLabel = 'Create', initial = '' }) {
	return new Promise((resolve) => {
		nameDialog.value = {
			open: true,
			title,
			label,
			hint,
			confirmLabel,
			value: initial,
			resolve,
		}
		nextTick(() => {
			nameDialogInput.value?.focus?.()
			nameDialogInput.value?.select?.()
		})
	})
}

function cancelNameDialog() {
	const resolve = nameDialog.value.resolve
	nameDialog.value = {
		open: false,
		title: '',
		label: '',
		hint: '',
		confirmLabel: 'Create',
		value: '',
		resolve: null,
	}
	resolve?.(null)
}

function submitNameDialog() {
	const value = String(nameDialog.value.value || '').trim()
	if (!value) return
	const resolve = nameDialog.value.resolve
	nameDialog.value = {
		open: false,
		title: '',
		label: '',
		hint: '',
		confirmLabel: 'Create',
		value: '',
		resolve: null,
	}
	resolve?.(value)
}

async function confirmCreateAlbumHere() {
	const name = await askName({
		title: 'Create album',
		label: 'Album name',
		hint: folderPickerPath.value
			? `Will be created in /${folderPickerPath.value}`
			: 'Will be created in Files root',
		confirmLabel: 'Create',
	})
	if (!name) return
	const parentPath = folderPickerPath.value
	try {
		refreshing.value = true
		groupStack.value = []
		currentAlbum.value = null
		const { data } = await axios.post(generateUrl('/apps/gallery/api/albums'), {
			name,
			parentPath,
			showHidden: showHidden.value,
			parentGroupId: null,
		})
		notifyFilesApp(data.created)
		closeFolderPicker()
		applyListPayload(data)
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to create album'
	} finally {
		refreshing.value = false
	}
}

async function promptCreateGroup() {
	const name = await askName({
		title: 'Create group',
		label: 'Group name',
		hint: selectedAlbumIds.value.length
			? `${selectedAlbumIds.value.length} selected album(s) will be added`
			: 'You can add albums later',
		confirmLabel: 'Create',
	})
	if (!name) return
	try {
		await post('/apps/gallery/api/groups', {
			name,
			albumIds: selectedAlbumIds.value.filter((id) => !String(id).startsWith('group-')),
		})
		selectedAlbumIds.value = []
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to create group'
	}
}

async function toggleShowHidden() {
	showHidden.value = !showHidden.value
	await loadAlbums()
}

async function toggleMerge() {
	try {
		await post('/apps/gallery/api/merge', { enabled: !mergeSameName.value })
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to toggle merge'
	}
}

async function hideSelected(hidden) {
	if (!selectedAlbumIds.value.length) return
	try {
		await post('/apps/gallery/api/albums/hidden', {
			albumIds: selectedAlbumIds.value,
			hidden,
		})
		selectedAlbumIds.value = []
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to update hidden'
	}
}

async function hideCurrentAlbum() {
	if (!currentAlbum.value) return
	try {
		await post('/apps/gallery/api/albums/hidden', {
			albumIds: [currentAlbum.value.id],
			hidden: true,
		})
		currentAlbum.value = null
		media.value = []
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to hide album'
	}
}

async function promptAddSelectedToGroup() {
	const groups = albums.value.filter((a) => a.isGroup)
	if (!groups.length) {
		await promptCreateGroup()
		return
	}
	const labels = groups.map((g, i) => `${i + 1}. ${g.name}`).join('\n')
	const choice = window.prompt(`Add to group number:\n${labels}\n\nOr type 0 to create a new group`)
	if (choice === null) return
	const n = Number(choice)
	if (n === 0) {
		await promptCreateGroup()
		return
	}
	const group = groups[n - 1]
	if (!group) return
	try {
		await post('/apps/gallery/api/groups/add', {
			groupId: group.id,
			albumIds: selectedAlbumIds.value.filter((id) => !String(id).startsWith('group-')),
		})
		selectedAlbumIds.value = []
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to add to group'
	}
}

function startPickCover() {
	pickCoverMode.value = true
	clearMediaSelection()
}

function relativeUserPath(path) {
	const raw = String(path || '')
	return raw.replace(/^\/[^/]+\/files\/?/, '') || raw.replace(/^\//, '')
}

function openViewer(item) {
	if (window.OCA?.Viewer?.open) {
		const relative = item.relativePath || relativeUserPath(item.path) || item.name
		const list = media.value.map((m) => ({
			fileid: m.id,
			filename: '/' + (m.relativePath || relativeUserPath(m.path)),
			basename: m.name,
			mime: m.mimetype,
			hasPreview: true,
		}))
		window.OCA.Viewer.open({
			path: relative.startsWith('/') ? relative : `/${relative}`,
			list,
		})
		return
	}
	const idx = media.value.findIndex((m) => m.id === item.id)
	viewerIndex.value = idx >= 0 ? idx : 0
}

function closeViewer() {
	viewerIndex.value = null
}

function viewerStep(delta) {
	if (viewerIndex.value === null) return
	const next = viewerIndex.value + delta
	if (next < 0 || next >= media.value.length) return
	viewerIndex.value = next
}

async function onMediaClick(item, event) {
	if (pickCoverMode.value) {
		try {
			await post('/apps/gallery/api/cover', {
				albumId: currentAlbum.value.id,
				path: item.relativePath || relativeUserPath(item.path),
			})
			pickCoverMode.value = false
			const id = currentAlbum.value.id
			await loadAlbums()
			const refreshed = albums.value.find((a) => a.id === id)
			if (refreshed) currentAlbum.value = refreshed
		} catch (e) {
			error.value = e?.response?.data?.message || e.message || 'Failed to set cover'
		}
		return
	}
	if (selectMode.value || event.ctrlKey || event.metaKey || selectedMediaIds.value.length) {
		toggleMediaSelect(item)
		return
	}
	openViewer(item)
}

async function loadMoveTargets() {
	const { data } = await axios.get(generateUrl('/apps/gallery/api/albums'), {
		params: { showHidden: '1' },
	})
	const currentId = currentAlbum.value?.id
	moveTargets.value = (data.albums || []).filter((a) => (
		!a.isGroup
		&& !a.isFavorites
		&& !String(a.id).startsWith('merged:')
		&& a.id !== currentId
	))
}

async function promptMoveSelected() {
	if (!selectedMediaIds.value.length) return
	try {
		await loadMoveTargets()
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to load destinations'
		return
	}
	if (!moveTargets.value.length) {
		error.value = 'No other albums available'
		return
	}
	const labels = moveTargets.value.map((a, i) => `${i + 1}. ${a.name}`).join('\n')
	const choice = window.prompt(`Move to album number:\n${labels}`)
	if (choice === null) return
	const dest = moveTargets.value[Number(choice) - 1]
	if (!dest) return
	try {
		await axios.post(generateUrl('/apps/gallery/api/media/move'), {
			fileIds: selectedMediaIds.value,
			destinationAlbumId: dest.id,
		})
		clearMediaSelection()
		await loadMedia(currentAlbum.value)
		await loadAlbums()
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to move'
	}
}

async function favoriteSelected(favorite) {
	if (!selectedMediaIds.value.length) return
	try {
		await axios.post(generateUrl('/apps/gallery/api/media/favorite'), {
			fileIds: selectedMediaIds.value,
			favorite,
		})
		clearMediaSelection()
		await loadMedia(currentAlbum.value)
		if (currentAlbum.value?.isFavorites && !favorite) {
			await loadAlbums()
		}
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to update favorites'
	}
}

async function deleteSelected() {
	if (!selectedMediaIds.value.length) return
	const n = selectedMediaIds.value.length
	if (!window.confirm(`Delete ${n} item${n === 1 ? '' : 's'}? This cannot be undone from Gallery (files go to trash if enabled).`)) {
		return
	}
	try {
		await axios.post(generateUrl('/apps/gallery/api/media/delete'), {
			fileIds: selectedMediaIds.value,
		})
		clearMediaSelection()
		await loadMedia(currentAlbum.value)
		await loadAlbums()
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to delete'
	}
}

function onDragStart(album, index, event) {
	if (sortMode.value !== 'custom') {
		event.preventDefault()
		return
	}
	dragAlbumId.value = album.id
	dragFromIndex.value = index
	event.dataTransfer.effectAllowed = 'move'
	event.dataTransfer.setData('text/plain', album.id)
}

function onDragOver(album, event) {
	if (sortMode.value !== 'custom' || !dragAlbumId.value) return
	dragOverId.value = album.id
	event.dataTransfer.dropEffect = 'move'
}

function onDragLeave(album) {
	if (dragOverId.value === album.id) dragOverId.value = null
}

async function onDrop(album, index) {
	dragOverId.value = null
	const fromId = dragAlbumId.value
	dragAlbumId.value = null
	if (!fromId || fromId === album.id) return

	if (album.isGroup && !String(fromId).startsWith('group-')) {
		try {
			await post('/apps/gallery/api/groups/add', {
				groupId: album.id,
				albumIds: [fromId],
			})
		} catch (e) {
			error.value = e?.response?.data?.message || e.message || 'Failed to add to group'
		}
		return
	}

	const ids = albums.value.map((a) => a.id)
	const from = ids.indexOf(fromId)
	if (from < 0) return
	ids.splice(from, 1)
	ids.splice(index, 0, fromId)
	try {
		await post('/apps/gallery/api/order', { order: ids })
	} catch (e) {
		error.value = e?.response?.data?.message || e.message || 'Failed to reorder'
	}
}

function onKeydown(event) {
	if (event.key === 'Escape') {
		event.preventDefault()
		goBack()
		return
	}
	if (viewerIndex.value !== null) {
		if (event.key === 'ArrowLeft') viewerStep(-1)
		if (event.key === 'ArrowRight') viewerStep(1)
		return
	}
	if (!currentAlbum.value || pickCoverMode.value) return
	if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
		event.preventDefault()
		selectMode.value = true
		selectedMediaIds.value = media.value.map((m) => m.id)
		return
	}
	if ((event.key === 'Delete' || event.key === 'Backspace') && selectedMediaIds.value.length) {
		event.preventDefault()
		deleteSelected()
	}
}

watch(showHidden, () => { /* loadAlbums called by toggle */ })

function onVisibilityChange() {
	if (document.visibilityState === 'visible') {
		scheduleFilesystemRefresh()
	}
}

function onPageShow(event) {
	if (event.persisted || document.visibilityState === 'visible') {
		scheduleFilesystemRefresh()
	}
}

function onStorageSync(event) {
	if (event.key === FILES_SYNC_STORAGE_KEY && event.newValue) {
		scheduleFilesystemRefresh()
	}
}

let filesSyncChannel = null

onMounted(() => {
	hydrateAlbumsCache()
	loadAlbums()
	window.addEventListener('keydown', onKeydown)
	document.addEventListener('visibilitychange', onVisibilityChange)
	window.addEventListener('pageshow', onPageShow)
	window.addEventListener('storage', onStorageSync)
	try {
		filesSyncChannel = new BroadcastChannel(FILES_SYNC_CHANNEL)
		filesSyncChannel.onmessage = (event) => {
			if (event?.data?.source === 'files' || event?.data?.type === 'deleted' || event?.data?.type === 'moved') {
				scheduleFilesystemRefresh()
			}
		}
	} catch {
		filesSyncChannel = null
	}
})
onUnmounted(() => {
	window.removeEventListener('keydown', onKeydown)
	document.removeEventListener('visibilitychange', onVisibilityChange)
	window.removeEventListener('pageshow', onPageShow)
	window.removeEventListener('storage', onStorageSync)
	if (refreshTimer) clearTimeout(refreshTimer)
	if (filesSyncChannel) {
		filesSyncChannel.close()
		filesSyncChannel = null
	}
})
</script>

<style scoped>
.gallery-app {
	box-sizing: border-box;
	width: 100%;
	min-height: 100%;
	height: 100%;
	padding: 20px 28px 40px;
	overflow: auto;
	background:
		radial-gradient(1400px 700px at 8% -10%, color-mix(in srgb, var(--color-primary-element) 16%, transparent), transparent 55%),
		var(--color-main-background);
	color: var(--color-main-text);
	outline: none;
}

.gallery-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 18px;
	flex-wrap: wrap;
}

.gallery-header__left {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	min-width: 200px;
}

.gallery-header h1 {
	margin: 0;
	font-size: 1.8rem;
	font-weight: 700;
	letter-spacing: -0.02em;
}

.gallery-subtitle, .hint {
	margin: 4px 0 0;
	opacity: 0.7;
	font-size: 0.9rem;
}

.gallery-header__right {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	justify-content: flex-end;
}

.btn {
	border: 1px solid var(--color-border);
	background: var(--color-main-background);
	color: inherit;
	border-radius: 10px;
	padding: 8px 12px;
	cursor: pointer;
	font: inherit;
}

.btn:disabled {
	opacity: 0.4;
	cursor: default;
}

.btn.active {
	border-color: var(--color-primary-element);
	background: color-mix(in srgb, var(--color-primary-element) 18%, var(--color-main-background));
}

.btn--primary {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.folder-picker {
	display: flex;
	flex-direction: column;
	gap: 14px;
	max-width: 720px;
}

.folder-picker__crumbs {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	font-size: 0.92rem;
}

.folder-picker__crumb {
	border: 0;
	background: transparent;
	color: var(--color-primary-element);
	cursor: pointer;
	padding: 2px 4px;
	font: inherit;
}

.folder-picker__crumb.active {
	color: var(--color-main-text);
	font-weight: 600;
	cursor: default;
}

.folder-picker__sep {
	opacity: 0.45;
}

.folder-picker__list {
	list-style: none;
	margin: 0;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: 14px;
	overflow: hidden;
	background: var(--color-main-background);
}

.folder-picker__row {
	width: 100%;
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 14px 16px;
	border: 0;
	border-bottom: 1px solid var(--color-border);
	background: transparent;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.folder-picker__list li:last-child .folder-picker__row {
	border-bottom: 0;
}

.folder-picker__row:hover {
	background: color-mix(in srgb, var(--color-primary-element) 10%, var(--color-main-background));
}

.folder-picker__icon {
	width: 28px;
	height: 22px;
	border-radius: 4px 4px 2px 2px;
	background: color-mix(in srgb, var(--color-primary-element) 55%, #c9a227);
	box-shadow: inset 0 8px 0 0 color-mix(in srgb, var(--color-primary-element) 35%, #e0c36a);
	flex: 0 0 auto;
}

.folder-picker__chevron {
	margin-left: auto;
	opacity: 0.45;
	font-size: 1.2rem;
}

.folder-picker__empty {
	padding: 28px 16px;
	opacity: 0.7;
	text-align: center;
}

.density {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.selection-bar {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
	margin-bottom: 14px;
	padding: 10px 12px;
	border-radius: 12px;
	background: color-mix(in srgb, var(--color-primary-element) 12%, var(--color-main-background));
}

.album-grid,
.media-grid {
	display: grid;
	width: 100%;
	grid-template-columns: repeat(var(--cols, 6), minmax(0, 1fr));
	gap: 14px;
}

.album-tile {
	display: flex;
	flex-direction: column;
	gap: 8px;
	cursor: pointer;
	user-select: none;
}

.album-tile--hidden { opacity: 0.45; }
.album-tile--selected .album-tile__cover { outline: 3px solid var(--color-primary-element); }
.album-tile--drop .album-tile__cover {
	outline: 3px dashed var(--color-primary-element);
	transform: scale(1.02);
}

.album-tile__cover {
	aspect-ratio: 1;
	border-radius: 14px;
	background-size: cover;
	background-position: center;
	position: relative;
	overflow: hidden;
	box-shadow: 0 8px 22px color-mix(in srgb, var(--color-main-text) 12%, transparent);
	transition: transform 0.12s ease;
}

.album-tile:hover .album-tile__cover { transform: translateY(-2px); }

.collage {
	display: grid;
	gap: 2px;
	background: var(--color-background-dark);
}
.collage--2 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr; }
.collage--3 {
	grid-template-columns: 1fr 1fr;
	grid-template-rows: 1fr 1fr;
}
.collage--3 .collage-cell:nth-child(3) { grid-column: 1 / -1; }
.collage--4 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; }
.collage-cell {
	background-size: cover;
	background-position: center;
	min-height: 0;
}

.badge {
	position: absolute;
	top: 8px;
	left: 8px;
	background: color-mix(in srgb, var(--color-main-background) 82%, transparent);
	backdrop-filter: blur(6px);
	border-radius: 999px;
	padding: 2px 8px;
	font-size: 0.7rem;
	z-index: 1;
}
.badge--fav { color: #f5a623; }
.badge--sel { left: auto; right: 8px; }

.album-tile__meta {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 0 2px;
}
.album-tile__meta strong { font-size: 0.92rem; }
.album-tile__meta span { font-size: 0.78rem; opacity: 0.65; }

.media-tile {
	position: relative;
	aspect-ratio: 1;
	padding: 0;
	border: 0;
	border-radius: 12px;
	overflow: hidden;
	cursor: pointer;
	background: var(--color-background-dark);
}
.media-tile--selected { outline: 3px solid var(--color-primary-element); }
.media-tile img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	display: block;
}
.media-tile__video,
.media-tile__fav {
	position: absolute;
	background: rgba(0, 0, 0, 0.55);
	color: #fff;
	border-radius: 999px;
	display: grid;
	place-items: center;
	font-size: 0.75rem;
	z-index: 1;
}
.media-tile__video {
	right: 8px;
	bottom: 8px;
	width: 28px;
	height: 28px;
}
.media-tile__fav {
	left: 8px;
	top: 8px;
	width: 26px;
	height: 26px;
	color: #f5a623;
}

.lightbox {
	position: fixed;
	inset: 0;
	z-index: 10000;
	background: rgba(0, 0, 0, 0.88);
	display: flex;
	align-items: center;
	justify-content: center;
}

.lightbox .btn {
	border: 1px solid rgba(255, 255, 255, 0.35);
	background: rgba(0, 0, 0, 0.55);
	color: #fff;
	border-radius: 10px;
	padding: 8px 12px;
	cursor: pointer;
	font: inherit;
}
.lightbox__stage {
	max-width: min(96vw, 1400px);
	max-height: 86vh;
	display: flex;
	align-items: center;
	justify-content: center;
}
.lightbox__stage img,
.lightbox__stage video {
	max-width: 96vw;
	max-height: 86vh;
	object-fit: contain;
	border-radius: 8px;
	background: #000;
}
.lightbox__close {
	position: absolute;
	top: 16px;
	right: 16px;
}
.lightbox__nav {
	position: absolute;
	top: 50%;
	transform: translateY(-50%);
	font-size: 1.6rem;
	padding: 10px 14px;
}
.lightbox__nav--prev { left: 16px; }
.lightbox__nav--next { right: 16px; }
.lightbox__caption {
	position: absolute;
	bottom: 18px;
	left: 50%;
	transform: translateX(-50%);
	color: #fff;
	opacity: 0.85;
	font-size: 0.9rem;
}

.name-dialog {
	position: fixed;
	inset: 0;
	z-index: 10050;
	display: grid;
	place-items: center;
	padding: 20px;
}

.name-dialog__backdrop {
	position: absolute;
	inset: 0;
	background: rgba(0, 0, 0, 0.45);
}

.name-dialog__panel {
	position: relative;
	width: min(420px, 100%);
	display: flex;
	flex-direction: column;
	gap: 14px;
	padding: 22px 22px 18px;
	border-radius: 16px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border);
	box-shadow: 0 18px 50px color-mix(in srgb, var(--color-main-text) 22%, transparent);
}

.name-dialog__panel h2 {
	margin: 0;
	font-size: 1.2rem;
	font-weight: 700;
}

.name-dialog__hint {
	margin: 0;
	font-size: 0.9rem;
	opacity: 0.7;
}

.name-dialog__label {
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-size: 0.88rem;
}

.name-dialog__input {
	border: 1px solid var(--color-border);
	border-radius: 10px;
	padding: 10px 12px;
	font: inherit;
	background: var(--color-main-background);
	color: inherit;
}

.name-dialog__input:focus {
	outline: 2px solid color-mix(in srgb, var(--color-primary-element) 55%, transparent);
	border-color: var(--color-primary-element);
}

.name-dialog__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 4px;
}

.gallery-empty, .gallery-error { padding: 24px; opacity: 0.75; }
.gallery-empty--full { grid-column: 1 / -1; text-align: center; padding: 64px 16px; }
.gallery-error { color: var(--color-error); }

.gallery-refreshing {
	margin: 0;
	padding: 8px 0;
	font-size: 0.82rem;
	opacity: 0;
	visibility: hidden;
	white-space: nowrap;
	pointer-events: none;
	user-select: none;
}

.gallery-refreshing.is-active {
	opacity: 0.7;
	visibility: visible;
}

.is-refreshing {
	opacity: 0.92;
	transition: opacity 0.15s ease;
}

@media (max-width: 900px) {
	.album-grid, .media-grid {
		grid-template-columns: repeat(min(var(--cols, 6), 4), minmax(0, 1fr));
	}
}
</style>

<style>
#content.app-gallery {
	display: block !important;
	background-color: var(--color-main-background) !important;
}
#content.app-gallery > .gallery-root,
#content.app-gallery > #app-content-vue {
	position: absolute !important;
	inset: 0 !important;
	width: 100% !important;
	height: 100% !important;
	max-width: none !important;
	overflow: auto !important;
	background-color: var(--color-main-background) !important;
}
</style>
