# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [6.0.0] — Twilio Support, Error Handling Overhaul, and Config-Based Contexts

### Breaking Changes
- **Dropped Laravel 9 support.** Laravel 9 is EOL and all versions are blocked by Composer's
  security audit. Minimum requirements are now PHP 8.1 and Laravel 10.
- **Notification contexts moved from database to config.** `NotificationContext` is no longer an
  Eloquent model. Contexts are now defined in a publishable `config/notification-contexts.php`
  file and resolved at runtime with zero DB queries. Consumers must:
  1. Run `php artisan vendor:publish --tag=raven-contexts` to publish the new config file.
  2. Move any existing notification context rows from the `notification_contexts` database table
     into the new config file using the array format shown in `README.md`.
  3. Drop the `notification_contexts` table if it is no longer needed.

### Added
- `config/notification-contexts.php` — publishable config file where notification contexts are
  defined as named arrays.
- `NotificationContext::fromConfig(string $name, array $config): self` — static factory that
  constructs a `NotificationContext` DTO from a config array entry.
- `raven-contexts` vendor publish tag for the new contexts config file.
- Twilio SMS channel (`TwilioChannel`) — Twilio is now available as an alternative SMS provider
  alongside Vonage. Set `SMS_NOTIFICATION_PROVIDER=twilio` to use it.
- `toTwilio()` method on `SmsNotificationSender` for building Twilio message payloads.
- `raven.providers.twilio` config block (`account_sid`, `auth_token`).
- `raven.customizations.sms.from.phone_number` config key for Twilio's `from` number.
- `twilio/sdk` dependency (`^8.11`).
- `RavenDeliveryException` (502) — thrown by all channels on delivery failure (API errors,
  non-success status codes, SDK exceptions). Replaces inconsistent use of generic `Exception`.
- `RavenTemplateNotFoundException` (404) — thrown when a template file or SendGrid template
  cannot be found.
- PHPStan / Larastan static analysis at level 5 (`phpstan.neon`).
- Laravel Pint code-style enforcement (`pint.json` preset: `laravel`).
- CI matrix covering PHP 8.1 / 8.2 / 8.3 against Laravel 10 / 11.
- Separate `lint` and `analyse` jobs in the CI pipeline.
- Tests for AmazonSES email notifications and direct `AmazonSesChannel` error handling.
- Tests for `active` key defaulting to `true` when absent from a context config entry.
- Tests for contexts with empty `channels` arrays (no notification sent).

### Changed
- `NotificationContext` converted from an Eloquent model to a plain PHP DTO with
  `public readonly` properties — all existing callers are unaffected.
- `Raven` is now a job (src/Jobs/Raven.php), not an event+listener pair. The public API is identical — callers
  still write Raven::dispatch($scroll).
- All three notification senders: `EmailNotificationSender`, `SmsNotificationSender`, `DatabaseNotificationSender` —
  removed `ShouldQueue`, `Queueable`, and the queue code. They are now plain synchronous notifications that
  run inside the Raven job.
- `Raven` now resolves contexts via `config("notification-contexts.$name")` instead of a database query.
- Config files are now published directly via `vendor:publish` tags (`raven-config`, `raven-contexts`).
- All channels (`SendGridChannel`, `VonageChannel`, `AmazonSesChannel`, `TwilioChannel`) now accept
  the base `Notification` type and validate with an `instanceof` guard, throwing
  `RavenDeliveryException` on mismatch.
- All channels now catch SDK/network exceptions and wrap them in `RavenDeliveryException` with the
  original exception preserved as `$previous` — ensuring consistent error handling for the Raven job.
- `AmazonSesChannel`: fixed `throw new Exception($e)` bug that produced garbled error messages;
  non-200 SES responses now throw instead of silently succeeding; status code comparison changed
  from string to integer.
- `TemplateCleaner::cleanFile()` now throws `RavenTemplateNotFoundException` when the template file
  cannot be read.
- Provider registration in `RavenServiceProvider` refactored from conditional blocks to a
  loop-based approach for cleaner extensibility.
- Updated `composer.json` dev dependencies: testbench `^8.0|^9.0`, PHPUnit `^10.5|^11.0`,
  added `laravel/pint` and `larastan/larastan`.
- `SmsNotificationTest` now declares its namespace correctly.
- `assertTimesSent` replaced with `assertSentTimes` for Laravel 11 compatibility.

### Removed
- **`GET /api/v1/notification-contexts` API endpoint** — removed. Contexts live in config
  alongside code and are not runtime data; an HTTP endpoint adds unnecessary surface area.
  `NotificationContextController`, `Controller`, and `routes/api.php` are all deleted.
- `src/Events/Raven.php` - no longer needed. An equivalent job of the same name is now in use.
- `src/Listeners/RavenListener.php` - no longer needed.
- `src/Providers/EventServiceProvider.php` - no longer needed since we don't use `Raven` events anymore
- `database/migrations/create_notification_contexts_table.php.stub` — no longer needed.
- `database/factories/NotificationContextFactory.php` — no longer needed.
- `RefreshDatabase` trait removed from all feature tests (no DB required for context lookups).
- `raven:install` Artisan command (`InstallCommand`) — config files are now published directly
  via `php artisan vendor:publish --tag=raven-config` and `--tag=raven-contexts`.
- `raven-migrations` vendor publish tag removed from `RavenServiceProvider`.
- `api` block (`prefix`, `middleware`) removed from `config/raven.php` — no longer needed.

---

## [5.1.0] — Vonage SMS Support

### Added
- Vonage SMS channel (`VonageChannel`) and `SmsNotificationSender`.
- `toVonage()` method on `SmsNotificationSender` returning a Vonage `SMS` message object.
- `sms_template_filename` field on notification contexts — `.txt` files stored in the
  `templates/sms/` directory.
- `raven.providers.vonage` config block (`api_key`, `api_secret`).
- `raven.customizations.sms.from.name` config key.
- On-demand SMS routing: string phone numbers accepted as recipients alongside notifiable models.
- `TemplateCleaner` utility for `{{placeholder}}` substitution in SMS and in-app templates.
- `vonage/client` dependency.
- Comprehensive SMS notification tests.
- Project logo (`raven_logo.png`).

### Fixed
- Phone number pattern matcher for on-demand SMS routing.

### Changed
- Removed duplicate parameter validation across channels — centralized in sender classes.

---

## [5.0.0] — Architecture Overhaul

### Breaking Changes
- **Removed `NotificationChannel` model.** Channels are now defined directly on the
  `NotificationContext` model, eliminating the pivot table.

### Added
- GitHub Actions CI workflow (`run-tests.yml`).
- MIT LICENSE file.
- `DatabaseNotificationTest` — comprehensive tests for database notifications.

### Changed
- Notification channels moved from a separate model/pivot table to a `channels` column on
  `NotificationContext`.
- Context channel matching is now case-insensitive.
- Migrations converted to anonymous classes.

### Removed
- `NotificationChannel` model and `notification_channel_notification_context` pivot table.
- `create_notification_channels_table.php.stub` migration.

---

## [4.0.0] — Database Notifications and Context Refactoring

### Added
- Database / in-app notification channel and `DatabaseNotificationSender`.
- Filesystem template source for database notifications — `.json` files stored in the
  `templates/in_app/` directory.
- `in_app_template_filename` field on notification contexts.
- `databaseType()` support — notification type is set to the context name.
- `vonage/client` dependency (foundation for SMS support).

### Changed
- Notification context column names updated for consistency.
- JSON response structure for the notification context API endpoint.

---

## [3.1.2] — SES Validation

### Changed
- Added notification validation in `AmazonSesChannel` before sending.

---

## [3.1.1] — SES and SendGrid Fixes

### Fixed
- Email attachment handling in `AmazonSesChannel`.
- CC field logic in `SendGridChannel`.
- Subject string cleanup when using SendGrid templates with SES.

---

## [3.1.0] — Queue Customization

### Added
- `raven.customizations.queue_name` config key — allows overriding the queue name for
  notification jobs.

### Fixed
- Channel sender method signatures.
- `Scroll` class attribute initialization.

### Changed
- `SendGridChannel` now validates the notification context before sending.

---

## [3.0.0] — Amazon SES Support

### Added
- Amazon SES email channel (`AmazonSesChannel`).
- Support for SES with SendGrid as the template source — retrieves template content from the
  SendGrid API, substitutes parameters, and sends via the SES SMTP relay.
- `toAmazonSes()` method on `EmailNotificationSender` returning a configured `PHPMailer` object.
- `phpmailer/phpmailer` dependency for SES MIME message construction.
- `aws/aws-sdk-php` dependency.
- `raven.providers.ses` config block (`key`, `secret`, `region`, `template_source`).
- PHPUnit configuration (`phpunit.xml`).

### Changed
- `raven.default.email` now accepts `ses` in addition to `sendgrid`.
- `NotificationData` renamed to `Scroll`.

---

## [2.0.1] — SendGrid Channel Fixes

### Fixed
- SendGrid channel registration in `RavenServiceProvider`.
- Removed stale database provider reference from config file.

---

## [2.0.0] — Initial Restructure

### Changed
- Internal restructuring and preparation for multi-provider support.

---

## [1.0.0] — Initial Release

### Added
- `Raven` event and `RavenListener` for event-driven multi-channel notification dispatch.
- `Scroll` data-transfer object for building notification payloads
  (`contextName`, `recipients`, `ccs`, `params`, `attachmentUrls`).
- SendGrid email channel (`SendGridChannel`) and `EmailNotificationSender`.
- `NotificationContext` — Eloquent model, seeded via migration stubs.
- `raven:install` Artisan command for publishing config and migrations.
- `GET /api/v1/notification-contexts` API endpoint (authentication required).
- `RavenEntityNotFoundException` (404) and `RavenInvalidDataException` (422) exceptions.
- On-demand email routing: plain email address strings accepted as recipients.
- CC recipient support for email notifications.
- Attachment URL support for email notifications.
- `raven.php` config file covering default providers, credentials, customizations, and API prefix.
