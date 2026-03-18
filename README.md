# Dynamic Alt Tags

Generate and manage AI-suggested alt text for WordPress images using a Cloudflare Worker endpoint.

## Overview
Dynamic Alt Tags provides an admin-first workflow for generating, reviewing, and applying AI image alt text suggestions.

### Highlights
- Queue-based processing for media library images
- Search tab with live filters, grid browsing, and bulk queue add
- Active Queue review workflow with approve, skip, and generate actions
- History bulk `Re-queue` support
- Attachment Details button for one-off alt generation
- Optional title and description sync
- Live metrics with reset tools
- Self-hosted plugin updates with native update-page icon metadata

## Requirements
- WordPress 6.2+
- PHP 7.4+
- Accessible Cloudflare Worker endpoint
- Optional Cloudflare API token

## Installation
1. Upload the plugin to `/wp-content/plugins/dynamic-alt-tags`.
2. Activate it from **Plugins**.
3. Go to **Settings > Dynamic Alt Tags**.
4. Add your Cloudflare Worker URL and token.
5. Click **Test Provider Connection** and save.

## Usage
### Quick Start
1. Open **Settings > Dynamic Alt Tags** and verify provider settings.
2. Process queue items from Settings or **Media > Dynamic Alt Tags**.
3. Review suggestions and approve/skip as needed.
4. Use **Generate Alt Text** in Attachment Details for one-off generation.

### Queue Workflow
Go to **Media > Dynamic Alt Tags**.

#### Active Queue
- **Generate Alt Text**: generate or refresh suggestion
- **Approve**: apply suggested alt text
- **Skip Image**: move item to History
- **View Image**: open source image
- Top actions: **Run Backfill**, **Generate Alt Text For Queued**, **Refresh**
- Bulk actions: **Approve**, **Skip Image**, **Generate Alt Text**

#### Search
- Grid-style media browser for searching and filtering images
- Filters:
  - **All dates**
  - **All Images / No Alt Text Images**
  - **Search images**
  - optional media taxonomy filter
  - optional FileBird folder filter
- **Load More Images** appends additional results
- **Bulk Select** supports shift-click range selection and **Add to Queue**
- Clicking a thumbnail opens WordPress **Attachment Details** and returns to Search on close
- Bulk-added Search results can be focused first in Active Queue

#### History
- Shows finalized items (`approved`, `rejected`, `skipped`) with final alt text and processed timestamp
- Row actions: **Re-queue**, **View Image**
- Bulk action: **Re-queue**

### Attachment Details Workflow
- No queue row: image is queued and processed
- `generated`: suggested alt text is applied
- `processing`: try-again message is shown
- `skipped`, `approved`, `rejected`: automatically requeued and processed

## Access and Permissions
### Settings
- Administrators only

### Queue
- Administrators always have access
- Additional roles can be granted queue access in **Settings > Dynamic Alt Tags > Access**

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

## Metrics Notes
- **Total images processed** now counts unique attachment IDs, not raw processing attempts
- If you want a clean baseline after older event-based metrics, use **Reset Metrics**

## Self-Hosted Updates
- The plugin can check a hosted `info.json` endpoint for updates
- `Dashboard > Updates` can display a custom plugin icon through native WordPress update metadata
- Fallback icon assets are included for `svg`, `1x`, and `2x` update icon slots

## Troubleshooting
### Provider Test Fails
- Verify Worker URL/token
- Verify endpoint reachability from WordPress host
- Re-run **Test Provider Connection**

### Queue Does Not Process
- Check if items are already `generated` and waiting for review
- Verify provider/network availability
- Try processing a single row first

### URL Mode Issues on Local/Private Sites
- Worker may not reach local/private URLs
- Disable URL mode and use direct upload mode

## Changelog
### 0.1.6
- Add WordPress 5.8+ `Update URI` support for self-hosted plugin updates
- Keep legacy update transient injection for backward compatibility

### 0.1.5
- Add History bulk re-queue workflow
- Fix History table layout for the new bulk action column
- Count unique attachments in metrics instead of raw processing events
- Add Reset Metrics action to the Tools tab
- Add self-hosted updater and native update-page plugin icon support
- Add PNG fallback icon assets

### 0.1.0
- Initial MVP

---

`readme.txt` remains the WordPress.org-compatible readme source.
