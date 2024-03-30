<?php declare(strict_types=1);

namespace axxapy\EasyFork\SharedMemoryDrivers;

use ParseError;
use RuntimeException;

class Filesystem implements DriverInterface {
	private        $fp;
	private string $filename;
	private bool   $locked = false;

	private readonly int $master_pid;

	public function __construct(?string $filename = null) {
		$this->master_pid = getmypid();

		$filename = $filename ?: @tempnam("/dev/shm", "state-{$this->master_pid}-") . '.json';
		if ($filename) {
			$this->filename = $filename;
			$this->fp       = fopen($filename, 'w+');
		} else {
			$this->fp = tmpfile();
		}

		$this->fp || throw new RuntimeException("failed to create temporarily state file");
	}

	public function __destruct() {
		if (getmypid() === $this->master_pid) {
			$this->fp && fclose($this->fp);
			$this->filename && unlink($this->filename);
		}
	}

	private function lock(): bool {
		if (!$this->locked) {
			$this->locked = flock($this->fp, LOCK_EX);
		}
		return $this->locked;
	}

	private function unlock(): bool {
		if ($this->locked) {
			$this->locked = !flock($this->fp, LOCK_UN);
		}
		return !$this->locked;
	}

	public function set(string $key, mixed $value): void {
		if (!$this->lock()) {
			throw new RuntimeException("failed to obtain state file lock");
		}

		$data       = $this->getAll();
		$data[$key] = $value;

		if (!ftruncate($this->fp, 0)) {
			throw new RuntimeException("failed to truncate state file before saving state");
		}

		if (!rewind($this->fp)) {
			throw new RuntimeException('failed to rewind state file before saving state');
		}

		$size = fwrite($this->fp, var_export($data, true));
		if ($size === false) {
			throw new RuntimeException("failed to write state to state file");
		}

		if (!fflush($this->fp)) {
			throw new RuntimeException("failed to flush state to state file");
		}

		if (!$this->unlock()) {
			throw new RuntimeException("failed to release state file lock");
		}
	}

	public function get(string $key, mixed $default = null): mixed {
		$data = $this->getAll();
		return array_key_exists($key, $data) ? $data[$key] : $default;
	}

	private function getAll(): array {
		if (!$this->lock()) {
			throw new RuntimeException("failed to obtain state file lock");
		}

		$stat = fstat($this->fp);
		if (!$stat['size']) {
			$data = [];
		} else {
			if (!rewind($this->fp)) {
				throw new RuntimeException("failed to rewind of the state file before reading");
			}

			$buf = "";
			do {
				$data = fread($this->fp, 1024);
				if ($data === false) {
					throw new RuntimeException("failed to read data from state file");
				}
				$buf .= $data;
			} while (!feof($this->fp));

			try {
				$data = $buf ? eval("return " . $buf . ";") : [];
			} catch (ParseError $err) {
				throw new RuntimeException($err->getMessage(), $err->getCode(), $err);
			}
		}

		if (!$this->unlock()) {
			throw new RuntimeException("failed to release state file lock");
		}

		return $data;
	}
}
