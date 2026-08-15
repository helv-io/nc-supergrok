<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\ShapeEnumValue;
use OCP\TaskProcessing\TaskTypes\TextToTextChangeTone;

class ChangeToneProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-changetone';
	}

	protected function taskTypeClass(): string {
		return TextToTextChangeTone::class;
	}

	public function getInputShapeEnumValues(): array {
		return [
			'tone' => [
				new ShapeEnumValue($this->l->t('Less wordy'), 'less wordy'),
				new ShapeEnumValue($this->l->t('Simpler'), 'simpler'),
				new ShapeEnumValue($this->l->t('More convincing'), 'more convincing'),
				new ShapeEnumValue($this->l->t('Less buzzwords'), 'less buzzwords'),
				new ShapeEnumValue($this->l->t('Friendlier'), 'friendlier'),
				new ShapeEnumValue($this->l->t('More formal'), 'more formal'),
				new ShapeEnumValue($this->l->t('More urgent'), 'more urgent'),
				new ShapeEnumValue($this->l->t('Funnier'), 'funnier'),
				new ShapeEnumValue($this->l->t('More passionate'), 'more passionate'),
				new ShapeEnumValue($this->l->t('Less emotional'), 'less emotional'),
				new ShapeEnumValue($this->l->t('More casual'), 'more casual'),
			],
		];
	}

	public function getInputShapeDefaults(): array {
		return [
			'tone' => 'less wordy',
		];
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid input text');
		$tone = isset($input['tone']) && is_string($input['tone']) && $input['tone'] !== ''
			? $input['tone']
			: 'less wordy';
		return [
			['role' => 'user', 'content' => 'Reformulate the following text in a ' . $tone . ' tone in its original language. Output only the reformulation. Here is the text:' . "\n\n" . $text . "\n\n" . 'Do not mention the used language in your reformulation. Here is your reformulation in the same language:'],
		];
	}
}
