---
sidebar_position: 4
---

# Server components

If you're a **React dev**: a server component in `phpx-server` is what a
React Server Component is when you strip away streaming and client
boundaries — a function that takes props, returns markup, and never ships
any code to the browser.

If you're a **PHP dev**: this is a PHP function that returns an array
instead of echoing a string. The array is the tuple tree from
[Concepts: the door & tuples](./concepts-the-door-and-tuples.md); a renderer
walks it and turns it into HTML at the end. That's the whole trick — nothing
here needs a framework runtime in the browser.

## Authored in `.phpx`, compiled to tuples

Components are written in `.phpx` files using JSX-like syntax, which PHPX
compiles to plain PHP that builds tuples. From the todo example
(`examples/todo/src/components.phpx`):

```php
$TodoItem = function (array $props): array {
    ['id' => $id, 'text' => $text, 'done' => $done] = $props;

    return (
        <li className={['TodoItemView' => true, 'is-done' => $done]} data-id={$id}>
            <form method="POST" className="TodoItemFormView">
                {actionFields('todo/toggle', ['id' => $id])}
                <button type="submit" className="TodoToggleButton" aria-pressed={$done ? 'true' : 'false'}>
                    {$done ? '☑' : '☐'}
                </button>
            </form>
            <span className="TodoItemText">{$text}</span>
            <form method="POST" className="TodoItemFormView">
                {actionFields('todo/delete', ['id' => $id])}
                <button type="submit" className="TodoDeleteButton" aria-label="Delete todo">✕</button>
            </form>
        </li>
    );
};

$TodoList = function (array $props) use ($TodoItem): array {
    ['todos' => $todos] = $props;

    if (empty($todos)) {
        return (<p className="TodoEmptyText">Nothing to do. Add something above.</p>);
    }

    return (
        <ul className="TodoListView">
            {array_map(fn(array $t): array => ['$', $TodoItem, $t], $todos)}
        </ul>
    );
};
```

Two things worth noticing, both familiar from React:

- `$TodoList` composes `$TodoItem` by reference — `['$', $TodoItem, $t]` is a
  tuple that says "render this callable with these props," exactly like
  `<TodoItem {...t} />` says "render this component with these props."
- Conditional rendering (`empty($todos)` → the empty state) works the same
  way it does in JSX: it's just PHP control flow that decides which markup
  to return.

## Zero client JS

`TodoList` and `TodoItem` never appear in the browser bundle. They run once,
on the server, and produce HTML. There's no hydration step for them, no
client-side re-render, and no JavaScript cost — the same property that makes
React Server Components attractive for anything that doesn't need
interactivity (a list, an article, a table of data).

The interactive parts of the todo app — the add form's optimistic update,
instant toggle/delete — live in a separate [client island](./client-islands.md).
Everything else, including the list you just saw above, is pure server
component.
