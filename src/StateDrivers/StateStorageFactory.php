<?php declare(strict_types=1);

namespace axxapy\EasyFork\StateDrivers;

use RuntimeException;

class StateStorageFactory {
	public static function newDriver(): StateDriver {
		try {
			return new Apcu();
		} catch (RuntimeException $ex) {}

		try {
			return new Filesystem();
		} catch (RuntimeException $ex) {}

		throw new RuntimeException('failed to find working state driver');
	}
}