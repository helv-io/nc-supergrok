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

/**
 * Thin Assistant text wrapper. Subclasses set the task type and prompt.
 */
abstract class AbstractTextProvider implements ISynchronousProvider {
	public function __construct(
		protected ClientFactory $clientFactory,
		protected SettingsService $settingsService,
		protected IL10N $l,
	) {
	}

	abstract protected function providerIdSuffix(): string;

	abstract protected function taskTypeClass(): string;

	/**
	 * @param array<string, mixed> $input
	 * @return list<array{role: string, content: string}>
	 */
	abstract protected function buildMessages(array $input): array;

	public function getId(): string {
		return AppConstants::APP_ID . '-' . $this->providerIdSuffix();
	}

	public function getName(): string {
		return AppConstants::APP_NAME;
	}

	public function getTaskTypeId(): string {
		$class = $this->taskTypeClass();
		return $class::ID;
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
			'max_tokens' => new ShapeDescriptor(
				$this->l->t('Maximum output tokens'),
				$this->l->t('The maximum number of tokens that can be generated.'),
				EShapeType::Number,
			),
		];
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalInputShapeDefaults(): array {
		return [
			'max_tokens' => AppConstants::DEFAULT_MAX_TOKENS,
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
		$messages = $this->buildMessages($input);
		$maxTokens = (isset($input['max_tokens']) && is_int($input['max_tokens']))
			? $input['max_tokens']
			: AppConstants::DEFAULT_MAX_TOKENS;
		$client = $this->requireClient($userId);
		try {
			$result = $client->chat($this->settingsService->getChatModel(), $messages, $maxTokens);
		} catch (GrokClientException $e) {
			throw new ProcessingException($e->getMessage());
		}
		if ($result['text'] === '') {
			throw new ProcessingException('No result in SuperGrok response.');
		}
		$reportProgress(1.0);
		return ['output' => $result['text']];
	}

	protected function requireString(array $input, string $key, string $error): string {
		if (!isset($input[$key]) || !is_string($input[$key])) {
			throw new ProcessingException($error);
		}
		return $input[$key];
	}

	protected function requireClient(?string $userId): GrokClient {
		if ($userId === null || $userId === '') {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
		if (!$this->settingsService->isTextEnabled()) {
			throw new ProcessingException('SuperGrok conversation is disabled by the administrator');
		}
		try {
			return $this->clientFactory->forUser($userId);
		} catch (GrokOAuthException $e) {
			throw new ProcessingException('Sign in with SuperGrok under Personal settings');
		}
	}
}
