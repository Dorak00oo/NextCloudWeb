<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'api#albums', 'url' => '/api/albums', 'verb' => 'GET'],
		['name' => 'api#media', 'url' => '/api/media', 'verb' => 'GET'],
		['name' => 'api#getMetadata', 'url' => '/api/metadata', 'verb' => 'GET'],
		['name' => 'api#putMetadata', 'url' => '/api/metadata', 'verb' => 'PUT'],
		['name' => 'api#folders', 'url' => '/api/folders', 'verb' => 'GET'],
		['name' => 'api#createAlbum', 'url' => '/api/albums', 'verb' => 'POST'],
		['name' => 'api#createGroup', 'url' => '/api/groups', 'verb' => 'POST'],
		['name' => 'api#setHidden', 'url' => '/api/albums/hidden', 'verb' => 'POST'],
		['name' => 'api#setMerge', 'url' => '/api/merge', 'verb' => 'POST'],
		['name' => 'api#setCover', 'url' => '/api/cover', 'verb' => 'POST'],
		['name' => 'api#setOrder', 'url' => '/api/order', 'verb' => 'POST'],
		['name' => 'api#addToGroup', 'url' => '/api/groups/add', 'verb' => 'POST'],
		['name' => 'api#deleteGroup', 'url' => '/api/groups/delete', 'verb' => 'POST'],
		['name' => 'api#moveMedia', 'url' => '/api/media/move', 'verb' => 'POST'],
		['name' => 'api#deleteMedia', 'url' => '/api/media/delete', 'verb' => 'POST'],
		['name' => 'api#setFavorite', 'url' => '/api/media/favorite', 'verb' => 'POST'],
	],
];
