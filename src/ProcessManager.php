<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use axxapy\EasyFork\_\_;

class ProcessManager extends Process {
	private ?int $exit_code = null;

	public static function fromProcess(Process $process): self {
		return new ProcessManager(
			id           : $process->id,
			pid          : $process->pid,
			shared_memory: $process->shared_memory,
		);
	}

	public function isRunning(): bool {
		if (!$this->pid || $this->exit_code !== null) {
			return false;
		}

		if (pcntl_waitpid($this->pid, $status, WNOHANG) === $this->pid) { // stopped
			$this->exit_code = pcntl_wexitstatus($status);
			return false;
		}

		return true;
	}

	public function waitFor(): ?int {
		if ($this->exit_code === null) {
			pcntl_waitpid($this->pid, $status, WUNTRACED);
			$this->exit_code = pcntl_wexitstatus($status);
		}
		return $this->exit_code;
	}

	public function sendSignal(int $signal): bool {
		return posix_kill($this->pid, $signal);
	}

	public function stop(): bool {
		return $this->sendSignal(SIGTERM);
	}

	public function kill(float $graceful_timeout_sec = 0): bool {
		if (!$graceful_timeout_sec) {
			return $this->sendSignal(SIGKILL);
		}

		$time_finish = microtime(true) + $graceful_timeout_sec;
		do {
			$this->sendSignal(SIGTERM);
			usleep(_::$TICK_TIME_US);
		} while ($this->isRunning() && microtime(true) <= $time_finish);

		return !$this->isRunning() || $this->kill();
	}
}
