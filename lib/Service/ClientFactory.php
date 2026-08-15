<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Service;

use OCA\SuperGrok\Exception\GrokOAuthException;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class ClientFactory {
	public function __construct(
		private IClientService $clientService,
		private OAuthService $oauthService,
		private TokenStore $tokenStore,
		private LoggerInterface $logger,
	) {
	}

	public function forUser(?string $userId): GrokClient {
		$uid = $userId ?? '';
		$tokens = $this->tokenStore->getTokens($uid);
		if ($tokens === null && $uid !== '') {
			$tokens = $this->tokenStore->getTokens('');
			$uid = '';
		}
		if ($tokens === null) {
			throw new GrokOAuthException('not_signed_in', 'Sign in with SuperGrok under Personal settings');
		}
		return new GrokClient(
			$this->clientService,
			$this->oauthService,
			$this->tokenStore,
			$this->logger,
			$uid,
			$tokens,
		);
	}
}
