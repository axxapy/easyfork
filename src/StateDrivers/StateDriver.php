<?php declare(strict_types=1);

namespace axxapy\EasyFork\StateDrivers;

interface StateDriver {
	public function set(string $key, $value);

	public function get(string $key, $default = null);
}
