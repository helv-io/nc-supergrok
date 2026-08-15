<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Settings;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private SettingsService $settingsService,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('admin', $this->settingsService->getAdminConfig());
		Util::addScript(AppConstants::APP_ID, 'admin');
		Util::addStyle(AppConstants::APP_ID, 'settings');
		return new TemplateResponse(AppConstants::APP_ID, 'admin');
	}

	public function getSection(): string {
		return 'ai';
	}

	public function getPriority(): int {
		return 10;
	}
}
