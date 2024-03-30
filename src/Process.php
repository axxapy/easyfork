<?php declare(strict_types=1);

namespace axxapy\EasyFork;

class Process {
	public function __construct(
		readonly public string       $id,
		readonly public int          $pid,
		readonly public SharedMemory $shared_memory,
	) {}
}
