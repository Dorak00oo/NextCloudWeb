<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gallery\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'gallery';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$context->injectFn($this->registerFilesSync(...));
	}

	/**
	 * Keep Files folder tree in sync with albums created from Gallery.
	 */
	private function registerFilesSync(): void {
		Util::addScript(self::APP_ID, 'files-sync', 'files');
	}
}
