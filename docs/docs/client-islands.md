---
sidebar_position: 7
---

# Client islands

## The door, made concrete

If you're a **React dev**: this is the client boundary you're used to —
a component that ships JavaScript and hydrates in the browser, as opposed to
a server component that doesn't. `phpx-server` just makes the boundary
explicit as a function call instead of a `"use client"` directive.

If you're a **PHP dev**: `Client()` doesn't render a React component — PHP
has no idea how to do that, and never tries to. It renders a `<div>` with a
`data-client` attribute naming the component and a `data-props` attribute
holding its JSON-encoded props. A separate React bundle, loaded by a normal
`<script>` tag, looks for that `<div>` and mounts into it. Two independent
runtimes, connected by one HTML attribute and a JSON string.

## `Client()`

```php
function Client(string $name, array $props = [], mixed $ssr = null): array
{
    return ['$', 'div', [
        'data-client' => $name,
        'data-props' => json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ], $ssr === null ? [] : [$ssr]];
}
```

- `$name` is a string the client bundle recognizes — there's no shared
  import, no build-time linking between the PHP and the TypeScript sides.
  It's a convention, matched at runtime.
- `$props` **must be JSON-serializable** — this is [the door](./concepts-the-door-and-tuples.md)
  in its most literal form. `json_encode()` is the enforcement mechanism: if
  you pass something it can't encode, it fails loudly rather than silently
  shipping a closure to the browser.
- `$ssr`, if given, renders inside the `<div>` as a no-JS fallback. In the
  todo example this is the fully working, server-rendered add form and
  Suspense-streamed list — so the page is complete before React ever loads.

From `examples/todo/public/index.php`:

```php
use function Attitude\PHPX\Server\Client;

Client('TodoApp', ['todos' => $todos], ['$', 'div', ['className' => 'TodoAppView', 'data-ssr' => 'true'], [
    ['$', $AddForm],
    Suspense(
        ['$', 'p', ['className' => 'TodoLoadingText'], ['Loading todos…']],
        ['$', $LazyList]
    ),
]]),
```

## The React side

The client bundle's job is: find the mount point, read the props, render.
From `examples/todo/src/client/main.tsx`:

```tsx
import { createRoot } from 'react-dom/client'
import TodoApp from './TodoApp.tsx'
import type { Todo } from './TodoApp.tsx'

const mount = document.querySelector<HTMLElement>('[data-client="TodoApp"]')

if (mount) {
  const props = JSON.parse(mount.getAttribute('data-props') ?? '{}') as { todos: Todo[] }
  createRoot(mount).render(<TodoApp initialTodos={props.todos ?? []} />)
}
```

`createRoot(mount).render(...)` replaces the server-rendered fallback with
the interactive `TodoApp` — the same component that then calls
[server actions](./server-actions.md) over `fetch` for every mutation,
instead of relying on form submissions.

## Why props must be serializable

There is no other option: `Client()` can only pass what `json_encode()` can
encode, and `main.tsx` can only read what `JSON.parse()` can decode. That
symmetry *is* the door. It's also why a database connection, a closure, or a
PHP object with private state can never end up as a client prop here — not
because of a validation rule, but because there's no representation for them
on the other side.
