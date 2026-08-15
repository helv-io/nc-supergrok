<?php

declare(strict_types=1);

namespace OCA\SuperGrok\AppInfo;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Service\SettingsService;
use OCA\SuperGrok\TaskProcessing\AudioToTextProvider;
use OCA\SuperGrok\TaskProcessing\ChangeToneProvider;
use OCA\SuperGrok\TaskProcessing\ContextWriteProvider;
use OCA\SuperGrok\TaskProcessing\EmojiProvider;
use OCA\SuperGrok\TaskProcessing\HeadlineProvider;
use OCA\SuperGrok\TaskProcessing\ProofreadProvider;
use OCA\SuperGrok\TaskProcessing\ReformulateProvider;
use OCA\SuperGrok\TaskProcessing\SummaryProvider;
use OCA\SuperGrok\TaskProcessing\TextToImageProvider;
use OCA\SuperGrok\TaskProcessing\TextToSpeechProvider;
use OCA\SuperGrok\TaskProcessing\TextToTextChatProvider;
use OCA\SuperGrok\TaskProcessing\TextToTextChatWithToolsProvider;
use OCA\SuperGrok\TaskProcessing\TextToTextProvider;
use OCA\SuperGrok\TaskProcessing\TopicsProvider;
use OCA\SuperGrok\TaskProcessing\TranslateProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = AppConstants::APP_ID;

	public function __construct(array $urlParams = []) {
		parent::__construct(AppConstants::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Providers are registered at bootstrap (no user yet). process()
		// still requires a per-user SuperGrok session.
		$settings = $this->settings();

		if ($settings->isTextEnabled()) {
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToText', TextToTextProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextChat', TextToTextChatProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextChatWithTools', TextToTextChatWithToolsProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextSummary', SummaryProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextHeadline', HeadlineProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextTopics', TopicsProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextReformulation', ReformulateProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextChangeTone', ChangeToneProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextProofread', ProofreadProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\ContextWrite', ContextWriteProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\GenerateEmoji', EmojiProvider::class);
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToTextTranslate', TranslateProvider::class);
		}
		if ($settings->isImageEnabled()) {
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToImage', TextToImageProvider::class);
		}
		if ($settings->isSttEnabled()) {
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\AudioToText', AudioToTextProvider::class);
		}
		if ($settings->isTtsEnabled()) {
			$this->registerIfTypeExists($context, 'OCP\\TaskProcessing\\TaskTypes\\TextToSpeech', TextToSpeechProvider::class);
		}
	}

	public function boot(IBootContext $context): void {
	}

	private function registerIfTypeExists(IRegistrationContext $context, string $taskType, string $provider): void {
		if (class_exists($taskType)) {
			$context->registerTaskProcessingProvider($provider);
		}
	}

	private function settings(): SettingsService {
		try {
			return $this->getContainer()->get(SettingsService::class);
		} catch (\Throwable $e) {
			return new SettingsService($this->getContainer()->get(\OCP\IConfig::class));
		}
	}
}
