<?php

use \Neosmic\ArangoPhpOgm\BinaryDb;
use PHPUnit\Framework\TestCase;

class BinarydbTest extends TestCase
{
    public function testStart()
    {
        $connection = BinaryDb::start("/app");

        $this->assertContainsOnlyInstancesOf(BinaryDb::class, [$connection]);
    }
    public function testMain()
    {
        $connection = BinaryDb::start("/app");
        $out = $connection::main();
        $this->assertArrayHasKey("_key", $out);
    }
}
