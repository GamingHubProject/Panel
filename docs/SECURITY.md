# Security

- Connection Application keys and default Client tokens are Laravel-encrypted in Panel-owned storage.
- Optional per-server Client overrides are Laravel-encrypted in the existing Panel credential table.
- Secret fields use `dontFlash`, are blank-preserving, and never render stored values back to HTML.
- Core provider JSON contains only connection/server references and non-secret overrides; it never contains a Panel URL or token.
- Application keys are limited to administrative test/discovery calls. Runtime calls require a Client token.
- Connection, discovery, provider mapping, test, credential, diagnostics, and Settings actions have specific permissions and ownership validation.
- Read-only connection users cannot mutate credentials; diagnostics permissions expose presence/status only, never ciphertext.
- URLs reject unsupported schemes, embedded credentials, query/fragment components, malformed ports, unresolved hosts, and private/reserved addresses unless explicitly trusted.
- HTTPS and TLS verification are enabled by default. Insecure HTTP and private destinations require separate administrator opt-ins.
- DNS results are validated and pinned into cURL resolution where supported. Redirects are bounded, revalidated, and restricted to the original scheme/host/port.
- Response sizes are bounded before JSON normalization. Discovery stores an allowlisted normalized structure, not raw responses or headers.
- Public reads are filtered by Gaming Hub Core and never include connection IDs, URLs, panel identifiers, node names, credentials, or raw diagnostics.
- Laravel ciphertext depends on `APP_KEY`; losing or rotating that key without an application-supported rotation path makes stored credentials undecryptable and requires replacement.
