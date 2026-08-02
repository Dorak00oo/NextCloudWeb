/*!
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Loaded with the Files app to keep the folder tree in sync with Gallery
 * album create/delete across tabs (and after navigating from Gallery).
 */

import { getCurrentUser } from '@nextcloud/auth'
import { emit, subscribe } from '@nextcloud/event-bus'
import { Folder, Permission } from '@nextcloud/files'
import { generateRemoteUrl } from '@nextcloud/router'

const CHANNEL = 'nc-gallery-files-sync'
const KEY = 'nc-gallery-files-sync'

/**
 * @param {object} payload
 */
function applyCreated(payload) {
	if (!payload?.fileId || !payload?.path) {
		return
	}
	const uid = getCurrentUser()?.uid
	if (!uid) {
		return
	}
	const relative = String(payload.path).replace(/^\/+/, '')
	try {
		emit('files:node:created', new Folder({
			source: generateRemoteUrl(`dav/files/${uid}/${relative}`),
			id: Number(payload.fileId),
			mtime: new Date(),
			owner: uid,
			permissions: Permission.ALL,
			root: `/files/${uid}`,
		}))
	} catch (error) {
		console.warn('[gallery] could not sync created folder to Files', error)
	}
}

/**
 * @param {object} payload
 */
function publish(payload) {
	try {
		localStorage.setItem(KEY, JSON.stringify(payload))
	} catch {
		// ignore quota / private mode
	}
	try {
		const bc = new BroadcastChannel(CHANNEL)
		bc.postMessage(payload)
		bc.close()
	} catch {
		// ignore
	}
}

function consumePending() {
	try {
		const raw = localStorage.getItem(KEY)
		if (!raw) {
			return
		}
		const data = JSON.parse(raw)
		if (!data || Date.now() - (data.at || 0) > 120000) {
			return
		}
		if (data.type === 'created') {
			applyCreated(data)
		}
	} catch {
		// ignore
	}
}

consumePending()

window.addEventListener('storage', (event) => {
	if (event.key !== KEY || !event.newValue) {
		return
	}
	try {
		const data = JSON.parse(event.newValue)
		if (data?.type === 'created') {
			applyCreated(data)
		}
	} catch {
		// ignore
	}
})

try {
	const bc = new BroadcastChannel(CHANNEL)
	bc.onmessage = (event) => {
		if (event.data?.type === 'created') {
			applyCreated(event.data)
		}
	}
} catch {
	// ignore
}

// Bridge Files → Gallery tabs (do not re-broadcast creates; avoids loops)
let publishing = false

subscribe('files:node:deleted', (node) => {
	if (!node || publishing) {
		return
	}
	publishing = true
	try {
		publish({
			type: 'deleted',
			fileId: node.fileid ?? null,
			path: String(node.path || '').replace(/^\/+/, ''),
			at: Date.now(),
			source: 'files',
		})
	} finally {
		publishing = false
	}
})

subscribe('files:node:moved', (event) => {
	if (publishing) {
		return
	}
	const node = event?.node ?? event
	publishing = true
	try {
		publish({
			type: 'moved',
			fileId: node?.fileid ?? null,
			path: String(node?.path || '').replace(/^\/+/, ''),
			at: Date.now(),
			source: 'files',
		})
	} finally {
		publishing = false
	}
})
