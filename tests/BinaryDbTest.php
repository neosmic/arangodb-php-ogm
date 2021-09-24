<?php

namespace ArangoPhpOgm\tests;

use Neosmic\ArangoPhpOgm\BinaryDb as BinaryDb;
use PHPUnit\Framework\TestCase as TestCase;

class BinaryDbTest extends TestCase
{
    public function testStart()
    {
        $connection = BinaryDb::start('/app');

        $this->assertContainsOnlyInstancesOf(BinaryDb::class, [$connection]);
    }
    public function testMain()
    {
        $connection = BinaryDb::start('/app');
        $out = $connection::main();
        $this->assertArrayHasKey('_key', $out);
    }
}
