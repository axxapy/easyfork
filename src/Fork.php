<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use Closure;
use RuntimeException;
use axxapy\EasyFork\SharedMemoryDrivers\DriverFactory;

readonly class Fork {
	public function __construct(
		private Closure       $job,
		private ?string       $id = null,
		private ?Logger       $logger = null,
		private ?Closure      $signal_handler = null,
		private ?SharedMemory $shared_memory = null,
		private ?Closure      $shared_memory_driver_factory = null,
		private string        $title_prefix = "[{parent_pid}|FORK]",
	) {
		$this->logger?->addPrefix($this->id ?? "FORK");
	}

	/** @throws RuntimeException */
	public function run(mixed ...$run_args): ProcessManager {
		$shared_memory = $this->shared_memory ?? new SharedMemory(
			driver_factory: $this->shared_memory_driver_factory ?? fn() => DriverFactory::createDriver(),
		);

		$parent_pid = getmypid();

		$pid = pcntl_fork();
		$pid >= 0 || throw new RuntimeException('Failed to fork. Out of memory?');

		if ($pid > 0) { // parent
			return new ProcessManager(
				id           : $this->id ?? $parent_pid . '_' . $pid,
				pid          : $pid,
				shared_memory: $shared_memory,
			);
		}

		// fork
		$self = new Process(
			id           : $this->id ?? $parent_pid . '_' . getmypid(),
			pid          : getmypid(),
			shared_memory: $shared_memory,
		);

		$this->signal_handler && $this->registerSignalHandler($self);

		$title_prefix = '';
		if ($this->title_prefix) {
			$replaces     = ['{parent_pid}' => $parent_pid, '{pid}' => $self->pid, '{id}' => $self->id];
			$title_prefix = str_replace(array_keys($replaces), array_values($replaces), $this->title_prefix) . ' ';
		}
		@cli_set_process_title($title_prefix . implode(' ', $_SERVER['argv'])); //cli_get_process_title();

		$job = $this->job;
		$result = $job($self, ...$run_args);
		exit(match (true) {
			is_bool($result) => $result === true ? 0 : 1,
			default          => (int)$result,
		});
	}

	private function registerSignalHandler(Process $process): void {
		$pid     = getmypid();
		$handler = function (int $signo) use ($pid, $process): void {
			if ($pid !== getmypid()) {
				return;
			}

			$this->logger?->log("Got signal", $signo);

			if ($this->signal_handler?->call($process, $signo) === false) {
				$this->logger?->logf("Signal (%d) handler returned false. Do not interrupt.", $signo);
				return;
			}

			if ($signo == SIGTERM || $signo == SIGINT) {
				$this->logger?->logf("Got %s signal. Shutting down.", $signo);
				exit;
			}
		};

		pcntl_async_signals(true);
		foreach ([SIGTERM, SIGHUP, SIGINT, SIGUSR1, SIGUSR2] as $signo) {
			pcntl_signal($signo, $handler);
		}
	}
}
