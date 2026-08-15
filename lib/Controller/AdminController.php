<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Controller;

use OCA\SuperGrok\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
	) {
		parent::__construct($appName, $request);
	}

	public function setConfig(): JSONResponse {
		$this->settingsService->setAdminConfig([
			'chat_model' => $this->request->getParam('chat_model'),
			'image_model' => $this->request->getParam('image_model'),
			'voice_model' => $this->request->getParam('voice_model'),
			'tts_voice' => $this->request->getParam('tts_voice'),
			'enable_text' => $this->request->getParam('enable_text'),
			'enable_image' => $this->request->getParam('enable_image'),
			'enable_stt' => $this->request->getParam('enable_stt'),
			'enable_tts' => $this->request->getParam('enable_tts'),
		]);
		return new JSONResponse($this->settingsService->getAdminConfig());
	}
}
