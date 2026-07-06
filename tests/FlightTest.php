<?php declare(strict_types=1);

use Attitude\PHPX\Server\Flight;
use PHPUnit\Framework\TestCase;
use function Attitude\PHPX\Server\await;
use function Attitude\PHPX\Server\Client;
use function Attitude\PHPX\Server\Suspense;

final class FlightTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['__flight'], $_SERVER['HTTP_X_FLIGHT'], $_SERVER['HTTP_ACCEPT']);
    }

    public function testWantsDetectsQueryHeaderAndAccept(): void
    {
        $this->assertFalse(Flight::wants());

        $_GET['__flight'] = '1';
        $this->assertTrue(Flight::wants());
        unset($_GET['__flight']);

        $_SERVER['HTTP_X_FLIGHT'] = '1';
        $this->assertTrue(Flight::wants());
        unset($_SERVER['HTTP_X_FLIGHT']);

        $_SERVER['HTTP_ACCEPT'] = 'application/x-component';
        $this->assertTrue(Flight::wants());
    }

    public function testServerComponentsAreExecutedAway(): void
    {
        $Greeting = fn (array $props): array => ['$', 'p', null, ['Hi ' . $props['name']]];

        $tree = Flight::serialize(['$', $Greeting, ['name' => 'Ada']]);

        $this->assertSame(['$', 'p', null, ['Hi Ada']], $tree);
    }

    public function testNamedComponentsResolveFromMap(): void
    {
        $components = ['Box' => fn (array $p): array => ['$', 'div', null, $p['children'] ?? []]];

        $tree = Flight::serialize(['$', 'Box', null, ['x']], $components);

        $this->assertSame(['$', 'div', null, ['x']], $tree);
    }

    public function testClassNameClsxIsNormalisedToClassString(): void
    {
        $tree = Flight::serialize(['$', 'li', ['className' => ['Item' => true, 'done' => true, 'hidden' => false]], ['a']]);

        $this->assertSame('Item done', $tree[2]['class']);
        $this->assertArrayNotHasKey('className', $tree[2]);
    }

    public function testStyleObjectIsNormalisedToCssString(): void
    {
        $tree = Flight::serialize(['$', 'div', ['style' => ['marginTop' => '4px', 'color' => 'red']]]);

        $this->assertSame('margin-top:4px;color:red', $tree[2]['style']);
    }

    public function testClientBoundaryIsPreservedWithSerializableProps(): void
    {
        $tree = Flight::serialize(Client('TodoApp', ['todos' => [['id' => 't1']]]));

        $this->assertSame('div', $tree[1]);
        $this->assertSame('TodoApp', $tree[2]['data-client']);
        $decoded = json_decode($tree[2]['data-props'], true);
        $this->assertSame([['id' => 't1']], $decoded['todos']);
    }

    public function testSuspenseResolvesInlineOffFiber(): void
    {
        $Slow = function (): array {
            $value = await(0.0, fn (): string => 'ready');
            return ['$', 'span', null, [$value]];
        };

        $tree = Flight::serialize(Suspense(['$', 'p', null, ['loading']], ['$', $Slow]));

        // Suspense children resolve to the real content (a list with the span).
        $json = json_encode($tree);
        $this->assertStringContainsString('ready', (string) $json);
        $this->assertStringNotContainsString('loading', (string) $json);
    }

    public function testTrailingNullSlotsAreTrimmedLikeTheCompiler(): void
    {
        $this->assertSame(['$', 'br'], Flight::serialize(['$', 'br']));
        $this->assertSame(['$', 'hr', ['id' => 'x']], Flight::serialize(['$', 'hr', ['id' => 'x']]));
    }
}
