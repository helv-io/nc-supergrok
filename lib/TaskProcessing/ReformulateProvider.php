<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\TaskTypes\TextToTextReformulation;

class ReformulateProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-reformulation';
	}

	protected function taskTypeClass(): string {
		return TextToTextReformulation::class;
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid prompt');
		return [
			['role' => 'user', 'content' => 'Reformulate the following text. Use the same language as the original text. Output only the reformulation. Here is the text:' . "\n\n" . $text . "\n\n" . 'Do not mention the used language in your reformulation. Here is your reformulation in the same language:'],
		];
	}
}
