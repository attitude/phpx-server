---
sidebar_position: 8
---

# Flight navigation

The last piece of the RSC model: **client-driven navigation**. Instead of a full-page reload, the client asks the server for a route, gets back the serialized component tree as JSON, rebuilds that view in place, and re-mounts any islands.

> **React devs:** this is RSC's "refetch and re-render" — the Flight payload, minus React's binary protocol.
>
> **PHP devs:** this is like Turbo/htmx, except the wire format is a **data tree** (your PHPX tuples), not pre-rendered HTML. The client reconstructs the DOM from data.

## Why PHPX tuples are already a Flight payload

React's Flight format is a serialized tree where server components have been executed away and only data remains — host elements plus references to client components. A PHPX tuple `['$', tag, props, children]` is exactly that shape. So serialization is mostly: **run the server components, keep the rest.**

`Flight::serialize()` walks the tree and:

- **executes server components** (closures / named callables) and recurses into their result — they disappear, exactly like RSC;
- **resolves Suspense inline** — off a fiber, `await()` runs synchronously, so the payload is complete (streaming Flight is future work);
- **keeps host elements** as `['$', tag, props, children]`, with `className`/`style` normalised to final `class`/`style` strings so the client needs no render logic;
- **keeps client boundaries** — the `[data-client]` divs from [`Client()`](./client-islands) — untouched, so the client can mount them after insertion. Their props already crossed the door as JSON.

## The endpoint

There's no separate route to maintain. The same entry that renders a page also answers Flight requests — it just serializes instead of streaming HTML:

```php
use Attitude\PHPX\Server\Flight;

$viewContent = ($views[$current])();      // a server-rendered tuple tree

if (Flight::wants()) {                      // ?__flight=1 | X-Flight: 1 | Accept
    Flight::respond($viewContent, $components); // JSON + exit
}

// …otherwise render the full HTML page (shell + $viewContent)
```

`Flight::wants()` returns true when the request carries `?__flight=1`, an `X-Flight: 1` header, or `Accept: application/x-component`.

A request for `/stats` with `X-Flight: 1` returns something like:

```json
["$","div",{"class":"ProseView"},[
  ["$","h2",{"class":"ProseHeadingText"},["Stats"]],
  ["$","ul",{"class":"StatsListView"},[
    ["$","li",{"class":"StatsItemView"},["Total",["$","span",{"class":"StatsNumberText"},[4]]]]
  ]]
]]
```

Note the count `4` was computed on the server — the client just draws the data.

## The client runtime

A small runtime turns links into Flight navigations (`examples/todo/src/client/flight.ts`):

```ts
async function navigate(url: string, push: boolean) {
  const res = await fetch(url, { headers: { 'X-Flight': '1' } })
  const tree = await res.json()
  const root = document.getElementById('view-root')
  root.replaceChildren(toNode(tree))   // rebuild DOM from the tuple tree
  mountIslands(root)                    // boot React islands in the new view
  if (push) history.pushState({ flight: true }, '', url)
}
```

`toNode()` is a ~30-line tuple → DOM builder (element, attributes, text, fragments, `dangerouslySetInnerHTML`). Because the server already normalised props, the client applies attributes verbatim. Clicks on `<a data-flight-link>` are intercepted; `popstate` re-fetches. Modifier-clicks and cross-origin links fall through to normal navigation, and if the fetch fails the runtime does a real navigation instead.

## Progressive enhancement

Every `data-flight-link` is a real `<a href>`. With JavaScript off, the links are ordinary navigation and each route renders as a full server page. With JavaScript on, the same clicks become instant Flight swaps. Same URLs, same server code — the enhancement is entirely additive.

## What's intentionally not here (yet)

- **Streaming Flight.** The payload is resolved fully before sending. Streaming a Flight response (like the HTML [Suspense streaming](./suspense-streaming)) is future work.
- **Root unmounting.** Navigating away replaces the DOM; React roots from the old view are dropped rather than formally unmounted. Fine for a demo, worth tightening for production.
