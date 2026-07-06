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

return [
    'TodoItem' => $TodoItem,
    'TodoList' => $TodoList,
    'AddForm' => $AddForm,
];
