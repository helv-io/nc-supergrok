<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\ShapeEnumValue;
use OCP\TaskProcessing\TaskTypes\TextToTextProofread;

class ProofreadProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-proofread';
	}

	protected function taskTypeClass(): string {
		return TextToTextProofread::class;
	}

	public function getOptionalInputShape(): array {
		$shape = parent::getOptionalInputShape();
		$shape['strictness'] = new ShapeDescriptor(
			$this->l->t('Strictness'),
			$this->l->t('How thoroughly to check spelling and grammar.'),
			EShapeType::Enum,
		);
		return $shape;
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [
			'strictness' => [
				new ShapeEnumValue($this->l->t('Minimal'), 'minimal'),
				new ShapeEnumValue($this->l->t('Standard'), 'standard'),
				new ShapeEnumValue($this->l->t('Strict'), 'strict'),
			],
		];
	}

	public function getOptionalInputShapeDefaults(): array {
		$defaults = parent::getOptionalInputShapeDefaults();
		$defaults['strictness'] = 'standard';
		return $defaults;
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid prompt');
		$strictness = isset($input['strictness']) && is_string($input['strictness'])
			? $input['strictness']
			: 'standard';
		$instruction = match ($strictness) {
			'minimal' => 'List only grammatical and spelling errors that clearly affect meaning or readability, and list how to correct them.',
			'strict' => 'List every conceivable issue, including minor grammar rules, and list how to correct them. Also flag redundancy, and phrasing that could be clearer.',
			default => 'List all spelling and grammar mistakes and list how to correct them.',
		};
		return [
			['role' => 'system', 'content' => 'Proofread the following text. ' . $instruction . ' Output only the list.'],
			['role' => 'user', 'content' => $text],
		];
	}
}
