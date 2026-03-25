# Troubleshooting

## Provider Test Fails

- Verify the Worker URL and token.
- Verify the WordPress host can reach the Worker.
- Re-run `Test Provider Connection`.

## Queue Does Not Process

- Check whether items are already `generated` and waiting for review.
- Verify provider or network availability.
- Try processing a single row first.

## URL Mode Fails on Local or Private Sites

- The Worker may not be able to fetch private media URLs.
- Disable URL mode and use direct upload mode.
