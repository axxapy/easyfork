<?php declare(strict_types=1);

namespace axxapy\EasyFork\_;

use axxapy\EasyFork\ProcessManager;

class _fork {
	public function __construct(
		public ProcessManager $process,
		public int            $generation = 0,
	) {}
}
