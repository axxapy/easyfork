<?php namespace axxapy\EasyFork\Tests;

use axxapy\EasyFork\Fork;
use axxapy\EasyFork\ForkPoolExecutor;
use axxapy\EasyFork\Modes\RunMode;
use axxapy\EasyFork\Process;
use axxapy\EasyFork\SharedMemory;
use PHPUnit\Framework\TestCase;

class Test extends TestCase {
	public function testTmp() {
		$this->markTestSkipped();
		$fork = (new Fork(function(SharedMemory $State) {
			echo "[fork] started\n";
			sleep(1);
			echo "[fork] done\n";
		}))->start();
		echo "[main] after fork started\n";
		$fork->waitFor();
		echo "[main] after fork done\n";
	}
    
    public function testTest() {
		$this->markTestSkipped();
        new ForkPoolExecutor(job: function() {
            echo 12;
            sleep(1);
        });
    }
	
	public function testFork() {
		$this->markTestSkipped();

		//var_dump(getmypid());
		$fork = (new Fork(job: function(...$args) {
			/** @var Process $this */
			
			var_dump($this);
			
			var_dump($this->shared_memory);
			var_dump(getmypid());
			return 3;
		}))->run();
		
		while($fork->isRunning()) {
			usleep(100);
		}
		var_dump($fork->waitFor());
		var_dump($fork->waitFor());
	}
	
	public function testForkPool() {
		$this->markTestSkipped();
		$result = (new ForkPoolExecutor(
			job: function (...$args) {
				/** @var Process $this */
				
				$counter = (int)$this->shared_memory['counter'];
				$counter_max = $args[0];
				$this->shared_memory['counter'] = ++$counter;
				
				echo "{$this->id}|{$this->pid}: $counter/$counter_max\n";
				
				sleep(3);
				
				return $counter >= $counter_max;
			},
			forks:    3,
			run_mode: RunMode::RUN_UNTIL_SUCCESS,
		))->run(3);
		
		var_dump($result);
	}
}
