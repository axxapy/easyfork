<?php declare(strict_types=1);

namespace axxapy\EasyFork\StateDrivers;

use RuntimeException;

class Memcached implements StateDriver {
	private $pid;
	private $port;
	private $client;

	public function __construct() {
		if (!extension_loaded('sockets')) {
			throw new RuntimeException('"sockets" extension is not loaded');
		}

		$binary = `which memcached`;
		if (!$binary) {
			throw new RuntimeException('memcached binary not found');
		}

		$this->port = self::find_port(10000);

		$this->pid = pcntl_fork();
		if ($this->pid < 0) {
			throw new RuntimeException("Failed to fork for memcached. Out of memory?");
		}

		if (!$this->pid) {
			pcntl_exec($binary, ['-p', $this->port, '-u', 'memcached']);
			die;
		}

		$start_time = time();
		while (self::is_connectable($this->port)) {
			if (time() - $start_time > 10) {
				posix_kill($this->pid, SIGKILL);
				throw new RuntimeException("memcache isnt starting");
			}
			usleep(100000);
		}

		$this->client = new \Memcached();
		$this->client->addServer('127.0.0.1', $this->port);
	}

	public function __destruct() {
		$this->pid && posix_kill($this->pid, SIGKILL);
	}

	private static function is_connectable(int $port): bool {
		$sock    = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		$success = socket_connect($sock, '127.0.0.1', $port);
		socket_close($sock);
		return $success;
	}

	private static function find_port(int $begin, int $end = 0): int {
		for ($port = $begin; $port < $end ? $end : 65000; $port++) {
			if (self::is_connectable($port)) {
				return $port;
			}
		}
		return -1;
	}

	public function set(string $key, $value) {
		if (!$this->client->set($key, $value)) {
			throw new RuntimeException(sprintf('failed to save state key to memcached: [%d] [%d] %s',
				$this->client->getLastErrorCode(),
				$this->client->getLastErrorErrno(),
				$this->client->getLastErrorMessage()
			));
		}
	}

	public function get(string $key, $default = null) {
		$val = $this->client->get($key);
		if ($val === false) {
			if ($this->client->getLastErrorCode() == \Memcached::RES_NOTFOUND) {
				return $default;
			}
			throw new RuntimeException(sprintf('failed to get state key from memcached: [%d] [%d] %s',
				$this->client->getLastErrorCode(),
				$this->client->getLastErrorErrno(),
				$this->client->getLastErrorMessage()
			));
		}
		return $val;
	}
}