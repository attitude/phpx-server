<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

use Fiber;

/**
 * Suspend the current render until $work produces a value.
 *
 * The PHP equivalent of a React component reading a promise: the component stops
 * mid-render, {@see StreamingRenderer} emits the Suspense fallback in its place,
 * and later resumes this exact point with the value $work() returns.
 *
 * $seconds is used by the demo scheduler to simulate and order latency; real
 * code would await I/O here (a DB query, an HTTP call, a queued job).
 *
 * Outside a Suspense boundary there is no fiber to suspend, so $work runs
 * immediately — the same component works streamed or not.
 */
function await(float $seconds, callable $work): mixed
{
    if (Fiber::getCurrent() === null) {
        return $work();
    }

    return Fiber::suspend(['delay' => $seconds, 'work' => $work]);
}

/**
 * Build a Suspense boundary node.
 *
 * Shape: `['$', 'Suspense', ['fallback' => <node>], [<children>]]`. The
 * {@see StreamingRenderer} recognises the 'Suspense' type; the base PHPX
 * Renderer does not, so boundaries only take effect when streamed.
 */
function Suspense(mixed $fallback, mixed $children): array
{
    $children = (is_array($children) && ($children[0] ?? null) === '$') ? [$children] : (array) $children;

    return ['$', 'Suspense', ['fallback' => $fallback], $children];
}

/**
 * A client-component boundary — the RSC "door" made concrete.
 *
 * On the server a client component is not a closure (closures cannot cross the
 * boundary). It is a serializable reference: a $name the client bundle knows,
 * plus JSON-serializable $props. Optional $ssr markup renders inside as a no-JS
 * fallback; the client runtime finds `[data-client]` nodes, reads the props and
 * mounts the matching component there.
 *
 *   Client('TodoApp', ['todos' => $todos], $ssrFallbackNode)
 */
function Client(string $name, array $props = [], mixed $ssr = null): array
{
    return ['$', 'div', [
        'data-client' => $name,
        'data-props' => json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ], $ssr === null ? [] : [$ssr]];
}

/**
 * Build an error boundary node.
 *
 * Shape: `['$', 'ErrorBoundary', ['fallback' => <node|callable>], [<children>]]`.
 * If rendering the children throws, {@see StreamingRenderer} renders the
 * fallback instead. `$fallback` may be a closure receiving the Throwable.
 * Only meaningful when streamed.
 */
function ErrorBoundary(mixed $fallback, mixed $children): array
{
    $children = (is_array($children) && ($children[0] ?? null) === '$') ? [$children] : (array) $children;

    return ['$', 'ErrorBoundary', ['fallback' => $fallback], $children];
}

/** Convenience registration helper: `action('todo/add', fn($args) => ...)`. */
function action(string $id, callable $fn): void
{
    Actions::register($id, $fn);
}

/**
 * Hidden fields that make a `<form>` invoke a server action without JS.
 *
 * @return list<array> a fragment of hidden input nodes
 */
function actionFields(string $id, array $args = []): array
{
    $fields = [['$', 'input', ['type' => 'hidden', 'name' => '__action', 'value' => $id]]];
    foreach ($args as $name => $value) {
        $fields[] = ['$', 'input', ['type' => 'hidden', 'name' => (string) $name, 'value' => (string) $value]];
    }

    return $fields;
}

/**
 * Wrap $fn so repeated calls with the same arguments are memoized — the PHP
 * equivalent of React's `cache()`. See {@see Cache} for the memoization rules.
 *
 *   $getUser = cache(fn (string $id) => $db->user($id));
 *   $getUser('42'); // runs the query
 *   $getUser('42'); // returns the stored result
 */
function cache(callable $fn): callable
{
    return fn (mixed ...$args) => Cache::memoize($fn, $args);
}

/**
 * Redirect the client to $url — usable inside a server action, the PHP
 * equivalent of calling `redirect()` from a Next.js/RSC server action.
 *
 * A Flight/JSON request (client-driven navigation, or the JSON fetch a server
 * action call makes) can't follow a raw HTTP redirect the way a full page
 * load would, so it gets a small JSON envelope the client understands
 * instead; any other request gets a real HTTP redirect.
 */
function redirect(string $url, int $status = 303): never
{
    $isJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

    if (Flight::wants() || $isJson) {
        header('Content-Type: application/json');
        echo json_encode(['__redirect' => $url]);
        exit;
    }

    header('Location: ' . $url, true, $status);
    exit;
}
