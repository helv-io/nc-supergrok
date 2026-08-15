<?php

declare(strict_types=1);

/** @var \OCP\IL10N $l */
?>
<div id="supergrok-personal" class="section">
	<h2><?php p($l->t('SuperGrok OAuth')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Sign in with SuperGrok or X Premium+. No API key. SpaceXAI.')); ?>
	</p>

	<div id="supergrok-signed-in" class="supergrok-panel" hidden>
		<p id="supergrok-account"></p>
		<button id="supergrok-logout" class="button"><?php p($l->t('Sign out')); ?></button>
	</div>

	<div id="supergrok-signed-out" class="supergrok-panel">
		<h3><?php p($l->t('Device code (recommended)')); ?></h3>
		<p>
			<?php p($l->t('Open the verification URL on any device and approve. Nextcloud continues on its own.')); ?>
		</p>
		<button id="supergrok-device-start" class="button primary"><?php p($l->t('Sign in with device code')); ?></button>
		<div id="supergrok-device-pending" hidden>
			<p><?php p($l->t('Enter this code if asked:')); ?></p>
			<p class="supergrok-user-code" id="supergrok-user-code"></p>
			<p>
				<a id="supergrok-verify-link" href="#" target="_blank" rel="noopener noreferrer"></a>
			</p>
			<p id="supergrok-device-status"></p>
			<button id="supergrok-device-cancel" class="button"><?php p($l->t('Cancel')); ?></button>
		</div>

		<h3><?php p($l->t('Browser login (backup)')); ?></h3>
		<p>
			<?php p($l->t('Open SuperGrok, then paste the localhost callback. After you approve, the browser opens http://127.0.0.1:56121/callback and says the site cannot be reached. That is expected. Copy the full URL from the address bar and paste it here.')); ?>
		</p>
		<button id="supergrok-pkce-open" class="button"><?php p($l->t('Open SuperGrok')); ?></button>
		<p>
			<label for="supergrok-callback"><?php p($l->t('Callback URL, query string, or code')); ?></label>
			<input type="text" id="supergrok-callback" class="supergrok-input" autocomplete="off" />
		</p>
		<button id="supergrok-pkce-finish" class="button primary"><?php p($l->t('Finish sign-in')); ?></button>
	</div>

	<p id="supergrok-error" class="supergrok-error" hidden></p>
</div>
