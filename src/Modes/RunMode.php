<?php declare(strict_types=1);

namespace axxapy\EasyFork\Modes;

enum RunMode {
	case RUN_ONCE;
	case RUN_UNTIL_SUCCESS;
}
