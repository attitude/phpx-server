<?php declare(strict_types=1);

use Attitude\PHPX\Server\Head;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/StreamHarness.php';

final class HeadTest extends TestCase
{
    protected function tearDown(): void
    {
        Head::clear();
    }

    public function testTitleSetTwiceRendersOnlyTheLastOne(): void
    {
        Head::title('First');
        Head::title('Second');

        $html = Head::render();

        $this->assertStringNotContainsString('First', $html);
        $this->assertStringContainsString('<title>Second</title>', $html);
        $this->assertSame(1, substr_count($html, '<title>'));
    }

    public function testPushMetaAndLinkAreRendered(): void
    {
        Head::push(['$', 'meta', ['name' => 'author', 'content' => 'Attitude']]);
        Head::meta(['name' => 'description', 'content' => 'A demo app']);
        Head::link(['rel' => 'canonical', 'href' => 'https://example.test']);

        $html = Head::render();

        $this->assertStringContainsString('<meta name="author" content="Attitude"', $html);
        $this->assertStringContainsString('<meta name="description" content="A demo app"', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.test"', $html);
    }

    public function testRenderClearsTheStore(): void
    {
        Head::title('Something');
        Head::meta(['name' => 'description', 'content' => 'x']);

        Head::render();
        $second = Head::render();

        $this->assertSame('', $second);
    }

    public function testMarkerIsAStableStringConstant(): void
    {
        $this->assertSame(Head::marker(), Head::marker());
        $this->assertIsString(Head::marker());
    }

    public function testShellIntegrationReplacesMarkerWithHoistedTitle(): void
    {
        $output = StreamHarness::run(<<<'PHP'
        use Attitude\PHPX\Server\Head;

        $Page = function () {
            Head::title('X');

            return ['$', 'div', null, ['Hello']];
        };

        $page = ['$', 'html', null, [
            ['$', 'head', null, [
                ['$', 'fragment', ['dangerouslySetInnerHTML' => ['__html' => Head::marker()]]],
            ]],
            ['$', 'body', null, [['$', $Page]]],
        ]];
        (new StreamingRenderer())->stream($page);
        PHP);

        $this->assertStringContainsString('<title>X</title>', $output);
        $this->assertStringNotContainsString(Head::marker(), $output);
    }
}
