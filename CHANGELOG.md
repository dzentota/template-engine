# Changelog

## [Unreleased]

### Fixed
- **Security/correctness:** `if ($flag)` / `if ($user['is_admin'])` in templates was always true because
  every scalar was wrapped in a `TemplateVariable` object. `bool` and `null` values are now passed to
  templates unwrapped (requires dzentota/template-variable with the 1.2.0 changes).
- `null` template variables and globals no longer cause a `TypeError` in `createVariable()`.
- README conditional example and the `href` example (previously used the URL-component `url` context).

### Added
- Documented `$var->isTruthy()` for wrapped strings/numbers/collections in conditions.
- Documented the `href` / `src` / `url_attr` URL-attribute context for whole URLs in attributes.

### Changed / BC notes
- `TemplateEngine::createVariable()` and the `$e()` helper now return `mixed` (bool/null/objects pass through);
  previously declared `TemplateVariable|TemplateVariableCollection`. Subclasses overriding `createVariable()`
  with the old return type remain compatible (covariant narrowing).
- Templates that called escape contexts on boolean values (e.g. `$flag('html')`) must echo them directly instead.
- Requires PHP >= 8.3.
