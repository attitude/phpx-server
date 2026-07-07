---
sidebar_position: 14
---

# API reference

Everything in this library lives under the `Attitude\PHPX\Server` namespace. The functions below are autoloaded through Composer's `files` entry (`src/functions.php`), so they are available without a `use` statement once the package is installed. The classes are PSR-4 autoloaded; import them as needed.

## Functions

### await

```php
function await(float $seconds, callable $work): mixed
```

Suspends the current render until `$work()` produces a value — the PHP equivalent of a React component reading a promise. Inside a `Suspense` boundary (which runs children in a Fiber) it suspends the fiber mid-render so the fallback can stream; the render resumes at that exact point with the value `$work()` returns. Outside a Fiber there is nothing to suspend, so `$work()` runs immediately and its result is returned inline. `$seconds` orders the demo scheduler's simulated latency; real code awaits actual I/O in `$work`.

```php
$user = await(0.3, fn () => $db->user($id));
```

See [Suspense streaming](./suspense-streaming.md).

### Suspense

```php
function Suspense(mixed $fallback, mixed $children): array
```

Builds a Suspense boundary node: `['$', 'Suspense', ['fallback' => <node>], [<children>]]`. Only `StreamingRenderer` (and `FlightStream`) recognize the `'Suspense'` type; the base PHPX renderer does not, so boundaries only take effect when streamed.

```php
Suspense(<p>Loading…</p>, <UserCard id={$id} />)
```

See [Suspense streaming](./suspense-streaming.md).

### ErrorBoundary

```php
function ErrorBoundary(mixed $fallback, mixed $children): array
```

Builds an error boundary node: `['$', 'ErrorBoundary', ['fallback' => <node|callable>], [<children>]]`. If rendering the children throws, the fallback is rendered instead. `$fallback` may be a closure that receives the `Throwable`. Only meaningful when streamed.

```php
ErrorBoundary(fn ($e) => <p>Failed: {$e->getMessage()}</p>, <Report />)
```

See [Suspense streaming](./suspense-streaming.md).

### Client

```php
function Client(string $name, array $props = [], mixed $ssr = null): array
```

A client-component boundary — the RSC "door" made concrete. Returns a `<div data-client="$name" data-props="…">` node whose props are JSON-encoded (`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`). `$name` is a component the client bundle knows; `$props` must be JSON-serializable; optional `$ssr` markup renders inside as a no-JS fallback. The client runtime finds `[data-client]` nodes and mounts the matching component there.

```php
Client('TodoApp', ['todos' => $todos], $ssrFallbackNode)
```

See [Client islands](./client-islands.md).

### action

```php
function action(string $id, callable $fn): void
```

Convenience wrapper for `Actions::register()` — registers a named server action.

```php
action('todo/add', fn ($args) => $db->addTodo($args['text']));
```

See [Server actions](./server-actions.md).

### actionFields

```php
function actionFields(string $id, array $args = []): array
```

Returns a fragment of hidden `<input>` nodes that make a `<form>` invoke a server action without JavaScript: one field named `__action` holding `$id`, plus one field per entry in `$args` (name and value cast to string).

```php
<form method="post">
  {actionFields('todo/add', ['listId' => $id])}
  <input name="text" />
</form>
```

See [Server actions](./server-actions.md).

### cache

```php
function cache(callable $fn): callable
```

Wraps `$fn` so repeated calls with the same arguments are memoized for the request — the PHP equivalent of React's `cache()`. The returned callable forwards its arguments to `Cache::memoize()`, which keys on the callable's identity plus its serialized arguments. Calls with unserializable arguments (closures, live objects) are never cached and always run `$fn`.

```php
$getUser = cache(fn (string $id) => $db->user($id));
$getUser('42'); // runs the query
$getUser('42'); // returns the stored result
```

See [Caching](./caching.md).

### redirect

```php
function redirect(string $url, int $status = 303): never
```

Redirects the client to `$url` and exits (return type `never`). If the current request wants a Flight payload (`Flight::wants()`) or has a JSON content type, it emits a small JSON envelope `{"__redirect": "<url>"}` with an `application/json` header — because a Flight/JSON request can't follow a raw HTTP redirect. Any other request gets a real `Location` header with `$status`.

```php
action('todo/add', function ($args) {
    $db->addTodo($args['text']);
    redirect('/todos');
});
```

## Classes

### StreamingRenderer

Streaming HTML renderer with Suspense and ErrorBoundary support, built on the PHPX `Renderer`. Sends the shell immediately, then streams each boundary's real content out of order as its data resolves. See [Suspense streaming](./suspense-streaming.md).

- `stream(mixed $root, array $components = []): void` — Renders `$root`, streaming boundaries as they resolve. Turns off output buffering so each flush reaches the socket, sends the shell with the collected `Head` markup swapped in, then resolves pending boundaries soonest-ready first, emitting a `<template>` plus a swap script per boundary. `$components` is the name-to-callable map passed to the renderer.
- `renderBoundary(array $props): array` — *(internal)* The `'Suspense'` component. Runs children in a Fiber; inlines the result if it finishes synchronously, otherwise registers the pending fiber and returns the fallback placeholder.
- `renderErrorBoundary(array $props): array` — *(internal)* The `'ErrorBoundary'` component. Renders children; on a thrown error renders the fallback (calling it with the `Throwable` if it is a closure).
- `static clientRuntime(): string` — Returns the `<script>` that swaps each resolved boundary's streamed content into its placeholder.

### Actions

Server-action registry: a named callable invoked either from a plain `<form method="POST">` (works without JavaScript) or from a `fetch()` with a JSON body (returns fresh state). Only JSON-serializable arguments cross the boundary. See [Server actions](./server-actions.md).

- `static register(string $id, callable $fn): void` — Registers an action under `$id`.
- `static has(string $id): bool` — Whether an action is registered under `$id`.
- `static dispatch(string $id, array $args = []): mixed` — Invokes the registered action with `$args`; throws `RuntimeException` if `$id` is unknown.
- `static fromRequest(): ?array` — Extracts an action invocation from the current request, or `null`. Returns `['id' => string, 'args' => array, 'json' => bool]`. Reads a JSON body when the content type is `application/json` (keyed on `id`/`args`), otherwise reads a form POST via the `__action` field. Non-POST requests return `null`.

### Flight

Flight-style serialization for client-driven navigation: walks a tuple tree, executes server components away, resolves Suspense inline, and returns a `json_encode`-able tree the client rebuilds without a full reload. See [Flight navigation](./flight-navigation.md).

- `static wants(): bool` — Whether the request wants a Flight payload. True if `$_GET['__flight']` is set, the `X-Flight: 1` header is present, or the `Accept` header contains `application/x-component`.
- `static wantsStream(): bool` — Whether the request wants a streaming (NDJSON) Flight payload. True if `$_GET['__flight_stream']` is set, `X-Flight-Stream: 1` is present, or `Accept` contains `application/x-ndjson`.
- `static respond(mixed $node, array $components = []): void` — Serializes `$node`, echoes it as JSON with an `application/x-component+json` header, and exits.
- `static stream(mixed $node, array $components = []): void` — Streams `$node` as NDJSON Flight rows via `FlightStream` (shell first, boundaries out of order), setting the `application/x-ndjson` and `X-Accel-Buffering: no` headers, then exits.
- `static serialize(mixed $node, array $components = []): mixed` — Resolves server components and returns a JSON-serializable tuple tree: executes closures/named callables and recurses into their result, resolves Suspense children inline, and normalizes host-element props to final HTML attributes.

### FlightStream

Streaming counterpart to `Flight`: emits the tree as newline-delimited JSON (NDJSON) — the shell row first (pending boundaries as `F:<n>` placeholders), then one `{"b": <n>, "tree": <resolved tree>}` row per boundary as it resolves, out of order. Suspend/resume uses a Fiber, as in `StreamingRenderer`. See [Streaming Flight](./streaming-flight.md).

- `stream(mixed $root, array $components = []): void` — Streams `$root` as NDJSON Flight rows: emits the shell row, then resolves pending boundaries soonest-ready first, emitting a row per boundary.
- `boundary(array $props): mixed` — *(internal)* The Suspense handler used during serialization. Runs children in a Fiber and serializes them; inlines the result if synchronous, otherwise registers the pending fiber and returns a placeholder keyed by boundary id.

### Cache

Per-request memoization — the PHP equivalent of React's `cache()`. The store lives for the life of the process, so long-lived workers should `clear()` it at request boundaries. Keys are built from the callable's identity plus its serialized arguments. See [Caching](./caching.md).

- `static memoize(callable $fn, array $args): mixed` — Returns the stored result for `$fn` + `$args`, running `$fn` once and storing the result on a miss. Unserializable arguments produce a `null` key and always fall through to `$fn` (never cached).
- `static has(string $key): bool` — Whether a value is stored under `$key`.
- `static clear(): void` — Empties the store.

### Head

Head/metadata hoisting — the PHP port of React 19's automatic head hoisting. Components anywhere in the shell can contribute `<title>`/`<meta>`/`<link>` tags; they are collected here and swapped into the shell's `marker()` placeholder after rendering. Limitation: only the initial, non-suspended shell can contribute head tags — anything pushed from inside a streamed Suspense boundary arrives after `<head>` is sent and is dropped. See [Head metadata](./head-metadata.md).

- `static push(array $node): void` — Adds a raw head tuple, e.g. `['$', 'meta', ['name' => 'description', 'content' => '…']]`.
- `static title(string $title): void` — Sets the document title; only the last call before `render()` wins.
- `static meta(array $attrs): void` — Pushes a `<meta>` tag with the given attributes.
- `static link(array $attrs): void` — Pushes a `<link>` tag with the given attributes.
- `static marker(): string` — Returns the stable placeholder (`<!--phpx:head-->`) to embed in the shell's `<head>`.
- `static render(): string` — Renders the collected head nodes (title appended last) to HTML, then clears the store.
- `static clear(): void` — Clears the collected nodes and title.
