<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use Closure;

class Logger {
	public function __construct(
		private ?Closure $echo_fn = null,
		private string   $prefix = '',
	) {
		$this->echo_fn = $echo_fn ?: function (...$args) {
			echo date('[Y-m-d H:i:s] [').implode("] [", $args) . "]\n";
		};
	}

	public function addPrefix(string $prefix): void {
		if ($this->prefix) {
			$this->prefix .= "|$prefix";
		} else {
			$this->prefix = $prefix;
		}
	}

	public function withPrefix(string $prefix): Logger {
		$logger = clone $this;
		$logger->addPrefix($prefix);
		return $logger;
	}

	public function log(mixed ...$args): void {
		$args = array_map(static fn($val) => (string)$val, $args);
		$this->echo_fn->call($this, $this->prefix, ...$args);
	}

	public function logf(string $msg, mixed ...$args): void {
		$this->log(sprintf($msg, ...$args));
	}
}
