<?php declare(strict_types=1);

namespace Attitude\PHPX\Server\Examples\Todo;

/**
 * Dead-simple JSON-file todo store. This is the only business logic in the
 * example — everything else is the RSC-for-PHP machinery. Not concurrency-safe;
 * fine for a single-user demo.
 */
final class Store
{
    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? sys_get_temp_dir() . '/phpx_server_todos.json';

        if (!file_exists($this->file)) {
            $this->write([
                ['id' => 't1', 'text' => 'Render on the server with PHPX', 'done' => true],
                ['id' => 't2', 'text' => 'Stream the list with Suspense', 'done' => false],
                ['id' => 't3', 'text' => 'Mutate with a server action', 'done' => false],
            ]);
        }
    }

    /** @return list<array{id: string, text: string, done: bool}> */
    public function all(): array
    {
        return json_decode((string) file_get_contents($this->file), true) ?: [];
    }

    public function add(string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $todos = $this->all();
        $todos[] = ['id' => 'id' . bin2hex(random_bytes(4)), 'text' => $text, 'done' => false];
        $this->write($todos);
    }

    public function toggle(string $id): void
    {
        $this->write(array_map(static function (array $todo) use ($id): array {
            if ($todo['id'] === $id) {
                $todo['done'] = !$todo['done'];
            }

            return $todo;
        }, $this->all()));
    }

    public function remove(string $id): void
    {
        $this->write(array_values(array_filter($this->all(), static fn (array $t): bool => $t['id'] !== $id)));
    }

    public function clearCompleted(): void
    {
        $this->write(array_values(array_filter($this->all(), static fn (array $t): bool => !$t['done'])));
    }

    private function write(array $todos): void
    {
        file_put_contents($this->file, json_encode($todos, JSON_PRETTY_PRINT));
    }
}
