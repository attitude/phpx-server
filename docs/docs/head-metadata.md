---
sidebar_position: 11
---

# Head & metadata

If you're a **React dev**: React 19 hoists `<title>`, `<meta>`, and `<link>`
tags rendered *anywhere* in the tree into the document `<head>` automatically
— a component deep in the page can call `<title>My page</title>` and it
lands where the browser expects it, no portal needed.

If you're a **PHP dev**: `phpx-server` has no tree to walk after the fact —
by the time you'd want to inspect it, the shell has already been rendered to
a string. So hoisting here means: components/routes push head tuples into a
static store as they render, and a placeholder marker embedded in the shell's
`<head>` gets swapped for the collected HTML right before that shell is
echoed.

## `Head`

```php
use Attitude\PHPX\Server\Head;

Head::title('PHPX Todo · Stats');
Head::meta(['name' => 'description', 'content' => 'Your stats at a glance.']);
Head::link(['rel' => 'canonical', 'href' => 'https://example.test/stats']);
Head::push(['$', 'meta', ['property' => 'og:title', 'content' => 'PHPX Todo']]);
```

- `Head::title(string $title)` — sets the document title. Call it as many
  times as you like from as many components as you like; only the **last**
  call before render wins, so the page ends up with a single `<title>`.
- `Head::meta(array $attrs)` / `Head::link(array $attrs)` — convenience
  wrappers that push a `['$', 'meta', $attrs]` / `['$', 'link', $attrs]` tuple.
- `Head::push(array $node)` — push any raw head tuple.
- `Head::marker()` — a stable placeholder string (`<!--phpx:head-->`) to embed
  in the shell's `<head>`.
- `Head::render()` — renders every collected node to HTML and **clears the
  store**, so the next request starts empty.

## Wiring it into the shell

In `examples/todo/public/index.php`, the `<head>` tuple embeds the marker via
a `dangerouslySetInnerHTML` fragment, and the route sets its title/description
before the page tree is built:

```php
Head::title('PHPX Todo · ' . ucfirst($current));
Head::meta(['name' => 'description', 'content' => '…']);

$page = ['$', 'html', ['lang' => 'en'], [
    ['$', 'head', null, [
        ['$', 'meta', ['charSet' => 'UTF-8']],
        ['$', 'fragment', ['dangerouslySetInnerHTML' => ['__html' => Head::marker()]]],
    ]],
    // ...body...
]];
```

`StreamingRenderer::stream()` renders the whole shell to a string first —
running every server component in it — and only then echoes it:

```php
$shell = $this->renderWith($root);
echo str_replace(Head::marker(), Head::render(), $shell);
```

By the time `renderWith()` returns, every component in the shell has had its
chance to call `Head::title()`/`Head::push()`, so swapping the marker for
`Head::render()`'s output before echoing puts the right tags in the right
place — no second pass over the tree required.

## The honest limitation

Only components in the **initial, non-suspended shell** can contribute head
tags. A component under a `Suspense` boundary that resolves later streams
its markup after the `<head>` has already been sent to the browser — there's
no going back to add a `<meta>` tag to a document the client already has.
If a route's title or description depends on data that's slow enough to
suspend, resolve that data (or at least its title-relevant part) before the
shell renders, not inside the boundary.
