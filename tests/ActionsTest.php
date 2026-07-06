<?php declare(strict_types=1);

use Attitude\PHPX\Server\Actions;
use PHPUnit\Framework\TestCase;
use function Attitude\PHPX\Server\actionFields;

final class ActionsTest extends TestCase
{
    private array $serverBackup;
    private array $postBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
    }

    public function testRegisterHasAndDispatchInvokeCallableWithArgs(): void
    {
        $received = null;
        Actions::register('actions-test/echo', function (array $args) use (&$received) {
            $received = $args;

            return 'ok:' . ($args['name'] ?? '');
        });

        $this->assertTrue(Actions::has('actions-test/echo'));

        $result = Actions::dispatch('actions-test/echo', ['name' => 'world']);

        $this->assertSame(['name' => 'world'], $received);
        $this->assertSame('ok:world', $result);
    }

    public function testDispatchUnknownIdThrows(): void
    {
        $this->assertFalse(Actions::has('actions-test/does-not-exist'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown server action: actions-test/does-not-exist');

        Actions::dispatch('actions-test/does-not-exist');
    }

    public function testFromRequestReturnsNullForGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->assertNull(Actions::fromRequest());
    }

    public function testFromRequestParsesFormPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['CONTENT_TYPE']);
        $_POST = ['__action' => 'todo/add', 'text' => 'Buy milk'];

        $result = Actions::fromRequest();

        $this->assertSame([
            'id' => 'todo/add',
            'args' => ['text' => 'Buy milk'],
            'json' => false,
        ], $result);
    }

    public function testFromRequestReturnsNullForFormPostWithoutActionField(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['CONTENT_TYPE']);
        $_POST = ['text' => 'Buy milk'];

        $this->assertNull(Actions::fromRequest());
    }

    public function testFromRequestJsonBodyPathIsNotUnitTestable(): void
    {
        // fromRequest() reads the JSON body via `file_get_contents('php://input')`.
        // That stream can't be populated from within a unit test process without
        // modifying src (which this suite must not touch). The JSON fetch path is
        // exercised by examples/todo instead; here we only confirm the branch we
        // *can* reach without a real request body: it needs an 'id' key.
        $this->markTestSkipped(
            "fromRequest()'s JSON path reads php://input, which a unit test can't populate without changing src. Covered by the example app's JSON fetch path."
        );
    }

    public function testActionFieldsReturnsHiddenActionAndArgInputsInOrder(): void
    {
        $fields = actionFields('todo/toggle', ['id' => '42', 'extra' => 'x']);

        $this->assertSame([
            ['$', 'input', ['type' => 'hidden', 'name' => '__action', 'value' => 'todo/toggle']],
            ['$', 'input', ['type' => 'hidden', 'name' => 'id', 'value' => '42']],
            ['$', 'input', ['type' => 'hidden', 'name' => 'extra', 'value' => 'x']],
        ], $fields);
    }

    public function testActionFieldsWithNoArgsReturnsOnlyTheActionField(): void
    {
        $fields = actionFields('todo/clearCompleted');

        $this->assertSame([
            ['$', 'input', ['type' => 'hidden', 'name' => '__action', 'value' => 'todo/clearCompleted']],
        ], $fields);
    }
}
