<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Exception\GrokClientException;
use OCA\SuperGrok\Exception\GrokOAuthException;
use OCA\SuperGrok\Service\ClientFactory;
use OCA\SuperGrok\Service\GrokClient;
use OCA\SuperGrok\Service\SettingsService;
use OCP\IL10N;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\Exception\ProcessingException;
use OCP\TaskProcessing\ISynchronousProvider;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\TaskTypes\TextToSpeech;
use Psr\Log\LoggerInterface;

class TextToSpeechProvider implements ISynchronousProvider {
	public function __construct(
		private ClientFactory $clientFactory,
		private SettingsService $settingsService,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return AppConstants::APP_ID . '-text2speech';
	}

	public function getName(): string {
		return AppConstants::APP_NAME;
	}

	public function getTaskTypeId(): string {
		return TextToSpeech::ID;
	}

	public function getExpectedRuntime(): int {
		return 20;
	}

	public function getInputShapeEnumValues(): array {
		return [];
	}

	public function getInputShapeDefaults(): array {
		return [];
	}

	public function getOptionalInputShape(): array {
		return [
			'voice' => new ShapeDescriptor(
				$this->l->t('Voice'),
				$this->l->t('The SuperGrok TTS voice'),
				EShapeType::Text,
			),
			'speed' => new ShapeDescriptor(
				$this->l->t('Speed'),
				$this->l->t('Speech speed modifier'),
				EShapeType::Number,
			),
		];
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalInputShapeDefaults(): array {
		return [
			'voice' => $this->settingsService->getTtsVoice(),
			'speed' => 1,
		];
	}

	public function getOptionalOutputShape(): array {
		return [];
	}

	public function getOutputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalOutputShapeEnumValues(): array {
		return [];
	}

	public function process(?string $userId, array $input, callable $reportProgress): array {
		if (!isset($input['input']) || !is_string($input['input'])) {
			throw new ProcessingException('Invalid prompt');
		}
		$voice = (isset($input['voice']) && is_string($input['voice']) && $input['voice'] !== '')
			? $input['voice']
			: $this->settingsService->getTtsVoice();
		$speed = 1.0;
		if (isset($input['speed']) && is_numeric($input['speed'])) {
			$speed = (float)$input['speed'];
		}

		$client = $this->requireClient($userId);
		try {
			[$audio] = $client->tts($input['input'], $voice, 'en', 'mp3', 24000, $speed);
		} catch (GrokClientException $e) {
			$this->logger->warning('SuperGrok TTS failed: ' . $e->getMessage(), [
				'app' => AppConstants::APP_ID,
				'exception' => $e,
			]);
			throw new ProcessingException($e->getMessage());
		}
		return ['speech' => $audio];
	}

	private function requireClient(?string $userId): GrokClient {
		if ($userId === null || $userId === '') {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
		if (!$this->settingsService->isTtsEnabled()) {
			throw new ProcessingException('SuperGrok Voice TTS is disabled by the administrator');
		}
		try {
			return $this->clientFactory->forUser($userId);
		} catch (GrokOAuthException $e) {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
	}
}
