=== Dynamic Alt Tags ===
Contributors: ericsatzman
Tags: accessibility, images, alt text, ai
Requires at least: 6.2
Tested up to: 6.9.4
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate and manage AI-suggested alt text for WordPress images using a Cloudflare Worker endpoint.

== Description ==
Dynamic Alt Tags provides an admin-first workflow for generating, reviewing, and applying AI image alt text suggestions.

=== Overview ===
The plugin is designed for editorial teams that need fast generation plus controlled review:
* Generate suggestions in bulk from a queue.
* Review and approve or skip suggestions before publication.
* Generate directly from Attachment Details when needed.
* Re-queue finalized items from History when you want to run them again.

=== Key Features ===
* Queue-based processing for media library images.
* Cloudflare Worker provider integration for AI alt suggestions.
* Queue review actions: Approve, Skip Image, Generate Alt Text, and bulk re-queue from History.
* Search tab with grid-based media browser, live filters, and bulk selection.
* Focused Active Queue view after bulk-adding items from Search.
* Attachment Details button to Generate Alt Text directly into the Alternative Text field.
* Sync options for attachment title and description.
* Queue dashboard and settings metrics with Reset Metrics action.
* Self-hosted plugin update support with native update-page icon metadata.
* Mobile/tablet responsive queue layout improvements.
* Role-based access control for queue visibility and actions.

=== Requirements ===
* WordPress `6.2+`
* PHP `7.4+`
* Accessible Cloudflare Worker endpoint for alt generation
* Optional Cloudflare API token (if your endpoint requires authorization)

=== Worker Request/Response Contract ===
* Sends POST JSON to worker with: `image_url`, `context`, `rules`
* Optional bearer token authorization
* Accepts JSON response with `alt_text` (or `caption`) and optional confidence

== Installation ==
1. Upload the plugin to `/wp-content/plugins/dynamic-alt-tags`.
2. Activate it from Plugins.
3. Go to `Settings > Dynamic Alt Tags`.
4. Add your Worker URL and token.
5. Click `Test Provider Connection`.
6. Save settings.

== Usage ==
=== Quick Start ===
1. Open `Settings > Dynamic Alt Tags` and confirm provider settings.
2. Process queue items from Settings or from `Media > Dynamic Alt Tags`.
3. Review pending suggestions in the queue.
4. Approve or skip based on your editorial standard.
5. In Attachment Details, click `Generate Alt Text` for one-off generation.

=== Queue Workflow ===
Go to `Media > Dynamic Alt Tags`.

==== Active Queue ====
Use this tab to process and review pending items.

Row actions:
* `Generate Alt Text`: generates or refreshes suggestion
* `Approve`: applies suggested alt text
* `Skip Image`: moves item to History without applying
* `View Image`: opens source image in a new tab

Top actions:
* `Run Backfill`
* `Generate Alt Text For Queued`
* `Refresh`

Bulk actions:
* `Approve`
* `Skip Image`
* `Generate Alt Text`

==== Search ====
Grid-style media browser for finding/filtering attachments.

Filters:
* `All dates`
* `All Images` or `No Alt Text Images`
* `Search images` (live/debounced)
* Optional media taxonomy filter
* Optional FileBird folder filter

Behavior:
* `Load More Images` appends additional results.
* `Bulk Select` supports shift-click range selection and `Add to Queue`.
* Clicking a thumbnail opens WordPress Attachment Details (media modal) and returns to the plugin Search tab when the modal closes.
* After bulk add from Search, the Active Queue can focus on the newly added items first.

==== History ====
Shows finalized items (`approved`, `rejected`, `skipped`) with final alt text and processed timestamp.

Row actions:
* `Re-queue`
* `View Image`

Bulk actions:
* `Re-queue`

=== Attachment Details Workflow ===
In Media Attachment Details, use `Generate Alt Text` for direct generation.

Behavior:
* No queue row: image is queued and processed
* `generated`: suggested alt text is applied
* `processing`: returns a try-again message
* `skipped`, `approved`, or `rejected`: automatically requeued and processed

=== Access and Permissions ===
==== Settings ====
* Administrators only.

==== Queue ====
* Administrators always have access.
* Additional roles can be granted queue access in `Settings > Dynamic Alt Tags > Access`.

=== Settings Reference ===
* `Cloudflare Worker URL`
* `Cloudflare API Token`
* `Batch Size`
* `Min Confidence`
* `Use URL Mode - Send Image URL`
* `Auto-Approve New Uploads`
* `Sync Alt Text to Attachment Title`
* `Sync Alt Text to Attachment Description`
* `Search Media Taxonomy`
* `Overwrite Existing Alt Text`
* `Require Manual Review for Queue Items`
* `Keep Data On Delete`

=== Metrics Notes ===
* `Total images processed` counts unique attachment IDs, not raw processing attempts.
* If you had older metrics from before unique counting was introduced, use `Reset Metrics` to start a clean baseline.

=== Troubleshooting ===
==== Provider Test Fails ====
* Confirm Worker URL and token are valid.
* Confirm endpoint is reachable from the WordPress host.
* Run `Test Provider Connection` again after saving settings.

==== Queue Does Not Process ====
* Check whether items are already in `generated` waiting for review.
* Confirm provider/network availability.
* Try processing a single row first from Active Queue.

==== URL Mode Problems on Local/Private Sites ====
* Remote Worker may not be able to fetch local/private URLs.
* Disable URL mode (use direct upload mode).

==== Plugin Updates ====
* The plugin supports self-hosted update checks via the hosted `info.json` endpoint.
* `Dashboard > Updates` can display a native custom plugin icon when an update is available.

=== Best Practices ===
* Start with `Require Manual Review` enabled.
* Use a conservative `Batch Size` during rollout.
* Review History regularly for quality.
* Grant queue access only to users who manage media metadata.

== Changelog ==
= 0.1.4 =
* Add History bulk re-queue workflow.
* Keep History table layout aligned after adding bulk actions.
* Count unique attachments in metrics instead of raw processing events.
* Add Reset Metrics action to the Tools tab.
* Add self-hosted updater and native update-page plugin icon support.
* Add PNG fallback icon assets for update UI.

= 0.1.0 =
* Initial MVP.
