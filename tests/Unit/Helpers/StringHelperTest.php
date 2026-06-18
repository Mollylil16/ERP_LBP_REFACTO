<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StringHelperTest extends TestCase
{
    public function test_trim_removes_spaces(): void
    {
        $values = trim('  ERP LBP  ');
        $this->assertSame('ERP LBP', $values);
    }
}
