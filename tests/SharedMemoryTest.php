<?php namespace axxapy\EasyFork\Tests;

use axxapy\EasyFork\SharedMemory;
use axxapy\EasyFork\SharedMemoryDrivers\DriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedMemory::class)]
class SharedMemoryTest extends TestCase {
	private static DriverInterface $driver;

	protected function setUp(): void {
		self::$driver = new class implements DriverInterface {
			private array $memory = [];

			public function set(string $key, mixed $value): void {
				$this->memory[$key] = $value;
			}

			public function get(string $key, mixed $default = null): mixed {
				return $this->memory[$key] ?? $default;
			}
		};
	}

	public function test() {
		$memory = new SharedMemory(driver: self::$driver);
		$memory['a'] = 'a1';
		$memory['b'] = 'b1';

		$this->assertEquals('a1', $memory['a']);
		$this->assertEquals('b1', $memory['b']);
		$this->assertCount(2, $memory);

		$this->assertEquals('a1', $memory->current());
		$memory->seek(1);
		$this->assertEquals('b1', $memory->current());

		$this->assertTrue(isset($memory['a']));
		$this->assertFalse(isset($memory['c']));

		$arr = [];
		foreach ($memory as $key => $val) {
			$arr[$key] = $val;
		}
		$this->assertEquals(['a' => 'a1', 'b' => 'b1'], $arr);
		$this->assertEquals(['a' => 'a1', 'b' => 'b1'], $memory->toArray());

		unset($memory['a1']);
		$this->assertNull($memory['a1']);
	}
}
