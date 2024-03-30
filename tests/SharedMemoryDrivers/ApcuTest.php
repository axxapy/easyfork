<?php namespace axxapy\EasyFork\Tests\SharedMemoryDrivers;

use axxapy\EasyFork\_\_fork;
use axxapy\EasyFork\Fork;
use axxapy\EasyFork\ForkPoolExecutor;
use axxapy\EasyFork\Logger;
use axxapy\EasyFork\Modes\RunMode;
use axxapy\EasyFork\Process;
use axxapy\EasyFork\ProcessManager;
use axxapy\EasyFork\SharedMemory;
use axxapy\EasyFork\SharedMemoryDrivers\Apcu;
use axxapy\EasyFork\SharedMemoryDrivers\Dummy;
use axxapy\EasyFork\SharedMemoryDrivers\Filesystem;
use axxapy\EasyFork\SharedMemoryDrivers\InMemoryViaSocket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Apcu::class)]
#[UsesClass(SharedMemory::class)]
#[UsesClass(Dummy::class)]
#[UsesClass(ForkPoolExecutor::class)]
#[UsesClass(Fork::class)]
#[UsesClass(Process::class)]
#[UsesClass(ProcessManager::class)]
#[UsesClass(Logger::class)]
#[UsesClass(_fork::class)]
class ApcuTest extends TestCase {
	public function testPureDriver() {
		$ram = new Apcu;

		$this->assertEquals('default', $ram->get('a', 'default'));
		$this->assertEquals(null, $ram->get('b'));

		(new Fork(job: function () use ($ram) {
			$ram->set('a', 'a1');
			$ram->set('b', 'b1');
		}, shared_memory_driver_factory: fn() => new Dummy))->run()->waitFor();

		$this->assertEquals('a1', $ram->get('a'));
		$this->assertEquals('b1', $ram->get('b'));
	}

	public function testViaSharedMemory() {
		$shared_memory = new SharedMemory(new Apcu);

		(new Fork(job: function () use ($shared_memory) {
			$shared_memory['a'] = 'a1';
			$shared_memory['b'] = 'b1';
		}, shared_memory_driver_factory: fn() => new Dummy))->run()->waitFor();

		$this->assertEquals('a1', $shared_memory['a']);
		$this->assertEquals('b1', $shared_memory['b']);
	}

	public function testMultiThread() {
		$result = (new ForkPoolExecutor(
			job          : function () {
				/** @var Process $this */
				$count                          = $this->shared_memory[$this->id] ?? 0;
				$this->shared_memory[$this->id] = ++$count;
				return $this->shared_memory[$this->id] >= 100;
			},
			forks        : 10,
			run_mode     : RunMode::RUN_UNTIL_SUCCESS,
			shared_memory: new SharedMemory(new Apcu),
		))->run();
		$this->assertEquals(array_fill(0, 10, 100), $result);
	}
}
