# Contributing to Midway Weather

Thanks for helping make household weather displays more useful. This is a personal, self-hosted project; focused contributions that fit the existing Laravel/SQLite application are easiest to review.

## Report a problem

Open a [GitHub issue](https://github.com/thenumber3/midway-weather/issues) with:

- What you expected, what happened, and steps to reproduce.
- Your application revision, PHP version, browser/device, and viewport where relevant.
- Whether you used demo data or a real station, plus the station model and affected reading if useful.
- A small, sanitized log excerpt or screenshot when it helps.

Remove API keys, passwords/hashes, application keys, device identifiers, precise home coordinates, and personal information. Do not attach `.env`, SQLite databases, full provider payloads, or unfiltered diagnostics.

For a suspected security vulnerability, avoid public issue details. Use GitHub's private vulnerability reporting if available; otherwise ask the maintainer for a private reporting channel without disclosing the vulnerability.

## Suggest a feature

Describe the household/display problem, the outcome you want, and a concrete example. Check [the roadmap](docs/roadmap.md) and existing issues first. Roadmap ideas are not commitments. Discuss large features, new providers, dependencies, or architectural changes before implementing them.

## Submit a pull request

1. Fork the repository and create a focused branch from `main`.
2. Follow the [local setup guide](README.md#try-it-locally) using a disposable database and demo data. Create `.env` and the SQLite file before `composer install`.
3. Reuse the existing presenter, settings registry, tile partials, and provider patterns. Include a targeted test when changing behavior; update relevant documentation.
4. Run `npm ci`, `bash scripts/test.sh`, and `./vendor/bin/pint --test` for application changes. The [operations guide](docs/operations.md#developer-validation) explains the separately gated inherited sparkline test. Documentation-only changes need link and example validation.
5. For UI changes, inspect desktop and mobile rendering and include sanitized screenshots. Keep weather/map attribution visible.
6. Open the PR against `main`, describing the problem, resulting behavior, validation, and any limitations. Keep runtime data, secrets, generated dependencies, and unrelated changes out of the diff.

Production deployment is maintainer-managed. Contributors do not need Midway infrastructure access or production credentials. Contributions are provided under the project's [MIT license](LICENSE); third-party materials retain their own licenses and rights.
