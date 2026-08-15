<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\TaskTypes\TextToTextHeadline;

class HeadlineProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-headline';
	}

	protected function taskTypeClass(): string {
		return TextToTextHeadline::class;
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid prompt');
		return [
			['role' => 'user', 'content' => 'Give me the headline of the following text in its original language. Do not output the language. Output only the headline without any quotes or additional punctuation.' . "\n\n" . $text],
		];
	}
}
