# Scrutinizer

WordPress Performance Profiler — See where your server request duration is spent.

> **Status:** Early development. Not yet available on WordPress.org.

## What It Does

Scrutinizer hooks into WordPress request processing and tells you exactly where time goes: which plugins, which hooks, which callbacks. No guesswork, no approximation — monotonic high-resolution timing with exclusive and inclusive cost attribution.

- **Hook Execution Trace** — See every hooked callback with its exclusive time cost
- **Plugin & theme attribution** — Callbacks resolved to their source plugin/theme automatically
- **Baseline comparison** — Save a profile, change something, compare the diff
- **Share reports** — POST to scrutineer.dev for time-limited shareable links
- **WP-CLI** — Full data management from the command line

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Development

```bash
composer install
composer lint
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Links

- [scrutineer.dev](https://scrutineer.dev) — Project home
- [@scrutineer.dev](https://bsky.app/profile/scrutineer.dev) — Bluesky
- [GitHub](https://github.com/scrutineerhq/scrutinizer) — Source
