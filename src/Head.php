<?php declare(strict_types=1);

namespace Attitude\PHPX\Server;

use Attitude\PHPX\Renderer\Renderer;

/**
 * Head / metadata hoisting — the PHP port of React 19's automatic head
 * hoisting: components rendered anywhere in the tree can contribute
 * `<title>`/`<meta>`/`<link>` tags, and they get collected here instead of
 * being emitted in place.
 *
 * A shell renders a {@see marker()} placeholder inside its `<head>`; once the
 * shell render has run (and every component under it had a chance to call
 * {@see title()}/{@see meta()}/{@see link()}/{@see push()}), {@see render()}
 * produces the collected HTML and the placeholder is swapped for it — see
 * {@see StreamingRenderer::stream()}.
 *
 * Honest limitation: only components in the initial, non-suspended shell can
 * contribute head tags. By the time a Suspense boundary streams in its
 * resolved content, the `<head>` has already been sent to the client, so
 * anything pushed from inside a boundary is too late and is silently
 * dropped when the store is cleared after render().
 */
final class Head
{
    /** @var list<array<int|string, mixed>> */
    private static array $nodes = [];

    private static ?string $title = null;

    /** Add a raw head tuple, e.g. `['$', 'meta', ['name' => 'description', 'content' => '…']]`. */
    public static function push(array $node): void
    {
        self::$nodes[] = $node;
    }

    /** Set the document title. Only the last call before render() wins. */
    public static function title(string $title): void
    {
        self::$title = $title;
    }

    /** @param array<string, mixed> $attrs */
    public static function meta(array $attrs): void
    {
        self::push(['$', 'meta', $attrs]);
    }

    /** @param array<string, mixed> $attrs */
    public static function link(array $attrs): void
    {
        self::push(['$', 'link', $attrs]);
    }

    /** Stable placeholder to embed in the shell's `<head>`. */
    public static function marker(): string
    {
        return '<!--phpx:head-->';
    }

    /** Render the collected head nodes to HTML, then clear the store. */
    public static function render(): string
    {
        $nodes = self::$nodes;
        if (self::$title !== null) {
            $nodes[] = ['$', 'title', null, [self::$title]];
        }

        $renderer = new Renderer();
        $renderer->react = true;
        $html = $renderer->render(['$', 'fragment', null, $nodes]);

        self::clear();

        return $html;
    }

    public static function clear(): void
    {
        self::$nodes = [];
        self::$title = null;
    }
}
