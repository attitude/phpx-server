<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

/**
 * Server actions: the client -> server half of RSC, ported to PHP.
 *
 * A server action is a named callable. It can be invoked two ways from the same
 * registration:
 *
 *   1. A plain `<form method="POST">` — works with zero JavaScript (progressive
 *      enhancement). The handler mutates and Post/Redirect/Get redirects.
 *   2. A `fetch()` with a JSON body — the client gets fresh state back and
 *      re-renders. This is the "refetch after mutation" path.
 *
 * The serialization boundary ("the door") is respected: only JSON-serializable
 * arguments cross. No closures, no live objects.
 */
final class Actions
{
    /** @var array<string, callable> */
    private static array $registry = [];

    public static function register(string $id, callable $fn): void
    {
        self::$registry[$id] = $fn;
    }

    public static function has(string $id): bool
    {
        return isset(self::$registry[$id]);
    }

    public static function dispatch(string $id, array $args = []): mixed
    {
        if (!isset(self::$registry[$id])) {
            throw new \RuntimeException("Unknown server action: {$id}");
        }

        return (self::$registry[$id])($args);
    }

    /**
     * Extract an action invocation from the current request, or null if none.
     *
     * @return array{id: string, args: array, json: bool}|null
     */
    public static function fromRequest(): ?array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return null;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
            if (!isset($body['id'])) {
                return null;
            }

            return ['id' => (string) $body['id'], 'args' => (array) ($body['args'] ?? []), 'json' => true];
        }

        // Form POST (progressive-enhancement path).
        if (!isset($_POST['__action'])) {
            return null;
        }

        $id = (string) $_POST['__action'];
        $args = $_POST;
        unset($args['__action']);

        return ['id' => $id, 'args' => $args, 'json' => false];
    }
}
