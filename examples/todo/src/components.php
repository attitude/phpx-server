<?php declare(strict_types=1);

// Server components for the todo example, authored in PHPX.
// They render on the server and disappear into HTML — zero client JS.
// Each mutation is a plain <form> server action, so the app is fully usable
// before (or without) React ever loads.

use function Attitude\PHPX\Server\actionFields;

$TodoItem = function (array $props): array {
    ['id' => $id, 'text' => $text, 'done' => $done] = $props;

    return (
        ['$', 'li', ['className'=>(['TodoItemView' => true, 'is-done' => $done]), 'data-id'=>($id)], [
            ['$', 'form', ['method'=>"POST", 'className'=>"TodoItemFormView"], [
                (actionFields('todo/toggle', ['id' => $id])),
                ['$', 'button', ['type'=>"submit", 'className'=>"TodoToggleButton", 'aria-pressed'=>($done ? 'true' : 'false')], [
                    ($done ? '☑' : '☐'),
                ]],
            ]],
            ['$', 'span', ['className'=>"TodoItemText"], [($text)]],
            ['$', 'form', ['method'=>"POST", 'className'=>"TodoItemFormView"], [
                (actionFields('todo/delete', ['id' => $id])),
                ['$', 'button', ['type'=>"submit", 'className'=>"TodoDeleteButton", 'aria-label'=>"Delete todo"], ['✕']],
            ]],
        ]]
    );
};

$TodoList = function (array $props) use ($TodoItem): array {
    ['todos' => $todos] = $props;

    if (empty($todos)) {
        return (['$', 'p', ['className'=>"TodoEmptyText"], ['Nothing to do. Add something above.']]);
    }

    return (
        ['$', 'ul', ['className'=>"TodoListView"], [
            (array_map(fn(array $t): array => ['$', $TodoItem, $t], $todos)),
        ]]
    );
};

$AddForm = function (): array {
    return (
        ['$', 'form', ['method'=>"POST", 'className'=>"AddFormView"], [
            (actionFields('todo/add')),
            ['$', 'input', [
                'className'=>"AddFormInput",
                'type'=>"text",
                'name'=>"text",
                'placeholder'=>"Add a todo…",
                'autoComplete'=>"off",
                'required'=>true,
            ]],
            ['$', 'button', ['type'=>"submit", 'className'=>"AddFormButton"], ['Add']],
        ]]
    );
};

$Nav = function (array $props): array {
    ['current' => $current] = $props;

    $link = fn(string $href, string $label, string $key): array => (
        ['$', 'a', ['href'=>($href), 'data-flight-link'=>true, 'className'=>(['NavLink' => true, 'is-active' => $current === $key])], [
            ($label),
        ]]
    );

    return (
        ['$', 'nav', ['className'=>"NavView"], [
            ($link('/', 'Todos', 'todos')),
            ($link('/stats', 'Stats', 'stats')),
            ($link('/about', 'About', 'about')),
        ]]
    );
};

$AboutView = function (): array {
    return (
        ['$', 'div', ['className'=>"ProseView"], [
            ['$', 'h2', ['className'=>"ProseHeadingText"], ['About this demo']],
            ['$', 'p', ['className'=>"ProseText"], [
                'Every page you navigate to here is a server component rendered by PHPX. Clicking the
                nav does not reload the page — the browser fetches a Flight-style JSON payload (the
                serialized tuple tree), rebuilds the view, and boots any React islands in place.',
            ]],
            ['$', 'p', ['className'=>"ProseText"], [
                'Turn JavaScript off and the same links still work as plain server-rendered navigation.',
            ]],
        ]]
    );
};

$StatsView = function (array $props): array {
    ['total' => $total, 'done' => $done, 'active' => $active] = $props;

    return (
        ['$', 'div', ['className'=>"ProseView"], [
            ['$', 'h2', ['className'=>"ProseHeadingText"], ['Stats']],
            ['$', 'ul', ['className'=>"StatsListView"], [
                ['$', 'li', ['className'=>"StatsItemView"], ['Total', ['$', 'span', ['className'=>"StatsNumberText"], [($total)]]]],
                ['$', 'li', ['className'=>"StatsItemView"], ['Completed', ['$', 'span', ['className'=>"StatsNumberText"], [($done)]]]],
                ['$', 'li', ['className'=>"StatsItemView"], ['Active', ['$', 'span', ['className'=>"StatsNumberText"], [($active)]]]],
            ]],
        ]]
    );
};

return [
    'TodoItem' => $TodoItem,
    'TodoList' => $TodoList,
    'AddForm' => $AddForm,
    'Nav' => $Nav,
    'AboutView' => $AboutView,
    'StatsView' => $StatsView,
];
