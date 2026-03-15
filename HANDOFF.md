# Dynamic Alt Tags - Project Handoff (Current)

## 1) Project Snapshot
Dynamic Alt Tags is a WordPress plugin that generates image alt text through a Cloudflare Worker, supports queue/history review workflows, and allows per-image generation from Media Attachment Details.

Plugin path:
- `/Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags`

Repository:
- `https://github.com/ericsatzman/dynamic-alt-tags.git`
- Branch: `main`
- Plugin directory is the git root.

Current git state:
- Working tree clean after latest push.
- `main` synced with `origin/main`.

Latest commit:
- `57b5458` - Add generate-all action for active queue

Recent commits (newest first):
1. `57b5458` Add generate-all action for active queue
2. `ccb71da` Sanitize focused queue request params
3. `efac47c` Add bulk queue selection to search
4. `a12def9` Add attachment description sync option
5. `a3fbde6` Add FileBird search filter support
6. `18ca39a` Add configurable media taxonomy for search
7. `c38059e` Add media category filter to queue search
8. `9198431` Set first-install checkbox defaults for settings
9. `2d3fa3f` Respect title sync setting in attachment details generation
10. `3a5dd2e` Clarify review and auto-approve settings labels

## 2) Current Menus and Navigation
### Settings menu (left admin)
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-settings`
- Page title (H1): `Dynamic Alt Tags Settings`

### Media menu (left admin)
- Menu label: `Dynamic Alt Tags`
- Page slug: `ai-alt-text-queue`
- Queue page title (H1): `Dynamic Alt Tags`

## 3) Access and Security Model
Access split by page type:

### Settings page access
- Administrator only.
- Menu capability now explicitly requires `manage_options`.

### Queue page access (Media > Dynamic Alt Tags)
- Administrator always has access.
- Additional roles can be granted queue access via Access tab role checkboxes.
- Queue menu capability now uses dedicated plugin capability `ai_alt_manage_queue`, dynamically mapped via role settings.

### Additional permission hardening
- Queue/media actions enforce per-attachment capability checks (`edit_post`) before modifying attachment metadata/title.
- Attachment upload-action AJAX validates capability + nonce.
- Upload-action debug logs are now metadata-only; alt text/custom text fields are explicitly redacted.

### Where this is enforced
- Menu visibility and admin-post/wp-ajax handlers: `includes/class-admin.php`
- Attachment Details retrieve controls and upload-action AJAX: `includes/class-plugin.php`
- Queue processing/approval guards: `includes/class-processor.php`
- Access methods:
  - `WPAI_Alt_Text_Settings::current_user_can_access_settings()`
  - `WPAI_Alt_Text_Settings::current_user_can_access_queue()`
  - `WPAI_Alt_Text_Settings::current_user_is_administrator()`

## 4) Settings Page - Current Behavior
Settings are rendered by `includes/class-settings.php` and `admin/views-page-settings.php`.

### Settings page tabs
- `Settings`
- `Tools`
- `Access`
- `Metrics` (tab key remains `metrics`)

### Current fields
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

### First-install checkbox defaults
- All checkbox settings default to unchecked except `Require Manual Review for Queue Items`, which defaults to checked.

### Token behavior
- If Cloudflare API Token field is saved empty, stored token is cleared.

### Access tab behavior
- Roles are sorted alphabetically by display name.
- Administrator is enforced as always selected (backend sanitize enforcement + UI lock/recheck behavior).
- Description clarifies:
  - Administrator always full access
  - Selected roles get queue page access under Media

### Metrics tab behavior
- Shows:
  - Images on site
  - Images with alt tags
  - Images without alt tags
  - Total images processed
  - Success/failure counts
  - Avg/last processing time
  - Avg/last provider latency
  - Last processed at
- Includes `Reset Metrics` action.
- Metrics update live via AJAX every 15s (and on tab open/visibility return), no reload required.
- Default tab when opening from left menu is `Settings` (notice-based redirects can still open other tabs).

### Tools tab behavior
- `Run Backfill`
- `Process Queue Now`
- `Test Provider Connection`
- Provider connection status panel (notice-based).

### Redirect behavior fixes
- Settings-page actions now redirect to `options-general.php?page=ai-alt-text-settings` (avoids "Sorry, you are not allowed to access this page" after tools actions).

## 5) Queue Page - Current Behavior
Queue page template: `admin/views-page-queue.php`

### Views/Tabs
- Dashboard
- Active Queue
- Search
- History

Default queue view from left Media menu:
- `Dashboard`

### Active Queue columns
- Image
- Status
- Confidence
- Suggested Alt Text
- Actions

### History columns
- Image
- Status
- Alt Text
- Processed On (uses WP General Settings date/time format)
- Actions

### Search tab behavior
- Grid-only media-style image browser.
- Filters always include: `All dates` + `All Images / No Alt Text Images` + `Search images`.
- Optional filters appear only when available:
  - selected attachment taxonomy terms (`All Categories`) based on `Search Media Taxonomy` settings field or auto-detected `media_category`
  - FileBird Lite folder filter (`All FileBird Folders`) when FileBird virtual folders/tables exist
- Search input is live/debounced; dropdown changes auto-refresh results.
- Search input includes an inline clear (`X`) button.
- Supports paginated `Load More Images` via AJAX.
- Includes `Bulk Select` mode with WordPress-media-library-style selection UI:
  - selected thumbnails show a corner check
  - non-selected thumbnails dim while bulk mode is active
  - shift-click range selection is supported across visible search results
  - selected images can be sent to queue via `Add to Queue`
- Clicking a thumbnail opens WordPress Attachment Details (media modal).
- Closing the modal returns to the plugin Search tab with current filters.

### Actions and labels
- Active row actions: `Approve`, `Skip Image`, `Generate Alt Text`, `View Image`
- History row actions: `Re-queue`, `View Image`
- Top actions: `Run Backfill`, `Generate Alt Text For All`, `Refresh`

Queue Dashboard behavior:
- Uses dashboard metrics cards/table (same data set as settings metrics panel).
- Does **not** include a `Reset Metrics` button.

### Bulk actions
- Order now: `Approve`, `Skip Image`, `Generate Alt Text`
- `Reject` is not in bulk dropdown.
- `Generate Alt Text For All` reuses the existing bulk-process flow and progress bar by auto-selecting visible `queued`/`failed` rows.

### Suggested Alt Text field behavior
- Rendered as autosizing textarea in Active Queue rows so full suggestions are visible.
- Autosize applies to initial page load and AJAX-loaded/updated rows.

### Progress UI
- Queue-level progress bar appears below “Total queue items” and above bulk actions.
- Used for:
  - Top `Generate Alt Text For All` processing
  - Bulk `Generate Alt Text` processing
- Inline top progress error text suppressed; errors surface in top alert notices.

### Active Queue focused-view behavior
- When items are bulk-added from Search, the Active Queue initially shows only the newly queued items.
- Older queue items are hidden from the initial page and appear only after the user clicks `View more images`.
- Focused Active Queue state is carried via `queued_ids` request parameter and excluded correctly from older-row pagination.

### Refresh behavior
- `Refresh` uses clean queue URL args (page/view/status/paged) to avoid stale alerts.

### Thumbnail behavior
- Search-grid thumbnails open Attachment Details (media modal).

### Responsiveness
- Responsive breakpoints in `assets/admin.css`:
  - `max-width: 1024px` (tablet and down)
  - `max-width: 782px` (mobile/tablet WP-admin collapse range)
  - `max-width: 600px` (small phones)
  - `max-width: 390px` (very small phones)
  - `max-width: 932px` + `max-height: 430px` + landscape (short-height landscape phones)
- Active Queue / History / No Alt queue tables now convert to stacked card rows on phones (`max-width: 782px`) with per-field labels.
- In mobile stacked view, Suggested Alt Text textareas are forced full-width.
- Action buttons are stacked vertically and full-width on phones and stacked in the Action column on tablets.

### Accessibility updates (WCAG 2.2 AA-focused)
- Search summary now uses status semantics (`role="status"`, `aria-live="polite"`, `aria-atomic="true"`).
- Search clear button target size increased to 24x24.
- Search clear button now has explicit visible focus styling.
- Mobile stacked queue rows include explicit per-cell field labels (`data-label`) to preserve context when headers are visually hidden.

## 6) Admin UI Motif (New)
### Settings motif
- Scoped to settings page wrapper class: `ai-alt-settings-page`
- Slate/nav style tabs + light content cards + updated form controls and buttons.
- Dashboard cards and metrics table styled consistently within motif.

### Queue motif
- Scoped to queue page wrapper class: `ai-alt-queue-page`
- Unified queue header bar (`ai-alt-queue-header-bar`) containing tabs and top actions.
- Tab strip and action buttons harmonized in same motif language.
- Approve buttons on queue rows use the same dark blue/slate family as the queue tab background.

## 7) Attachment Details (Media Modal) - Current Behavior
Implemented in `includes/class-plugin.php` and `assets/admin.js`.

### UI
- Single `Generate Alt Text` button shown in plugin field area.

### Retrieval flow (`review_action=generate`)
- If no row exists: enqueue + process
- If status `generated`: apply suggested alt directly
- If status `processing`: returns “try again” message
- If status `skipped`, `approved`, or `rejected`: auto-requeue + process

### Reliability fixes for Media Library grid/right-sidebar
- Frontend updates both:
  - DOM fields (Alt/Text, Title if enabled, and Description if enabled)
  - `wp.media` model state for selected attachment
- Reapplies updates after short delays to survive async sidebar re-renders.
- Prevents false “Unable to apply upload action” messages after successful apply.

### Alt/title/description sync behavior
- Wherever alt text is applied, title sync follows `sync_title_from_alt` (default on).
- Description sync follows `sync_description_from_alt` (default off).
- Attachment Details `Generate Alt Text` updates title/description fields and media-model state only when the corresponding sync settings are enabled.

## 8) Alt Text Truncation Behavior
Implemented in `includes/class-alt-generator.php`.

- Normalization trims likely cut-off endings.
- Attempts to keep complete sentence output.
- Trims dangling connectors/partial clause tails (e.g., trailing “and a”).
- Appends terminal punctuation when needed.

## 9) Upload/New Image Behavior
### New uploads
- Option: `auto_apply_new_uploads` (default off)
- If enabled, newly uploaded image suggestion is auto-approved/applied.

## 10) Provider/Runtime Notes
- Cloudflare Worker URL historically used: `https://alt-text-generator.webprod.workers.dev/`
- URL mode can fail for local/private URLs (e.g., `sandbox.local`)
- Direct upload mode is default/recommended
- Provider timeout currently: 90 seconds
- Error messaging includes more context where available
- SVGs are treated as unsupported by the configured provider:
  - Queue/backfill excludes SVG attachments.
  - Processor skips SVG queue rows (marks `skipped`) without provider calls.
  - Attachment-level generate actions return a friendly message: `SVG images are not supported by the configured provider.`
  - Settings > Tools > Test Provider Connection now uses the latest non-SVG queued image for queued-image test, and skips queued-image check if no non-SVG candidate exists.

## 11) Cron/Scheduling Notes
- Plugin schedules queue processing on `five_minutes` cadence.
- Existing non-`five_minutes` schedules are migrated on init.

## 12) CI / Quality Tooling
### GitHub Actions
- Workflow: PHPCS + Composer audit

### Local checks
- PHPCS:
  - `composer -d /Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

## 13) Documentation Status
- Primary WordPress readme: `readme.txt`
- Styled Markdown readme: `README.md`
- `USER-GUIDE.md` removed

## 14) Key Files (Start Here)
- `dynamic-alt-tags.php`
- `includes/class-plugin.php`
- `includes/class-admin.php`
- `includes/class-settings.php`
- `includes/class-processor.php`
- `includes/class-queue-repo.php`
- `includes/class-alt-generator.php`
- `admin/views-page-settings.php`
- `admin/views-page-queue.php`
- `assets/admin.js`
- `assets/admin.css`
- `readme.txt`
- `README.md`

## 15) Known Constraints / Open Risks
1. Search tab uses a custom media-style grid (not WP core media grid internals).
2. Provider remains external dependency (availability/latency risk).
3. Queue access is role-list based in settings and mapped into dedicated capability `ai_alt_manage_queue` via `user_has_cap` filter.
4. Media modal behavior depends on WP media-frame internals and can be sensitive to admin/plugin conflicts.
5. FileBird Search integration depends on FileBird Lite table structure (`fbv`, `fbv_attachment_folder`) rather than WP taxonomy APIs.

## 16) Suggested Next Steps
1. Add integration tests for:
   - role/capability access split
   - queue progress workflows
   - media grid/right-sidebar apply reliability
2. Add automated accessibility checks (axe/pa11y) for settings tabs, queue search controls, and responsive table behavior.
3. Add provider health visibility in admin (status + recent failure trend).
4. Add changelog/version entries for post-0.1.0 improvements.

## 17) Resume Checklist for New Codex Window
1. Confirm status:
- `git -C /Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags status -sb`

2. Validate coding standards:
- `composer -d /Users/local-esatzman/Desktop/Sites/dynamic-alt-tags/app/public/wp-content/plugins/dynamic-alt-tags phpcs`

3. Validate core flows in WP admin:
- Settings page admin-only access.
- Queue page visibility for admin + selected roles.
- Dashboard metrics live refresh updates.
- Settings tools actions redirect correctly (no access error).
- Queue tabs and top action header render and align correctly.
- Search tab date/alt-filter/search controls update results correctly and `Load More Images` appends correctly.
- Search tab optional taxonomy filter appears only when configured/available.
- Search tab FileBird folder filter appears only when FileBird folders exist.
- Search tab bulk-select mode works, including shift-click range selection and `Add to Queue`.
- After Search bulk add, Active Queue initially shows only the newly added items; `View more images` reveals older queue items.
- `Generate Alt Text For All` appears only when visible Active Queue rows still need generation and uses the existing progress flow.
- Search thumbnail click opens Attachment Details modal and returns to Search tab on close.
- History row `Re-queue` action returns items to Active Queue.
- Attachment Details `Generate Alt Text` behavior in list and grid/sidebar media views.
- Attachment Details title/description sync behavior respects both sync settings.
- SVG behavior checks:
  - SVGs are not queued from backfill or manual queue add actions.
  - Processing skips SVG rows cleanly (no provider 502 surfaced to UI).
  - Provider connection queued-image test does not pick SVG attachments.

4. If access/apply bugs appear:
- Start with `includes/class-settings.php` access methods.
- Then `includes/class-admin.php` + `includes/class-plugin.php`.
- For media grid/sidebar sync issues, inspect `assets/admin.js` media model sync helpers.
