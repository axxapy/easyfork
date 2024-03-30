<?php declare(strict_types=1);

namespace axxapy\EasyFork\Tests;

use axxapy\EasyFork\Process;

class TestHelper {
	public static function confirmStarted(Process $proc) {
		$proc->shared_memory['started'] = true;
	}

	public static function ensureValue(Process $proc, string $key = 'started'): bool {
		// Fork needs some time to start executing code.
		// If we send signal right away, pricess will just terminate with exit code 0
		for ($n = 0; $proc->shared_memory[$key] !== true && $n < 20; $n++) {
			usleep(1000);
		}
		return $proc->shared_memory[$key] === true;
	}
}
