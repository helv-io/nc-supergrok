<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\TaskTypes\GenerateEmoji;

class EmojiProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-generateemoji';
	}

	protected function taskTypeClass(): string {
		return GenerateEmoji::class;
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid prompt');
		return [
			['role' => 'user', 'content' => 'Give me an emoji for the following text. Output only the emoji without any other characters.' . "\n\n" . $text],
		];
	}
}
