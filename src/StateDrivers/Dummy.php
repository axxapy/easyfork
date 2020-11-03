<?php declare(strict_types=1);

namespace axxapy\EasyFork\StateDrivers;

class Dummy implements StateDriver {
	public function set(string $key, $value) {
	}

	public function get(string $key, $default = null) {
		return null;
	}
}