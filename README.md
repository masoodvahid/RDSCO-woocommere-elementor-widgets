# BB Product Search (WooCommerce smart search)

Lightweight live product search for WooCommerce. Searches product **title, content and SKU** (including variation SKUs) and shows results as you type.

- Starts after 3 characters, always waits 500 ms after the last keystroke
- Aborts stale requests, caches recent terms in the browser
- One ranked SQL query; SKUs come from the indexed `wc_product_meta_lookup` table
- Uses the persistent object cache when available (auto-invalidated on product changes)
- Respects "Hidden from search" and "Hide out of stock items"
- Vanilla JS, scoped CSS, keyboard and screen-reader friendly, RTL-ready

Requires WordPress 6.5+, WooCommerce, PHP 8.1+.

## Install

Download `bb-product-search.zip` from the [latest release](../../releases/latest) and upload it in **Plugins → Add New → Upload Plugin**.

## Usage

```
[bb_product_search placeholder="Search products…" limit="8"]
```

| Attribute     | Default              | Notes            |
|---------------|----------------------|------------------|
| `placeholder` | `Search products…`   |                  |
| `limit`       | `8`                  | 1–10 results     |

## Updates

The plugin checks this repository's GitHub Releases (every 12 hours, or immediately with **Check for updates** on the Plugins screen) and updates from **wp-admin → Plugins** like any other plugin.

Optional, only if the GitHub API rate limit is ever hit on your host:

```php
// wp-config.php
define( 'BBPS_GITHUB_TOKEN', 'github_pat_...' ); // fine-grained, read-only, public repos
```

## Releasing a new version

1. Bump the version in **both** places in `bb-product-search.php`:
   - the header: ` * Version: 1.2.0`
   - the constant: `public const VERSION = '1.2.0';`
2. Commit and push to `main`.
3. Create the release, either way:
   - **Website:** Releases → *Draft a new release* → *Choose a tag* → type `v1.2.0` → *Create new tag on publish* (target `main`) → *Publish release*.
   - **Command line:**

     ```bash
     git tag v1.2.0
     git push origin v1.2.0
     ```

The **Release** workflow checks that the tag matches both versions, lints PHP, builds `bb-product-search.zip` (folder `bb-product-search/`) and attaches it to the release. Sites see the update within 12 hours, or immediately via **Check for updates**.
