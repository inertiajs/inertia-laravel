# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/inertiajs/inertia-laravel/compare/v0.6.0...HEAD)

Nothing!

## [v0.6.0](https://github.com/inertiajs/inertia-laravel/compare/v0.5.4...v0.6.0) - 2022-05-10

### Added

- Inertia now redirects back by default when no response is returned from a controller ([#350](https://github.com/inertiajs/inertia-laravel/pull/350))
- The Middleware has an overridable `onEmptyResponse` hook to customize the default 'redirect back' behavior ([#350](https://github.com/inertiajs/inertia-laravel/pull/350))

### Changed

- Internal: Replaced the Middleware's `checkVersion` method with an `onVersionChange` hook ([#350](https://github.com/inertiajs/inertia-laravel/pull/350))

### Fixed

- Fixed namespace issue with `Route::inertia()` method ([#368](https://github.com/inertiajs/inertia-laravel/pull/368))
- Added session check when sharing validation errors ([#380](https://github.com/inertiajs/inertia-laravel/pull/380))
- Fixed docblock on facade render method ([#387](https://github.com/inertiajs/inertia-laravel/pull/387))

## [v0.5.4](https://github.com/inertiajs/inertia-laravel/compare/v0.5.3...v0.5.4) - 2022-01-18

### Added

- `.tsx` extension is now included to the testing paths by default ([#354](https://github.com/inertiajs/inertia-laravel/pull/354))

### Fixed

- Dot-notated props weren't being removed after unpacking ([507b0a](https://github.com/inertiajs/inertia-laravel/commit/507b0a0ad8321028b8651528099f73a88b158359))

## [v0.5.3](https://github.com/inertiajs/inertia-laravel/compare/v0.5.2...v0.5.3) - 2022-01-17

### Fixed

- Incorrect `Arrayable` type-hint ([#353](https://github.com/inertiajs/inertia-laravel/pull/353))
- Pagination with API Resources and other nested props weren't resolving properly ([#342](https://github.com/inertiajs/inertia-laravel/pull/342), [#298](https://github.com/inertiajs/inertia-laravel/pull/298))

## [v0.5.2](https://github.com/inertiajs/inertia-laravel/compare/v0.5.1...v0.5.2) - 2022-01-12

### Added

- Laravel 9 Support ([#347](https://github.com/inertiajs/inertia-laravel/pull/347))

### Fixed

- Respect `X-Forwarded-For` header ([#333](https://github.com/inertiajs/inertia-laravel/pull/333))

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
