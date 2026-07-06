<?php declare(strict_types=1);

use Attitude\PHPX\Server\Cache;
use PHPUnit\Framework\TestCase;
use function Attitude\PHPX\Server\cache;

final class CacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::clear();
    }

    public function testSameArgsInvokeUnderlyingOnceAndReturnTheSameValue(): void
    {
        $calls = 0;
        $wrapped = cache(function (string $id) use (&$calls) {
            $calls++;

            return "user-{$id}";
        });

        $first = $wrapped('42');
        $second = $wrapped('42');

        $this->assertSame(1, $calls);
        $this->assertSame('user-42', $first);
        $this->assertSame($first, $second);
    }

    public function testDifferentArgsInvokeUnderlyingAgain(): void
    {
        $calls = 0;
        $wrapped = cache(function (string $id) use (&$calls) {
            $calls++;

            return "user-{$id}";
        });

        $wrapped('42');
        $wrapped('43');

        $this->assertSame(2, $calls);
    }

    public function testClearCausesNextCallToRecompute(): void
    {
        $calls = 0;
        $wrapped = cache(function (string $id) use (&$calls) {
            $calls++;

            return "user-{$id}";
        });

        $wrapped('42');
        Cache::clear();
        $wrapped('42');

        $this->assertSame(2, $calls);
    }

    public function testTwoDifferentWrappedCallablesWithSameArgsDoNotCollide(): void
    {
        $callsA = 0;
        $callsB = 0;
        $wrappedA = cache(function (string $id) use (&$callsA) {
            $callsA++;

            return "a-{$id}";
        });
        $wrappedB = cache(function (string $id) use (&$callsB) {
            $callsB++;

            return "b-{$id}";
        });

        $resultA = $wrappedA('42');
        $resultB = $wrappedB('42');

        $this->assertSame(1, $callsA);
        $this->assertSame(1, $callsB);
        $this->assertSame('a-42', $resultA);
        $this->assertSame('b-42', $resultB);
    }
}
