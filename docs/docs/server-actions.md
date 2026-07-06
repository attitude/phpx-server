---
sidebar_position: 6
---

# Server actions

## One registration, two paths

If you're a **React dev**: this is the same idea as a Next.js/RSC server
action — a function that only ever runs on the server, callable from a form
submission or from client code, with the framework handling the wire
protocol.

If you're a **PHP dev**: this is a named-callable registry plus a small
convention for reading `$_POST` or a JSON body. Nothing here needs a
framework; it's the kind of dispatch table you may have already written by
hand for form handlers.

The key idea is that **one registration serves two invocation paths**:

1. A plain `<form method="POST">` — works with zero JavaScript. The handler
   runs, then the response does a redirect (Post/Redirect/Get) so reloading
   or going back doesn't resubmit the form.
2. A `fetch()` call with a JSON body — the same handler runs, but the
   response is fresh JSON state instead of a redirect, so the client can
   re-render without a full page load.

## Registering an action

```php
use function Attitude\PHPX\Server\action;

action('todo/add', fn ($args) => $store->add($args['text'] ?? ''));
```

`action($id, $fn)` is a thin wrapper over `Actions::register()` — `$id` is
just a string key, `$fn` receives the decoded arguments as an array. Nothing
about `$fn` crosses [the door](./concepts-the-door-and-tuples.md); only the
`$args` array (JSON-serializable by construction) does.

## Dispatching a request

```php
use Attitude\PHPX\Server\Actions;

if ($req = Actions::fromRequest()) {
    Actions::dispatch($req['id'], $req['args']);

    if ($req['json']) {
        echo json_encode(['todos' => $store->all()]);
        exit;
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit; // no-JS: Post/Redirect/Get
}
```

`Actions::fromRequest()` looks at the current request and returns
`['id' => ..., 'args' => ..., 'json' => bool]`, or `null` if this isn't an
action request:

- `Content-Type: application/json` with a JSON body containing `id` and
  `args` → the `fetch()` path, `json: true`.
- A form POST with a hidden `__action` field → the no-JS path, `json: false`,
  `args` is `$_POST` minus `__action`.

`Actions::dispatch($id, $args)` just looks up `$id` in the registry and
calls it with `$args`.

## Making a `<form>` invoke an action

`actionFields($id, $args)` renders the hidden inputs a form needs to invoke
an action without any JavaScript — one for `__action`, one per argument.
From `examples/todo/src/components.phpx`:

```php
use function Attitude\PHPX\Server\actionFields;

<form method="POST" className="TodoItemFormView">
    {actionFields('todo/toggle', ['id' => $id])}
    <button type="submit" className="TodoToggleButton">…</button>
</form>
```

Submitting that form POSTs `__action=todo/toggle&id=<id>`, which
`Actions::fromRequest()` recognizes as the form path.

## The React side of the same action

The client island calls the *same* registered action over `fetch`, and gets
back fresh state instead of a redirect (`examples/todo/src/client/TodoApp.tsx`):

```tsx
async function callAction(id: ActionId, args: Record<string, unknown>): Promise<Todo[]> {
  const res = await fetch(window.location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, args }),
  })
  const data = await res.json()
  return data.todos as Todo[]
}
```

Same `todo/toggle` action, same server-side handler — only the request
shape and the response differ, and that difference is exactly the `json`
flag `Actions::fromRequest()` reports.
