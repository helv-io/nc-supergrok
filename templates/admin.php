<?php

declare(strict_types=1);

/** @var \OCP\IL10N $l */
?>
<div id="supergrok-admin" class="section">
	<h2><?php p($l->t('SuperGrok OAuth')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Default models and which Task Processing providers to expose. Users sign in under Personal settings. No API key.')); ?>
	</p>

	<p>
		<label for="supergrok-chat-model"><?php p($l->t('Default chat model')); ?></label>
		<input type="text" id="supergrok-chat-model" class="supergrok-input" />
	</p>
	<p>
		<label for="supergrok-image-model"><?php p($l->t('Default Imagine model')); ?></label>
		<input type="text" id="supergrok-image-model" class="supergrok-input" />
	</p>
	<p>
		<label for="supergrok-voice-model"><?php p($l->t('Default Voice model')); ?></label>
		<input type="text" id="supergrok-voice-model" class="supergrok-input" />
	</p>
	<p>
		<label for="supergrok-tts-voice"><?php p($l->t('Default TTS voice')); ?></label>
		<input type="text" id="supergrok-tts-voice" class="supergrok-input" />
	</p>

	<p>
		<input type="checkbox" id="supergrok-enable-text" class="checkbox" />
		<label for="supergrok-enable-text"><?php p($l->t('Enable conversation (text)')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="supergrok-enable-image" class="checkbox" />
		<label for="supergrok-enable-image"><?php p($l->t('Enable Imagine (image generation)')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="supergrok-enable-stt" class="checkbox" />
		<label for="supergrok-enable-stt"><?php p($l->t('Enable Voice speech-to-text')); ?></label>
	</p>
	<p>
		<input type="checkbox" id="supergrok-enable-tts" class="checkbox" />
		<label for="supergrok-enable-tts"><?php p($l->t('Enable Voice text-to-speech')); ?></label>
	</p>

	<button id="supergrok-admin-save" class="button primary"><?php p($l->t('Save')); ?></button>
	<p id="supergrok-admin-status" class="supergrok-status" hidden></p>
</div>
