<?php declare(strict_types=1);

namespace axxapy\EasyFork;

class Logger {
	/** @var callable */
	private $writer;

	/** @var string */
	private $prefix;

	public function setPrefix(string $prefix): self {
		$this->prefix = $prefix;
		return $this;
	}

	public function setWriter(callable $func): self {
		$this->writer = $func;
		return $this;
	}

	public function log(...$args): void {
		if ($this->writer) {
			$args = array_map(static function ($val) { return var_export($val, true); }, $args);
			call_user_func($this->writer, $this->prefix . '[' . implode('] [', $args) . ']');
		}
	}

	public function logf($msg, ...$args): void {
		if ($this->writer) {
			call_user_func($this->writer, sprintf($this->prefix . "[$msg]", ...$args));
		}
	}
}