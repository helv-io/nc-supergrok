<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\ShapeEnumValue;
use OCP\TaskProcessing\TaskTypes\TextToTextTranslate;

class TranslateProvider extends AbstractTextProvider {
	private const LANGUAGES = [
		['en', 'English'],
		['zh', 'Chinese'],
		['de', 'German'],
		['es', 'Spanish'],
		['fr', 'French'],
		['it', 'Italian'],
		['ja', 'Japanese'],
		['ko', 'Korean'],
		['pt', 'Portuguese'],
		['ru', 'Russian'],
		['ar', 'Arabic'],
		['hi', 'Hindi'],
		['nl', 'Dutch'],
		['pl', 'Polish'],
		['tr', 'Turkish'],
		['vi', 'Vietnamese'],
	];

	protected function providerIdSuffix(): string {
		return 'text2text-translate';
	}

	protected function taskTypeClass(): string {
		return TextToTextTranslate::class;
	}

	public function getInputShapeEnumValues(): array {
		$values = [];
		foreach (self::LANGUAGES as $language) {
			$values[] = new ShapeEnumValue($language[1], $language[0]);
		}
		$detect = new ShapeEnumValue($this->l->t('Detect language'), 'detect_language');
		return [
			'origin_language' => array_merge([$detect], $values),
			'target_language' => $values,
		];
	}

	public function getInputShapeDefaults(): array {
		return [
			'origin_language' => 'detect_language',
		];
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid input text');
		if (trim($text) === '') {
			throw new \OCP\TaskProcessing\Exception\ProcessingException('Input text cannot be empty');
		}
		$from = isset($input['origin_language']) && is_string($input['origin_language'])
			? $input['origin_language']
			: 'detect_language';
		$to = isset($input['target_language']) && is_string($input['target_language'])
			? $input['target_language']
			: 'en';
		$fromLabel = $from === 'detect_language' ? 'the detected source language' : $from;
		return [
			['role' => 'user', 'content' => 'Translate the following text from ' . $fromLabel . ' to ' . $to . '. Detect the language if needed. Output only the translation. Do not mention the languages.' . "\n\n" . $text],
		];
	}
}
