<?php

error_reporting(E_ALL);
ini_set('display_errors', true);

use axxapy\EasyFork\Fork;
use axxapy\EasyFork\ForkPoolExecutor;
use axxapy\EasyFork\SharedMemory;

require __DIR__ . '/vendor/autoload.php';


$stop = false;
(new ForkPoolExecutor(job               : function() use (&$stop) {
	$this->shared_memory["fork_$this->id"] = 'a1';
	while (!$stop) {
		sleep(1);
	}
},   job_signal_handler: function() use (&$stop) {
	$stop = true;
	return false;
}))->run();

exit;

function test(Closure $test) {
	try {
		var_dump($test->call(new \axxapy\EasyFork\_\_, "x", "y"));
		echo "OK\n";
	} catch (Throwable $e) {
		echo "FAIL: " . $e->getMessage() . "\n";
	}
}

test(fn(...$x) => implode(",", $x));

exit;


$pid = pcntl_fork();

if ($pid === 0) {
	echo "I'm a fork\n";
	pcntl_signal(SIGTERM, function(int $sig) {
		echo "got signal: $sig\n";
	});
	pcntl_async_signals(true);
	while (true) {
		sleep(100500);
	}
	exit;
}

echo "I'm a papa\n";
sleep(1);

while (true) {
	posix_kill($pid, SIGTERM);
	echo "sent SIGTERM\n";
	sleep(1);
}

exit;

var_dump(getmypid());
(new Fork(
	job: function (SharedMemory $state) {
		while (true) {
			sleep(100000);
		}
		var_dump(getmypid());
	},
	signal_handler: function() {
		echo "interrupted";
	}
))->run()->waitFor();

exit;


$Pool = new ForkPoolExecutor(function (SharedMemory $State) {

	while (true) {
		sleep(1);
	}


	$State->put('mynum', $State->getNum());
	$inc = $State->get('inc', 0);
	$State->put('inc', ++$inc);
	$State->put('generation', array_merge($State->get('generation', []), [$State->getGeneration()]));
	sleep(100);
	if ($State->getGeneration() >= 3) {
		sleep(100);
		return true;
	}
}, 1);
$Pool->setLogger(function ($log) {
	echo $log . "\n";
});
$Pool->setInterruptHandler(function ($signo) {
	var_dump("GLOBAL INTERRUPTION HANDLER: $signo");
})->setForkInterruptHandler(function (SharedMemory $state, $signo) {
	var_dump("FORK INTERRUPTION HANDLER: $signo " . get_class($state));
});
$res = $Pool->run();
var_dump($res);
