<?php

declare(strict_types=1);

namespace OCA\SuperGrok\Exception;

use Exception;

class GrokOAuthException extends Exception {
	public function __construct(
		private string $reason,
		string $details = '',
		?\Throwable $previous = null,
	) {
		parent::__construct($details !== '' ? $details : $reason, 0, $previous);
	}

	public function getReason(): string {
		return $this->reason;
	}

	public function getDetails(): string {
		return $this->getMessage();
	}
}
