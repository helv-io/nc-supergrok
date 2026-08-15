<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\TaskTypes\TextToTextTopics;

class TopicsProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-topics';
	}

	protected function taskTypeClass(): string {
		return TextToTextTopics::class;
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid prompt');
		return [
			['role' => 'system', 'content' => 'Extract topics from the following text. Detect the language of the text. Use the same language as the text. Output only the topics, comma separated.'],
			['role' => 'user', 'content' => $text],
		];
	}
}
