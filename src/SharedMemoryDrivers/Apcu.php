<?php declare(strict_types=1);

namespace axxapy\EasyFork\SharedMemoryDrivers;

use RuntimeException;

class Apcu implements DriverInterface {
	private string $prefix;

	public function __construct() {
		if (!extension_loaded('apcu')) {
			throw new RuntimeException('"apcu" extension is not loaded');
		}
		if (!apcu_enabled()) {
			throw new RuntimeException('"apcu" extension is disabled. Consider enabling it in php.ini');
		}
		do {
			$this->prefix = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 10) . "_";
		} while (apcu_exists(__CLASS__ . "_prefix_" . $this->prefix));
	}

	public function set(string $key, mixed $value): void {
		$res = apcu_store($this->prefix . $key, $value);

		if ($res === false || is_array($res)) {
			throw new RuntimeException('failed to store state into apc: ' . var_export($res, true));
		}
	}

	public function get(string $key, mixed $default = null): mixed {
		$success = null;
		if (!apcu_exists($this->prefix . $key)) {
			return $default;
		}
		$res = apcu_fetch($this->prefix . $key, $success);
		if (!$success) {
			throw new RuntimeException("failed to fetch '{$key}' from apcu");
		}
		return $res;
	}
}
