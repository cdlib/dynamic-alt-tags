# Cloudflare Worker Setup

Dynamic Alt Tags expects a Cloudflare Worker that accepts a JSON `POST` request and returns JSON with `alt_text` or `caption`.

## Requirements

- A Cloudflare account with Workers enabled
- Node.js
- Wrangler
- Optional `WORKER_TOKEN` secret for shared authentication with WordPress

A free Cloudflare account can be used but a paid plan may be required depending on how many images you process.

## 1. Create a Worker

Cloudflare's current guidance is to create a Worker project with Wrangler and add a Workers AI binding.

```bash
npm create cloudflare@latest dynamic-alt-tags-worker
```

Choose a basic Worker template, then move into the project directory.

## 2. Add a Workers AI Binding

Update `wrangler.toml`:

```toml
name = "dynamic-alt-tags-worker"
main = "src/index.js"
compatibility_date = "2026-03-24"

[ai]
binding = "AI"
```

## 3. Add an Optional Shared Secret

If you want the WordPress plugin to authenticate to your Worker, create a secret:

```bash
npx wrangler secret put WORKER_TOKEN
```

Use the same value later in **Settings > Dynamic Alt Tags > Cloudflare API Token**.

## 4. Add the Worker Code

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

## 5. Deploy the Worker

```bash
npx wrangler deploy
```

Copy the deployed Worker URL into **Settings > Dynamic Alt Tags > Cloudflare Worker URL**.

## 6. Configure the Plugin

In WordPress:

1. Open **Settings > Dynamic Alt Tags**.
2. Paste the Worker URL.
3. If you set `WORKER_TOKEN`, paste the same value into **Cloudflare API Token**.
4. Decide whether to use URL mode or direct upload mode.
5. Click **Test Provider Connection**.

## URL Mode vs Direct Upload Mode

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
