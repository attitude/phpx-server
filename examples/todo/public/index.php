<?php declare(strict_types=1);

/**
 * The todo example — a working CRUD app that exercises every RSC idea ported to
 * PHP by phpx-server:
 *
 *   - Server components : every view is rendered on the server (components.phpx)
 *   - Suspense streaming : the todo list arrives after the shell, out of band
 *   - Server actions     : add/toggle/delete, usable with OR without JS
 *   - Client island      : React enhances the todo list once it loads
 *   - Flight navigation  : the nav fetches a serialized tuple tree (JSON) and
 *                          swaps the view in place — no full reload, islands
 *                          re-mount. Works as plain navigation with JS off.
 */

use mindplay\vite\Manifest;
use Attitude\PHPX\Server\StreamingRenderer;
use Attitude\PHPX\Server\Actions;
use Attitude\PHPX\Server\Flight;
use Attitude\PHPX\Server\Examples\Todo\Store;
use function Attitude\PHPX\Server\Suspense;
use function Attitude\PHPX\Server\Client;
use function Attitude\PHPX\Server\await;
use function Attitude\PHPX\Server\action;

require __DIR__ . '/../../../vendor/autoload.php';

/** @var array<string, callable> $components */
$components = require __DIR__ . '/../src/components.php';
['TodoList' => $TodoList, 'AddForm' => $AddForm, 'Nav' => $Nav, 'AboutView' => $AboutView, 'StatsView' => $StatsView] = $components;

$store = new Store();
action('todo/add', fn (array $a) => $store->add((string) ($a['text'] ?? '')));
action('todo/toggle', fn (array $a) => $store->toggle((string) ($a['id'] ?? '')));
action('todo/delete', fn (array $a) => $store->remove((string) ($a['id'] ?? '')));
action('todo/clearCompleted', fn (array $a) => $store->clearCompleted());

// ---- Handle a server action (JSON fetch path or plain form POST) ----------
if ($req = Actions::fromRequest()) {
    Actions::dispatch($req['id'], $req['args']);

    if ($req['json']) {
        header('Content-Type: application/json');
        echo json_encode(['todos' => $store->all()]);
        exit;
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ---- The views (each is a server-rendered tuple tree) ---------------------
$todos = $store->all();

$LazyList = function () use ($TodoList): array {
    $todos = await(0.9, fn () => (new Store())->all()); // suspends the fiber
    return ['$', $TodoList, ['todos' => $todos]];
};

// The todo island: a client boundary whose SSR children are the fully working,
// no-JS app (add form + streamed list). React mounts over it when JS is on.
$todoIsland = Client('TodoApp', ['todos' => $todos], ['$', 'div', ['className' => 'TodoAppView', 'data-ssr' => 'true'], [
    ['$', $AddForm],
    Suspense(
        ['$', 'p', ['className' => 'TodoLoadingText'], ['Loading todos…']],
        ['$', $LazyList]
    ),
]]);

$views = [
    'todos' => static fn (): array => $todoIsland,
    'about' => static fn (): array => ['$', $AboutView],
    'stats' => static function () use ($StatsView, $todos): array {
        $done = count(array_filter($todos, static fn (array $t): bool => $t['done']));
        return ['$', $StatsView, ['total' => count($todos), 'done' => $done, 'active' => count($todos) - $done]];
    },
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$current = match ($path) {
    '/about' => 'about',
    '/stats' => 'stats',
    default => 'todos',
};
$viewContent = ($views[$current])();

// ---- Flight navigation: return the serialized view tree as JSON ------------
if (Flight::wants()) {
    Flight::respond($viewContent, $components);
}

// ---- Vite assets (optional: only if a client build exists) -----------------
$manifestPath = __DIR__ . '/dist/.vite/manifest.json';
$viteJs = $viteCss = $vitePreload = '';
if (file_exists($manifestPath)) {
    try {
        $env = getenv('APP_ENV') ?: 'production';
        $vite = new Manifest(manifest_path: $manifestPath, base_path: '/dist/', dev: $env === 'development');
        $tags = $vite->createTags('src/client/main.tsx');
        $viteJs = $tags->js;
        $viteCss = $tags->css;
        $vitePreload = $tags->preload;
    } catch (\Throwable) {
        // No client build yet — the app still works fully server-side.
    }
}

// ---- Full HTML page (shell + current view) --------------------------------
$head = StreamingRenderer::clientRuntime()
    . '<script id="__todo_state" type="application/json">' . json_encode(['todos' => $todos], JSON_UNESCAPED_SLASHES) . '</script>'
    . $viteCss . $vitePreload;

$page = ['$', 'html', ['lang' => 'en'], [
    ['$', 'head', null, [
        ['$', 'meta', ['charSet' => 'UTF-8']],
        ['$', 'meta', ['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']],
        ['$', 'title', null, ['PHPX Server · RSC Todo']],
        ['$', 'fragment', ['dangerouslySetInnerHTML' => ['__html' => $head]]],
    ]],
    ['$', 'body', null, [
        ['$', 'main', ['className' => 'AppView'], [
            ['$', 'h1', ['className' => 'AppTitleText'], ['PHPX Todo']],
            ['$', 'p', ['className' => 'AppSubText'], ['Server components, Suspense streaming, server actions, and Flight navigation — in PHP.']],
            ['$', $Nav, ['current' => $current]],
            ['$', 'div', ['id' => 'view-root', 'className' => 'ViewRootView'], [$viewContent]],
        ]],
        ['$', 'fragment', ['dangerouslySetInnerHTML' => ['__html' => $viteJs]]],
    ]],
]];

header('Content-Type: text/html; charset=utf-8');
(new StreamingRenderer())->stream($page, $components);
