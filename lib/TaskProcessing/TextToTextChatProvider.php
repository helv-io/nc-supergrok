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
use OCP\TaskProcessing\TaskTypes\TextToTextChat;

class TextToTextChatProvider implements ISynchronousProvider {
	public function __construct(
		private ClientFactory $clientFactory,
		private SettingsService $settingsService,
		private IL10N $l,
	) {
	}

	public function getId(): string {
		return AppConstants::APP_ID . '-text2text-chat';
	}

	public function getName(): string {
		return AppConstants::APP_NAME;
	}

	public function getTaskTypeId(): string {
		return TextToTextChat::ID;
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
			'model' => new ShapeDescriptor(
				$this->l->t('Model'),
				$this->l->t('The SuperGrok chat model'),
				EShapeType::Text,
			),
		];
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [];
	}

	public function getOptionalInputShapeDefaults(): array {
		return [
			'max_tokens' => AppConstants::DEFAULT_MAX_TOKENS,
			'model' => $this->settingsService->getChatModel(),
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
			throw new ProcessingException('Invalid input');
		}
		if (!isset($input['system_prompt']) || !is_string($input['system_prompt'])) {
			throw new ProcessingException('Invalid system_prompt');
		}
		if (!isset($input['history']) || !is_array($input['history'])) {
			throw new ProcessingException('Invalid history');
		}

		$model = (isset($input['model']) && is_string($input['model']) && $input['model'] !== '')
			? $input['model']
			: $this->settingsService->getChatModel();
		$maxTokens = (isset($input['max_tokens']) && is_int($input['max_tokens']))
			? $input['max_tokens']
			: AppConstants::DEFAULT_MAX_TOKENS;

		$messages = [];
		if ($input['system_prompt'] !== '') {
			$messages[] = ['role' => 'system', 'content' => $input['system_prompt']];
		}
		foreach ($input['history'] as $item) {
			$decoded = is_string($item) ? json_decode($item, true) : $item;
			if (!is_array($decoded)) {
				continue;
			}
			$role = (string)($decoded['role'] ?? 'user');
			if ($role === 'human') {
				$role = 'user';
			}
			if (!in_array($role, ['user', 'assistant', 'system'], true)) {
				continue;
			}
			$messages[] = [
				'role' => $role,
				'content' => (string)($decoded['content'] ?? ''),
			];
		}
		$messages[] = ['role' => 'user', 'content' => $input['input']];

		$client = $this->requireClient($userId);
		try {
			$result = $client->chat($model, $messages, $maxTokens);
		} catch (GrokClientException $e) {
			throw new ProcessingException($e->getMessage());
		}
		if ($result['text'] === '') {
			throw new ProcessingException('No result in SuperGrok response.');
		}
		return ['output' => $result['text']];
	}

	private function requireClient(?string $userId): GrokClient {
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
