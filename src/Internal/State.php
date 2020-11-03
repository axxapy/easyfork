<?php declare(strict_types=1);

namespace axxapy\EasyFork\Internal;

use axxapy\EasyFork\StateDrivers\StateStorageFactory;
use axxapy\EasyFork\StateDrivers\StateDriver;
use RuntimeException;

class State {
	/** @var int */
	private $num;

	/** @var array */
	private $payload;

	/** @var int */
	private $generation;

	/** @var StateDriver */
	private $driver;

	public function __construct(int $num, int $generation, array $payload = [], StateDriver $driver = null) {
		$this->num        = $num;
		$this->payload    = $payload;
		$this->generation = $generation;
		if ($driver) {
			$this->driver = $driver;
		}
	}

	public function getNum(): int {
		return $this->num;
	}

	public function getPayload(): array {
		return $this->payload;
	}

	public function getGeneration(): int {
		return $this->generation;
	}

	/** @throws RuntimeException */
	public function getStorageDriver(): StateDriver {
		if (!$this->driver) {
			$this->driver = StateStorageFactory::newDriver();
		}
		return $this->driver;
	}

	/** @throws RuntimeException */
	public function markDone(): void {
		$this->getStorageDriver()->set("::DONE::", true);
	}

	/** @throws RuntimeException */
	public function isDone(): bool {
		return (bool)$this->getStorageDriver()->get("::DONE::", false);
	}

	/** @throws RuntimeException */
	public function put(string $key, $value): self {
		$data       = $this->getStorageDriver()->get('::DATA::', []);
		$data[$key] = $value;
		$this->getStorageDriver()->set('::DATA::', $data);
		return $this;
	}

	/** @throws RuntimeException */
	public function get(string $key, $default = null) {
		$data = $this->getStorageDriver()->get('::DATA::', []);
		return array_key_exists($key, $data) ? $data[$key] : $default;
	}

	/** @throws RuntimeException */
	public function getAll(): array {
		return $this->getStorageDriver()->get('::DATA::', []);
	}
}
