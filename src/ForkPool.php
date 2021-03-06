<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use axxapy\EasyFork\Internal\LoggerWrapper;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ForkPool implements LoggerAwareInterface  {
	const STATE_IDLE     = 0;
	const STATE_RUNNING  = 1;
	const STATE_STOPPING = 2;

	/** @var int */
	private $forks_count;

	/** @var callable */
	private $job;

	private $state = self::STATE_IDLE;

	/** @var Fork[] */
	private $forks = [];

	private $tick_us               = 20000; //0.02 seconds
	private $graceful_shutdown_sec = 5; //seconds

	/** @var LoggerWrapper */
	private $logger;

	private $interrupt_handler_fork;
	private $interrupt_handler;

	/** @throws InvalidArgumentException */
	public function __construct(callable $job, int $forks = 1) {
		if ($forks < 1) {
			throw new InvalidArgumentException('minimum 1 fork required');
		}
		$this->job         = $job;
		$this->forks_count = $forks;
		$this->logger      = new LoggerWrapper;
		$this->registerSigHandler();
	}

	public function setLogger(LoggerInterface $logger): self {
		$this->logger = (new LoggerWrapper($logger))->setPrefix("[MAIN] ");
		return $this;
	}

	public function setInterruptHandler(callable $handler): self {
		$this->interrupt_handler = $handler;
		return $this;
	}

	public function setForkInterruptHandler(callable $handler): self {
		$this->interrupt_handler_fork = $handler;
		return $this;
	}

	/** @throws RuntimeException */
	public function start(array $payload = []): array {
		if ($this->state !== self::STATE_IDLE) {
			throw new RuntimeException('can not be started twice');
		}

		$this->state = self::STATE_RUNNING;

		$title = '[MAIN] ' . implode(' ', $_SERVER['argv']); //cli_get_process_title();
		@cli_set_process_title($title);

		$logger = $this->logger->unwrap();
		for ($i = 0; $i < $this->forks_count; $i++) {
			$this->forks[$i] = new Fork($this->job, $i);
			if ($this->interrupt_handler_fork) {
				$this->forks[$i]->setInterruptHandler($this->interrupt_handler_fork);
			}
			if ($logger) {
				$this->forks[$i]->setLogger($logger);
			}
		}

		do {
			$done = 0;
			foreach ($this->forks as $fork) {
				if ($fork->getState()->isDone()) {
					$done++;
					continue;
				}

				if (!$fork->isRunning()) {
					$fork->start($payload);
				}

				usleep($this->tick_us);
			}

			if ($done == $this->forks_count) {
				break;
			}
		} while ($this->state == self::STATE_RUNNING);

		$result = array_map(function (Fork $Fork) {
			return $Fork->getState()->getAll();
		}, $this->forks);

		$this->state = self::STATE_IDLE;
		$this->forks = [];

		return $result;
	}

	public function stop() {
		if ($this->state !== self::STATE_RUNNING) {
			return $this->state === self::STATE_IDLE;
		}

		$this->state = self::STATE_STOPPING;

		$this->logger->logf('Gracefully stopping children...');

		$time = time();
		do {
			$running = 0;
			foreach ($this->forks as $fork) {
				if (!$fork->stop()) {
					$running++;
				}
			}
			$running && sleep(1);
		} while ($running && time() - $time <= $this->graceful_shutdown_sec);

		if ($running) {
			$this->logger->logf("Failed to gracefully %d/%d children. Killing them...", $running, count($this->forks));
		}

		for ($attempts = 0; $attempts < 10 && $running; $attempts++) {
			$running = 0;
			foreach ($this->forks as $fork) {
				if (!$fork->kill()) $running++;
			}
			$running && sleep(1);
		}

		$this->logger->log($running ? "Failed to stop $running kids" : 'Done');
	}

	private function registerSigHandler(): void {
		$pid = getmypid();
		$handler = function (int $signo) use ($pid) {
			if ($pid !== getmypid()) return;

			$this->logger->logf("Signal received: %d", $signo);

			if ($this->interrupt_handler) {
				if (call_user_func($this->interrupt_handler, $signo) === false) {
					return; // ignore signal if handler returns false
				}
			}

			$this->stop();
		};

		foreach ([SIGTERM, SIGHUP, SIGINT, SIGUSR1, SIGUSR2] as $signo) {
			pcntl_signal($signo, $handler);
		}
	}
}
