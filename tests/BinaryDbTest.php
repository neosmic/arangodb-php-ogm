<?php

namespace ArangoPhpOgm\tests;

use Neosmic\ArangoPhpOgm\BinaryDb as BinaryDb;
use PHPUnit\Framework\TestCase as TestCase;

class BinaryDbTest extends TestCase
{
    public function testStart()
    {
        $connection = BinaryDb::start(['envDir' => 'src']);

        $this->assertContainsOnlyInstancesOf(BinaryDb::class, [$connection]);
    }
    public function testMain()
    {
        $connection = BinaryDb::start(['envDir' => 'src']);
        $out = $connection::main();
        $this->assertArrayHasKey('_key', $out);
    }
}
