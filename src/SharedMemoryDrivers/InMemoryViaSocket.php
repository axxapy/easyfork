<?php declare(strict_types=1);

namespace axxapy\EasyFork\SharedMemoryDrivers;

use axxapy\EasyFork\_\_;
use axxapy\EasyFork\Fork;
use axxapy\EasyFork\Process;
use RuntimeException;

class InMemoryViaSocket implements DriverInterface {
	private readonly int     $master_pid;
	private readonly string  $socket_filename;
	private readonly Process $server_process;

	public function __construct(?string $socket_filename = null) {
		$this->master_pid      = getmypid();
		$this->socket_filename = $socket_filename ?? $this->default_socket_filename();
		$socket_filename       = $this->socket_filename;

		$this->server_process = (new Fork(job: function (Process $proc, ...$args) use ($socket_filename) {
			set_time_limit(0);

			$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
			if (!$socket) {
				throw new RuntimeException("Error: Failed to create socket\n");
			}

			if (!socket_bind($socket, $socket_filename)) {
				throw new RuntimeException("Error: Failed to bind socket to {$socket_filename}\n");
			}

			if (!socket_listen($socket)) {
				throw new RuntimeException("Error: Failed to listen on socket\n");
			}

			$memory = [];

			while (true) {
				$clientSocket = socket_accept($socket);
				if (!$clientSocket) {
					throw new RuntimeException("Error: Failed to accept connection\n");
				}

				$request = socket_read($clientSocket, 1024);

				// Process the command
				$parts   = explode(' ', trim($request), 2);
				$command = $parts[0];

				switch ($command) {
					case 'put':
						$decoded = $this->decode($parts[1]);
						[$key, $value] = [$decoded['k'], $decoded['v']];
						$memory[$key] = $value;
						$response     = "OK\n";
						break;

					case 'get':
						$key = trim($parts[1]);
						if (array_key_exists($key, $memory)) {
							$response = "OK:" . $this->encode($key, $memory[$key]) . "\n";
						} else {
							$response = "NOT_FOUND:Key not found\n";
						}
						break;
					default:
						$response = "ERR:Invalid command\n";
				}

				socket_write($clientSocket, $response, strlen($response));

				socket_close($clientSocket);
			}

			socket_close($socket);
		}, shared_memory_driver_factory: fn() => new Dummy))->run();

		usleep(_::$TICK_TIME_US);
	}

	public function __destruct() {
		if (getmypid() === $this->master_pid) {
			$this->server_process->kill();
			unlink($this->socket_filename);
		}
	}

	private function default_socket_filename(): string {
		switch (PHP_OS_FAMILY) {
			case 'Darwin':
				return "/private/tmp/{$this->master_pid}.sock";
			case 'Linux':
				return tempnam(sys_get_temp_dir(), 'inmem_socket') . '.sock';
		}
		throw new RuntimeException('Unsupported OS');
	}

	private function encode(string $key, mixed $value): string {
		return base64_encode(var_export(['k' => $key, 'v' => $value], true));
	}

	private function decode(string $encoded): mixed {
		return eval("return " . base64_decode($encoded) . ";");
	}

	/** @throws RuntimeException */
	public function socket($command): string {
		$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
		if (socket_connect($socket, $this->socket_filename) === false) {
			throw new RuntimeException("Filed to connect to unix socket {$this->socket_filename}");
		}

		socket_write($socket, $command . "\n");
		$response = socket_read($socket, 1024);
		socket_close($socket);

		return trim($response);
	}

	public function set(string $key, mixed $value): void {
		$this->socket("put " . $this->encode($key, $value));
	}

	public function get(string $key, mixed $default = null): mixed {
		$resp = $this->socket("get $key");
		if (str_starts_with($resp, 'OK:')) {
			return $this->decode(substr($resp, 3))['v'];
		} elseif (str_starts_with($resp, 'NOT_FOUND:')) {
			return $default;
		}
		throw new RuntimeException("Error: $resp");
	}
}

