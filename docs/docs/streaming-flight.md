---
sidebar_position: 12
---

# Streaming Flight

[Flight navigation](./flight-navigation) resolves the whole tree before sending it. **Streaming Flight** sends it progressively, the same way [Suspense streaming](./suspense-streaming) works for HTML — but over the data protocol, so client-driven navigation shows fallbacks immediately and fills them in as data resolves.

> **React devs:** this is the RSC stream's out-of-order rows, over plain NDJSON instead of React's binary Flight wire.
>
> **PHP devs:** it's the same Fiber scheduler as HTML Suspense streaming, but each chunk is a piece of the tree as data, not rendered HTML.

## The protocol: NDJSON

`Flight::stream()` emits newline-delimited JSON:

1. The **shell row** — the serialized tree, with each pending `Suspense` boundary replaced by a placeholder `["$","div",{"id":"F:<n>"},[<fallback>]]`.
2. A **boundary row** for each boundary as it resolves, out of order: `{"b": <n>, "tree": <resolved tree>}`.

```php
use Attitude\PHPX\Server\Flight;

if (Flight::wantsStream()) {           // ?__flight_stream=1 | X-Flight-Stream: 1 | Accept: application/x-ndjson
    Flight::stream($viewContent, $components);
}
```

A two-boundary page where the second declared boundary is faster streams like this:

```
["$","main",null,[["$","div",{"id":"F:0"},[["$","em",null,["load-a"]]]],["$","div",{"id":"F:1"},[["$","em",null,["load-b"]]]]]]
{"b":1,"tree":[["$","p",{"id":"done-b"},["B"]]]}
{"b":0,"tree":[["$","p",{"id":"done-a"},["A"]]]}
```

Boundary `1` arrives before `0` — out of order, as soon as its data is ready. Nested boundaries work too: resolving one can reveal more, which stream in turn.

## The client reader

`streamNavigate(url)` (in `examples/todo/src/client/flight.ts`) reads the stream row by row:

```ts
const reader = res.body.getReader()
// first row -> build the view with fallbacks in place
root.replaceChildren(toNode(shell)); mountIslands(root)
// each later row -> patch its boundary as it arrives
slot = document.getElementById('F:' + row.b)
slot.replaceChildren(toNode(row.tree)); mountIslands(slot)
```

It reuses the same `toNode` DOM builder and `mountIslands` from Flight navigation, so islands inside a streamed boundary boot as soon as that boundary lands.

## Relationship to the other streaming

| | Transport | Chunks are |
| --- | --- | --- |
| [Suspense streaming](./suspense-streaming) | HTML + `<template>` swap | rendered HTML |
| **Streaming Flight** | NDJSON rows | serialized tuple trees |

Both are driven by the same Fiber scheduler; they differ only in what they put on the wire and who assembles the DOM (the browser vs. the client runtime).
