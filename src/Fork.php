<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use axxapy\EasyFork\Internal\LoggerWrapper;
use axxapy\EasyFork\Internal\State;

class Fork implements LoggerAwareInterface {
	private $child_pid = 0;

	private $job;
	private $num;

	/** @var State */
	private $state;

	private $generation = 0;

	private $interrupt_handler;

	/** @var LoggerWrapper */
	private $logger;

	public function __construct(callable $job, int $num = 0) {
		$this->job    = $job;
		$this->num    = $num;
		$this->logger = new LoggerWrapper;
		$this->state  = new State($this->num, $this->generation);

		pcntl_async_signals(true);
	}

	public function setInterruptHandler(callable $handler): self {
		$this->interrupt_handler = $handler;
		return $this;
	}

	public function setLogger(LoggerInterface $logger): self {
		$this->logger = (new LoggerWrapper($logger))->setPrefix("[{$this->num}:{$this->generation}] ");
		return $this;
	}

	/** @throws RuntimeException */
	public function start(array $payload = []): self {
		if ($this->isRunning()) {
			return $this;
		}

		$this->state = new State($this->num, $this->generation++, $payload, $this->state->getStorageDriver());

		$pid = pcntl_fork();
		if ($pid < 0) {
			throw new RuntimeException('Failed to fork. Out of memory?');
		}

		if ($pid === 0) {
			$this->registerSigHandler();

			$title = sprintf('[%s:%s] ', $this->num, $this->generation) . implode(' ', $_SERVER['argv']); //cli_get_process_title();
			@cli_set_process_title($title);

			if (call_user_func($this->job, $this->state) === true) {
				$this->state->markDone();
			}
			die();
			// sometimes child process not properly exiting
			posix_kill($pid, SIGKILL);
		}

		$this->child_pid = $pid;
		return $this;
	}

	public function getPid(): int {
		return $this->child_pid;
	}

	public function getState(): State {
		return $this->state;
	}

	public function stop(): bool {
		if (!$this->isRunning()) {
			return true;
		}

		if (!posix_kill($this->child_pid, SIGTERM)) {
			return false;
		}

		usleep(1000);
		return !$this->isRunning();
	}

	public function kill(): bool {
		if (!$this->isRunning()) {
			return true;
		}

		if (!posix_kill($this->child_pid, SIGKILL)) {
			return false;
		}

		usleep(1000);
		return !$this->isRunning();
	}

	public function isRunning(): bool {
		if (!$this->child_pid) return false;

		$res = pcntl_waitpid($this->child_pid, $status, WNOHANG);
		usleep(1000);
		return !($res == -1 || $res > 0);
	}

	public function waitFor() {
		if ($this->isRunning()) {
			$null = null;
			pcntl_waitpid($this->child_pid, $null);
		}
	}

	private function registerSigHandler(): void {
		$pid     = getmypid();
		$handler = function (int $signo) use ($pid) {
			if ($pid !== getmypid()) return;

			if ($this->interrupt_handler) {
				call_user_func($this->interrupt_handler, $this->state, $signo);
			}
		};

		foreach ([SIGTERM, SIGHUP, SIGINT, SIGUSR1, SIGUSR2] as $signo) {
			pcntl_signal($signo, $handler);
		}
	}
}
