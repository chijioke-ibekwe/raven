# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [1.0.0] — First Stable Release

Multi-channel Laravel notification package supporting Email (SendGrid, Amazon SES), SMS (Vonage,
Twilio), and Database/In-App notifications through a single, config-driven interface.

### Features
- **Config-driven notification contexts** — define notification contexts in a publishable
  `config/notification-contexts.php` file. Each context specifies its channels, templates, and
  whether it is active. No database tables required.
- **Email notifications** via SendGrid or Amazon SES.
  - SendGrid: dynamic templates referenced by template ID.
  - Amazon SES: raw email via SES with template content sourced from SendGrid or the filesystem.
  - CC, BCC, reply-to, and file attachment support.
- **SMS notifications** via Vonage or Twilio.
  - Template-based messages using `.txt` files with `{{placeholder}}` substitution.
  - On-demand routing: pass phone number strings directly as recipients.
- **Database / in-app notifications** using Laravel's built-in notification system.
  - Template-based using `.json` files with `{{placeholder}}` substitution.
- **Asynchronous dispatch** — `Raven::dispatch($scroll)` queues a job that resolves the
  notification context and sends through all configured channels.
- **Configurable queue** — override the queue name via `raven.customizations.queue_name`.
- **Provider abstraction** — switch providers (e.g. Vonage to Twilio) by changing an env var.
  No code changes required.
- **Consistent error handling** — all channels throw `RavenDeliveryException` (502) on delivery
  failure, wrapping SDK exceptions with the original as `$previous`. Template errors throw
  `RavenTemplateNotFoundException` (404).
- **Observability events** — `RavenNotificationSent` and `RavenNotificationFailed` events fired
  after each channel delivery attempt.
- **On-demand email routing** — pass plain email strings as recipients alongside notifiable models.

### Exceptions
- `RavenEntityNotFoundException` (404) — notification context not found in config.
- `RavenInvalidDataException` (422) — missing or invalid data on the `Scroll` or context.
- `RavenDeliveryException` (502) — channel delivery failure.
- `RavenTemplateNotFoundException` (404) — template file or SendGrid template not found.

### Requirements
- PHP >= 8.1
- Laravel >= 10.0

### Dependencies
- `sendgrid/sendgrid: ~7`
- `aws/aws-sdk-php: ^3.300`
- `phpmailer/phpmailer: ^6.9`
- `vonage/client: ^4.0`
- `twilio/sdk: ^8.11`

---

## [0.x] — Experimental

Pre-release versions (`v0.1.0`–`v0.5.1` in git history). These were experimental iterations
used in internal projects and are not documented individually. See git history for details.
