<?php declare(strict_types=1);

namespace axxapy\EasyFork\SharedMemoryDrivers;

use axxapy\EasyFork\Logger;
use RuntimeException;

class DriverFactory {
	/** @throws RuntimeException */
	public static function createDriver(?Logger $logger = null): DriverInterface {
		$drivers = [
			Apcu::class,
			InMemoryViaSocket::class,
			Filesystem::class,
		];

		$logger = $logger?->withPrefix("DriverFactory::create");

		foreach ($drivers as $driver) {
			try {
				$instance = new $driver;
				$logger?->logf("Using shared memory driver: %s", $driver);
				return $instance;
			} catch (RuntimeException $ex) {
				$logger?->logf("Failed to create shared memory driver %s: %s", $driver, $ex->getMessage());
			}
		}

		throw new RuntimeException('Failed to find working shared memory driver');
	}
}
