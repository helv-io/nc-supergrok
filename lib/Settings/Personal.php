<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Settings;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Service\TokenStore;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private TokenStore $tokenStore,
		private ?string $userId,
	) {
	}

	public function getForm(): TemplateResponse {
		$account = ['signedIn' => false, 'email' => '', 'name' => ''];
		if ($this->userId !== null) {
			$account = $this->tokenStore->getPublicAccount($this->userId);
		}
		$this->initialState->provideInitialState('personal', [
			'signedIn' => $account['signedIn'],
			'email' => $account['email'],
			'name' => $account['name'],
			'redirectUri' => AppConstants::OAUTH_REDIRECT_URI,
		]);
		Util::addScript(AppConstants::APP_ID, 'personal');
		Util::addStyle(AppConstants::APP_ID, 'settings');
		return new TemplateResponse(AppConstants::APP_ID, 'personal');
	}

	public function getSection(): string {
		return 'ai';
	}

	public function getPriority(): int {
		return 10;
	}
}
