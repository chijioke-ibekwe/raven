# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Breaking Changes
- **Notification contexts moved from database to config.** `NotificationContext` is no longer an
  Eloquent model. Contexts are now defined in a publishable `config/notification-contexts.php`
  file and resolved at runtime with zero DB queries. Consumers must:
  1. Run `php artisan vendor:publish --tag=raven-contexts` (or re-run `php artisan raven:install`)
     to publish the new config file.
  2. Move any existing notification context rows from the `notification_contexts` database table
     into the new config file using the array format shown in `README.md`.
  3. Drop the `notification_contexts` table if it is no longer needed.

### Added
- `config/notification-contexts.php` — publishable config file where notification contexts are
  defined as named arrays.
- `NotificationContext::fromConfig(string $name, array $config): self` — static factory that
  constructs a `NotificationContext` DTO from a config array entry.
- `raven-contexts` vendor publish tag for the new contexts config file.
- PHPStan / Larastan static analysis at level 5 (`phpstan.neon`).
- Laravel Pint code-style enforcement (`pint.json` preset: `laravel`).
- CI matrix covering PHP 8.1 / 8.2 / 8.3 against Laravel 9 / 10 / 11.
- Separate `lint` and `analyse` jobs in the CI pipeline.
- Tests for AmazonSES email notifications and direct `AmazonSesChannel` error handling.
- Tests for `active` key defaulting to `true` when absent from a context config entry.
- Tests for contexts with empty `channels` arrays (no notification sent).

### Changed
- `NotificationContext` converted from an Eloquent model to a plain PHP DTO with
  `public readonly` properties — all existing callers are unaffected.
- `RavenListener` now resolves contexts via `config("notification-contexts.$name")` instead of
  a database query.
- `InstallCommand` now publishes the contexts config file instead of migration stubs.
- `SendGridChannel` and `VonageChannel` now type-hint their specific sender classes
  (`EmailNotificationSender` / `SmsNotificationSender`) instead of the base `Notification` class.
- Updated `composer.json` dev dependencies: testbench `^7.0|^8.0|^9.0`, PHPUnit `^9.6|^10.5|^11.0`,
  added `laravel/pint` and `larastan/larastan`.
- `SmsNotificationTest` now declares its namespace correctly.
- `assertTimesSent` replaced with `assertSentTimes` for Laravel 11 compatibility.

### Removed
- **`GET /api/v1/notification-contexts` API endpoint** — removed. Contexts live in config
  alongside code and are not runtime data; an HTTP endpoint adds unnecessary surface area.
  `NotificationContextController`, `Controller`, and `routes/api.php` are all deleted.
- `database/migrations/create_notification_contexts_table.php.stub` — no longer needed.
- `database/factories/NotificationContextFactory.php` — no longer needed.
- `RefreshDatabase` trait removed from all feature tests (no DB required for context lookups).
- Migration-related methods removed from `InstallCommand`
  (`handleMigrationsPublishing`, `migrationsExist`, `shouldRunMigrations`, `publishMigrations`).
- `raven-migrations` vendor publish tag removed from `RavenServiceProvider`.
- `api` block (`prefix`, `middleware`) removed from `config/raven.php` — no longer needed.

---

## [1.3.0] — Amazon SES Support

### Added
- Amazon SES email channel (`AmazonSesChannel`).
- Support for SES with SendGrid as the template source — retrieves template content from the
  SendGrid API, substitutes parameters, and sends via the SES SMTP relay.
- `toAmazonSes()` method on `EmailNotificationSender` returning a configured `PHPMailer` object.
- `phpmailer/phpmailer` dependency for SES MIME message construction.
- `aws/aws-sdk-php` dependency.
- `raven.providers.ses` config block (`key`, `secret`, `region`, `template_source`).

### Changed
- `raven.default.email` now accepts `ses` in addition to `sendgrid`.

---

## [1.2.0] — Vonage SMS Support

### Added
- Vonage SMS channel (`VonageChannel`) and `SmsNotificationSender`.
- `sms_template_filename` field on notification contexts — `.txt` files stored in the
  `templates/sms/` directory.
- `raven.providers.vonage` config block (`api_key`, `api_secret`).
- `raven.customizations.sms.from.name` config key.
- On-demand SMS routing: string phone numbers accepted as recipients alongside notifiable models.
- `TemplateCleaner` utility for `{{placeholder}}` substitution in SMS and in-app templates.
- `vonage/client` dependency.

---

## [1.1.0] — Database / In-App Notifications

### Added
- Database notification channel and `DatabaseNotificationSender`.
- `in_app_template_filename` field on notification contexts — `.json` files stored in the
  `templates/in_app/` directory.
- `databaseType()` support — notification type is set to the context name.

---

## [1.0.0] — Initial Release

### Added
- `Raven` event and `RavenListener` for event-driven multi-channel notification dispatch.
- `Scroll` data-transfer object for building notification payloads
  (`contextName`, `recipients`, `ccs`, `params`, `attachmentUrls`).
- SendGrid email channel (`SendGridChannel`) and `EmailNotificationSender`.
- `NotificationContext` — originally an Eloquent model, seeded via migration stubs.
- `raven:install` Artisan command for publishing config and migrations.
- `GET /api/v1/notification-contexts` API endpoint (authentication required).
- `RavenEntityNotFoundException` (404) and `RavenInvalidDataException` (422) exceptions.
- On-demand email routing: plain email address strings accepted as recipients.
- CC recipient support for email notifications.
- Attachment URL support for email notifications.
- `raven.php` config file covering default providers, credentials, customizations, and API prefix.
