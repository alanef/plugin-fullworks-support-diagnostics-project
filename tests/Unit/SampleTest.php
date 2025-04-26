<?php
/**
 * Sample test case.
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\AssertEqualsCanonicalizing;

class SampleTest extends TestCase {
    use AssertEqualsCanonicalizing;

    /**
     * A sample test.
     */
    public function test_sample() {
        $this->assertTrue(true);
    }
}