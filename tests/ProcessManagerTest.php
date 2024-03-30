<?php namespace axxapy\EasyFork\Tests;

use axxapy\EasyFork\Fork;
use axxapy\EasyFork\Process;
use axxapy\EasyFork\ProcessManager;
use axxapy\EasyFork\SharedMemory;
use axxapy\EasyFork\SharedMemoryDrivers\DriverFactory;
use axxapy\EasyFork\SharedMemoryDrivers\Dummy;
use axxapy\EasyFork\SharedMemoryDrivers\InMemoryViaSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessManager::class)]
#[UsesClass(Process::class)]
#[UsesClass(Fork::class)]
#[UsesClass(SharedMemory::class)]
#[UsesClass(DriverFactory::class)]
#[UsesClass(InMemoryViaSocket::class)]
class ProcessManagerTest extends TestCase {
	public function testFromProcess() {
		$shared_memory = new SharedMemory(driver: new Dummy);
		$proc          = ProcessManager::fromProcess(
			new Process(
				id           : 1,
				pid          : 2,
				shared_memory: $shared_memory,
			),
		);
		$this->assertEquals(1, $proc->id);
		$this->assertEquals(2, $proc->pid);
		$this->assertEquals($shared_memory, $proc->shared_memory);
	}

	public function testIsRunning() {
		$proc = (new Fork(job: function () {
			/** @var Process $this */
			$this->shared_memory['done'] = true;
			return 123;
		}, shared_memory_driver_factory: fn() => new InMemoryViaSocket))->run();

		$this->assertTrue($proc->isRunning());
		for ($n = 0; $proc->shared_memory['done'] !== false && $n < 20; $n++) {
			usleep(1000);
		}
		$this->assertTrue($proc->shared_memory['done']);
		$this->assertFalse($proc->isRunning());
		$this->assertEquals(123, $proc->waitFor());

		// second time asking, first if
		$this->assertFalse($proc->isRunning());
	}

	public function testWaitFor() {
		$proc = (new Fork(job: function () {
			return 123;
		}, shared_memory_driver_factory: fn() => new InMemoryViaSocket))->run();
		$this->assertEquals(123, $proc->waitFor());

		// second call, exit code from variable
		$this->assertEquals(123, $proc->waitFor());
	}

	public function testKill() {
		$proc = (new Fork(function () {
			while (true) sleep(1);
		}, shared_memory_driver_factory: fn() => new InMemoryViaSocket))->run();

		$this->assertTrue($proc->isRunning());
		$this->assertTrue($proc->kill());
		for ($n = 0; $proc->isRunning() && $n < 20; $n++) {
			usleep(1000); // it takes time to kill (dispatch and process signal). May fail on very slow or overloaded systems.
		}
		$this->assertFalse($proc->isRunning());
	}

	public function testStopNoKill() {
		$stop = false;
		$proc = (new Fork(
			job           : function () use (&$stop) {
				TestHelper::confirmStarted($this);

				while (!$stop) usleep(1000);
				return 9;
			},
			signal_handler: function (int $sig) use (&$stop): bool {
				$stop = true;
				return false;
			},
			shared_memory_driver_factory: fn() => new InMemoryViaSocket,
		))->run();
		$this->assertTrue(TestHelper::ensureValue($proc));

		$proc->stop();
		$this->assertEquals(9, $proc->waitFor());
	}

	public function testKillGracefulStop() {
		$stop_after = 0;
		$proc       = (new Fork(
			job           : function () use (&$stop_after) {
				TestHelper::confirmStarted($this);

				while ($stop_after == 0 || microtime(true) < $stop_after) {
					usleep(1000);
				}

				return 9;
			},
			signal_handler: function (int $sig) use (&$stop_after): bool {
				$stop_after || $stop_after = microtime(true) + 1;
				return false;
			},
			shared_memory_driver_factory: fn() => new InMemoryViaSocket,
		))->run();
		$this->assertTrue(TestHelper::ensureValue($proc));

		$time = microtime(true);
		$proc->kill(5);
		$time_diff = microtime(true) - $time;

		$this->assertTrue($time_diff >= 1 && $time_diff < 1.1);
		$this->assertEquals(9, $proc->waitFor());
	}

	public function testKillTimeoutKill() {
		$proc = (new Fork(
			job           : function () use (&$stop_by) {
				TestHelper::confirmStarted($this);

				while (true) sleep(1);
				return 9;
			},
			signal_handler: function (int $sig) use (&$stop_by): bool {
				return false;
			},
			shared_memory_driver_factory: fn() => new InMemoryViaSocket,
		))->run();
		$this->assertTrue(TestHelper::ensureValue($proc));

		$time = microtime(true);
		$proc->kill(1.2);
		$time_diff = microtime(true) - $time;

		$this->assertTrue($time_diff >= 1.2 && $time_diff < 1.3);
		$this->assertEquals(0, $proc->waitFor());
	}
}


