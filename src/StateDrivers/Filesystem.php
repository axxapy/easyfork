<?php declare(strict_types=1);

namespace axxapy\EasyFork\StateDrivers;

use RuntimeException;

class Filesystem implements StateDriver {
	private $file;
	private $lock_counter = 0;

	public function __construct() {
		$name = @tempnam("/dev/shm", "state");
		if ($name !== false) {
			$this->file = fopen($name, 'w+');
		} else {
			$this->file = tmpfile();
		}

		if ($this->file === false) {
			throw new RuntimeException("failed to create temporarily state file");
		}
	}

	public function __destruct() {
		$this->file && fclose($this->file);
	}

	private function lock(): bool {
		$this->lock_counter++;

		if ($this->lock_counter == 1) {
			return true;
		}

		return flock($this->file, LOCK_EX);
	}

	private function unlock(): bool {
		if ($this->lock_counter === 0) {
			return true;
		}

		$this->lock_counter--;

		if ($this->lock_counter > 0) {
			return true;
		}

		return flock($this->file, LOCK_UN);
	}

	public function set(string $key, $value) {
		if (!$this->lock()) {
			throw new RuntimeException("failed to obtain state file lock");
		}

		$data       = $this->getAll();
		$data[$key] = $value;

		if (!ftruncate($this->file, 0)) {
			throw new RuntimeException("failed to truncate state file before saving state");
		}

		if (!rewind($this->file)) {
			throw new RuntimeException('failed to rewind state file before saving state');
		}

		$size = fwrite($this->file, json_encode($data));
		if ($size === false) {
			throw new RuntimeException("failed to write state to state file");
		}

		if (!fflush($this->file)) {
			throw new RuntimeException("failed to flush state to state file");
		}

		if (!$this->unlock()) {
			throw new RuntimeException("failed to release state file lock");
		}
	}

	public function get(string $key, $default = null) {
		$data = $this->getAll();
		return array_key_exists($key, $data) ? $data[$key] : $default;
	}

	private function getAll(): array {
		if (!$this->lock()) {
			throw new RuntimeException("failed to obtain state file lock");
		}

		$stat = fstat($this->file);
		if (!$stat['size']) {
			$data = [];
		} else {
			if (!rewind($this->file)) {
				throw new RuntimeException("failed to rewind of the state file before reading");
			}

			$buf = "";
			do {
				$data = fread($this->file, 1024);
				if ($data === false) {
					throw new RuntimeException("failed to read data from state file");
				}
				$buf .= $data;
			} while (!feof($this->file));

			$data = json_decode($buf, true);
			if ($data === null) {
				throw new RuntimeException('failed to decode state json data: [' . json_last_error() . ']' . json_last_error_msg() . ': ' . var_export($buf, true));
			}
		}

		if (!$this->unlock()) {
			throw new RuntimeException("failed to release state file lock");
		}

		return $data;
	}
}