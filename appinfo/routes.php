<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'oauth#status', 'url' => '/status', 'verb' => 'GET'],
		['name' => 'oauth#startDevice', 'url' => '/device/start', 'verb' => 'POST'],
		['name' => 'oauth#pollDevice', 'url' => '/device/poll', 'verb' => 'POST'],
		['name' => 'oauth#startPkce', 'url' => '/pkce/start', 'verb' => 'POST'],
		['name' => 'oauth#exchangePkce', 'url' => '/pkce/exchange', 'verb' => 'POST'],
		['name' => 'oauth#logout', 'url' => '/logout', 'verb' => 'POST'],
		['name' => 'oauth#models', 'url' => '/models', 'verb' => 'GET'],
		['name' => 'admin#setConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
	],
];
