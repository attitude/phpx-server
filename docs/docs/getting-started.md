---
sidebar_position: 2
---

# Getting started

## Requirements

- **PHP 8.1+** — the streaming renderer needs [Fibers](https://www.php.net/manual/en/language.fibers.php), added in 8.1.
- **[PHPX](https://github.com/attitude/phpx)** — compiles the `.phpx` JSX-like syntax to plain PHP. Installed automatically by Composer.
- **Node + pnpm** — only needed to build the example's React island. The PHP side of `phpx-server` has no JavaScript dependency.

## Run the example

From the repo root:

```bash
composer install          # PHP: the phpx-server library + PHPX compiler
composer example:build    # JS: install + build the React island, compile .phpx
composer example:serve    # http://localhost:8080
```

- `composer install` pulls in `phpx-server` and the PHPX compiler.
- `composer example:build` installs the example's `pnpm` dependencies, builds
  the React island with Vite, and compiles `examples/todo/src/components.phpx`
  to plain PHP.
- `composer example:serve` starts PHP's built-in server at
  `http://localhost:8080`.

## See progressive enhancement for yourself

Open `http://localhost:8080`, then reload the page **with JavaScript
disabled** in your browser's dev tools. The app still works: you can add,
toggle, and delete todos, and the list still streams in a moment after the
page shell. That's not a fallback mode — it's the same PHP rendering path
that runs when JavaScript is on. React just layers an optimistic, instant UI
on top once it loads.

That one test is the fastest way to understand what "progressive
enhancement, not isomorphism" (see [Introduction](./introduction.md)) means
in practice.
