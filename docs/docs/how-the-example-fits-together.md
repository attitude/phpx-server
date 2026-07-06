---
sidebar_position: 9
---

# How the example fits together

`examples/todo` is a small CRUD todo app that exercises every capability
covered in these docs at once. Here's how the pieces connect, in the order a
request actually flows through them.

## 1. The request arrives (`public/index.php`)

Every request — page load or action — hits `examples/todo/public/index.php`.
It loads the compiled components, creates a `Store` (a JSON-file-backed
todo list — see `examples/todo/src/Store.php`), and registers four
[server actions](./server-actions.md):

```php
action('todo/add', fn (array $a) => $store->add((string) ($a['text'] ?? '')));
action('todo/toggle', fn (array $a) => $store->toggle((string) ($a['id'] ?? '')));
action('todo/delete', fn (array $a) => $store->remove((string) ($a['id'] ?? '')));
action('todo/clearCompleted', fn (array $a) => $store->clearCompleted());
```

## 2. Action requests short-circuit

Before rendering anything, the script checks whether this request *is* a
server action:

```php
if ($req = Actions::fromRequest()) {
    Actions::dispatch($req['id'], $req['args']);

    if ($req['json']) {
        header('Content-Type: application/json');
        echo json_encode(['todos' => $store->all()]);
        exit;
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
```

If it is, the action runs and the script exits — either with fresh JSON (the
`fetch` path from the React island) or a redirect (the no-JS form path). No
page rendering happens on this branch at all.

## 3. Otherwise, render the page

The page is one tuple tree, assembled from every concept in these docs:

- **[Server components](./server-components.md):** `$TodoList` and
  `$TodoItem` (from `components.phpx`) render the todos to HTML.
- **[Suspense streaming](./suspense-streaming.md):** `$LazyList` wraps the
  list in an `await()` that simulates a slow data source; `Suspense(...)`
  streams a "Loading todos…" fallback first, then the real list.
- **[Client island](./client-islands.md):** `Client('TodoApp', ['todos' => $todos], $ssrMarkup)`
  wraps the add form and the Suspense boundary. `$ssrMarkup` is a
  fully-working, no-JS version of the app; the `TodoApp` React component
  mounts over it once its bundle loads.
- **[Server actions](./server-actions.md):** the add form and each todo's
  toggle/delete buttons are plain `<form>`s built with `actionFields()`,
  wired to the same four actions registered in step 1.

```php
$page = ['$', 'html', ['lang' => 'en'], [
    // ...head...
    ['$', 'body', null, [
        ['$', 'main', ['className' => 'AppView'], [
            // ...heading...
            Client('TodoApp', ['todos' => $todos], ['$', 'div', ['className' => 'TodoAppView'], [
                ['$', $AddForm],
                Suspense(
                    ['$', 'p', ['className' => 'TodoLoadingText'], ['Loading todos…']],
                    ['$', $LazyList]
                ),
            ]]),
        ]],
    ]],
]];

(new StreamingRenderer())->stream($page);
```

## JS on vs. JS off

- **JavaScript off:** the page streams in — shell first, then the todo list a
  moment later. Adding, toggling, and deleting all work through plain form
  submissions with Post/Redirect/Get. Every interaction reloads the page,
  but nothing is broken and nothing requires JavaScript.
- **JavaScript on:** `main.tsx` finds the `[data-client="TodoApp"]` mount
  point, reads the initial todos from `data-props`, and mounts `TodoApp`.
  From then on, every mutation goes through `fetch()` against the same
  server actions, with optimistic local updates (see `handleAdd`,
  `handleToggle`, etc. in `TodoApp.tsx`) — no page reloads, and the UI
  updates before the server even responds.

Same markup, same server actions, same data — two different levels of
interactivity layered on top of each other. That's the whole idea of
`phpx-server` in one running app.
