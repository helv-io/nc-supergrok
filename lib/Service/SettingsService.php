<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

use OCA\SuperGrok\AppConstants;
use OCP\IConfig;

class SettingsService {
	public function __construct(
		private IConfig $config,
	) {
	}

	public function getChatModel(): string {
		return $this->getAppString(AppConstants::APP_CHAT_MODEL, AppConstants::DEFAULT_CHAT_MODEL);
	}

	public function getImageModel(): string {
		return $this->getAppString(AppConstants::APP_IMAGE_MODEL, AppConstants::DEFAULT_IMAGE_MODEL);
	}

	public function getVoiceModel(): string {
		return $this->getAppString(AppConstants::APP_VOICE_MODEL, AppConstants::DEFAULT_VOICE_MODEL);
	}

	public function getTtsVoice(): string {
		return $this->getAppString(AppConstants::APP_TTS_VOICE, AppConstants::DEFAULT_TTS_VOICE);
	}

	public function isTextEnabled(): bool {
		return $this->isFlagEnabled(AppConstants::APP_ENABLE_TEXT);
	}

	public function isImageEnabled(): bool {
		return $this->isFlagEnabled(AppConstants::APP_ENABLE_IMAGE);
	}

	public function isSttEnabled(): bool {
		return $this->isFlagEnabled(AppConstants::APP_ENABLE_STT);
	}

	public function isTtsEnabled(): bool {
		return $this->isFlagEnabled(AppConstants::APP_ENABLE_TTS);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getAdminConfig(): array {
		return [
			'chat_model' => $this->getChatModel(),
			'image_model' => $this->getImageModel(),
			'voice_model' => $this->getVoiceModel(),
			'tts_voice' => $this->getTtsVoice(),
			'enable_text' => $this->isTextEnabled(),
			'enable_image' => $this->isImageEnabled(),
			'enable_stt' => $this->isSttEnabled(),
			'enable_tts' => $this->isTtsEnabled(),
		];
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function setAdminConfig(array $values): void {
		$stringKeys = [
			'chat_model' => AppConstants::APP_CHAT_MODEL,
			'image_model' => AppConstants::APP_IMAGE_MODEL,
			'voice_model' => AppConstants::APP_VOICE_MODEL,
			'tts_voice' => AppConstants::APP_TTS_VOICE,
		];
		foreach ($stringKeys as $input => $key) {
			if (isset($values[$input]) && is_string($values[$input]) && $values[$input] !== '') {
				$this->config->setAppValue(AppConstants::APP_ID, $key, trim($values[$input]));
			}
		}
		$flags = [
			'enable_text' => AppConstants::APP_ENABLE_TEXT,
			'enable_image' => AppConstants::APP_ENABLE_IMAGE,
			'enable_stt' => AppConstants::APP_ENABLE_STT,
			'enable_tts' => AppConstants::APP_ENABLE_TTS,
		];
		foreach ($flags as $input => $key) {
			if (array_key_exists($input, $values)) {
				$this->config->setAppValue(AppConstants::APP_ID, $key, $this->truthy($values[$input]) ? '1' : '0');
			}
		}
	}

	private function getAppString(string $key, string $default): string {
		$value = $this->config->getAppValue(AppConstants::APP_ID, $key, $default);
		return $value !== '' ? $value : $default;
	}

	private function isFlagEnabled(string $key): bool {
		return $this->config->getAppValue(AppConstants::APP_ID, $key, '1') !== '0';
	}

	private function truthy(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value === 1;
		}
		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
		}
		return false;
	}
}
