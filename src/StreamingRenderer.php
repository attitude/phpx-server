<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

use Fiber;
use Attitude\PHPX\Renderer\Renderer;

/**
 * Streaming renderer with Suspense boundaries, built on the PHPX Renderer.
 *
 * This is the PHP port of React's streaming SSR: the server sends the shell
 * (with a fallback in each Suspense boundary) immediately, then streams each
 * boundary's real content as its data resolves — out of order — and a tiny
 * client script swaps the fallback for the real thing.
 *
 * The suspend/resume primitive is a PHP Fiber (8.1+). A component under a
 * Suspense boundary calls {@see await()}, which suspends the fiber mid-render;
 * we emit the fallback, and later resume the fiber at that exact point with the
 * resolved value. No manual pagelets, no central orchestration — the
 * declarative Suspense model.
 */
final class StreamingRenderer
{
    private int $seq = 0;

    /** @var array<int, array{fiber: Fiber, delay: float, work: callable, errorFallback: callable}> */
    private array $pending = [];

    /** @var array<string, callable> */
    private array $components = [];

    private function newRenderer(): Renderer
    {
        $renderer = new Renderer();
        $renderer->react = true;

        return $renderer;
    }

    /**
     * Render $root to the output buffer, streaming Suspense boundaries as they
     * resolve. $components is the name => callable map passed to the renderer.
     */
    public function stream(mixed $root, array $components = []): void
    {
        $this->components = $components;

        // Turn off buffering so each flush() actually reaches the socket.
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        // Shell pass: 'Suspense'/'ErrorBoundary' are components that record
        // pending boundaries and return placeholders. Everything else renders
        // normally. Boundaries may nest — resolving one can register more.
        echo $this->renderWith($root);
        echo "\n";
        $this->flush();

        // Resolve boundaries out of order: soonest-ready first. Boundaries
        // discovered while resolving another are appended and picked up here.
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

            try {
                $html = $this->drive($job['fiber'], ($job['work'])());
            } catch (\Throwable $e) {
                // A boundary that errors while resolving streams its fallback
                // instead of killing the whole response.
                $html = $job['errorFallback']($e);
            }

            echo "<template data-b=\"{$id}\">{$html}</template>";
            echo "<script>window.__rscSwap&&__rscSwap({$id})</script>\n";
            $this->flush();
        }
    }

    /** Render a node with Suspense + ErrorBoundary handling registered. */
    private function renderWith(mixed $node): string
    {
        return $this->newRenderer()->render($node, [
            'Suspense' => [$this, 'renderBoundary'],
            'ErrorBoundary' => [$this, 'renderErrorBoundary'],
        ] + $this->components);
    }

    /**
     * Suspense component. Runs its children inside a Fiber. If they finish
     * without suspending, the result is inlined. If they suspend on {@see
     * await()}, the pending fiber is registered and the fallback is returned.
     *
     * @internal Registered as the 'Suspense' component by {@see stream()}.
     */
    public function renderBoundary(array $props): array
    {
        $id = $this->seq++;
        $children = $props['children'] ?? [];

        // Render children WITH boundary handling so nested Suspense/ErrorBoundary
        // inside a suspended subtree register and stream too.
        $fiber = new Fiber(fn (): string => $this->renderWith($children));
        /** @var array{delay: float, work: callable} $signal */
        $signal = $fiber->start();

        if ($fiber->isTerminated()) {
            // Resolved synchronously — no boundary needed, inline it.
            return $this->rawHtml((string) $fiber->getReturn());
        }

        // If this suspended subtree errors while resolving, show its fallback.
        $errorFallback = fn (\Throwable $e): string => $this->newRenderer()->render($props['fallback'] ?? '');

        $this->pending[$id] = [
            'fiber' => $fiber,
            'delay' => $signal['delay'],
            'work' => $signal['work'],
            'errorFallback' => $errorFallback,
        ];

        $fallbackHtml = $this->newRenderer()->render($props['fallback'] ?? '');

        return ['$', 'div', ['id' => "B:{$id}", 'dangerouslySetInnerHTML' => ['__html' => $fallbackHtml]]];
    }

    /**
     * ErrorBoundary component. Renders its children; if rendering throws
     * synchronously (a server component error), the fallback is rendered
     * instead. `fallback` may be a closure receiving the Throwable.
     *
     * @internal Registered as the 'ErrorBoundary' component by {@see stream()}.
     */
    public function renderErrorBoundary(array $props): array
    {
        try {
            return $this->rawHtml($this->renderWith($props['children'] ?? []));
        } catch (\Throwable $e) {
            $fallback = $props['fallback'] ?? '';
            if ($fallback instanceof \Closure) {
                $fallback = $fallback($e);
            }

            return $this->rawHtml($this->newRenderer()->render($fallback));
        }
    }

    /**
     * Resume a fiber with $value, feeding further await()s until it returns its
     * final HTML string. Supports several sequential awaits per boundary.
     */
    private function drive(Fiber $fiber, mixed $value): string
    {
        /** @var array{delay: float, work: callable} $signal */
        $signal = $fiber->resume($value);
        while (!$fiber->isTerminated()) {
            $this->sleep($signal['delay']);
            /** @var array{delay: float, work: callable} $signal */
            $signal = $fiber->resume(($signal['work'])());
        }

        return (string) $fiber->getReturn();
    }

    private function rawHtml(string $html): array
    {
        return ['$', 'fragment', ['dangerouslySetInnerHTML' => ['__html' => $html]]];
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

    /** The client runtime that swaps a resolved boundary into its placeholder. */
    public static function clientRuntime(): string
    {
        return <<<'JS'
        <script>
        window.__rscSwap = function (id) {
          var tpl = document.querySelector('template[data-b="' + id + '"]');
          var slot = document.getElementById('B:' + id);
          if (tpl && slot) { slot.replaceWith(tpl.content.cloneNode(true)); tpl.remove(); }
        };
        </script>
        JS;
    }
}
