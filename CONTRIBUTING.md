# Contributing

Thank you for considering a contribution to Raven!

## Getting Started

```bash
git clone https://github.com/chijioke-ibekwe/raven.git
cd raven
composer install
```

## Running the Test Suite

```bash
composer test        # PHPUnit tests
composer pint        # Fix code style
composer stan        # Static analysis (PHPStan level 5)
```

All three must pass before a pull request can be merged.

## Pull Request Guidelines

1. **One change per PR.** Bug fixes and new features should be submitted as separate pull requests.
2. **Write tests.** Every non-trivial change should include corresponding tests.
3. **Follow existing code style.** The project uses [Laravel Pint](https://laravel.com/docs/pint) with the `laravel` preset. Run `composer pint` before committing.
4. **Update `CHANGELOG.md`.** Add your change under the `[Unreleased]` section following the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format.
5. **Target the `main` branch.**

## Reporting Bugs

Open an issue on [GitHub](https://github.com/chijioke-ibekwe/raven/issues) with a clear description of the problem, steps to reproduce, and the PHP/Laravel versions you are using.
