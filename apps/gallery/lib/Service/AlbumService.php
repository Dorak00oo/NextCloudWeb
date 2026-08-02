<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gallery\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\ITags;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

/**
 * Derives Samsung-style albums from folders that contain media,
 * using the same /.gallery/albums-meta.json contract as the Android APK.
 */
class AlbumService {
	public const REMOTE_DIR = '/.gallery';
	public const REMOTE_PATH = '/.gallery/albums-meta.json';
	public const FAVORITES_ID = 'favorites';

	private bool $metadataNeedsPersist = false;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private IPreview $previewManager,
		private ITagManager $tagManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function listAlbums(?string $parentGroupId = null, bool $showHidden = false): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$derived = $this->deriveFolderAlbums($userFolder);
		$this->applyPinnedAlbums($userFolder, $metadata, $derived);
		if ($this->metadataNeedsPersist) {
			// Best-effort: never fail album listing because metadata file is briefly locked
			// (common right after deleting a folder from Files while Gallery refreshes).
			$this->persistMetadata($userFolder, $metadata, false);
			$this->metadataNeedsPersist = false;
		}
		$favorites = $this->buildFavoritesAlbum($userFolder);
		if ($favorites !== null) {
			$derived[self::FAVORITES_ID] = $favorites;
		}

		foreach ($metadata['customCovers'] as $albumId => $coverPath) {
			if (!isset($derived[$albumId])) {
				continue;
			}
			try {
				$node = $this->resolveUserFile($userFolder, (string)$coverPath);
				$derived[$albumId]['coverFileId'] = $node->getId();
				// Always expose preview URL; Movie/ffmpeg may generate on first request.
				$derived[$albumId]['coverUrl'] = $this->previewUrl($node->getId());
			} catch (NotFoundException|\InvalidArgumentException) {
				// keep derived cover
			}
		}

		$hidden = array_map([$this, 'normalizeId'], $metadata['hiddenAlbumIds']);
		$groupedIds = [];
		foreach ($metadata['groups'] as $group) {
			foreach ($group['albumIds'] as $id) {
				$groupedIds[$this->normalizeId((string)$id)] = true;
			}
		}

		if ($parentGroupId !== null) {
			$group = null;
			foreach ($metadata['groups'] as $g) {
				if (($g['id'] ?? '') === $parentGroupId) {
					$group = $g;
					break;
				}
			}
			if ($group === null) {
				return ['albums' => [], 'metadata' => $metadata];
			}
			$albums = [];
			foreach ($group['albumIds'] as $id) {
				$key = (string)$id;
				if (!isset($derived[$key])) {
					continue;
				}
				$isHidden = in_array($this->normalizeId($key), $hidden, true);
				if ($isHidden && !$showHidden) {
					continue;
				}
				$album = $derived[$key];
				$album['isHidden'] = $isHidden;
				$albums[] = $album;
			}
			foreach ($metadata['groups'] as $g) {
				if (($g['parentGroupId'] ?? null) === $parentGroupId) {
					$albums[] = $this->groupToAlbum($g, $derived);
				}
			}
			return [
				'albums' => $this->applyOrder($albums, $metadata),
				'metadata' => $metadata,
			];
		}

		$albums = [];
		foreach ($metadata['groups'] as $g) {
			if (($g['parentGroupId'] ?? null) === null) {
				$albums[] = $this->groupToAlbum($g, $derived);
			}
		}
		foreach ($derived as $id => $album) {
			if (isset($groupedIds[$this->normalizeId($id)])) {
				continue;
			}
			$isHidden = in_array($this->normalizeId($id), $hidden, true);
			if ($isHidden && !$showHidden) {
				continue;
			}
			$album['isHidden'] = $isHidden;
			$albums[] = $album;
		}

		if (!empty($metadata['mergeSameName'])) {
			$albums = $this->mergeSameName($albums);
		}

		return [
			'albums' => $this->disambiguate($this->applyOrder($albums, $metadata)),
			'metadata' => $metadata,
		];
	}

	/**
	 * List child folders for the create-album file tree (APK FolderPicker).
	 *
	 * @return array{path: string, folders: list<array{name: string, path: string}>}
	 */
	public function listFolders(string $path = ''): array {
		$userFolder = $this->userFolder();
		$parent = $this->normalizeParentPath($path);
		$folder = $parent === '' ? $userFolder : $userFolder->get($parent);
		if (!$folder instanceof Folder) {
			throw new \InvalidArgumentException('Not a folder');
		}
		$folders = [];
		foreach ($folder->getDirectoryListing() as $child) {
			if (!$child instanceof Folder) {
				continue;
			}
			$name = $child->getName();
			if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
				continue;
			}
			$folders[] = [
				'name' => $name,
				'path' => $this->relativePath($userFolder, $child),
			];
		}
		usort($folders, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
		return [
			'path' => $parent,
			'folders' => $folders,
		];
	}

	/**
	 * Create a folder album under an optional parent path (APK FolderPicker flow).
	 *
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function createAlbum(
		string $name,
		string $parentPath = '',
		bool $showHidden = false,
		?string $parentGroupId = null,
	): array {
		$userFolder = $this->userFolder();
		$name = trim($name);
		if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
			throw new \InvalidArgumentException('Invalid album name');
		}
		$parent = $this->normalizeParentPath($parentPath);
		$parentFolder = $parent === '' ? $userFolder : $userFolder->get($parent);
		if (!$parentFolder instanceof Folder) {
			throw new \InvalidArgumentException('Parent must be a folder');
		}
		if ($parentFolder->nodeExists($name)) {
			throw new \InvalidArgumentException('Album already exists');
		}
		$createdFolder = $parentFolder->newFolder($name);
		$relative = $parent === '' ? $name : $parent . '/' . $name;
		$metadata = $this->loadMetadata($userFolder);
		$id = $this->normalizeId($relative);
		if (!in_array($id, $metadata['pinnedAlbumPaths'], true)) {
			array_unshift($metadata['pinnedAlbumPaths'], $id);
		}
		if (!in_array($id, $metadata['albumOrder'], true)) {
			array_unshift($metadata['albumOrder'], $id);
		}
		$this->persistMetadata($userFolder, $metadata);
		$result = $this->listAlbums($parentGroupId, $showHidden);
		$result['created'] = [
			'id' => $id,
			'name' => $createdFolder->getName(),
			'relativePath' => $relative,
			'fileId' => $createdFolder->getId(),
			'path' => $createdFolder->getPath(),
		];
		return $result;
	}

	/**
	 * @param list<string> $albumIds
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function createGroup(string $name, ?string $parentGroupId = null, array $albumIds = []): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$name = trim(preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $name) ?? $name);
		if ($name === '') {
			throw new \InvalidArgumentException('Invalid group name');
		}
		$groupId = 'group-' . bin2hex(random_bytes(8));
		$metadata['groups'][] = [
			'id' => $groupId,
			'name' => $name,
			'parentGroupId' => $parentGroupId,
			'albumIds' => array_values(array_map(fn ($id) => $this->normalizeId((string)$id), $albumIds)),
		];
		array_unshift($metadata['albumOrder'], $groupId);
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, false);
	}

	/**
	 * @param list<string> $albumIds
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function setAlbumsHidden(array $albumIds, bool $hidden, bool $showHidden = false, ?string $parentGroupId = null): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		foreach ($albumIds as $rawId) {
			$id = $this->normalizeId((string)$rawId);
			if ($hidden) {
				if (!in_array($id, array_map([$this, 'normalizeId'], $metadata['hiddenAlbumIds']), true)) {
					$metadata['hiddenAlbumIds'][] = $id;
				}
			} else {
				$metadata['hiddenAlbumIds'] = array_values(array_filter(
					$metadata['hiddenAlbumIds'],
					fn ($h) => $this->normalizeId((string)$h) !== $id
				));
			}
		}
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, $showHidden);
	}

	/**
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function setMergeSameName(bool $enabled, bool $showHidden = false, ?string $parentGroupId = null): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$metadata['mergeSameName'] = $enabled;
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, $showHidden);
	}

	/**
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function setCover(string $albumId, string $filePath, bool $showHidden = false, ?string $parentGroupId = null): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$albumId = $this->normalizeId($albumId);
		$node = $this->resolveUserFile($userFolder, $filePath);
		$relative = $this->relativePath($userFolder, $node);
		$metadata['customCovers'][$albumId] = '/' . ltrim($relative, '/');
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, $showHidden);
	}

	/**
	 * @param list<string> $order
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function setAlbumOrder(array $order, bool $showHidden = false, ?string $parentGroupId = null): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$metadata['albumOrder'] = array_values(array_map(fn ($id) => (string)$id, $order));
		$metadata['albumsSortMode'] = 'custom';
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, $showHidden);
	}

	/**
	 * @param list<string> $albumIds
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function addToGroup(array $albumIds, string $groupId, bool $showHidden = false, ?string $parentGroupId = null): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$found = false;
		foreach ($metadata['groups'] as &$group) {
			if (($group['id'] ?? '') !== $groupId) {
				continue;
			}
			$found = true;
			foreach ($albumIds as $rawId) {
				$id = $this->normalizeId((string)$rawId);
				if (!in_array($id, $group['albumIds'], true)) {
					$group['albumIds'][] = $id;
				}
			}
		}
		unset($group);
		if (!$found) {
			throw new \InvalidArgumentException('Group not found');
		}
		// remove from other groups
		foreach ($metadata['groups'] as &$group) {
			if (($group['id'] ?? '') === $groupId) {
				continue;
			}
			$group['albumIds'] = array_values(array_filter(
				$group['albumIds'],
				fn ($id) => !in_array($this->normalizeId((string)$id), array_map([$this, 'normalizeId'], $albumIds), true)
			));
		}
		unset($group);
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, $showHidden);
	}

	/**
	 * @return array{albums: list<array<string, mixed>>, metadata: array<string, mixed>}
	 */
	public function deleteGroup(string $groupId, bool $showHidden = false, ?string $parentGroupId = null): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$toDelete = [$groupId];
		// cascade subgroups
		$changed = true;
		while ($changed) {
			$changed = false;
			foreach ($metadata['groups'] as $g) {
				$parent = $g['parentGroupId'] ?? null;
				$id = $g['id'] ?? '';
				if ($parent !== null && in_array($parent, $toDelete, true) && !in_array($id, $toDelete, true)) {
					$toDelete[] = $id;
					$changed = true;
				}
			}
		}
		$metadata['groups'] = array_values(array_filter(
			$metadata['groups'],
			fn ($g) => !in_array($g['id'] ?? '', $toDelete, true)
		));
		$metadata['albumOrder'] = array_values(array_filter(
			$metadata['albumOrder'],
			fn ($id) => !in_array((string)$id, $toDelete, true)
		));
		$this->persistMetadata($userFolder, $metadata);
		return $this->listAlbums($parentGroupId, $showHidden);
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private function persistMetadata(Folder $userFolder, array $metadata, bool $required = true): void {
		$normalized = $this->normalizeMetadata($metadata);
		$normalized['updatedAt'] = (int)round(microtime(true) * 1000);
		try {
			$this->writeMetadata($userFolder, $normalized);
		} catch (LockedException $e) {
			if ($required) {
				throw $e;
			}
			$this->logger->warning('Gallery metadata write skipped because file is locked', [
				'app' => 'gallery',
				'exception' => $e,
			]);
		}
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @param array<string, array<string, mixed>> $derived
	 */
	private function applyPinnedAlbums(Folder $userFolder, array &$metadata, array &$derived): void {
		$stillPinned = [];
		foreach ($metadata['pinnedAlbumPaths'] as $raw) {
			$id = $this->normalizeId((string)$raw);
			$path = trim($id, '/');
			try {
				$folder = $path === '' ? $userFolder : $userFolder->get($path);
			} catch (NotFoundException) {
				continue;
			}
			if (!$folder instanceof Folder) {
				continue;
			}
			$stillPinned[] = $id;
			if (isset($derived[$id])) {
				continue;
			}
			$parent = $folder->getParent();
			$derived[$id] = [
				'id' => $id,
				'name' => $folder->getName(),
				'count' => 0,
				'parentName' => ($parent && $parent->getPath() !== $userFolder->getPath()) ? $parent->getName() : '',
				'folderFileId' => $folder->getId(),
				'coverFileId' => null,
				'coverUrl' => null,
				'newest' => 0,
				'isFavorites' => false,
				'isGroup' => false,
				'mergedFolderIds' => [],
				'isHidden' => false,
			];
		}
		$removed = array_values(array_diff(
			array_map(fn ($p) => $this->normalizeId((string)$p), $metadata['pinnedAlbumPaths']),
			$stillPinned,
		));
		if ($removed !== []) {
			$metadata['pinnedAlbumPaths'] = $stillPinned;
			$metadata['albumOrder'] = array_values(array_filter(
				$metadata['albumOrder'],
				fn ($id) => !in_array($this->normalizeId((string)$id), $removed, true),
			));
			foreach ($removed as $gone) {
				unset($metadata['customCovers'][$gone], $metadata['customCovers'][trim($gone, '/')]);
			}
			foreach ($metadata['groups'] as &$group) {
				$group['albumIds'] = array_values(array_filter(
					$group['albumIds'],
					fn ($id) => !in_array($this->normalizeId((string)$id), $removed, true),
				));
			}
			unset($group);
			$this->metadataNeedsPersist = true;
		} else {
			$metadata['pinnedAlbumPaths'] = $stillPinned;
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listAlbumMedia(string $albumId): array {
		$userFolder = $this->userFolder();
		$metadata = $this->loadMetadata($userFolder);
		$sort = (string)($metadata['mediaSortOrder'] ?? 'sort_new_to_old');

		if ($albumId === self::FAVORITES_ID || $albumId === '/' . self::FAVORITES_ID) {
			$nodes = $this->favoriteMediaNodes($userFolder);
		} elseif (str_starts_with($albumId, 'merged:')) {
			$paths = explode('|', substr($albumId, strlen('merged:')));
			$nodes = [];
			foreach ($paths as $path) {
				$nodes = array_merge($nodes, $this->directMediaInPath($userFolder, $path));
			}
		} elseif (str_starts_with($albumId, 'group-')) {
			return [];
		} else {
			$nodes = $this->directMediaInPath($userFolder, $albumId);
		}

		$favoriteIds = $this->favoriteFileIdSet($nodes);
		$items = array_map(fn (File $file) => $this->fileToMediaItem($file, isset($favoriteIds[$file->getId()])), $nodes);
		return $this->sortMedia($items, $sort);
	}

	/**
	 * @param list<int> $fileIds
	 * @return array{moved: int}
	 */
	public function moveMedia(array $fileIds, string $destinationAlbumId): array {
		$userFolder = $this->userFolder();
		$destId = $this->normalizeId($destinationAlbumId);
		if ($destId === self::FAVORITES_ID || str_starts_with($destId, 'group-') || str_starts_with($destId, 'merged:')) {
			throw new \InvalidArgumentException('Destination must be a folder album');
		}
		$folderPath = trim($destId, '/');
		$dest = $folderPath === '' ? $userFolder : $userFolder->get($folderPath);
		if (!$dest instanceof Folder) {
			throw new \InvalidArgumentException('Destination album not found');
		}
		$moved = 0;
		foreach ($fileIds as $rawId) {
			$file = $this->fileById($userFolder, (int)$rawId);
			$name = $file->getName();
			$target = $dest->getFullPath($name);
			if ($file->getPath() === $target) {
				continue;
			}
			if ($dest->nodeExists($name)) {
				throw new \InvalidArgumentException('File already exists in destination: ' . $name);
			}
			$file->move($target);
			$moved++;
		}
		return ['moved' => $moved];
	}

	/**
	 * @param list<int> $fileIds
	 * @return array{deleted: int}
	 */
	public function deleteMedia(array $fileIds): array {
		$userFolder = $this->userFolder();
		$deleted = 0;
		foreach ($fileIds as $rawId) {
			$file = $this->fileById($userFolder, (int)$rawId);
			$file->delete();
			$deleted++;
		}
		return ['deleted' => $deleted];
	}

	/**
	 * @param list<int> $fileIds
	 * @return array{updated: int, favorite: bool}
	 */
	public function setMediaFavorite(array $fileIds, bool $favorite): array {
		$userFolder = $this->userFolder();
		$tagger = $this->tagManager->load('files');
		$updated = 0;
		foreach ($fileIds as $rawId) {
			$fileId = (int)$rawId;
			$this->fileById($userFolder, $fileId); // ensure accessible
			if ($favorite) {
				$tagger->addToFavorites($fileId);
			} else {
				$tagger->removeFromFavorites($fileId);
			}
			$updated++;
		}
		return ['updated' => $updated, 'favorite' => $favorite];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getMetadata(): array {
		return $this->loadMetadata($this->userFolder());
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	public function saveMetadata(array $metadata): array {
		$userFolder = $this->userFolder();
		$normalized = $this->normalizeMetadata($metadata);
		$normalized['updatedAt'] = (int)round(microtime(true) * 1000);
		$this->writeMetadata($userFolder, $normalized);
		return $normalized;
	}

	private function userFolder(): Folder {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new NotPermittedException('Not logged in');
		}
		return $this->rootFolder->getUserFolder($user->getUID());
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function deriveFolderAlbums(Folder $userFolder): array {
		$media = array_merge(
			$userFolder->searchByMime('image'),
			$userFolder->searchByMime('video'),
		);

		/** @var array<string, array{count: int, newest: int, cover: ?File, name: string, parentName: string}> $buckets */
		$buckets = [];
		foreach ($media as $node) {
			if (!$node instanceof File) {
				continue;
			}
			$path = $node->getPath();
			// Skip app data / gallery meta
			$relative = $this->relativePath($userFolder, $node);
			if (str_starts_with($relative, '.gallery/') || $relative === '.gallery') {
				continue;
			}
			$parent = $node->getParent();
			if ($parent->getId() === $userFolder->getId()) {
				// Media in root: virtual album for root
				$albumId = '/';
				$albumName = '/';
				$parentName = '';
			} else {
				$albumId = $this->normalizeId($this->relativePath($userFolder, $parent));
				$albumName = $parent->getName();
				try {
					$grand = $parent->getParent();
					$parentName = $grand->getId() === $userFolder->getId() ? '' : $grand->getName();
				} catch (\Throwable) {
					$parentName = '';
				}
			}

			if (!isset($buckets[$albumId])) {
				$buckets[$albumId] = [
					'count' => 0,
					'newest' => 0,
					'cover' => null,
					'name' => $albumName,
					'parentName' => $parentName,
					'folderFileId' => $parent->getId(),
				];
			}
			$buckets[$albumId]['count']++;
			$mtime = $node->getMTime();
			if ($mtime >= $buckets[$albumId]['newest']) {
				$buckets[$albumId]['newest'] = $mtime;
				$buckets[$albumId]['cover'] = $node;
			}
		}

		$albums = [];
		foreach ($buckets as $id => $bucket) {
			/** @var ?File $cover */
			$cover = $bucket['cover'];
			$albums[$id] = [
				'id' => $id,
				'name' => $bucket['name'] === '/' ? 'Root' : $bucket['name'],
				'count' => $bucket['count'],
				'parentName' => $bucket['parentName'],
				'folderFileId' => $bucket['folderFileId'],
				'coverFileId' => $cover?->getId(),
				'coverUrl' => $cover ? $this->previewUrl($cover->getId()) : null,
				'newest' => $bucket['newest'],
				'isFavorites' => false,
				'isGroup' => false,
				'mergedFolderIds' => [],
			];
		}
		return $albums;
	}

	/**
	 * @return ?array<string, mixed>
	 */
	private function buildFavoritesAlbum(Folder $userFolder): ?array {
		$nodes = $this->favoriteMediaNodes($userFolder);
		if ($nodes === []) {
			return null;
		}
		usort($nodes, fn (File $a, File $b) => $b->getMTime() <=> $a->getMTime());
		$cover = $nodes[0];
		return [
			'id' => self::FAVORITES_ID,
			'name' => 'Favorites',
			'count' => count($nodes),
			'parentName' => '',
			'folderFileId' => null,
			'coverFileId' => $cover->getId(),
			'coverUrl' => $this->previewUrl($cover->getId()),
			'newest' => $cover->getMTime(),
			'isFavorites' => true,
			'isGroup' => false,
			'mergedFolderIds' => [],
		];
	}

	/**
	 * @return list<File>
	 */
	private function favoriteMediaNodes(Folder $userFolder): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [];
		}
		$tagged = $userFolder->searchByTag(ITags::TAG_FAVORITE, $user->getUID());
		$out = [];
		foreach ($tagged as $node) {
			if (!$node instanceof File) {
				continue;
			}
			$mime = $node->getMimetype();
			if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/')) {
				$out[] = $node;
			}
		}
		return $out;
	}

	/**
	 * @return list<File>
	 */
	private function directMediaInPath(Folder $userFolder, string $albumId): array {
		$path = trim($albumId, '/');
		try {
			$folder = $path === '' ? $userFolder : $userFolder->get($path);
		} catch (NotFoundException) {
			return [];
		}
		if (!$folder instanceof Folder) {
			return [];
		}
		$out = [];
		foreach ($folder->getDirectoryListing() as $child) {
			if (!$child instanceof File) {
				continue;
			}
			$mime = $child->getMimetype();
			if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/')) {
				$out[] = $child;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fileToMediaItem(File $file, bool $isFavorite = false): array {
		$mime = $file->getMimetype();
		$relative = $this->relativePath($this->userFolder(), $file);
		return [
			'id' => $file->getId(),
			'name' => $file->getName(),
			'path' => $file->getPath(),
			'relativePath' => $relative,
			'mimetype' => $mime,
			'isVideo' => str_starts_with($mime, 'video/'),
			'isFavorite' => $isFavorite,
			'size' => $file->getSize(),
			'mtime' => $file->getMTime(),
			'previewUrl' => $this->previewUrl($file->getId()),
			'sourceUrl' => $this->sourceUrl($relative),
		];
	}

	/**
	 * @param list<File> $nodes
	 * @return array<int, true>
	 */
	private function favoriteFileIdSet(array $nodes): array {
		if ($nodes === []) {
			return [];
		}
		$ids = array_map(static fn (File $f) => $f->getId(), $nodes);
		$tagsById = $this->tagManager->load('files')->getTagsForObjects($ids);
		$out = [];
		foreach ($tagsById as $fileId => $tags) {
			if (is_array($tags) && in_array(ITags::TAG_FAVORITE, $tags, true)) {
				$out[(int)$fileId] = true;
			}
		}
		return $out;
	}

	private function fileById(Folder $userFolder, int $fileId): File {
		$nodes = $userFolder->getById($fileId);
		foreach ($nodes as $node) {
			if ($node instanceof File) {
				return $node;
			}
		}
		throw new NotFoundException('File not found: ' . $fileId);
	}

	/**
	 * @param list<array<string, mixed>> $items
	 * @return list<array<string, mixed>>
	 */
	private function sortMedia(array $items, string $sort): array {
		usort($items, match ($sort) {
			'sort_a_to_z' => fn ($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']),
			'sort_z_to_a' => fn ($a, $b) => strcasecmp((string)$b['name'], (string)$a['name']),
			'sort_old_to_new' => fn ($a, $b) => ($a['mtime'] <=> $b['mtime']),
			'sort_small_to_big' => fn ($a, $b) => ($a['size'] <=> $b['size']),
			'sort_big_to_small' => fn ($a, $b) => ($b['size'] <=> $a['size']),
			default => fn ($a, $b) => ($b['mtime'] <=> $a['mtime']),
		});
		return $items;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function loadMetadata(Folder $userFolder): array {
		try {
			$node = $userFolder->get(ltrim(self::REMOTE_PATH, '/'));
			if ($node instanceof File) {
				$content = $node->getContent();
				$data = json_decode($content, true);
				if (is_array($data)) {
					return $this->normalizeMetadata($data);
				}
			}
		} catch (NotFoundException) {
			// empty defaults
		}
		return $this->normalizeMetadata([]);
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private function writeMetadata(Folder $userFolder, array $metadata): void {
		$dirName = ltrim(self::REMOTE_DIR, '/');
		try {
			$folder = $userFolder->get($dirName);
		} catch (NotFoundException) {
			$folder = $userFolder->newFolder($dirName);
		}
		if (!$folder instanceof Folder) {
			throw new NotPermittedException('Cannot create gallery metadata folder');
		}
		$json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			throw new NotPermittedException('Cannot encode gallery metadata');
		}
		$fileName = 'albums-meta.json';
		$attempts = 0;
		$last = null;
		while ($attempts < 5) {
			$attempts++;
			try {
				try {
					$file = $folder->get($fileName);
					if ($file instanceof File) {
						$file->putContent($json);
						return;
					}
				} catch (NotFoundException) {
					$folder->newFile($fileName, $json);
					return;
				}
				$folder->newFile($fileName, $json);
				return;
			} catch (LockedException $e) {
				$last = $e;
				usleep(40_000 * $attempts);
			}
		}
		if ($last instanceof LockedException) {
			throw $last;
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function normalizeMetadata(array $data): array {
		return [
			'version' => (int)($data['version'] ?? 1),
			'albumOrder' => array_values(array_map('strval', $data['albumOrder'] ?? [])),
			'hiddenAlbumIds' => array_values(array_map('strval', $data['hiddenAlbumIds'] ?? [])),
			'customCovers' => is_array($data['customCovers'] ?? null) ? $data['customCovers'] : [],
			'groups' => array_values(array_map(function ($g) {
				return [
					'id' => (string)($g['id'] ?? ''),
					'name' => (string)($g['name'] ?? ''),
					'parentGroupId' => $g['parentGroupId'] ?? null,
					'albumIds' => array_values(array_map('strval', $g['albumIds'] ?? [])),
				];
			}, is_array($data['groups'] ?? null) ? $data['groups'] : [])),
			'mergeSameName' => (bool)($data['mergeSameName'] ?? false),
			'pinnedAlbumPaths' => array_values(array_map('strval', $data['pinnedAlbumPaths'] ?? [])),
			'albumsSortMode' => (string)($data['albumsSortMode'] ?? 'custom'),
			'mediaSortOrder' => (string)($data['mediaSortOrder'] ?? 'sort_new_to_old'),
			'updatedAt' => (int)($data['updatedAt'] ?? 0),
		];
	}

	/**
	 * @param array<string, mixed> $group
	 * @param array<string, array<string, mixed>> $derived
	 * @return array<string, mixed>
	 */
	private function groupToAlbum(array $group, array $derived): array {
		$count = 0;
		$newest = 0;
		$coverUrl = null;
		$coverFileId = null;
		$memberCovers = [];
		foreach ($group['albumIds'] as $id) {
			if (!isset($derived[$id])) {
				continue;
			}
			$count += (int)$derived[$id]['count'];
			if ((int)$derived[$id]['newest'] >= $newest) {
				$newest = (int)$derived[$id]['newest'];
				$coverUrl = $derived[$id]['coverUrl'];
				$coverFileId = $derived[$id]['coverFileId'];
			}
			if (!empty($derived[$id]['coverUrl']) && count($memberCovers) < 4) {
				$memberCovers[] = [
					'coverUrl' => $derived[$id]['coverUrl'],
					'coverFileId' => $derived[$id]['coverFileId'],
				];
			}
		}
		return [
			'id' => $group['id'],
			'name' => $group['name'],
			'count' => $count,
			'parentName' => '',
			'folderFileId' => null,
			'coverFileId' => $coverFileId,
			'coverUrl' => $coverUrl,
			'memberCovers' => $memberCovers,
			'newest' => $newest,
			'isFavorites' => false,
			'isGroup' => true,
			'mergedFolderIds' => [],
		];
	}

	/**
	 * @param list<array<string, mixed>> $albums
	 * @param array<string, mixed> $metadata
	 * @return list<array<string, mixed>>
	 */
	private function applyOrder(array $albums, array $metadata): array {
		$mode = (string)($metadata['albumsSortMode'] ?? 'custom');
		if ($mode === 'newest') {
			usort($albums, fn ($a, $b) => ($b['newest'] <=> $a['newest']));
			return $albums;
		}
		if ($mode === 'name') {
			usort($albums, fn ($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));
			return $albums;
		}
		$order = array_map([$this, 'normalizeId'], $metadata['albumOrder']);
		usort($albums, function ($a, $b) use ($order) {
			$ia = array_search($this->normalizeId((string)$a['id']), $order, true);
			$ib = array_search($this->normalizeId((string)$b['id']), $order, true);
			$ia = $ia === false ? PHP_INT_MAX : $ia;
			$ib = $ib === false ? PHP_INT_MAX : $ib;
			if ($ia === $ib) {
				return strcasecmp((string)$a['name'], (string)$b['name']);
			}
			return $ia <=> $ib;
		});
		return $albums;
	}

	/**
	 * @param list<array<string, mixed>> $albums
	 * @return list<array<string, mixed>>
	 */
	private function mergeSameName(array $albums): array {
		$byName = [];
		foreach ($albums as $album) {
			if (!empty($album['isGroup']) || !empty($album['isFavorites'])) {
				$byName['__keep_' . $album['id']] = $album;
				continue;
			}
			$key = mb_strtolower((string)$album['name']);
			if (!isset($byName[$key])) {
				$byName[$key] = $album;
				continue;
			}
			$existing = $byName[$key];
			$ids = array_merge(
				$existing['mergedFolderIds'] ?: [$existing['folderFileId']],
				$album['mergedFolderIds'] ?: [$album['folderFileId']],
			);
			$byName[$key] = [
				'id' => 'merged:' . trim((string)$existing['id'], '/') . '|' . trim((string)$album['id'], '/'),
				'name' => $existing['name'],
				'count' => (int)$existing['count'] + (int)$album['count'],
				'parentName' => '',
				'folderFileId' => null,
				'coverFileId' => ((int)$album['newest'] > (int)$existing['newest']) ? $album['coverFileId'] : $existing['coverFileId'],
				'coverUrl' => ((int)$album['newest'] > (int)$existing['newest']) ? $album['coverUrl'] : $existing['coverUrl'],
				'newest' => max((int)$existing['newest'], (int)$album['newest']),
				'isFavorites' => false,
				'isGroup' => false,
				'mergedFolderIds' => array_values(array_filter($ids)),
			];
		}
		return array_values($byName);
	}

	/**
	 * @param list<array<string, mixed>> $albums
	 * @return list<array<string, mixed>>
	 */
	private function disambiguate(array $albums): array {
		$counts = [];
		foreach ($albums as $album) {
			$key = mb_strtolower((string)$album['name']);
			$counts[$key] = ($counts[$key] ?? 0) + 1;
		}
		foreach ($albums as &$album) {
			$key = mb_strtolower((string)$album['name']);
			$album['showParent'] = ($counts[$key] ?? 0) > 1 && $album['parentName'] !== '';
		}
		return $albums;
	}

	private function relativePath(Folder $userFolder, \OCP\Files\Node $node): string {
		$root = rtrim($userFolder->getPath(), '/');
		$full = $node->getPath();
		if ($full === $root) {
			return '';
		}
		return ltrim(substr($full, strlen($root)), '/');
	}

	/**
	 * Normalize a parent path that may be absolute (/user/files/...) or relative.
	 */
	private function normalizeParentPath(string $parentPath): string {
		$path = str_replace('\\', '/', $parentPath);
		$path = ltrim($path, '/');
		if ($path === '' || $path === '.') {
			return '';
		}
		if (preg_match('#^[^/]+/files/(.*)$#', $path, $matches) === 1) {
			$path = $matches[1];
		}
		$path = trim($path, '/');
		if ($path === '' || $path === '.') {
			return '';
		}
		if (str_contains($path, '..')) {
			throw new \InvalidArgumentException('Invalid parent path');
		}
		return $path;
	}

	/**
	 * Resolve a path that may be absolute (/user/files/...) or relative to the user folder.
	 */
	private function resolveUserFile(Folder $userFolder, string $filePath): File {
		$path = ltrim($filePath, '/');
		$userRoot = rtrim($userFolder->getPath(), '/'); // e.g. /admin/files
		$absolute = '/' . $path;
		if ($absolute === $userRoot || str_starts_with($absolute, $userRoot . '/')) {
			$path = ltrim(substr($absolute, strlen($userRoot)), '/');
		} elseif (preg_match('#^[^/]+/files/(.*)$#', $path, $matches) === 1) {
			$path = $matches[1];
		}
		try {
			$node = $userFolder->get($path);
		} catch (NotFoundException $e) {
			throw $e;
		}
		if (!$node instanceof File) {
			throw new \InvalidArgumentException('Cover must be a file');
		}
		$mime = $node->getMimetype();
		if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
			throw new \InvalidArgumentException('Cover must be an image or video');
		}
		return $node;
	}

	private function normalizeId(string $id): string {
		if ($id === self::FAVORITES_ID || str_starts_with($id, 'group-') || str_starts_with($id, 'merged:')) {
			return $id;
		}
		$t = trim($id);
		if ($t === '' || $t === '/') {
			return '/';
		}
		$t = trim($t, '/');
		return $t . '/';
	}

	private function previewUrl(int $fileId): string {
		return $this->urlGenerator->linkToRouteAbsolute('core.Preview.getPreviewByFileId', [
			'fileId' => $fileId,
			'x' => 512,
			'y' => 512,
			'a' => 1,
		]);
	}

	private function sourceUrl(string $relativePath): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return '';
		}
		$parts = array_map('rawurlencode', explode('/', ltrim($relativePath, '/')));
		return rtrim($this->urlGenerator->linkToRemote('dav'), '/')
			. '/files/' . rawurlencode($user->getUID()) . '/' . implode('/', $parts);
	}
}
