<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use axxapy\EasyFork\_\_;
use axxapy\EasyFork\_\_fork;
use axxapy\EasyFork\_\_state;
use axxapy\EasyFork\Modes\RunMode;
use axxapy\EasyFork\Modes\SharedMemoryMode;
use axxapy\EasyFork\SharedMemoryDrivers\DriverFactory;
use Closure;
use InvalidArgumentException;
use RuntimeException;

class ForkPoolExecutor {
	private _state $state = _state::IDLE;

	/** @var _fork[] $forks */
	private array $_forks = [];

	/** @throws InvalidArgumentException */
	public function __construct(
		private readonly Closure          $job,
		private readonly int              $forks = 1,
		private readonly Logger           $logger = new Logger(prefix: "MAIN"),
		private readonly RunMode          $run_mode = RunMode::RUN_ONCE,
		private readonly SharedMemoryMode $shared_memory_mode = SharedMemoryMode::ISOLATED,
		private readonly ?SharedMemory    $shared_memory = null,
		private readonly ?Closure         $shared_memory_driver_factory = null,
		private readonly ?Closure         $signal_handler = null,
		private readonly ?Closure         $job_signal_handler = null,
		private readonly float            $graceful_shutdown_sec = 5,     //seconds
	) {
		$this->forks > 0 || throw new InvalidArgumentException('Minimum 1 fork required');
		$this->registerSignalHandler();
	}

	/** @throws RuntimeException */
	public function run(...$run_args): array {
		if ($this->state !== _state::IDLE) {
			throw new RuntimeException('can not be started twice');
		}

		$this->state = _state::RUNNING;

		$title = '[MAIN] ' . implode(' ', $_SERVER['argv']); //cli_get_process_title();
		@cli_set_process_title($title);

		$new_fork = fn(string $id, int $generation, ?SharedMemory $shared_memory) => new Fork(
			job           : $this->job,
			id            : $id,
			logger        : clone $this->logger,
			signal_handler: $this->job_signal_handler,
			shared_memory : $shared_memory,
			title_prefix  : "[{id}:{$generation}]",
		);
		/** @var _fork[] $forks */
		$this->_forks = [];

		$common_shared_memory = $this->shared_memory;
		if (!$common_shared_memory && $this->shared_memory_mode === SharedMemoryMode::COMMON) {
			$common_shared_memory = new SharedMemory(
				driver_factory: $this->shared_memory_driver_factory ?? fn() => DriverFactory::createDriver(),
			);
		}

		for ($i = 0; $i < $this->forks; $i++) {
			$this->_forks[] = new _fork(
				process   : $new_fork(
					id           : (string)$i,
					generation   : 0,
					shared_memory: $common_shared_memory ?: new SharedMemory (
						driver_factory: $this->shared_memory_driver_factory ?? fn() => DriverFactory::createDriver(),
					),
				)->run(...$run_args),
				generation: 0,
			);
		}

		$semaphore = count($this->_forks);
		do {
			foreach ($this->_forks as &$fork) {
				if ($fork->process->isRunning()) {
					continue;
				}

				switch (true) {
					case $this->run_mode === RunMode::RUN_ONCE:
					case $fork->process->waitFor() === 0:
						$semaphore--;
						break;

					default:
						$fork->process = $new_fork(
							id           : $fork->process->id,
							generation   : ++$fork->generation,
							shared_memory: $fork->process->shared_memory,
						)->run(...$run_args);
				}
			}
			usleep(_::$TICK_TIME_US);
		} while ($this->state == _state::RUNNING && $semaphore > 0);

		$this->state = _state::IDLE;

		return $common_shared_memory ? $common_shared_memory->toArray() : array_map(function (_fork $fork) {
			return $fork->process->shared_memory->toArray();
		}, $this->_forks);
	}

	public function stop(): bool {
		if ($this->state !== _state::RUNNING) {
			return $this->state === _state::IDLE;
		}

		$this->state = _state::STOPPING;

		$this->logger?->logf('Gracefully stopping children (timeout: %d sec)...', $this->graceful_shutdown_sec);

		$time_to_kill = microtime(true) + $this->graceful_shutdown_sec;
		do {
			$running = 0;
			foreach ($this->_forks as $fork) {
				$fork->process->isRunning() && $fork->process->stop() && $running++;
			}
			$running === 0 || usleep(_::$TICK_TIME_US);
		} while ($running > 0 && microtime(true) <= $time_to_kill);

		if ($running === 0) {
			$this->logger?->log("Successfully graceful stopped all children.");
			$this->state = _state::IDLE;
			return true;
		}

		$this->logger?->log("Failed to gracefully stop some of the children. Killing them...");

		$running = 0;
		for ($attempts = 0; $attempts < 10 && $running > 0; $attempts++) {
			foreach ($this->_forks as $fork) {
				if (!$fork->process->kill()) $running++;
			}
		}

		if ($running === 0) {
			$this->logger?->log("Successfully killed all children.");
			$this->state = _state::IDLE;
			return true;
		}

		$this->logger?->logf("Failed to kill %d children. Entering unknown state. Restart is impossible.", $running);
		return false;
	}

	private function registerSignalHandler(): void {
		$pid     = getmypid();
		$handler = function (int $signo) use ($pid): void {
			if ($pid !== getmypid()) return;

			$this->logger?->log("Got signal", $signo);

			if ($this->signal_handler) {
				$processes = array_map(fn(_fork $fork) => $fork->process, $this->_forks);
				if ($this->signal_handler->call($this, $signo, $processes) === false) {
					$this->logger?->logf("Signal (%d) handler returned false. Do not interrupt.", $signo);
					return;
				}
			}

			if ($signo == SIGTERM || $signo == SIGINT) {
				$this->logger?->logf("Got %s signal. Shutting down.", $signo);
				$this->stop();
			}
		};

		pcntl_async_signals(true);
		foreach ([SIGTERM, SIGHUP, SIGINT, SIGUSR1, SIGUSR2] as $signo) {
			pcntl_signal($signo, $handler);
		}
	}
}
