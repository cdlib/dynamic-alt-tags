# Contributing

Thanks for your interest in improving Dynamic Alt Tags.

## Before you start

- The project is currently owner-maintained by Eric Satzman.
- Pull requests are welcome, including added functionality, but review and release timing may be limited.
- For larger features or workflow changes, open an issue first so we can align on scope before code is written.

## Local setup

1. Clone the repository into your local WordPress plugins directory.
2. Run `composer install`.
3. Activate the plugin in a local WordPress site.
4. Configure a Cloudflare Worker URL in plugin settings.
5. Run `composer phpcs` before opening a pull request.

## Development guidelines

- Keep changes focused and avoid unrelated refactors.
- Follow existing WordPress coding patterns and naming conventions in the codebase.
- Preserve current behavior unless the change intentionally updates user-facing functionality.
- Include or update tests when practical.
- Update documentation when settings, workflows, or external setup steps change.

## Pull request guidance

- Use a clear title and explain the user-facing outcome.
- Describe how you tested the change.
- Note any admin flows, queue flows, or media modal behavior that may be affected.
- Keep pull requests small enough to review without extensive back-and-forth.

## Good first contribution areas

- Bug fixes
- Accessibility improvements
- Documentation improvements
- Tests and validation coverage
- Small, self-contained feature additions

## Security

Please do not open public GitHub issues for security vulnerabilities. Follow the reporting instructions in [SECURITY.md](SECURITY.md).
