---
sidebar_position: 13
---

# Navigation UX: prefetch, pending, redirect

Flight navigation ([Flight navigation](./flight-navigation), [Streaming Flight](./streaming-flight)) already swaps views without a full reload. Three small additions bring it closer to what an RSC router does out of the box: it fetches routes before you click them, it shows that something is happening while it fetches, and a server action can send you somewhere else.

> **React devs:** this is `<Link prefetch>`, a route-transition pending state, and `redirect()` from a server action — the same three things Next.js's App Router gives you, built directly on the Flight primitives from the previous pages.
>
> **PHP devs:** none of this needs a framework. It's a `Map` cache, a data attribute toggled around a `fetch`, and a JSON envelope your existing `header('Location: ...')` call already had a use for.

## Prefetch on hover

`examples/todo/src/client/flight.ts` keeps a small cache of in-flight Flight fetches:

```ts
const prefetchCache = new Map<string, Promise<unknown>>()

function prefetch(url: string): void {
  if (prefetchCache.has(url)) return
  prefetchCache.set(url, fetch(url, { headers: { 'X-Flight': '1' } }).then((res) => res.json()))
}
```

`initFlight()` calls `prefetch(link.href)` on `mouseover` of any `<a data-flight-link>` (same-origin only). By the time the click handler runs `navigate()`, the payload is often already on the wire — `navigate()` just awaits the cached promise instead of starting a new fetch, then clears the cache entry so the next hover re-fetches fresh data.

## Pending indicator

Both `navigate()` and `streamNavigate()` set `data-flight-pending="1"` on `<html>` before the fetch and remove it once the user has something to look at — `streamNavigate()` clears it as soon as the shell (first row) is rendered, not after every boundary resolves, since that's the point where the transition reads as "done" to the user. A `finally` guarantees it's removed even if the fetch fails and the runtime falls back to a real navigation.

`todo.css` turns that attribute into a slim top progress bar with no extra markup:

```css
:root[data-flight-pending]::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  width: 40%;
  background: var(--accent);
  animation: flight-pending-slide 1s ease-in-out infinite;
}
```

## `redirect()` in a server action

A server action sometimes needs to send the user elsewhere — clear the list and jump to a summary page, for example. `redirect()` (in `src/functions.php`) is the PHP counterpart of calling `redirect()` from a Next.js server action:

```php
use function Attitude\PHPX\Server\redirect;

action('todo/clearAndReview', function () use ($store) {
    $store->clearCompleted();
    redirect('/stats');
});
```

It checks how the action was called and responds accordingly:

- **Flight/JSON request** (`Flight::wants()`, or the action's own `Content-Type: application/json` fetch) — it can't follow an HTTP redirect the way a browser navigation would, so `redirect()` emits `{"__redirect": "/stats"}` instead.
- **Plain form POST** — a normal `Location:` header and HTTP status (303 by default).

The todo island's `callAction()` in `TodoApp.tsx` checks the response for `__redirect` and, if present, does a hard `window.location.href` navigation instead of treating the payload as `{ todos }`. No current action in the demo redirects — this just makes the client honor one if it does.

## Progressive by default

Prefetch and the pending bar are enhancements layered on JavaScript that was already optional: without JS, links are ordinary `<a href>` navigation and there's nothing to prefetch or show pending state for — the browser's own loading indicator does that job. `redirect()` works either way, since both branches were already reachable from the existing action-dispatch code path.
