<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Dorak00oo and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Gallery\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialState,
		private IL10N $l10n,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		Util::addStyle('gallery', 'main');
		Util::addScript('gallery', 'main', 'theming');

		$this->initialState->provideInitialState('appName', 'gallery');

		return new TemplateResponse('gallery', 'index', [
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => null,
			'pageTitle' => $this->l10n->t('Gallery'),
		]);
	}
}
