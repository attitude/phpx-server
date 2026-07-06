<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

/**
 * Per-request memoization — the PHP equivalent of React's `cache()`.
 *
 * React's `cache()` dedupes data fetching within a single render: if several
 * server components ask for the same data, the wrapped function runs once and
 * every caller gets the same result. PHP has no persistent render tree, so
 * "one render" becomes "one request" — the store lives for as long as the
 * process does, and callers are expected to {@see Cache::clear()} it at
 * request boundaries in a long-lived worker.
 *
 * The key is built from the callable's identity plus its arguments. Arguments
 * must cross the same "door" as everything else in this library (see the
 * concepts doc): only values `serialize()` can handle are memoized. A call
 * with unserializable arguments (closures, live objects) is never cached and
 * always falls through to $fn.
 */
final class Cache
{
    /** @var array<string, mixed> */
    private static array $store = [];

    /** @param list<mixed> $args */
    public static function memoize(callable $fn, array $args): mixed
    {
        $key = self::key($fn, $args);

        if ($key === null) {
            return $fn(...$args);
        }

        if (array_key_exists($key, self::$store)) {
            return self::$store[$key];
        }

        return self::$store[$key] = $fn(...$args);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$store);
    }

    public static function clear(): void
    {
        self::$store = [];
    }

    /** @param list<mixed> $args */
    private static function key(callable $fn, array $args): ?string
    {
        // Object identity (Closures, invokable objects) can't be serialized
        // meaningfully — use spl_object_id instead. Plain callables (function
        // names, [$obj, 'method'] arrays) serialize fine on their own.
        $identity = is_object($fn) ? spl_object_id($fn) : $fn;

        try {
            return md5(serialize([$identity, $args]));
        } catch (\Throwable) {
            // Unserializable argument (closure, live object, ...) — the same
            // "door" rule as everywhere else: don't cache what can't cross it.
            return null;
        }
    }
}
