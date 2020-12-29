<?php declare(strict_types=1);

namespace axxapy\EasyFork;

use RuntimeException;

interface State {
	function getNum(): int;

	function getPayload(): array;

	function getGeneration(): int;

	function isDone(): bool;

	function shouldStop(): bool;

	/** @throws RuntimeException */
	function get(string $key, $default = null);

	/** @throws RuntimeException */
	function getAll(): array;
}
