<?php declare(strict_types=1);

namespace axxapy\EasyFork\_;

enum _state {
	case IDLE;
	case RUNNING;
	case STOPPING;
}
