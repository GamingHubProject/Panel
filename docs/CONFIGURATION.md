# Configuration

## Global security defaults

**Administration → Gaming Hub Panel → Settings** contains extension-wide defaults and security policy only:

- default timeout;
- default cache TTL;
- TLS verification default for new connections;
- explicit private/LAN destination opt-in;
- explicit insecure HTTP opt-in;
- prerelease compatibility warnings;
- boot compatibility and connection-health diagnostics.

Panel credentials do not belong on this page.

## Panel Connections

**Administration → Gaming Hub Panel → Connections** supports multiple Pelican and Pterodactyl installations.

Each connection stores a display name, type, normalized base URL, encrypted Application API key, optional encrypted default Client token, default timeout/cache TTL, TLS verification, enabled state, and safe test result.

Blank secret fields preserve existing ciphertext. Separate Replace and Remove actions are provided. Stored secret values are never rendered.

### Application API key

Used only for administrator-authorized Application API testing and discovery of all servers visible to that key.

### Client API token

Used for runtime server details/resources. A connection-level default can be overridden by a per-server provider credential. Application credentials alone do not satisfy runtime Client API requirements.

## Discovery

Discovery is manual. It fetches Application API server lists and stores safe normalized data:

- stable identifier;
- name;
- UUID and short identifier when supplied;
- node name and primary allocation when safely available;
- suspended state;
- discovery/availability timestamps;
- small allowlisted metadata.

No raw responses, headers, tokens, or exception traces are stored.

## Provider mappings

For Pelican and Pterodactyl, the Core provider form keeps general name, order, and enabled fields, then adds:

- matching enabled Panel Connection dropdown;
- discovered Panel Server dropdown;
- read-only stored identifier;
- optional Client-token override;
- optional attribution label;
- optional timeout/cache overrides.

TLS is inherited from the connection. Server-side validation rechecks connection existence, enabled state, type, server ownership, identifier format, and runtime credential availability.

## Advanced manual fallback

Manual identifier entry is hidden by default. When enabled, Pelican requires a full UUID and Pterodactyl requires a supported Client identifier. Discovery remains preferred.

## LAN/private panels

Private/reserved destinations require the Settings opt-in. Insecure HTTP requires a separate opt-in. DNS and redirects remain validated, redirects remain same-origin, and TLS verification should stay enabled whenever possible.
