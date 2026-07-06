<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

/**
 * Flight-style serialization for client-driven navigation.
 *
 * React's RSC "Flight" wire format is a serialized component tree: server
 * components are executed away, and what crosses to the client is data —
 * host elements plus references to client components. PHPX tuples are already
 * that shape, so the port is small.
 *
 * {@see serialize()} walks a tuple tree and:
 *   - executes server components (closures / named callables) and recurses into
 *     their result — they disappear, exactly like RSC;
 *   - resolves Suspense boundaries inline (off a fiber, {@see await()} runs
 *     synchronously), so the payload is complete;
 *   - keeps host elements as `['$', tag, props, children]` data, with props
 *     normalised to final attributes so the client needs no render logic;
 *   - keeps client-component boundaries (the `[data-client]` divs from
 *     {@see Client()}) as-is — the client runtime mounts them after insertion.
 *
 * The result is `json_encode`-able. The client rebuilds the DOM from it and
 * boots any islands, without a full-page reload.
 */
final class Flight
{
    /** Does the current request want a Flight payload rather than HTML? */
    public static function wants(): bool
    {
        return isset($_GET['__flight'])
            || ($_SERVER['HTTP_X_FLIGHT'] ?? '') === '1'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/x-component');
    }

    /** Serialize a tree, emit it as JSON with the right header, and stop. */
    public static function respond(mixed $node, array $components = []): void
    {
        header('Content-Type: application/x-component+json; charset=utf-8');
        echo json_encode(self::serialize($node, $components), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Resolve server components and return a JSON-serializable tuple tree. */
    public static function serialize(mixed $node, array $components = []): mixed
    {
        if ($node === null || is_scalar($node)) {
            return $node;
        }

        if (!is_array($node)) {
            return null;
        }

        if (($node[0] ?? null) === '$') {
            $type = $node[1] ?? null;
            $props = is_array($node[2] ?? null) ? $node[2] : [];
            $children = array_key_exists(3, $node) ? $node[3] : null;

            // Server component: a closure, or a name in the components map.
            $component = $type instanceof \Closure
                ? $type
                : (is_string($type) && isset($components[$type]) ? $components[$type] : null);

            if ($component !== null) {
                if ($children !== null) {
                    $props['children'] = $children;
                }

                return self::serialize($component($props), $components);
            }

            // Suspense: data is ready off-fiber, so just resolve the children.
            if ($type === 'Suspense') {
                $kids = $children ?? ($props['children'] ?? []);

                return self::serialize($kids, $components);
            }

            // Host element (including 'fragment' and client-boundary divs).
            // Trim trailing slots like the compiler: drop children if null, then
            // props if null — but keep the props slot when children remain.
            $tuple = ['$', $type];
            $outProps = self::normalizeProps($props);
            $outChildren = $children === null ? null : self::serialize($children, $components);

            if ($outChildren !== null) {
                $tuple[] = $outProps;   // index 2 (may be null)
                $tuple[] = $outChildren; // index 3
            } elseif ($outProps !== null) {
                $tuple[] = $outProps;
            }

            return $tuple;
        }

        // Fragment / list of children.
        return array_map(static fn ($child) => self::serialize($child, $components), $node);
    }

    /**
     * Normalise props to final HTML attributes so the client can apply them
     * verbatim: className/clsx -> class, style object -> css text, htmlFor ->
     * for; drop null and (plain) false; keep booleans and scalars.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>|null
     */
    private static function normalizeProps(array $props): ?array
    {
        $out = [];

        foreach ($props as $key => $value) {
            if ($key === 'children') {
                continue;
            }

            $key = match ($key) {
                'className' => 'class',
                'htmlFor' => 'for',
                default => $key,
            };

            if ($value === null) {
                continue;
            }

            if ($key === 'class' && (is_array($value) || $value instanceof \stdClass)) {
                $classes = array_keys(array_filter((array) $value));
                if ($classes !== []) {
                    $out['class'] = implode(' ', $classes);
                }
                continue;
            }

            if ($key === 'style' && (is_array($value) || $value instanceof \stdClass)) {
                $css = '';
                foreach ((array) $value as $prop => $sv) {
                    if ($sv === null || $sv === false || $sv === '') {
                        continue;
                    }
                    $prop = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', (string) $prop));
                    $css .= "{$prop}:{$sv};";
                }
                if ($css !== '') {
                    $out['style'] = rtrim($css, ';');
                }
                continue;
            }

            if (is_bool($value) && !str_contains($key, '-')) {
                if ($value) {
                    $out[$key] = true;
                }
                continue;
            }

            if (is_array($value)) {
                continue; // non-class/style array attrs are uncommon; skip in v1
            }

            $out[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return $out === [] ? null : $out;
    }
}
