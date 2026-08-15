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
use OCP\TaskProcessing\TaskTypes\TextToImage;
use Psr\Log\LoggerInterface;

class TextToImageProvider implements ISynchronousProvider {
	public function __construct(
		private ClientFactory $clientFactory,
		private SettingsService $settingsService,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	public function getId(): string {
		return AppConstants::APP_ID . '-text2image';
	}

	public function getName(): string {
		return AppConstants::APP_NAME;
	}

	public function getTaskTypeId(): string {
		return TextToImage::ID;
	}

	public function getExpectedRuntime(): int {
		return 45;
	}

	public function getInputShapeEnumValues(): array {
		return [];
	}

	public function getInputShapeDefaults(): array {
		return [
			'numberOfImages' => 1,
		];
	}

	public function getOptionalInputShape(): array {
		return [
			'model' => new ShapeDescriptor(
				$this->l->t('Model'),
				$this->l->t('The SuperGrok Imagine model'),
				EShapeType::Text,
			),
		];
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalInputShapeDefaults(): array {
		return [
			'model' => $this->settingsService->getImageModel(),
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
		$n = 1;
		if (isset($input['numberOfImages']) && is_int($input['numberOfImages'])) {
			$n = max(1, min(4, $input['numberOfImages']));
		}
		$model = (isset($input['model']) && is_string($input['model']) && $input['model'] !== '')
			? $input['model']
			: $this->settingsService->getImageModel();

		$client = $this->requireClient($userId);
		try {
			$rows = $client->generateImage($input['input'], $model, $n);
		} catch (GrokClientException $e) {
			$this->logger->warning('SuperGrok Imagine failed: ' . $e->getMessage(), [
				'app' => AppConstants::APP_ID,
				'exception' => $e,
			]);
			throw new ProcessingException($e->getMessage());
		}

		$images = [];
		foreach ($rows as $row) {
			if ($row['b64_json'] !== null) {
				$bytes = base64_decode($row['b64_json'], true);
				if ($bytes !== false && $bytes !== '') {
					$images[] = $bytes;
					continue;
				}
			}
			if ($row['url'] !== null) {
				try {
					$images[] = $client->downloadBytes($row['url']);
				} catch (GrokClientException $e) {
					$this->logger->warning('SuperGrok Imagine download failed: ' . $e->getMessage(), [
						'app' => AppConstants::APP_ID,
					]);
				}
			}
		}
		if ($images === []) {
			throw new ProcessingException('Grok Imagine returned no images');
		}
		return ['images' => $images];
	}

	private function requireClient(?string $userId): GrokClient {
		if ($userId === null || $userId === '') {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
		if (!$this->settingsService->isImageEnabled()) {
			throw new ProcessingException('SuperGrok Imagine is disabled by the administrator');
		}
		try {
			return $this->clientFactory->forUser($userId);
		} catch (GrokOAuthException $e) {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
	}
}
