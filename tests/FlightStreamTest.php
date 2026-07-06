<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/StreamHarness.php';

final class FlightStreamTest extends TestCase
{
    /** Run a Flight::stream() page in a subprocess and capture the NDJSON. */
    private function runStream(string $body): string
    {
        return StreamHarness::run("use Attitude\\PHPX\\Server\\Flight;\n" . $body);
    }

    public function testShellRowContainsPlaceholdersAndBoundariesResolveOutOfOrder(): void
    {
        $output = $this->runStream(<<<'PHP'
        $slow = fn(string $label, float $d) => (function () use ($label, $d) {
            $v = await($d, fn() => strtoupper($label));
            return ['$', 'p', ['id' => "done-{$label}"], [$v]];
        });
        Flight::stream(['$', 'main', null, [
            Suspense(['$', 'em', null, ['load-a']], ['$', $slow('a', 0.06)]),
            Suspense(['$', 'em', null, ['load-b']], ['$', $slow('b', 0.02)]),
        ]]);
        PHP);

        $lines = array_values(array_filter(explode("\n", trim($output))));
        $this->assertCount(3, $lines, "expected shell + 2 boundary rows, got:\n{$output}");

        // Shell row: placeholders with the fallbacks in place.
        $shell = json_decode($lines[0], true);
        $this->assertIsArray($shell);
        $this->assertStringContainsString('F:0', $lines[0]);
        $this->assertStringContainsString('F:1', $lines[0]);
        $this->assertStringContainsString('load-a', $lines[0]);
        $this->assertStringContainsString('load-b', $lines[0]);

        // Boundary rows carry {b, tree}; the faster (b, 0.02) resolves first.
        $first = json_decode($lines[1], true);
        $second = json_decode($lines[2], true);
        $this->assertSame(1, $first['b']);
        $this->assertSame(0, $second['b']);
        $this->assertStringContainsString('done-b', $lines[1]);
        $this->assertStringContainsString('done-a', $lines[2]);
    }

    public function testSynchronousTreeStreamsAsASingleShellRow(): void
    {
        $output = $this->runStream(<<<'PHP'
        Flight::stream(['$', 'div', null, [['$', 'span', null, ['hi']]]]);
        PHP);

        $lines = array_values(array_filter(explode("\n", trim($output))));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('hi', $lines[0]);
    }
}
