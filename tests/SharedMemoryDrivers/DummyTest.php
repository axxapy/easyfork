<?php namespace axxapy\EasyFork\Tests\SharedMemoryDrivers;

use axxapy\EasyFork\SharedMemory;
use axxapy\EasyFork\SharedMemoryDrivers\Dummy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dummy::class)]
#[UsesClass(SharedMemory::class)]
class DummyTest extends TestCase {
	public function testSetGet() {
		$mem = new Dummy;
		$mem->set('xxx', 'yyy');
		$this->assertEquals('default', $mem->get('xxx', 'default'));
	}
}
