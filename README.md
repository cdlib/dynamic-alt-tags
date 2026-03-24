# Dynamic Alt Tags

Dynamic Alt Tags is a WordPress plugin for generating, reviewing, and applying AI-suggested alt text through a Cloudflare Worker.

It is currently owner-maintained by [Eric Satzman](https://github.com/ericsatzman). Pull requests are welcome, but releases and review cycles may be infrequent.

## Why this project exists

The goal is to automate the process of generating and managing AI-suggested alt text for WordPress images.

That means:

- queue-based generation instead of one-off prompts only
- human review before approval when you want it
- per-image actions directly inside WordPress media workflows
- support for self-hosted infrastructure instead of a locked SaaS backend

## Highlights

- Queue-based processing for media library images
- Search tab with live filters, taxonomy filtering, and bulk queue add
- Active Queue review workflow with approve, skip, and generate actions
- History bulk `Re-queue` support
- Attachment Details button for one-off generation
- Optional title and description sync
- Live metrics with reset tools
- Self-hosted plugin updates with native WordPress update icon metadata

## Project Status

This project is usable and actively maintained.

- The maintainer remains the project owner and final decision-maker for scope and releases.
- Pull requests are welcome for added functionality, bug fixes, security fixes, accessibility improvements, tests, and documentation.
- Large feature work should start with an issue or discussion before code is written.
- Response times may be slow during busy periods.

## Requirements

- WordPress `6.2+`
- PHP `7.4+`
- A reachable Cloudflare Worker endpoint for alt generation
- A Cloudflare account with Workers enabled
- Optional shared secret token between WordPress and the Worker

## Install the Plugin

1. Copy this repo into `wp-content/plugins/dynamic-alt-tags`.
2. Activate **Dynamic Alt Tags** in WordPress.
3. Go to **Settings > Dynamic Alt Tags**.
4. Configure the Cloudflare Worker URL and optional token.
5. Click **Test Provider Connection**.

## Local Development Setup

1. Clone the repository into your local WordPress plugins directory.
2. Run `composer install`.
3. Activate the plugin in a local WordPress site.
4. Configure a Worker URL in plugin settings.
5. Run `composer phpcs` before opening a pull request.

## Cloudflare Setup

The plugin expects a Cloudflare Worker that accepts a JSON `POST` request and returns JSON with `alt_text` or `caption`.

### 1. Create a Cloudflare Worker

Cloudflare's current guidance is to create a Worker project and deploy it with Wrangler, then add an AI binding so the Worker can call Workers AI.

1. Sign in to Cloudflare and create an account if needed.
2. Install Node.js.
3. Create a Worker project:

```bash
npm create cloudflare@latest dynamic-alt-tags-worker
```

4. Choose a basic Worker template.
5. Move into the new project directory.

### 2. Add a Workers AI binding

Add an AI binding to `wrangler.toml`:

```toml
name = "dynamic-alt-tags-worker"
main = "src/index.js"
compatibility_date = "2026-03-24"

[ai]
binding = "AI"
```

### 3. Add an optional shared secret

If you want the WordPress plugin to authenticate to your Worker, create a secret:

```bash
npx wrangler secret put WORKER_TOKEN
```

Use the same value later in **Settings > Dynamic Alt Tags > Cloudflare API Token**.

### 4. Add the Worker code

Create `src/index.js` with a minimal Worker that supports both URL mode and direct upload mode:

```js
function json(data, init = {}) {
  return new Response(JSON.stringify(data), {
    ...init,
    headers: {
      "content-type": "application/json; charset=utf-8",
      ...(init.headers || {}),
    },
  });
}

function unauthorized() {
  return json({ error: "Unauthorized" }, { status: 401 });
}

function badRequest(message) {
  return json({ error: message }, { status: 400 });
}

async function buildImageDataUrl(payload) {
  if (payload.image_data_base64 && payload.image_mime_type) {
    return `data:${payload.image_mime_type};base64,${payload.image_data_base64}`;
  }

  if (payload.image_url) {
    const response = await fetch(payload.image_url);
    if (!response.ok) {
      throw new Error(`Unable to fetch image URL (${response.status})`);
    }

    const contentType = response.headers.get("content-type") || "image/jpeg";
    const bytes = new Uint8Array(await response.arrayBuffer());
    let binary = "";
    for (const byte of bytes) {
      binary += String.fromCharCode(byte);
    }

    return `data:${contentType};base64,${btoa(binary)}`;
  }

  throw new Error("Missing image input");
}

function extractText(result) {
  if (!result) {
    return "";
  }

  if (typeof result.response === "string") {
    return result.response.trim();
  }

  if (Array.isArray(result.response)) {
    return result.response.join(" ").trim();
  }

  if (typeof result.result === "string") {
    return result.result.trim();
  }

  return "";
}

export default {
  async fetch(request, env) {
    if (request.method !== "POST") {
      return badRequest("POST required");
    }

    if (env.WORKER_TOKEN) {
      const header = request.headers.get("authorization") || "";
      const token = header.startsWith("Bearer ") ? header.slice(7) : "";
      if (token !== env.WORKER_TOKEN) {
        return unauthorized();
      }
    }

    const payload = await request.json();

    try {
      const image = await buildImageDataUrl(payload);
      const prompt = [
        "Write concise, accurate alt text for this image.",
        "Do not start with 'image of' or 'photo of'.",
        "Do not guess details that are not visible.",
        "Return only the alt text and keep it under 18 words."
      ].join(" ");

      const result = await env.AI.run("@cf/meta/llama-4-scout-17b-16e-instruct", {
        messages: [
          {
            role: "user",
            content: [
              { type: "text", text: prompt },
              { type: "image_url", image_url: { url: image } },
            ],
          },
        ],
        max_tokens: 120,
        temperature: 0.2,
      });

      const altText = extractText(result);
      if (!altText) {
        return json({ error: "Model returned an empty response" }, { status: 502 });
      }

      return json({
        alt_text: altText,
        confidence: 0.9,
      });
    } catch (error) {
      return json(
        { error: error instanceof Error ? error.message : "Worker error" },
        { status: 500 }
      );
    }
  },
};
```

### 5. Deploy the Worker

```bash
npx wrangler deploy
```

Copy the deployed Worker URL into **Settings > Dynamic Alt Tags > Cloudflare Worker URL**.

### 6. Configure the plugin

In WordPress:

1. Open **Settings > Dynamic Alt Tags**.
2. Paste the Worker URL.
3. If you set `WORKER_TOKEN`, paste the same value into **Cloudflare API Token**.
4. Decide whether to use URL mode or direct upload mode.
5. Click **Test Provider Connection**.

### URL mode vs direct upload mode

- URL mode sends `image_url` to the Worker.
- Direct upload mode sends inline image data (`image_data_base64`, MIME type, and filename).
- URL mode is usually simpler on public sites.
- Direct upload mode is safer for local or private WordPress environments because the Worker does not need to fetch a private URL.

## Worker Request Contract

The plugin sends a JSON `POST` body with:

- `image_url`
- `context.attachment_title`
- `context.post_title`
- `rules.concise`
- `rules.no_guessing`
- `rules.max_words`
- `rules.no_image_of`
- `rules.alt_text_mode`

In direct upload mode it may also send:

- `image_source`
- `image_data_base64`
- `image_mime_type`
- `image_filename`

The Worker should return JSON with:

- `alt_text` or `caption`
- optional `confidence` from `0` to `1`

## WordPress Workflow

### Queue Workflow

Go to **Media > Dynamic Alt Tags**.

#### Active Queue

- `Generate Alt Text`: generate or refresh a suggestion
- `Approve`: apply the suggestion
- `Skip Image`: move the item to History
- `View Image`: open the source image
- Top actions: `Run Backfill`, `Generate Alt Text For Queued`, `Refresh`
- Bulk actions: `Approve`, `Skip Image`, `Generate Alt Text`

#### Search

- Grid-style media browser with live search and filters
- Supports bulk select, shift-click range selection, and `Add to Queue`
- Clicking a thumbnail opens WordPress Attachment Details

#### History

- Shows finalized items (`approved`, `rejected`, `skipped`)
- Supports row and bulk `Re-queue`

### Attachment Details Workflow

- No queue row: image is queued and processed
- `generated`: suggested alt text is applied
- `processing`: try-again message is shown
- `skipped`, `approved`, `rejected`: automatically requeued and processed

## Access and Permissions

- Settings access is administrator-only
- Queue access is available to administrators and optionally selected roles
- Attachment write actions enforce per-item capability checks

## Settings Reference

- Cloudflare Worker URL
- Cloudflare API Token
- Batch Size
- Min Confidence
- Use URL Mode - Send Image URL
- Auto-Approve New Uploads
- Sync Alt Text to Attachment Title
- Sync Alt Text to Attachment Description
- Search Media Taxonomy
- Overwrite Existing Alt Text
- Require Manual Review for Queue Items
- Keep Data On Delete

## Contributing

If you want to contribute:

1. Open an issue for major changes before implementation.
2. Keep changes scoped and backwards-compatible where possible.
3. Match WordPress coding standards.
4. Include tests or clear manual test notes when behavior changes.
5. Preserve accessibility, capability checks, and nonce coverage.

High-value contribution areas:

- security hardening
- accessibility audits and fixes
- integration tests for queue and media workflows
- provider observability and error reporting
- documentation improvements

## Security

Please report security issues privately instead of opening a public issue. Until a dedicated security policy is added, contact the maintainer through the repository owner profile.

## Recommended Open-Source Setup

If you plan to keep this repo public with low-maintenance stewardship, the highest-value next steps are:

1. Add a `LICENSE` file that matches the plugin header and `readme.txt`.
2. Add `CONTRIBUTING.md` with scope, test expectations, and review expectations.
3. Add `SECURITY.md` with a private reporting path.
4. Add issue and pull request templates in `.github/`.
5. Publish a minimal Worker example repo or `examples/` directory so users do not have to build their own Worker from scratch.
6. Document your support and release cadence clearly so expectations stay healthy.

## Troubleshooting

### Provider test fails

- Verify the Worker URL and token
- Verify the WordPress host can reach the Worker
- Re-run `Test Provider Connection`

### Queue does not process

- Check whether items are already `generated` and waiting for review
- Verify provider/network availability
- Try processing a single row first

### URL mode fails on local/private sites

- The Worker may not be able to fetch private media URLs
- Disable URL mode and use direct upload mode

## Changelog

### 1.0.1

- Prepare the first stable 1.0.x release of Dynamic Alt Tags
- Refresh documentation for current queue, access, metrics, and self-hosted update workflows
- Align self-hosted updater package metadata with the 1.0.1 release build

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
