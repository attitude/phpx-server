<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

use Fiber;

/**
 * Streaming Flight: the serialized-payload counterpart to {@see StreamingRenderer}.
 *
 * Where {@see Flight::serialize()} resolves the whole tree before returning JSON,
 * this streams it as newline-delimited JSON (NDJSON):
 *
 *   1. the shell tree, with each pending Suspense boundary replaced by a
 *      placeholder `['$','div',{'id':'F:<n>'},[<fallback>]]`;
 *   2. then, out of order as each boundary resolves, a row
 *      `{"b": <n>, "tree": <resolved tree>}`.
 *
 * The client reads the first line to build the view (fallbacks in place), then
 * patches each `#F:<n>` as its row arrives — the Flight equivalent of React's
 * out-of-order streaming, over a plain-text protocol. Suspend/resume is a Fiber,
 * exactly as in {@see StreamingRenderer}; only the output differs (data, not HTML).
 */
final class FlightStream
{
    private int $seq = 0;

    /** @var array<int, array{fiber: Fiber, delay: float, work: callable}> */
    private array $pending = [];

    /** @var array<string, callable> */
    private array $components = [];

    /** Stream $root as NDJSON Flight rows. */
    public function stream(mixed $root, array $components = []): void
    {
        $this->components = $components;

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        // Shell row: server components resolved, pending boundaries as placeholders.
        echo $this->line(Flight::serialize($root, $this->handlers()));
        $this->flush();

        // Boundary rows, soonest-ready first; nested boundaries appended here too.
        while ($this->pending !== []) {
            uasort($this->pending, static fn (array $a, array $b): int => $a['delay'] <=> $b['delay']);
            $id = array_key_first($this->pending);
            $job = $this->pending[$id];
            unset($this->pending[$id]);

            $this->sleep($job['delay']);
            foreach ($this->pending as &$other) {
                $other['delay'] = max(0.0, $other['delay'] - $job['delay']);
            }
            unset($other);

            $tree = $this->drive($job['fiber'], ($job['work'])());
            echo $this->line(['b' => $id, 'tree' => $tree]);
            $this->flush();
        }
    }

    /**
     * Suspense handler used during serialization: run children in a fiber and
     * serialize them to a tuple tree. Inline if synchronous; otherwise register
     * the pending fiber and emit a placeholder keyed by boundary id.
     *
     * @internal
     */
    public function boundary(array $props): mixed
    {
        $id = $this->seq++;
        $children = $props['children'] ?? [];

        $fiber = new Fiber(fn (): mixed => Flight::serialize($children, $this->handlers()));
        /** @var array{delay: float, work: callable} $signal */
        $signal = $fiber->start();

        if ($fiber->isTerminated()) {
            return $fiber->getReturn();
        }

        $this->pending[$id] = ['fiber' => $fiber, 'delay' => $signal['delay'], 'work' => $signal['work']];

        return ['$', 'div', ['id' => "F:{$id}"], [Flight::serialize($props['fallback'] ?? '')]];
    }

    /** @return array<string, callable> */
    private function handlers(): array
    {
        return ['Suspense' => [$this, 'boundary']] + $this->components;
    }

    private function drive(Fiber $fiber, mixed $value): mixed
    {
        /** @var array{delay: float, work: callable} $signal */
        $signal = $fiber->resume($value);
        while (!$fiber->isTerminated()) {
            $this->sleep($signal['delay']);
            /** @var array{delay: float, work: callable} $signal */
            $signal = $fiber->resume(($signal['work'])());
        }

        return $fiber->getReturn();
    }

    private function line(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }

    private function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }
}
