---
sidebar_position: 10
---

# Caching ("freeze the dough")

React's `cache()` dedupes data fetching within a single render: if ten
different server components each ask for the same user by id, the wrapped
function runs once and every caller gets back the same result, instead of
issuing ten identical queries.

PHP has no persistent render tree to hang that lifetime off of, so the
closest equivalent is per-request memoization: a store that lives for the
duration of the request (the process, in the simple `php -S` case) and is
keyed by the function plus its arguments.

```php
use function Attitude\PHPX\Server\cache;

$getUser = cache(fn (string $id) => $db->user($id)); // loads once per id per request

$getUser('42'); // runs the query
$getUser('42'); // returns the stored result, no query
$getUser('43'); // different args, runs again
```

## Why this fits the "dough" framing

The [door and tuples](./concepts-the-door-and-tuples.md) doc frames a PHPX
tuple as inspectable, serializable dough — data you can `var_dump()`,
`json_encode()`, or diff before anything renders. `cache()` leans on the same
property: the key is built by serializing the function's identity and its
arguments, so a cached call is really just "the same dough, already mixed" —
recognized by its ingredients (arguments), not recomputed.

That's also the honest limit: only **serializable** arguments are memoized.
A call with a closure, a database connection, or another live object as an
argument can't be turned into a stable key, so it's never cached — it always
falls through to the wrapped function. This is the same "door" rule that
governs [`Client()`](./client-islands.md) props and
[server action](./server-actions.md) arguments; `cache()` just applies it to
memoization keys instead of a wire format.

## Clearing the store

In a long-lived process — a queue worker, a persistent PHP server — the
cache store outlives a single request unless you clear it yourself. Call
`Cache::clear()` at the start or end of each request boundary:

```php
use Attitude\PHPX\Server\Cache;

Cache::clear(); // reset before handling the next request
```
