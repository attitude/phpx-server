# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-07-07

### Changed

- `attitude/phpx` is now required as `^0.4.1` instead of `dev-main` — upstream tags releases now, so consumers no longer need dev-stability requires.
- Recompiled the todo example with the v0.4.1 compiler (static attributes now emit single-quoted PHP strings).

## [0.1.0] - 2026-07-07

### Added

- Server components and a streaming HTML renderer.
- Suspense streaming via PHP Fibers, with nested and parallel boundaries.
- Error boundaries that catch subtree errors, sync or while streaming.
- Server actions: plain `<form>` submissions, JSON `fetch` calls, and `redirect()`.
- Client components ("islands") that mount a React island into server-rendered markup.
- A Flight-style JSON endpoint for client-driven navigation without a reload.
- Streaming Flight payloads as NDJSON, using the same scheduler as HTML streaming.
- `cache()` for per-request memoization of data loading.
- Head and metadata hoisting for `<title>`, `<meta>`, and `<link>`.
- Router UX: prefetch-on-hover and a pending indicator during navigation.
- An `examples/todo` app demonstrating every capability above.
- A Docusaurus documentation site, deployed to GitHub Pages.
- CI: PHPStan, PHPUnit, and a client build check.
