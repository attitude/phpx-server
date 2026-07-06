# phpx-server

**React Server Components ideas — server components, Suspense streaming, and server actions — ported to PHP with [PHPX](https://github.com/attitude/phpx).**

> [!WARNING]
> Experimental. The API will change. It exists to answer one question: *how much of the RSC model is actually portable to PHP?* The surprising answer is **most of the server half — nearly for free — because PHP already works this way.**

---

## The idea in one minute

React Server Components split one program across two computers. Between them is a one-way **door**: only serializable data crosses it. Server components run, produce data, and disappear; client components are shipped as references and run in the browser.

PHP already lives on the server side of that door. PHPX compiles JSX-like markup into **serializable tuples** — `['$', 'tag', props, children]` — the same shape as React's element/Flight format. That one fact makes the RSC model portable:

| RSC capability | Ported here | How |
| --- | --- | --- |
| **Server components** | ✅ | PHPX components run on the server and render to HTML. This *is* PHP. |
| **The boundary ("the door")** | ✅ | Only JSON-serializable props cross to the client. Closures stay on the server. Tuples are the wire format. |
| **Suspense streaming** | ✅ | A `Suspense` boundary + PHP **Fibers**: the shell streams first, boundaries resolve out of order. |
| **Nested / parallel Suspense** | ✅ | Boundaries nest arbitrarily; independent ones resolve in parallel, streaming as each is ready. |
| **Error boundaries** | ✅ | `ErrorBoundary` catches a subtree's error (sync or while streaming) and streams a fallback. |
| **Client components** | ✅ | `Client('Name', $props)` emits a serializable reference; a React island mounts into it. |
| **Server actions** | ✅ | A named-callable registry, invoked by a plain `<form>` (no JS) **or** by `fetch` (JSON); `redirect()` supported. |
| **Flight navigation** | ✅ | Client-driven route changes: the server returns the serialized tuple tree as JSON; the client rebuilds the view and re-mounts islands, no reload. |
| **Streaming Flight** | ✅ | The Flight payload streamed as NDJSON — shell first, boundaries out of order — same Fiber scheduler as HTML streaming. |
| **`cache()`** | ✅ | Per-request memoization / request-level dedup of data loading ("freeze the dough"). |
| **Head & metadata** | ✅ | Components/routes contribute `<title>`/`<meta>`/`<link>`, hoisted into `<head>` (React 19 style). |
| **Router UX** | ✅ | Prefetch-on-hover and a pending indicator during navigation — progressive, no effect without JS. |

The one hard limit is physics: **browser interactivity needs JavaScript.** Server components port for free; interactive leaves are React islands — progressive enhancement, not isomorphism.

---

## See it work

The `examples/todo` app is a complete CRUD todo that uses every capability above. **With JavaScript off it is a full, streaming, form-driven app. With JavaScript on, React takes over for an instant, optimistic UI.**

```bash
composer install          # PHP: the phpx-server library + PHPX compiler
composer example:build    # JS: install + build the React island, compile .phpx
composer example:serve    # http://localhost:8080
```

Open it, then reload with JavaScript disabled — the same app still adds, toggles, and deletes, and the list still streams in after the shell.

---

## How it works

### Server components (`.phpx`)

Authored in PHPX, compiled to tuples, rendered on the server. No client JS.

```php
$TodoList = function (array $props): array {
    ['todos' => $todos] = $props;
    return (
        <ul className="TodoListView">
            {array_map(fn($t) => ['$', $TodoItem, $t], $todos)}
        </ul>
    );
};
```

### Suspense streaming (Fibers)

A component `await()`s its data. Under a `Suspense` boundary the fiber suspends, the fallback streams immediately, and the resolved subtree streams later — out of order.

```php
use function Attitude\PHPX\Server\{Suspense, await};

$LazyList = function () use ($TodoList) {
    $todos = await(0.9, fn () => (new Store())->all()); // suspends the fiber
    return ['$', $TodoList, ['todos' => $todos]];
};

$page = /* … */ Suspense(
    ['$', 'p', null, ['Loading todos…']], // shown instantly
    ['$', $LazyList]                        // streamed when ready
);

(new StreamingRenderer())->stream($page);
```

### Server actions (progressive enhancement)

One registration, two invocation paths — a plain form (works with no JS) and `fetch` (returns fresh state).

```php
use Attitude\PHPX\Server\Actions;
use function Attitude\PHPX\Server\action;

action('todo/add', fn ($args) => $store->add($args['text'] ?? ''));

if ($req = Actions::fromRequest()) {
    Actions::dispatch($req['id'], $req['args']);
    if ($req['json']) { echo json_encode(['todos' => $store->all()]); exit; }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit; // no-JS: Post/Redirect/Get
}
```

### Client islands (the door)

`Client()` emits a serializable reference; the React runtime finds `[data-client]` nodes and mounts the matching component with the JSON props.

```php
use function Attitude\PHPX\Server\Client;

Client('TodoApp', ['todos' => $todos], $ssrFallbackNode);
```

```tsx
const mount = document.querySelector('[data-client="TodoApp"]')
const props = JSON.parse(mount.getAttribute('data-props') ?? '{}')
createRoot(mount).render(<TodoApp initialTodos={props.todos} />)
```

---

## Documentation

Full docs — written for **both** backend and frontend developers, bridging the gap — live at the docs site (built with Docusaurus, deployed to GitHub Pages). See [`docs/`](./docs).

## Requirements

- PHP **8.1+** (Fibers)
- [PHPX](https://github.com/attitude/phpx)
- Node + pnpm (only to build the example's React island)

## License

MIT © Martin Adamko. Built on [PHPX](https://github.com/attitude/phpx).
