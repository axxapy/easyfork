<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use ArrayAccess;
use axxapy\EasyFork\_\_;
use axxapy\EasyFork\SharedMemoryDrivers\Apcu;
use axxapy\EasyFork\SharedMemoryDrivers\Filesystem;
use axxapy\EasyFork\SharedMemoryDrivers\DriverInterface;
use axxapy\EasyFork\SharedMemoryDrivers\InMemoryViaSocket;
use Closure;
use Countable;
use InvalidArgumentException;
use RuntimeException;
use SeekableIterator;
use stdClass;

class SharedMemory implements ArrayAccess, Countable, SeekableIterator {
	/** @throws InvalidArgumentException */
	public function __construct(
		private ?DriverInterface $driver = null,
		?Closure                 $driver_factory = null,
	) {
		if ($this->driver && $driver_factory) {
			throw new InvalidArgumentException("Either driver or driver_factory should be passed. Not both.");
		}

		if ($this->driver) {
			return;
		}

		$driver_factory || throw new InvalidArgumentException("Either driver or driver_factory should be passed.");

		$this->driver = $driver_factory->call(new _);

		$this->driver || throw new InvalidArgumentException("Failed to create storage memory driver.");
	}

	private function _data(?array $value = null): array {
		if ($value) {
			$this->driver->set('::DATA::', $value);
			return $value;
		}
		return (array)$this->driver->get('::DATA::', []);
	}

	public function toArray(): array {
		return $this->_data();
	}

	// ArrayAccess

	public function offsetExists(mixed $offset): bool {
		return array_key_exists($offset, $this->_data());
	}

	public function offsetGet(mixed $offset): mixed {
		return $this->_data()[$offset];
	}

	public function offsetSet(mixed $offset, mixed $value): void {
		$data          = $this->_data();
		$data[$offset] = $value;
		$this->_data($data);
	}

	public function offsetUnset(mixed $offset): void {
		$data = $this->_data();
		unset($data[$offset]);
		$this->_data($data);
	}

	// Countable

	public function count(): int {
		return count($this->_data());
	}

	// Iterator

	private int $offset = 0;

	public function current(): mixed {
		$data = $this->_data();
		$keys = array_keys($data);
		return $data[$keys[$this->offset]];
	}

	public function next(): void {
		$this->offset++;
	}

	public function key(): mixed {
		$data = $this->_data();
		$keys = array_keys($data);
		return $keys[$this->offset];
	}

	public function valid(): bool {
		return $this->offset < count($this->_data());
	}

	public function rewind(): void {
		$this->offset = 0;
	}

	// SeekableIterator

	public function seek(int $offset): void {
		$this->offset = $offset;
	}
}
