# Contributing

## Project status

phpx-server is experimental. It exists to answer one question: *how much of the
React Server Components model is portable to PHP?* The API may change as that
question gets answered. Keep that in mind before investing in a large PR —
open an issue first if you're planning something big.

## Setup

```bash
composer install
```

Run the checks before opening a PR:

```bash
composer test      # PHPUnit
composer analyse    # PHPStan, level 5 (see phpstan.neon)
```

## Example app

The `examples/todo` app exercises every capability in the README (streaming,
Suspense, server actions, client islands, Flight navigation). Building it
needs Node and pnpm; running it only needs PHP.

```bash
composer example:build    # installs + builds the React island, compiles .phpx
composer example:serve    # http://localhost:8080
```

## Pull requests

- Add tests for any behavior change. Bug fixes should include a test that
  reproduces the bug.
- `composer analyse` must be clean.
- CI must be green.
- Keep PRs small and focused on one change. Large, multi-purpose PRs are
  harder to review and more likely to be asked to split up.
- Match the existing code style — don't reformat or restructure code you
  aren't otherwise touching.

## Discussion

Use GitHub issues for bug reports, feature ideas, and questions about
direction. There's no separate mailing list or chat.
