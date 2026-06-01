# Dynamic Alt Tags

Dynamic Alt Tags is a WordPress plugin for generating, reviewing, and applying AI-suggested alt text to images.

It is currently owner-maintained by Eric Satzman. Pull requests are welcome, but releases and review cycles may be infrequent.

## Open Source

This project is open source under the `MIT` license. See [`LICENSE`](LICENSE) for the full text.

## Features

- Queue-based processing for media library images
- Search tab with live filters, taxonomy filtering, and bulk queue add
- Active Queue review workflow with approve, skip, and generate actions
- Human review before approval when you want it
- Per-image actions directly inside WordPress media workflows
- Safer queue processing with capped manual runs, retry backoff, and provider pause handling
- Support for self-hosted infrastructure
- Optional title, caption, and description sync
- Live metrics with reset tools, including day/week/month/year totals and processed-history charts
- Configurable direct-upload image size and chart color styles
- History bulk `Re-queue` support
- Self-hosted plugin updates

## Getting Started

### Requirements

- WordPress `6.2+`
- PHP `7.4+`
- A reachable Cloudflare Worker endpoint for alt generation
- A Cloudflare account with Workers enabled
- Optional shared secret token between WordPress and the Worker

A free Cloudflare account can be used but a paid plan may be required depending on how many images you process.

### Install the Plugin

1. Copy this repo into `wp-content/plugins/dynamic-alt-tags`.
2. Activate **Dynamic Alt Tags** in WordPress.
3. Go to **Settings > Dynamic Alt Tags**.
4. Configure the Cloudflare Worker URL and optional token.
5. Click **Test Provider Connection**.

### Quick Start

1. Set up and deploy a Cloudflare Worker.
2. Paste the Worker URL into **Settings > Dynamic Alt Tags**.
3. Add a shared token if your Worker requires authentication.
4. Choose URL mode or direct upload mode.
5. Use **Run Backfill** or queue items from the Search tab.
6. Open the **Active Queue** page to review suggested alt text before approving it.

### Cloudflare Setup

The plugin expects a Cloudflare Worker that accepts a JSON `POST` request and returns JSON with `alt_text` or `caption`.

Short version:

1. Create a Worker with `npm create cloudflare@latest`.
2. Add an `[ai]` binding in `wrangler.toml`.
3. Optionally add a `WORKER_TOKEN` secret.
4. Deploy the Worker with `npx wrangler deploy`.
5. Paste the Worker URL into WordPress plugin settings.

For the full setup guide, sample Worker code, request/response contract, and URL mode vs direct upload details, see [`docs/cloudflare-worker.md`](docs/cloudflare-worker.md).

## How to Use

### Queue Workflow

Go to **Media > Dynamic Alt Tags**.

#### Active Queue

- `Generate Alt Text`: generate or refresh a suggestion
- `Approve`: apply the suggestion
- `Skip Image`: move the item to History
- `View Image`: open the source image
- Top actions: `Run Backfill`, `Generate Alt Text For Queued`, `Refresh`
- Bulk actions: `Approve`, `Skip Image`, `Generate Alt Text`
- `Run Backfill` only scans for images with empty alt text and adds them to the queue. It does not call the provider by itself.
- `Generate Alt Text For Queued` processes queued items visible on the current Active Queue page.

#### Search

- Grid-style media browser with live search and filters
- Supports bulk select, shift-click range selection, and `Add to Queue`
- Clicking a thumbnail opens WordPress Attachment Details

#### History

- Shows finalized items (`approved`, `rejected`, `skipped`)
- Supports row and bulk `Re-queue`

## Project Status

This project is usable and actively maintained.

- The maintainer remains the project owner and final decision-maker for scope and releases.
- Pull requests are welcome for added functionality, bug fixes, security fixes, accessibility improvements, tests, and documentation.
- Large feature work should start with an issue or discussion before code is written.
- Response times may be slow during busy periods.

## Development

1. Clone the repository into your local WordPress plugins directory.
2. Run `composer install`.
3. Activate the plugin in a local WordPress site.
4. Configure a Worker URL in plugin settings.
5. Run `composer phpcs` before opening a pull request.

Developer tooling is Composer-based and currently includes PHPCS, WPCS, and PHP compatibility checks.

## Release Build

Run the release script from the plugin root:

```bash
cd /Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags
./build-release.sh
```

The script:

- reads the version from `dynamic-alt-tags.php`
- builds `dynamic-alt-tags-<version>.zip`
- writes the zip to `/Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/plugin-updates/`
- copies the current `info.json` from the config repo into that same `plugin-updates` directory

## Contributing

Pull requests are welcome. For setup, contribution expectations, and testing guidance, see [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Security

Please report security issues privately. See [`SECURITY.md`](SECURITY.md).

## Additional Docs

- [`docs/cloudflare-worker.md`](docs/cloudflare-worker.md)
- [`docs/troubleshooting.md`](docs/troubleshooting.md)
- [`CONTRIBUTING.md`](CONTRIBUTING.md)
- [`SECURITY.md`](SECURITY.md)
- [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md)

## Changelog

### 1.0.8

- Bump version to 1.0.8
- Add separate Dashboard settings to show or hide the processed images chart and detailed processing metrics panel
- Keep those Dashboard panels off by default while leaving the Dashboard summary cards visible

### 1.0.7

- Bump version to 1.0.7
- Add a Direct Upload Image Size setting with Large and Medium options for Cloudflare testing
- Add processed-images history charts to Dashboard and Metrics
- Add selectable chart color styles and expanded day, week, month, and year totals

### 1.0.6

- Bump version to 1.0.6
- Align self-hosted updater package metadata with the 1.0.6 release build
- Remove the Settings Tools page `Process Queue Now` action in favor of queue-page processing workflows
- Add background processing settings with configurable WP-Cron frequency and images-per-run limits, off by default
- Add an optional `Sync Alt Text to Attachment Caption` setting, off by default

### 1.0.5

- Prepare the first stable 1.0.x release of Dynamic Alt Tags
- Refresh documentation for current queue, access, metrics, and self-hosted update workflows
- Align self-hosted updater package metadata with the 1.0.5 release build

### 0.1.6

- Add WordPress 5.8+ `Update URI` support for self-hosted plugin updates
- Keep legacy update transient injection for backward compatibility

### 0.1.5

- Add History bulk re-queue workflow
- Fix History table layout for the new bulk action column
- Count unique attachments in metrics instead of raw processing attempts
- Add Reset Metrics action to the Tools tab
- Add self-hosted updater and native update-page plugin icon support
- Add PNG fallback icon assets

### 0.1.0

- Initial MVP

---

`readme.txt` remains the WordPress.org-compatible readme source.
