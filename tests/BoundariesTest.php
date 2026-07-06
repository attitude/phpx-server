<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/StreamHarness.php';

final class BoundariesTest extends TestCase
{
    public function testNestedSuspenseBoundariesStreamInOrder(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        $inner = fn() => (function () { $v = await(0.02, fn() => 'INNER'); return ['$', 'span', null, [$v]]; })();
        $outer = function () use ($inner) {
            await(0.05, fn() => null);
            return ['$', 'div', null, ['outer ', Suspense(['$', 'em', null, ['inner-loading']], ['$', $inner])]];
        };
        (new StreamingRenderer())->stream(['$', 'body', null, [
            Suspense(['$', 'p', null, ['outer-loading']], ['$', $outer]),
        ]]);
        PHP);

        // Shell shows the outer fallback.
        $this->assertStringContainsString('outer-loading', $output);
        // Outer resolves to a subtree containing a NESTED boundary placeholder.
        $this->assertStringContainsString('id="B:1"', $output);
        // The nested boundary resolves to its real content.
        $this->assertStringContainsString('INNER', $output);
        // Outer boundary streams before the inner one it contains.
        $this->assertLessThan(
            strpos($output, 'data-b="1"'),
            strpos($output, 'data-b="0"'),
        );
    }

    public function testErrorBoundaryRendersFallbackOnSyncThrow(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        use function Attitude\PHPX\Server\ErrorBoundary;
        $boom = function () { throw new \RuntimeException('kaboom'); };
        (new StreamingRenderer())->stream(['$', 'body', null, [
            ErrorBoundary(['$', 'p', null, ['caught']], ['$', $boom]),
        ]]);
        PHP);

        $this->assertStringContainsString('caught', $output);
        $this->assertStringNotContainsString('kaboom', $output);
        $this->assertStringNotContainsString('Fatal error', $output);
    }

    public function testSuspenseBoundaryErrorStreamsFallbackNotFatal(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        $asyncBoom = function () { await(0.01, fn() => null); throw new \RuntimeException('async-boom'); };
        (new StreamingRenderer())->stream(['$', 'body', null, [
            Suspense(['$', 'p', null, ['boundary-fallback']], ['$', $asyncBoom]),
        ]]);
        PHP);

        // The stream survives the error and delivers the boundary's fallback.
        $this->assertStringContainsString('boundary-fallback', $output);
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringNotContainsString('async-boom', $output);
    }
}
