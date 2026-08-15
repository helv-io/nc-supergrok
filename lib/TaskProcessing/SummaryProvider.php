<?php

declare(strict_types=1);

namespace OCA\SuperGrok\TaskProcessing;

use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\ShapeEnumValue;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;

class SummaryProvider extends AbstractTextProvider {
	protected function providerIdSuffix(): string {
		return 'text2text-summary';
	}

	protected function taskTypeClass(): string {
		return TextToTextSummary::class;
	}

	public function getOptionalInputShape(): array {
		$shape = parent::getOptionalInputShape();
		$shape['format'] = new ShapeDescriptor(
			$this->l->t('Format'),
			$this->l->t('The format of the summary'),
			EShapeType::Enum,
		);
		$shape['complexity'] = new ShapeDescriptor(
			$this->l->t('Complexity'),
			$this->l->t('The complexity of the summary'),
			EShapeType::Enum,
		);
		return $shape;
	}

	public function getOptionalInputShapeEnumValues(): array {
		return [
			'format' => [
				new ShapeEnumValue($this->l->t('Auto'), 'auto'),
				new ShapeEnumValue($this->l->t('One Sentence'), 'sentence'),
				new ShapeEnumValue($this->l->t('One Paragraph'), 'paragraph'),
				new ShapeEnumValue($this->l->t('Bullet Points'), 'bullet_points'),
			],
			'complexity' => [
				new ShapeEnumValue($this->l->t('Simple'), 'simple'),
				new ShapeEnumValue($this->l->t('Medium'), 'medium'),
				new ShapeEnumValue($this->l->t('Complex'), 'complex'),
			],
		];
	}

	public function getOptionalInputShapeDefaults(): array {
		$defaults = parent::getOptionalInputShapeDefaults();
		$defaults['format'] = 'auto';
		$defaults['complexity'] = 'medium';
		return $defaults;
	}

	protected function buildMessages(array $input): array {
		$text = $this->requireString($input, 'input', 'Invalid prompt');
		$system = 'You are a helpful assistant that summarizes text in the same language as the text. You should only return the summary without any additional information. ';
		if (($input['format'] ?? '') === 'paragraph') {
			$system .= 'Return the summary as a paragraph. ';
		} elseif (($input['format'] ?? '') === 'bullet_points') {
			$system .= 'Return the summary as a list of bullet points. ';
		} elseif (($input['format'] ?? '') === 'sentence') {
			$system .= 'Return the summary as a single sentence. Do not include more than one sentence. ';
		}
		if (($input['complexity'] ?? '') === 'complex') {
			$system .= 'Use complex language and vocabulary appropriate for an expert in the subject. ';
		} elseif (($input['complexity'] ?? '') === 'simple') {
			$system .= 'Use simple language and vocabulary appropriate for a 5 year old. ';
		}
		return [
			['role' => 'system', 'content' => $system],
			['role' => 'user', 'content' => $text],
		];
	}
}
