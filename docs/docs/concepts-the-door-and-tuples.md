---
sidebar_position: 3
---

# Concepts: the door & tuples

## The door

React Server Components draw a hard line between server and client code.
Data can cross that line; behavior can't.

- **Crosses the door:** strings, numbers, booleans, arrays/objects made of
  those — anything JSON can represent.
- **Does not cross the door:** closures, live objects, database connections,
  file handles — anything that only makes sense in the process that created
  it.

If you're a **React dev**, you already know this rule from Server
Components: you can pass a plain object as a prop from server to client, but
you can't pass a function unless it's specifically a Server Action reference.

If you're a **PHP dev**, this is the same rule you already apply whenever
you `json_encode()` something for an API response or a `<script>` tag —
you don't try to serialize a `PDO` connection, you serialize the *rows* it
returned. The door isn't a new concept; RSC just makes it the seam between
two runtimes instead of the seam between your backend and your frontend
`fetch()` call.

`phpx-server` enforces the same rule at exactly two places:

- [`Client()`](./client-islands.md) — the props you hand it must be
  JSON-serializable, because they get `json_encode`-d into a `data-props`
  attribute for the React runtime to read.
- [server actions](./server-actions.md) — arguments arrive as `$_POST` data
  or a decoded JSON body; there is no way to pass a closure in, only values.

## Why tuples

PHPX compiles JSX-like markup to a plain array:

```
['$', 'tag', props, children]
```

This is deliberate, and it mirrors what React's Server Components actually
send over the wire (the "Flight" format is a very similar tagged tuple, not
compiled JavaScript). A couple of things fall out of choosing this shape
instead of, say, calling a render function directly:

- **Serializable.** The tuple is just data. `json_encode(['$', 'p', null, ['hi']])`
  is a well-formed description of a paragraph element — no code runs to
  produce it, and none needs to run to inspect it.
- **Inspectable.** You can `var_dump()` an element tree before it renders,
  diff it, log it, or transform it, the same way you'd inspect a React
  element created by `React.createElement()` before it's rendered to the
  DOM. An opaque function call (`renderParagraph('hi')`) gives you none of
  that — it's already committed to running by the time you have a
  reference to it.

Every `.phpx` component you author compiles down to nested tuples like this.
The renderer's whole job is walking that tree and turning tuples into HTML.
