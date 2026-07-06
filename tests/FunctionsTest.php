<?php declare(strict_types=1);

use Attitude\PHPX\Server\Actions;
use PHPUnit\Framework\TestCase;
use function Attitude\PHPX\Server\action;
use function Attitude\PHPX\Server\await;
use function Attitude\PHPX\Server\Client;
use function Attitude\PHPX\Server\Suspense;

final class FunctionsTest extends TestCase
{
    public function testClientReturnsDivTupleWithDataClientAndJsonRoundTrippingProps(): void
    {
        $ssr = ['$', 'p', null, ['loading']];

        $node = Client('TodoApp', ['a' => 1], $ssr);

        $this->assertSame('$', $node[0]);
        $this->assertSame('div', $node[1]);
        $this->assertSame('TodoApp', $node[2]['data-client']);
        $this->assertSame(['a' => 1], json_decode($node[2]['data-props'], true));
        $this->assertSame([$ssr], $node[3]);
    }

    public function testClientWithNullSsrHasEmptyChildren(): void
    {
        $node = Client('TodoApp', ['a' => 1]);

        $this->assertSame([], $node[3]);
    }

    public function testSuspenseWrapsFallbackAndWrapsSingleElementChildInArray(): void
    {
        $fallback = ['$', 'p', null, ['loading']];
        $child = ['$', 'span', null, ['hi']];

        $node = Suspense($fallback, $child);

        $this->assertSame(['$', 'Suspense', ['fallback' => $fallback], [$child]], $node);
    }

    public function testAwaitOutsideFiberRunsWorkImmediatelyAndReturnsItsValue(): void
    {
        $ran = false;
        $value = await(0.5, function () use (&$ran) {
            $ran = true;

            return 'value';
        });

        $this->assertTrue($ran);
        $this->assertSame('value', $value);
    }

    public function testActionRegistersOnTheActionsRegistry(): void
    {
        action('functions-test/noop', fn (array $args) => $args);

        $this->assertTrue(Actions::has('functions-test/noop'));
        $this->assertSame(['x' => 1], Actions::dispatch('functions-test/noop', ['x' => 1]));
    }
}
