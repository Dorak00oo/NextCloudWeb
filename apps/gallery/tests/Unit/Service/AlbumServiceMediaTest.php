<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gallery\Tests\Unit\Service;

use OCA\Gallery\Service\AlbumService;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AlbumServiceMediaTest extends TestCase {
	/** @var IRootFolder&MockObject */
	private IRootFolder $rootFolder;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var IURLGenerator&MockObject */
	private IURLGenerator $urlGenerator;
	/** @var IPreview&MockObject */
	private IPreview $preview;
	/** @var ITagManager&MockObject */
	private ITagManager $tagManager;
	private AlbumService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->preview = $this->createMock(IPreview::class);
		$this->tagManager = $this->createMock(ITagManager::class);
		$this->service = new AlbumService(
			$this->rootFolder,
			$this->userSession,
			$this->urlGenerator,
			$this->preview,
			$this->tagManager,
			$this->createMock(LoggerInterface::class),
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);
		$folder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')->with('admin')->willReturn($folder);
	}

	public function testMoveMediaRejectsFavoritesDestination(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Destination must be a folder album');
		$this->service->moveMedia([1], AlbumService::FAVORITES_ID);
	}

	public function testMoveMediaRejectsGroupDestination(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Destination must be a folder album');
		$this->service->moveMedia([1], 'group-abc123');
	}

	public function testCreateAlbumRejectsPathTraversalInParent(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid parent path');
		$this->service->createAlbum('Vacation', '../escape');
	}
}
