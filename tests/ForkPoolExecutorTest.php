<?php namespace axxapy\EasyFork\Tests;

use axxapy\EasyFork\_\_fork;
use axxapy\EasyFork\Fork;
use axxapy\EasyFork\ForkPoolExecutor;
use axxapy\EasyFork\Logger;
use axxapy\EasyFork\Modes\RunMode;
use axxapy\EasyFork\Modes\SharedMemoryMode;
use axxapy\EasyFork\Process;
use axxapy\EasyFork\ProcessManager;
use axxapy\EasyFork\SharedMemory;
use axxapy\EasyFork\SharedMemoryDrivers\Apcu;
use axxapy\EasyFork\SharedMemoryDrivers\DriverFactory;
use axxapy\EasyFork\SharedMemoryDrivers\Dummy;
use axxapy\EasyFork\SharedMemoryDrivers\Filesystem;
use axxapy\EasyFork\SharedMemoryDrivers\InMemoryViaSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForkPoolExecutor::class)]
#[CoversClass(_fork::class)]
#[UsesClass(Fork::class)]
#[UsesClass(Process::class)]
#[UsesClass(ProcessManager::class)]
#[UsesClass(SharedMemory::class)]
#[UsesClass(Apcu::class)]
#[UsesClass(InMemoryViaSocket::class)]
#[UsesClass(Filesystem::class)]
#[UsesClass(Logger::class)]
#[UsesClass(DriverFactory::class)]
class ForkPoolExecutorTest extends TestCase {
	#[TestWith([SharedMemoryMode::ISOLATED, ['x'], [['args_0' => ['x']], ['args_1' => ['x']], ['args_2' => ['x']]]])]
	#[TestWith([SharedMemoryMode::COMMON, ['x'], ['args_0' => ['x'], 'args_1' => ['x'], 'args_2' => ['x']]])]
	public function testSharedMemoryModes(SharedMemoryMode $mode, array $args, array $expected_result) {
		$result = (new ForkPoolExecutor(
			job: function (Process $proc, ...$args) {
				$proc->shared_memory["args_{$proc->id}"] = $args;
			},
			forks:              3,
			shared_memory_mode: $mode,
		))->run(...$args);
		$this->assertEquals($expected_result, $result);
	}

	#[TestWith([RunMode::RUN_ONCE, [['counter' => 1]]])]
	#[TestWith([RunMode::RUN_UNTIL_SUCCESS, [['counter' => 3]]])]
	public function testRunModes(RunMode $mode, array $expected_result) {
		$result = (new ForkPoolExecutor(
			job: function (Process $proc, ...$args) {
				$proc->shared_memory['counter'] += 1;
				return $proc->shared_memory['counter'] >= 3;
			},
			run_mode: $mode,
		))->run();
		$this->assertEquals($expected_result, $result);
	}

	public function testStop() {
		$this->markTestSkipped('incomplete');

		$must_stop = false;
		$executor = new ForkPoolExecutor(
			job           : function (Process $proc) use (&$must_stop) {
				$proc->shared_memory['counter'] += 1;
				while (!$must_stop) {
					sleep(1);
				}
			},
			run_mode      : RunMode::RUN_UNTIL_SUCCESS,
			signal_handler: function(int $sig) use (&$must_stop): bool {
				$must_stop = true;
				return false;
			},
		);

		(new Fork(job: function(Process $proc) {
		}, shared_memory_driver_factory: fn() => new Dummy))->run();

		$executor->run();
		$executor->stop();
		$this->assertEquals([['counter' => 1], ['counter' => 2]], $executor->run());
	}
}
