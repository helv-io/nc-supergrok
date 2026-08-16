<?php

declare(strict_types=1);

return [
	'routes' => [
		// Nextcloud maps o_auth#foo -> OAuthController::foo (oauth#foo would
		// look for OauthController and return an HTML 500).
		['name' => 'o_auth#status', 'url' => '/status', 'verb' => 'GET'],
		['name' => 'o_auth#startDevice', 'url' => '/device/start', 'verb' => 'POST'],
		['name' => 'o_auth#pollDevice', 'url' => '/device/poll', 'verb' => 'POST'],
		['name' => 'o_auth#startPkce', 'url' => '/pkce/start', 'verb' => 'POST'],
		['name' => 'o_auth#exchangePkce', 'url' => '/pkce/exchange', 'verb' => 'POST'],
		['name' => 'o_auth#logout', 'url' => '/logout', 'verb' => 'POST'],
		['name' => 'o_auth#models', 'url' => '/models', 'verb' => 'GET'],
		['name' => 'admin#setConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
	],
];
