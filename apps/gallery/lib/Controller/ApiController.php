<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gallery\Controller;

use OCA\Gallery\Service\AlbumService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AlbumService $albumService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function albums(?string $parentGroupId = null, ?string $showHidden = null): DataResponse {
		$show = filter_var($showHidden ?? 'false', FILTER_VALIDATE_BOOLEAN);
		return $this->respond($this->albumService->listAlbums($parentGroupId, $show));
	}

	#[NoAdminRequired]
	public function media(string $albumId): DataResponse {
		return $this->respond([
			'items' => $this->albumService->listAlbumMedia($albumId),
		]);
	}

	#[NoAdminRequired]
	public function getMetadata(): DataResponse {
		return $this->respond($this->albumService->getMetadata());
	}

	#[NoAdminRequired]
	public function putMetadata(): DataResponse {
		$data = $this->jsonBody();
		return $this->respond($this->albumService->saveMetadata($data));
	}

	#[NoAdminRequired]
	public function folders(?string $path = null): DataResponse {
		try {
			return $this->respond($this->albumService->listFolders((string)($path ?? '')));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function createAlbum(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->createAlbum(
				(string)($data['name'] ?? ''),
				(string)($data['parentPath'] ?? ''),
				(bool)($data['showHidden'] ?? false),
				isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function createGroup(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->createGroup(
				(string)($data['name'] ?? ''),
				isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
				array_values($data['albumIds'] ?? []),
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function setHidden(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->setAlbumsHidden(
				array_values($data['albumIds'] ?? []),
				(bool)($data['hidden'] ?? true),
				(bool)($data['showHidden'] ?? false),
				isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function setMerge(): DataResponse {
		$data = $this->jsonBody();
		return $this->respond($this->albumService->setMergeSameName(
			(bool)($data['enabled'] ?? false),
			(bool)($data['showHidden'] ?? false),
			isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
		));
	}

	#[NoAdminRequired]
	public function setCover(): DataResponse {
		try {
			$data = $this->jsonBody();
			$path = (string)($data['path'] ?? '');
			if (preg_match('#^/[^/]+/files/(.+)$#', $path, $m)) {
				$path = $m[1];
			}
			return $this->respond($this->albumService->setCover(
				(string)($data['albumId'] ?? ''),
				$path,
				(bool)($data['showHidden'] ?? false),
				isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function setOrder(): DataResponse {
		$data = $this->jsonBody();
		return $this->respond($this->albumService->setAlbumOrder(
			array_values($data['order'] ?? []),
			(bool)($data['showHidden'] ?? false),
			isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
		));
	}

	#[NoAdminRequired]
	public function addToGroup(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->addToGroup(
				array_values($data['albumIds'] ?? []),
				(string)($data['groupId'] ?? ''),
				(bool)($data['showHidden'] ?? false),
				isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function deleteGroup(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->deleteGroup(
				(string)($data['groupId'] ?? ''),
				(bool)($data['showHidden'] ?? false),
				isset($data['parentGroupId']) ? (string)$data['parentGroupId'] : null,
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function moveMedia(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->moveMedia(
				array_map('intval', $data['fileIds'] ?? []),
				(string)($data['destinationAlbumId'] ?? ''),
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function deleteMedia(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->deleteMedia(
				array_map('intval', $data['fileIds'] ?? []),
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function setFavorite(): DataResponse {
		try {
			$data = $this->jsonBody();
			return $this->respond($this->albumService->setMediaFavorite(
				array_map('intval', $data['fileIds'] ?? []),
				(bool)($data['favorite'] ?? true),
			));
		} catch (\Throwable $e) {
			return $this->respond(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function respond(array $data, int $status = Http::STATUS_OK): DataResponse {
		$response = new DataResponse($data, $status);
		$response->cacheFor(0);
		return $response;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function jsonBody(): array {
		$input = file_get_contents('php://input');
		$data = is_string($input) ? json_decode($input, true) : null;
		return is_array($data) ? $data : [];
	}
}
