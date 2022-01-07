# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/inertiajs/inertia-laravel/compare/v0.5.1...HEAD)

## [v0.5.1](https://github.com/inertiajs/inertia-laravel/compare/v0.5.0...v0.5.1) - 2022-01-07

### Fixed

- When the SSR Server crashes, a `null` response will be returned, which wasn't being handled properly ([7d7d89](https://github.com/inertiajs/inertia-laravel/commit/7d7d891d72792f6cab6b616d5bbbb48f0526d65f))

## [v0.5.0](https://github.com/inertiajs/inertia-laravel/compare/v0.4.5...v0.5.0) - 2022-01-07

### Added

- PHP 8.1 Support ([#327](https://github.com/inertiajs/inertia-laravel/pull/327))
- Allow `Inertia::location` to be called with a `RedirectResponse` ([#302](https://github.com/inertiajs/inertia-laravel/pull/302))
- Support Guzzle Promises ([#316](https://github.com/inertiajs/inertia-laravel/pull/316))
- Server-side rendering support (`@inertiaHead` directive) ([#339](https://github.com/inertiajs/inertia-laravel/pull/339))
- Allow custom `@inertia` root element ID (e.g. `@inertia('foo')` -> `<div id="foo" data-page="...`) ([#339](https://github.com/inertiajs/inertia-laravel/pull/339))

### Changed

- We now keep a changelog here on GitHub :tada: For earlier releases, please see [the releases page of inertiajs.com](https://inertiajs.com/releases?all=true#inertia-laravel).
- Add PHP native type declarations ([#301](https://github.com/inertiajs/inertia-laravel/pull/301), [#337](https://github.com/inertiajs/inertia-laravel/pull/337))

### Deprecated

- Deprecate `Assert` library in favor of Laravel's AssertableJson ([#338](https://github.com/inertiajs/inertia-laravel/pull/338))

### Removed

- Laravel 5.4 Support ([#327](https://github.com/inertiajs/inertia-laravel/pull/327))

### Fixed

- Transform Responsable props to arrays instead of objects ([#265](https://github.com/inertiajs/inertia-laravel/pull/265))
- `Inertia::location()`: Fall back to regular redirects when a direct (non-Inertia) visit was made ([#312](https://github.com/inertiajs/inertia-laravel/pull/312))
- Use correct types for Resources ([#214](https://github.com/inertiajs/inertia-laravel/issues/214))
