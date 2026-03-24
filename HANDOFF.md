# Dynamic Alt Tags - Project Handoff (Current)

## 1) Project Snapshot
Dynamic Alt Tags is a WordPress plugin that generates image alt text through a Cloudflare Worker, supports queue/history review workflows, exposes per-image generation from Media Attachment Details, and now supports self-hosted plugin updates with native WordPress update icon metadata.

Plugin path:
- `/Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags`

Repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`
- Plugin directory is the git root.

Current plugin code version:
- `1.0.1`

Current git state at handoff update:
- `main` is the working branch.
- Hand this off by first re-running `git status -sb` because the exact cleanliness depends on whether newer local work has happened after this file was updated.

Latest code commit before this handoff refresh:
- `e296faf` - Document security follow-ups and harden updater URL trust

Recent commits (newest first):
1. `e296faf` Document security follow-ups and harden updater URL trust
2. `c3d2631` Show category hierarchy in search filter
3. `893688f` Keep uploaded images visible in active queue
4. `5f3e504` Modernize updater and preserve queue review flow
5. `f6d4461` Bump version to 0.1.5
6. `45a92c0` Refresh readme and handoff docs
7. `14888fc` Add history bulk requeue support
8. `7684fed` Add plugin icon fallback assets
9. `b570cda` Use native update icon metadata
10. `c75f167` Count unique attachments in metrics

## 2) Menus and Navigation
### Settings menu
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-settings`
- Page title (H1): `Dynamic Alt Tags Settings`

### Media menu
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-queue`
- Queue page title (H1): `Dynamic Alt Tags`

## 3) Access and Security Model
Access is split by page type.

### Settings page access
- Administrator only.
- Menu capability explicitly requires `manage_options`.

### Queue page access
- Administrator always has access.
- Additional roles can be granted queue access via the Access tab role checkboxes.
- Queue menu capability uses plugin capability `ai_alt_manage_queue`, mapped dynamically through settings.

### Additional permission hardening
- Queue/media actions enforce per-attachment `edit_post` capability checks before modifying attachment metadata, title, or description.
- Attachment upload-action AJAX validates capability and nonce.
- Upload-action debug logs are metadata-only; alt/custom text values are redacted.

### Main enforcement points
- `includes/class-admin.php`
- `includes/class-plugin.php`
- `includes/class-processor.php`
- `WPAI_Alt_Text_Settings::current_user_can_access_settings()`
- `WPAI_Alt_Text_Settings::current_user_can_access_queue()`
- `WPAI_Alt_Text_Settings::current_user_is_administrator()`

## 4) Settings Page - Current Behavior
Settings are rendered by `includes/class-settings.php` and `admin/views-page-settings.php`.

### Tabs
- `Settings`
- `Tools`
- `Access`
- `Metrics`

### Current settings fields
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
- Roles Allowed To Access Dynamic Alt Tags

### Defaults
- Checkbox settings default to unchecked except `Require Manual Review for Queue Items`, which defaults to checked.

### Metrics behavior
- Shows:
  - Images on site
  - Images with alt tags
  - Images without alt tags
  - Total images processed
  - Success count
  - Failure count
  - Average/last processing time
  - Average/last provider latency
  - Last processed at
- Metrics refresh via AJAX every 15 seconds and when the Metrics tab becomes visible.
- `Total images processed` now counts unique attachment IDs, not raw processing attempts.
- If older metrics existed before the unique-counting change, use `Reset Metrics` to establish a clean baseline.

### Tools tab behavior
- `Run Backfill`
- `Process Queue Now`
- `Test Provider Connection`
- `Delete History`
- `Reset Metrics`
- Provider connection status panel is notice-driven.

### Redirect behavior
- Tool actions redirect back to `options-general.php?page=ai-alt-text-settings`.
- Metrics reset now preserves tab context, so reset from Tools returns to Tools and reset from Metrics returns to Metrics.

## 5) Queue Page - Current Behavior
Queue page template: `admin/views-page-queue.php`

### Views/Tabs
- Dashboard
- Active Queue
- Search
- History

Default queue view from the left menu:
- `Dashboard`

### Active Queue
Columns:
- Select
- Image
- Status
- Confidence
- Suggested Alt Text
- Actions

Top actions:
- `Run Backfill`
- `Generate Alt Text For Queued`
- `Refresh`

Bulk actions:
- `Approve`
- `Skip Image`
- `Generate Alt Text`

Behavior:
- `Generate Alt Text For Queued` always appears on Active Queue.
- The button is disabled unless at least one visible row is actually `queued`.
- It only bulk-processes rows still in `queued` state, not failed/generated rows.

### History
Columns:
- Select
- Image
- Status
- Alt Text
- Processed On
- Actions

Row actions:
- `Re-queue`
- `View Image`

Bulk actions:
- `Re-queue`

Notes:
- History layout/CSS was updated to support the new checkbox column without pushing the Actions column off-screen.

### Search
- Grid-only media-style image browser.
- Filters always include:
  - `All dates`
  - `All Images / No Alt Text Images`
  - `Search images`
- Optional filters appear only when available:
  - configured attachment taxonomy / detected `media_category`
  - FileBird Lite folder filter
- Category dropdown labels now indicate parent/child hierarchy using prefixed indentation.
- Search input is live/debounced.
- Search clear button has explicit focus styling and larger target size.
- `Load More Images` appends results via AJAX.
- Bulk Select mode supports:
  - corner checkmark state
  - dimming non-selected cards
  - shift-click range selection
  - `Add to Queue`
- Clicking a thumbnail opens WordPress Attachment Details and returns to Search after modal close.

### Focused Active Queue behavior
- After bulk add from Search, Active Queue can focus on the newly queued items first.
- Focused queue state is only applied when `queued_ids` is present in the URL; stale client-side focused state is cleared instead of auto-restoring on unrelated queue visits.
- Clicking `View more images` clears the focused subset and reveals the broader queue list.

### Queue Dashboard
- Uses the same metrics dataset as the Settings Metrics tab.
- Does not include `Reset Metrics`.

### Progress UI
- Queue-level progress bar appears below the total count and above bulk actions.
- Used for:
  - `Generate Alt Text For Queued`
  - bulk `Generate Alt Text`

## 6) Attachment Details (Media Modal) - Current Behavior
Implemented in `includes/class-plugin.php` and `assets/admin.js`.

### UI
- Single `Generate Alt Text` button in the plugin field area.

### Retrieval flow
- If no queue row exists: enqueue + process.
- If status is `generated`: apply suggested alt directly.
- If status is `processing`: show try-again message.
- If status is `skipped`, `approved`, or `rejected`: auto-requeue + process.

### Reliability fixes
- Updates both visible DOM fields and `wp.media` model state.
- Reapplies after short delays to survive async sidebar re-renders.
- Prevents false “Unable to apply upload action” messages after successful apply.

### Sync behavior
- Title sync follows `sync_title_from_alt`.
- Description sync follows `sync_description_from_alt`.
- The plugin syncs attachment description (`post_content`), not WordPress caption (`post_excerpt`).

## 7) Update System / Icons
Self-hosted updater is implemented in `includes/class-updater.php`.

### Update endpoints
- Info JSON: `https://satzman.com/plugin-updates/dynamic-alt-tags/info.json`
- Package fallback URL constant: `https://satzman.com/plugin-updates/dynamic-alt-tags/dynamic-alt-tags-1.0.1.zip`

### Updater behavior
- Hooks:
  - `update_plugins_satzman.com`
  - `pre_set_site_transient_update_plugins`
  - `plugins_api`
- Main plugin header now includes `Update URI: https://satzman.com/plugin-updates/dynamic-alt-tags/`.
- Native update metadata includes icon URLs so WordPress core can render the plugin icon on `Dashboard > Updates`.
- Remote update JSON `download_url` is now allowlisted to `https://satzman.com/plugin-updates/dynamic-alt-tags/...`; unexpected hosts/paths fall back to the package URL constant.
- No Installed Plugins-page icon hack remains; that was intentionally removed.

### Icon assets
- `assets/plugin-icon.svg`
- `assets/plugin-icon-128.png`
- `assets/plugin-icon-256.png`

## 8) Provider / Runtime Notes
- Cloudflare Worker URL historically used: `https://alt-text-generator.webprod.workers.dev/`
- URL mode can fail for local/private URLs such as `sandbox.local`
- Direct upload mode is the safer default on local/private sites
- Provider timeout is currently 90 seconds
- SVG behavior:
  - Search/backfill/manual queue-add excludes SVGs
  - Processor skips SVG rows cleanly as `skipped`
  - Attachment-level generate returns a friendly unsupported message
  - Tools > Test Provider Connection uses the latest non-SVG queued image when available

## 9) Cron / Scheduling
- Queue processing runs on `five_minutes`.
- Existing non-`five_minutes` schedules are migrated on init.

## 10) Documentation Status
- `readme.txt` is the WordPress-compatible readme source.
- `README.md` is the styled Markdown overview.
- `HANDOFF.md` is this internal continuity document.

## 11) Key Files
- `dynamic-alt-tags.php`
- `includes/class-plugin.php`
- `includes/class-updater.php`
- `includes/class-admin.php`
- `includes/class-settings.php`
- `includes/class-processor.php`
- `includes/class-queue-repo.php`
- `includes/class-alt-generator.php`
- `admin/views-page-settings.php`
- `admin/views-page-queue.php`
- `assets/admin.js`
- `assets/admin.css`
- `assets/plugin-icon.svg`
- `assets/plugin-icon-128.png`
- `assets/plugin-icon-256.png`
- `readme.txt`
- `README.md`

## 12) Known Constraints / Open Risks
1. Search uses a custom media-style grid rather than WordPress core media grid internals.
2. Provider availability/latency remains an external dependency risk.
3. Queue access is role-list based and mapped through `user_has_cap`.
4. Media modal behavior depends on WordPress media-frame internals and can still be sensitive to admin/plugin conflicts.
5. FileBird integration depends on FileBird Lite table structure rather than WordPress taxonomy APIs.
6. Existing installs still need an updater-enabled build installed once before future self-hosted updates can appear automatically.
7. Queue/search/history read access is still broader than per-attachment `edit_post`; non-admin queue-enabled users can currently see media rows they may not be able to edit.
8. Updater package URLs are now host/path allowlisted, but there is still no checksum/signature verification of remote metadata or ZIPs.

## 13) Suggested Next Steps
1. Add integration tests for:
   - role/capability access split
   - queue progress workflows
   - media grid/right-sidebar apply reliability
   - History bulk re-queue behavior
2. Add automated accessibility checks for settings tabs, queue controls, and stacked table views.
3. Add provider health visibility in admin (status plus recent failure trend).
4. Keep hosted `info.json` / package URLs aligned with future version bumps.
5. Security follow-up for updater hardening (P1):
   - keep package downloads restricted to the expected `satzman.com` host/path
   - add checksum and signature fields to hosted metadata for release verification
6. Security follow-up for queue visibility (P2):
   - enforce per-attachment read filtering for non-admin queue users across Search, Active Queue, History, and related AJAX endpoints
   - prefer SQL-level filtering where practical, with PHP fallback if exact capability mapping is too expensive

## 14) Resume Checklist For A New Codex Window
1. Confirm status:
- `git -C /Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags status -sb`

2. Validate coding standards:
- `composer -d /Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

3. Validate core WP-admin flows:
- Settings page is admin-only.
- Queue page is visible for admin plus selected roles.
- Metrics live refresh updates on Settings and Queue Dashboard.
- Tools tab `Reset Metrics` works and returns to the Tools tab.
- Metrics tab `Reset Metrics` works and returns to the Metrics tab.
- Active Queue `Generate Alt Text For Queued` stays disabled unless queued rows are visible.
- Active Queue focused queue state only appears when `queued_ids` is present in the URL, and unrelated queue visits clear stale focused state.
- Search bulk-select + shift-click + Add to Queue still works.
- `View more images` clears focused queue state and reveals older rows.
- History page row `Re-queue` still works.
- History bulk `Re-queue` works and the History layout remains aligned.
- Attachment Details generate/apply still works in list view and media modal sidebar.
- Update metadata still appears correctly when a hosted update is available, including the custom update-page icon.

4. If bugs appear:
- Access issues: start in `includes/class-settings.php`, then `includes/class-admin.php`
- Media modal sync issues: inspect `assets/admin.js`
- Update icon/update availability issues: inspect `includes/class-updater.php` plus hosted `info.json`
- History layout issues: inspect `admin/views-page-queue.php` and `assets/admin.css`
- Upload-to-queue visibility issues: inspect `includes/class-plugin.php` (`maybe_queue_attachment`) and `assets/admin.js` focused-queue persistence
