<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCA\SuperGrok\AppConstants;
use OCA\SuperGrok\Exception\GrokClientException;
use OCA\SuperGrok\Exception\GrokOAuthException;
use OCA\SuperGrok\Service\ClientFactory;
use OCA\SuperGrok\Service\GrokClient;
use OCA\SuperGrok\Service\SettingsService;
use OCP\Files\File;
use OCP\IL10N;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\Exception\ProcessingException;
use OCP\TaskProcessing\ISynchronousProvider;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use Psr\Log\LoggerInterface;

class AudioToTextProvider implements ISynchronousProvider {
	public function __construct(
		private ClientFactory $clientFactory,
		private SettingsService $settingsService,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return AppConstants::APP_ID . '-audio2text';
	}

	public function getName(): string {
		return AppConstants::APP_NAME;
	}

	public function getTaskTypeId(): string {
		return AudioToText::ID;
	}

	public function getExpectedRuntime(): int {
		return 30;
	}

	public function getInputShapeEnumValues(): array {
		return [];
	}

	public function getInputShapeDefaults(): array {
		return [];
	}

	public function getOptionalInputShape(): array {
		return [
			'language' => new ShapeDescriptor(
				$this->l->t('Language'),
				$this->l->t('The language of the audio file'),
				EShapeType::Text,
			),
		];
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalInputShapeDefaults(): array {
		return [
			'language' => 'en',
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
		if (!isset($input['input']) || !$input['input'] instanceof File || !$input['input']->isReadable()) {
			throw new ProcessingException('Invalid input file');
		}
		$inputFile = $input['input'];
		$language = $input['language'] ?? 'en';
		if (!is_string($language)) {
			throw new ProcessingException('Invalid language');
		}

		$client = $this->requireClient($userId);
		try {
			$text = $client->stt(
				$inputFile->getContent(),
				$inputFile->getName(),
				$inputFile->getMimeType() ?: 'audio/wav',
				$language,
			);
		} catch (GrokClientException $e) {
			$this->logger->warning('SuperGrok STT failed: ' . $e->getMessage(), [
				'app' => AppConstants::APP_ID,
				'exception' => $e,
			]);
			throw new ProcessingException($e->getMessage());
		}
		return ['output' => $text];
	}

	private function requireClient(?string $userId): GrokClient {
		if ($userId === null || $userId === '') {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
		if (!$this->settingsService->isSttEnabled()) {
			throw new ProcessingException('SuperGrok Voice STT is disabled by the administrator');
		}
		try {
			return $this->clientFactory->forUser($userId);
		} catch (GrokOAuthException $e) {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
	}
}
