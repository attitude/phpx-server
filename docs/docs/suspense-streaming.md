---
sidebar_position: 5
---

# Suspense streaming

## The mental model

If you're a **React dev**: you already know `<Suspense fallback={...}>` —
wrap a subtree that isn't ready yet, show the fallback, and React swaps in
the real content when the data resolves, without blocking the rest of the
page.

If you're a **PHP dev**: think of it the other way around. A normal PHP
script blocks — it runs top to bottom, and nothing reaches the browser until
`echo` (or the end of the script) sends bytes. Suspense streaming breaks that
assumption on purpose: the **shell flushes immediately** with a placeholder
where the slow part goes, and the slow part's real markup **streams out
later**, out of order, as soon as it's ready. No polling, no manual
pagination of the response — the same `<Suspense>`-shaped code you'd write
in React, just running server-side in PHP.

## The mechanism: Fibers

PHP 8.1 added [Fibers](https://www.php.net/manual/en/language.fibers.php) —
a way to pause a function mid-execution and resume it later with a value.
That's the only primitive `StreamingRenderer` needs:

1. A component under a `Suspense` boundary calls `await()`.
2. `await()` suspends the current fiber (`Fiber::suspend()`), handing back a
   description of the work to do (`['delay' => ..., 'work' => ...]`).
3. `StreamingRenderer` renders the boundary's `fallback` immediately and
   keeps the fiber around as "pending."
4. Once the fiber's work resolves, the renderer resumes it
   (`Fiber::resume($value)`) at the exact point it suspended, gets back the
   real markup, and streams it into a `<template>` tag with a tiny script
   that swaps it into place.

Boundaries resolve **out of order** — whichever pending fiber's work
finishes soonest gets streamed first, regardless of where it appears in the
tree. That mirrors how React's streaming SSR doesn't wait for a slow
`<Suspense>` boundary before flushing a faster one below it.

```php
function await(float $seconds, callable $work): mixed
{
    if (Fiber::getCurrent() === null) {
        return $work();
    }

    return Fiber::suspend(['delay' => $seconds, 'work' => $work]);
}
```

Outside a `Suspense` boundary there's no fiber to suspend, so `await()` just
runs `$work()` immediately — the same component works whether it's streamed
or rendered plainly.

## Using it

From `examples/todo/public/index.php`:

```php
use Attitude\PHPX\Server\StreamingRenderer;
use function Attitude\PHPX\Server\{Suspense, await};

// A server component that suspends, then renders the list once ready.
// Streamed into place by StreamingRenderer.
$LazyList = function () use ($TodoList): array {
    $todos = await(0.9, fn () => (new Store())->all());

    return ['$', $TodoList, ['todos' => $todos]];
};

$page = /* ... */ Suspense(
    ['$', 'p', ['className' => 'TodoLoadingText'], ['Loading todos…']], // shown instantly
    ['$', $LazyList]                                                     // streamed when ready
);

header('Content-Type: text/html; charset=utf-8');
(new StreamingRenderer())->stream($page);
```

`Suspense($fallback, $children)` builds the boundary tuple; `stream()` does
the rest — it turns off output buffering, renders the shell (with
`'Suspense'` wired to a boundary-tracking renderer), flushes it, then works
through pending fibers soonest-first, flushing each resolved boundary as a
`<template>` plus a swap script.

## Not manual pagelets

This isn't BigPipe-style manual pagelet orchestration, where you decide up
front which chunks to defer and write separate code paths for each. It's the
same *declarative* model React uses: you wrap a subtree in `Suspense`, call
`await()` where you need to wait on something, and the renderer figures out
the streaming order for you. The component that calls `await()` doesn't know
or care whether it's being streamed — it reads the same either way.

## Nested and parallel boundaries

Boundaries compose. A suspended subtree can contain its own `Suspense` boundaries; when the outer one resolves, any inner boundaries it reveals are streamed in turn. Independent boundaries at the same level resolve in parallel, each arriving as soon as its own data is ready — the fiber scheduler tracks them all and streams whichever finishes first.

## Error boundaries

`ErrorBoundary(fallback, children)` is the error counterpart to `Suspense`. If rendering the children throws, the fallback is rendered instead of the subtree.

```php
use function Attitude\PHPX\Server\ErrorBoundary;

ErrorBoundary(
    ['$', 'p', ['className' => 'Error'], ['Could not load this section.']],
    ['$', $RiskyComponent]
);
```

Two cases are handled:

- **Synchronous errors** — a server component that throws while rendering is caught immediately and its boundary renders the fallback in the shell.
- **Errors while streaming** — if a *suspended* subtree throws when its data resolves, the stream doesn't die; that boundary streams its fallback and the rest of the page continues. `fallback` may be a closure receiving the `Throwable`.
