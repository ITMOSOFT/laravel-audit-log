# Changelog

All notable changes to `itmosoft/laravel-audit-log` will be documented in this file.

## Unreleased

### Added
- Automatic model auditing for supported Eloquent model events.
- Manual audit recording and grouped activity tracking.
- Actor resolution and configurable filtering of sensitive attributes.
- Queue job auditing support.
- Query scopes for subjects, actors, events, and date ranges.
- Activity history retrieval grouped by batch UUID.
- Feature tests for migrations, query scopes, and ungrouped activity entries.
- CI compatibility coverage for Laravel 12 and Laravel 13.

### Fixed
- Resolve the audit logger within queue listener callbacks to avoid retaining a scoped logger across queue worker scope resets.
- Keep audit entries without a batch UUID in separate activity groups.
