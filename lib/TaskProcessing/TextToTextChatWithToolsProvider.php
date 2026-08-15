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
use OCP\TaskProcessing\TaskTypes\TextToTextChatWithTools;

class TextToTextChatWithToolsProvider implements ISynchronousProvider {
	public function __construct(
		private ClientFactory $clientFactory,
		private SettingsService $settingsService,
		private IL10N $l,
	) {
	}

	public function getId(): string {
		return AppConstants::APP_ID . '-text2text-chat-tools';
	}

	public function getName(): string {
		return AppConstants::APP_NAME;
	}

	public function getTaskTypeId(): string {
		return TextToTextChatWithTools::ID;
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
		if (!isset($input['input']) || !is_string($input['input'])) {
			throw new ProcessingException('Invalid input');
		}
		if (!isset($input['system_prompt']) || !is_string($input['system_prompt'])) {
			throw new ProcessingException('Invalid system_prompt');
		}
		if (!isset($input['tool_message']) || !is_string($input['tool_message'])) {
			throw new ProcessingException('Invalid tool_message');
		}
		if (!isset($input['tools']) || !is_string($input['tools'])) {
			throw new ProcessingException('Invalid tools');
		}
		if (!isset($input['history']) || !is_array($input['history'])) {
			throw new ProcessingException('Invalid history');
		}

		$tools = json_decode($input['tools'], true);
		if (!is_array($tools) || !array_is_list($tools)) {
			throw new ProcessingException('Invalid JSON tools');
		}

		$messages = [];
		if ($input['system_prompt'] !== '') {
			$messages[] = ['role' => 'system', 'content' => $input['system_prompt']];
		}
		foreach ($input['history'] as $item) {
			$decoded = is_string($item) ? json_decode($item, true) : $item;
			if (!is_array($decoded)) {
				continue;
			}
			$mapped = $this->mapHistoryItem($decoded);
			if ($mapped !== null) {
				$messages[] = $mapped;
			}
		}
		if ($input['tool_message'] !== '') {
			$toolMessages = json_decode($input['tool_message'], true);
			if (is_array($toolMessages)) {
				foreach ($toolMessages as $toolMessage) {
					if (!is_array($toolMessage)) {
						continue;
					}
					$messages[] = [
						'role' => 'tool',
						'name' => (string)($toolMessage['name'] ?? ''),
						'tool_call_id' => (string)($toolMessage['tool_call_id'] ?? ''),
						'content' => (string)($toolMessage['content'] ?? ''),
					];
				}
			}
		}
		if ($input['input'] !== '') {
			$messages[] = ['role' => 'user', 'content' => $input['input']];
		}

		$maxTokens = (isset($input['max_tokens']) && is_int($input['max_tokens']))
			? $input['max_tokens']
			: AppConstants::DEFAULT_MAX_TOKENS;

		$client = $this->requireClient($userId);
		try {
			$result = $client->chat(
				$this->settingsService->getChatModel(),
				$messages,
				$maxTokens,
				null,
				$tools !== [] ? $tools : null,
			);
		} catch (GrokClientException $e) {
			throw new ProcessingException($e->getMessage());
		}

		$toolCallsJson = $result['tool_calls'] !== []
			? (string)json_encode($result['tool_calls'])
			: '';
		if ($result['text'] === '' && $toolCallsJson === '') {
			throw new ProcessingException('No result in SuperGrok response.');
		}
		return [
			'output' => $result['text'],
			'tool_calls' => $toolCallsJson,
		];
	}

	/**
	 * @param array<string, mixed> $decoded
	 * @return array<string, mixed>|null
	 */
	private function mapHistoryItem(array $decoded): ?array {
		$role = (string)($decoded['role'] ?? 'user');
		if ($role === 'human') {
			$role = 'user';
		}
		if ($role === 'tool') {
			return [
				'role' => 'tool',
				'name' => (string)($decoded['name'] ?? ''),
				'tool_call_id' => (string)($decoded['tool_call_id'] ?? ''),
				'content' => (string)($decoded['content'] ?? ''),
			];
		}
		if (!in_array($role, ['user', 'assistant', 'system'], true)) {
			return null;
		}
		$message = [
			'role' => $role,
			'content' => (string)($decoded['content'] ?? ''),
		];
		if ($role === 'assistant' && isset($decoded['tool_calls']) && is_array($decoded['tool_calls'])) {
			$openaiCalls = [];
			foreach ($decoded['tool_calls'] as $call) {
				if (!is_array($call)) {
					continue;
				}
				$openaiCalls[] = [
					'id' => (string)($call['id'] ?? ''),
					'type' => 'function',
					'function' => [
						'name' => (string)($call['name'] ?? ''),
						'arguments' => (string)json_encode($call['args'] ?? new \stdClass()),
					],
				];
			}
			if ($openaiCalls !== []) {
				$message['tool_calls'] = $openaiCalls;
			}
		}
		return $message;
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
