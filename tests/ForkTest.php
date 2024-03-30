<?php namespace axxapy\EasyFork\Tests;

use axxapy\EasyFork\_\_;
use axxapy\EasyFork\Fork;
use axxapy\EasyFork\Process;
use axxapy\EasyFork\ProcessManager;
use axxapy\EasyFork\SharedMemory;
use axxapy\EasyFork\SharedMemoryDrivers\Apcu;
use axxapy\EasyFork\SharedMemoryDrivers\DriverFactory;
use axxapy\EasyFork\SharedMemoryDrivers\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fork::class)]
#[CoversClass(Process::class)]
#[UsesClass(ProcessManager::class)]
#[UsesClass(SharedMemory::class)]
#[UsesClass(Apcu::class)]
#[UsesClass(Filesystem::class)]
#[UsesClass(DriverFactory::class)]
class ForkTest extends TestCase {
	#[TestWith([true, 0])]
	#[TestWith([false, 1])]
	#[TestWith([0, 0])]
	#[TestWith([1, 1])]
	#[TestWith([99, 99])]
	public function testExitCode(mixed $return_value, int $expected_exit_code) {
		$exit_code = (new Fork(job: function () use ($return_value) {
			return $return_value;
		}))->run()->waitFor();
		$this->assertEquals($expected_exit_code, $exit_code);
	}

	#[TestWith([true, 0])]
	#[TestWith([false, 1])]
	#[TestWith([0, 0])]
	#[TestWith([1, 1])]
	#[TestWith([99, 99])]
	public function testExitCodeViaIsRunning(mixed $return_value, int $expected_exit_code) {
		$proc = (new Fork(job: function () use ($return_value) {
			return $return_value;
		}))->run();
		while ($proc->isRunning()) {
			usleep(1000);
		}
		$exit_code = $proc->waitFor();
		$this->assertEquals($expected_exit_code, $exit_code);
	}

	public function testInterruptHandlerExit() {
		$proc = (new Fork(
			job: function () {
				TestHelper::confirmStarted($this);
				sleep(100500);
			},
			signal_handler: function (int $signo): bool {
				$this->shared_memory['interrupted'] = true;
				return true;
			},
		))->run();
		$this->assertTrue(TestHelper::ensureValue($proc));

		$proc->stop();
		$proc->waitFor();
		$this->assertFalse($proc->isRunning());

		$this->assertTrue($proc->shared_memory['interrupted']);
	}

	public function testInterruptHandlerIgnoresSignal() {
		$proc = (new Fork(
			job: function () {
				TestHelper::confirmStarted($this);
				while (true) {
					sleep(100500);
				}
				return 9;
			},
			signal_handler: function (int $signo): bool {
				$this->shared_memory['interrupted'] = true;
				return false;
			},
		))->run();
		$this->assertTrue(TestHelper::ensureValue($proc));

		$proc->stop();

		$this->assertTrue($proc->isRunning());
		$this->assertTrue(TestHelper::ensureValue($proc, 'interrupted'));

		//graceful shutdown
		$time = microtime(true);
		$proc->kill(1.2);
		$this->assertEquals(0, $proc->waitFor());
		$time_diff = microtime(true) - $time;

		$this->assertTrue($time_diff >= 1.2);
		$this->assertTrue($time_diff <= 1.3);
		$this->assertFalse($proc->isRunning());
	}

	public function testGracefulShutdownNoKill() {
		$stop_signal_received = false;
		$proc = (new Fork(
			job: function () use (&$stop_signal_received) {
				TestHelper::confirmStarted($this);
				while (!$stop_signal_received) {
					sleep(100500);
				}
			},
			signal_handler: function (int $signo) use (&$stop_signal_received): bool {
				$this->shared_memory['interrupt_counter'] = (int)$this->shared_memory['interrupt_counter'] + 1;
				$stop_signal_received = true;
				return false;
			},
		))->run();
		TestHelper::ensureValue($proc);

		$proc->kill(5);
		$this->assertFalse($proc->isRunning());
		$this->assertTrue($proc->shared_memory['started']);
		$this->assertEquals(1, $proc->shared_memory['interrupt_counter']);
	}
}
