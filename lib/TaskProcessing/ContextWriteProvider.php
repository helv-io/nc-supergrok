<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\TaskTypes\ContextWrite;

class ContextWriteProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-contextwrite';
	}

	protected function taskTypeClass(): string {
		return ContextWrite::class;
	}

	protected function buildMessages(array $input): array {
		$style = $this->requireString($input, 'style_input', 'Invalid inputs');
		$source = $this->requireString($input, 'source_input', 'Invalid inputs');
		$prompt = "You're a professional copywriter tasked with copying an instructed or demonstrated *WRITING STYLE*"
			. ' and writing a text on the provided *SOURCE MATERIAL*.'
			. " \n*WRITING STYLE*:\n$style\n\n*SOURCE MATERIAL*:\n\n$source\n\n"
			. 'Now write a text in the same style detailed or demonstrated under *WRITING STYLE* using the *SOURCE MATERIAL*'
			. ' as source of facts and instruction on what to write about.'
			. ' Do not invent any facts or events yourself.'
			. ' Also, use the *WRITING STYLE* as a guide for how to write the text ONLY and not as a source of facts or events.'
			. ' Detect the language used in the *SOURCE_MATERIAL*. Make sure to use the same language in your response. Do not mention the language explicitly.';
		return [
			['role' => 'user', 'content' => $prompt],
		];
	}
}
