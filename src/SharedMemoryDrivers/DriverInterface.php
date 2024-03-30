<?php declare(strict_types=1);

namespace axxapy\EasyFork\SharedMemoryDrivers;

interface DriverInterface {
	public function set(string $key, mixed $value): void;

	public function get(string $key, mixed $default = null): mixed;
}
