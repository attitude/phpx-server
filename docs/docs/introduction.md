---
sidebar_position: 1
---

# Introduction

## The one-minute idea

React Server Components split one program across two computers. Between the
server half and the browser half is a one-way **door**: only serializable
data crosses it. Server components run, produce data, and disappear. Client
components are shipped as references and run in the browser.

PHP already lives on the server side of that door — a PHP script runs,
produces HTML, and disappears. Nothing new there. What's new is
[PHPX](https://github.com/attitude/phpx): it compiles JSX-like markup into
**serializable tuples**:

```
['$', 'tag', props, children]
```

That's the same shape as React's element / Flight format. Because the tuple
*is* the data structure React already uses to describe an element tree, most
of the RSC model turns out to be portable to PHP — for free, because PHP was
already doing the server half.

`phpx-server` is that port. It exists to answer one question: *how much of
the RSC model is actually portable to PHP?* The answer is: most of the
server half.

## What's ported

| RSC capability | Ported here | How |
| --- | --- | --- |
| **Server components** | ✅ | PHPX components run on the server and render to HTML. This *is* PHP. |
| **The boundary ("the door")** | ✅ | Only JSON-serializable props cross to the client. Closures stay on the server. |
| **Suspense streaming** | ✅ | A `Suspense` boundary + PHP **Fibers**: the shell streams first, boundaries resolve out of order. |
| **Nested / parallel Suspense** | ✅ | Boundaries nest arbitrarily; independent ones resolve in parallel. |
| **Error boundaries** | ✅ | `ErrorBoundary` catches a subtree's error and streams a fallback. |
| **Client components** | ✅ | `Client('Name', $props)` emits a serializable reference; a React island mounts into it. |
| **Server actions** | ✅ | A named-callable registry, invoked by a plain `<form>` (no JS) **or** by `fetch` (JSON); `redirect()` supported. |
| **Flight navigation** | ✅ | The server returns the serialized tuple tree as JSON; the client rebuilds the view, no reload. |
| **Streaming Flight** | ✅ | The Flight payload as NDJSON — shell first, boundaries out of order. |
| **`cache()`** | ✅ | Per-request memoization / dedup of data loading. |
| **Head & metadata** | ✅ | Components/routes contribute `<title>`/`<meta>`/`<link>`, hoisted into `<head>`. |
| **Router UX** | ✅ | Prefetch-on-hover and a pending indicator during navigation. |

## The honest limit

**Browser interactivity needs JavaScript.** That's physics, not a PHP
limitation, and `phpx-server` doesn't pretend otherwise.

- Server components — the parts that just render data to markup — port for
  free. They never needed JavaScript in React either; PHPX just gives PHP the
  same declarative, composable syntax React uses.
- Interactive leaves — a button that needs `onClick`, an input with local
  state — become **React islands**: small client components hydrated into
  specific spots in server-rendered markup.

This is progressive enhancement, not isomorphism. There's no single
component tree that runs identically on both PHP and JavaScript. There's a
PHP tree that renders the page, and a React tree that mounts on top of
specific nodes once the browser has JS. The [todo example](./how-the-example-fits-together.md)
works completely without JavaScript; React only makes it feel instant.

## Who this is for

The rest of these docs assume you're coming from one of two directions:

- **React developers** curious how much of the mental model — Suspense,
  server/client boundaries, server actions — survives outside Node.
- **PHP developers** curious what RSC actually is, once you strip away the
  bundler/router/framework machinery and look at the underlying idea.

Each concept page frames itself from both sides.
