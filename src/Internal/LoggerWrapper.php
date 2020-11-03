<?php declare(strict_types=1);

namespace axxapy\EasyFork\Internal;

use Psr\Log\LoggerInterface;

class LoggerWrapper {
	/** @var string */
	private $prefix;

	/** @var ?LoggerInterface */
	private $logger;

	public function __construct(LoggerInterface $logger = null) {
		$this->logger = $logger;
	}

	public function unwrap(): ?LoggerInterface {
		return $this->logger;
	}

	public function setPrefix(string $prefix): self {
		$this->prefix = $prefix;
		return $this;
	}

	public function log(...$args): void {
		if ($this->logger) {
			$args = array_map(static function ($val) { return var_export($val, true); }, $args);
			call_user_func([$this->logger, 'debug'], $this->prefix . '[' . implode('] [', $args) . ']');
		}
	}

	public function logf($msg, ...$args): void {
		if ($this->logger) {
			call_user_func([$this->logger, 'debug'], sprintf($this->prefix . "[$msg]", ...$args));
		}
	}
}