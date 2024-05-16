# Release Notes

## [Unreleased](https://github.com/inertiajs/inertia-laravel/compare/v1.1.0...1.x)

## [v1.1.0](https://github.com/inertiajs/inertia-laravel/compare/v1.0.0...v1.1.0) - 2024-05-16

* Support dot notation in partial requests by [@lepikhinb](https://github.com/lepikhinb) in https://github.com/inertiajs/inertia-laravel/pull/620
* [1.x] Add `$request->inertia()` IDE helper by [@ycs77](https://github.com/ycs77) in https://github.com/inertiajs/inertia-laravel/pull/625

## [v1.0.0](https://github.com/inertiajs/inertia-laravel/compare/v0.6.11...v1.0.0) - 2024-03-08

- Add Laravel 11 support ([#560](https://github.com/inertiajs/inertia-laravel/pull/560), [#564](https://github.com/inertiajs/inertia-laravel/pull/564))
- Fix URL generation ([#592](https://github.com/inertiajs/inertia-laravel/pull/592))
- Remove deprecated `Assert` class and Laravel 6 & 7 support. ([#594](https://github.com/inertiajs/inertia-laravel/pull/594))

## [v0.6.11](https://github.com/inertiajs/inertia-laravel/compare/v0.6.10...v0.6.11) - 2023-09-13

- Add option for using the `bun` runtime in SSR ([#552](https://github.com/inertiajs/inertia-laravel/pull/552))

## [v0.6.10](https://github.com/inertiajs/inertia-laravel/compare/v0.6.9...v0.6.10) - 2023-09-13

- Add `inertia_location` helper function ([#491](https://github.com/inertiajs/inertia-laravel/pull/491))
- Add `Route::inertia()` IDE helper ([#413](https://github.com/inertiajs/inertia-laravel/pull/413))
- Automatically update Facade docblocks ([#538](https://github.com/inertiajs/inertia-laravel/pull/538))
- Restore request and session on redirects ([#539](https://github.com/inertiajs/inertia-laravel/pull/539))
- Add PHP 8.3 support ([#540](https://github.com/inertiajs/inertia-laravel/pull/540))

## [v0.6.9](https://github.com/inertiajs/inertia-laravel/compare/v0.6.8...v0.6.9) - 2023-01-17

- Conditionally use `pcntl` extension in `inertia:start-ssr` command ([#492](https://github.com/inertiajs/inertia-laravel/pull/492))

## [v0.6.8](https://github.com/inertiajs/inertia-laravel/compare/v0.6.7...v0.6.8) - 2023-01-14

- Reintroduce `inertia.ssr.enabled` config option ([#488](https://github.com/inertiajs/inertia-laravel/pull/488))
- Fix bug where SSR is dispatched twice when errors exist ([#489](https://github.com/inertiajs/inertia-laravel/pull/489))

## [v0.6.7](https://github.com/inertiajs/inertia-laravel/compare/v0.6.6...v0.6.7) - 2023-01-12

- Report SSR errors ([#486](https://github.com/inertiajs/inertia-laravel/pull/486))
- Auto enable SSR based on existence of SSR bundle ([#487](https://github.com/inertiajs/inertia-laravel/pull/487))

## [v0.6.6](https://github.com/inertiajs/inertia-laravel/compare/v0.6.5...v0.6.6) - 2023-01-11

- Add `inertia:start-ssr` and `inertia:stop-ssr` artisan commands ([#483](https://github.com/inertiajs/inertia-laravel/pull/483))

## [v0.6.5](https://github.com/inertiajs/inertia-laravel/compare/v0.6.4...v0.6.5) - 2023-01-10

- Add Laravel v10 support ([#480](https://github.com/inertiajs/inertia-laravel/pull/480))

## [v0.6.4](https://github.com/inertiajs/inertia-laravel/compare/v0.6.3...v0.6.4) - 2022-11-08

- Add PHP 8.2 support ([#463](https://github.com/inertiajs/inertia-laravel/pull/463))

## [v0.6.3](https://github.com/inertiajs/inertia-laravel/compare/v0.6.2...v0.6.3) - 2022-06-27

- Check Vite manifest path (`build/manifest.json`) when determining the current asset version ([#399](https://github.com/inertiajs/inertia-laravel/pull/399))

## [v0.6.2](https://github.com/inertiajs/inertia-laravel/compare/v0.6.1...v0.6.2) - 2022-05-24

- Switch to using the `Vary: X-Inertia` header ([#404](https://github.com/inertiajs/inertia-laravel/pull/404))
- Fix bug with incompatible `$request->header()` method ([#404](https://github.com/inertiajs/inertia-laravel/pull/404))

## [v0.6.1](https://github.com/inertiajs/inertia-laravel/compare/v0.6.0...v0.6.1) - 2022-05-24

- Set `Vary: Accept` header for all responses ([#398](https://github.com/inertiajs/inertia-laravel/pull/398))
- Only register Blade directives when actually needed ([#395](https://github.com/inertiajs/inertia-laravel/pull/395))

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
