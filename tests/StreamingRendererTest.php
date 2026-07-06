<?php declare(strict_types=1);

use Attitude\PHPX\Server\StreamingRenderer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/StreamHarness.php';

final class StreamingRendererTest extends TestCase
{
    public function testPageWithoutSuspenseRendersPlainHtml(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        $page = ['$', 'div', ['className' => 'App'], ['Hello, ', ['$', 'strong', null, ['world']]]];
        (new StreamingRenderer())->stream($page);
        PHP);

        // newRenderer() sets $renderer->react = true, which inserts React's
        // hydration whitespace markers (`<!-- -->`) after text with trailing
        // whitespace next to an element — hence the comment before <strong>.
        $this->assertStringContainsString('<div class="App">Hello, <!-- --><strong>world</strong></div>', $output);
    }

    public function testSuspenseBoundaryEmitsFallbackThenResolvedTemplateWithSwapScript(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        $page = ['$', 'div', null, [
            Suspense(
                ['$', 'p', null, ['Loading']],
                ['$', function () {
                    $value = await(0.0, fn () => 'Resolved');

                    return ['$', 'p', null, [$value]];
                }],
            ),
        ]];
        (new StreamingRenderer())->stream($page);
        PHP);

        $this->assertStringContainsString('<div id="B:0"><p>Loading</p></div>', $output);
        $this->assertStringContainsString('<template data-b="0"><p>Resolved</p></template>', $output);
        $this->assertStringContainsString('__rscSwap(0)', $output);

        $shellPos = strpos($output, 'B:0');
        $templatePos = strpos($output, '<template data-b="0">');
        $this->assertNotFalse($shellPos);
        $this->assertNotFalse($templatePos);
        $this->assertLessThan($templatePos, $shellPos, 'The resolved <template> must be streamed after the shell.');
    }

    public function testTwoSuspenseBoundariesResolveOutOfOrderByDelay(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        $page = ['$', 'div', null, [
            Suspense(
                ['$', 'p', null, ['F0']],
                ['$', function () {
                    $value = await(0.05, fn () => 'R0');

                    return ['$', 'p', null, [$value]];
                }],
            ),
            Suspense(
                ['$', 'p', null, ['F1']],
                ['$', function () {
                    $value = await(0.0, fn () => 'R1');

                    return ['$', 'p', null, [$value]];
                }],
            ),
        ]];
        (new StreamingRenderer())->stream($page);
        PHP);

        $this->assertStringContainsString('id="B:0"', $output);
        $this->assertStringContainsString('id="B:1"', $output);

        $template0Pos = strpos($output, '<template data-b="0">');
        $template1Pos = strpos($output, '<template data-b="1">');
        $this->assertNotFalse($template0Pos);
        $this->assertNotFalse($template1Pos);

        // Boundary 1 was declared second but has the smaller delay, so it must
        // resolve (and stream its <template>) before boundary 0.
        $this->assertLessThan($template0Pos, $template1Pos);
    }

    public function testClientRuntimeContainsSwapFunctionAndScriptTag(): void
    {
        $js = StreamingRenderer::clientRuntime();

        $this->assertStringContainsString('<script', $js);
        $this->assertStringContainsString('__rscSwap', $js);
    }
}
