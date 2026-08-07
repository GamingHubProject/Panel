# Release packaging

GitHub tag: `v0.2.1`

Release assets:

- `gaming-hub-panel-v0.2.1.zip`
- `gaming-hub-panel-v0.2.1.zip.sha256`

The ZIP contains exactly one root directory, `gaming-hub-panel`, and no nested source archive, vendor directory, Git metadata, or symbolic links.

Run:

```bash
scripts/package.sh /path/to/output
```

Upload the ZIP and checksum to the same release. The sample registry entry uses `gaming-hub-panel-v*.zip` and the exact v0.2.1 checksum asset name.
