<?php declare(strict_types=1);

namespace axxapy\EasyFork\Modes;

enum SharedMemoryMode {
	case ISOLATED; // Shared memory only shared between forks with same ID but different generations
	case COMMON;   // Single shared memory between all forks
}
