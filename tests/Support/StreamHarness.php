<?php declare(strict_types=1);

/**
 * StreamingRenderer::stream() drains every output buffer (`while (ob_get_level()
 * > 0) { ob_end_flush(); }`) before it echoes anything, then echoes/flushes
 * directly to the SAPI as it streams. That means a plain `ob_start()` /
 * `ob_get_clean()` wrapped around a call to stream() from inside a PHPUnit test
 * captures nothing (verified empirically) — it also pops any buffer PHPUnit
 * itself may hold open. Running the page in a fresh PHP subprocess sidesteps
 * both problems and gives us the exact bytes stream() produced.
 */
final class StreamHarness
{
    public static function run(string $body): string
    {
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);

        $script = "<?php\n"
            . "require {$autoload};\n"
            . "use Attitude\\PHPX\\Server\\StreamingRenderer;\n"
            . "use function Attitude\\PHPX\\Server\\Suspense;\n"
            . "use function Attitude\\PHPX\\Server\\await;\n"
            . $body;

        $tmp = tempnam(sys_get_temp_dir(), 'phpx_stream_') . '.php';
        file_put_contents($tmp, $script);

        try {
            return (string) shell_exec(PHP_BINARY . ' ' . escapeshellarg($tmp) . ' 2>&1');
        } finally {
            unlink($tmp);
        }
    }
}
