# RDSCO WooCommerce Elementor Widgets

Elementor widgets for WooCommerce, grouped under **RDSCO Widgets** in the Elementor panel.

| Widget | What it does |
|---|---|
| **Live Product Search** | A search bar that shows matching products as the visitor types (name, SKU, optionally description). Results are clickable. |
| **Category Search Popup** | A search box that opens a popup with a category tree beside the search field. The visitor ticks any categories (or none for all products) and searches inside them. |

Requires WordPress 6.5+, WooCommerce, Elementor 3.20+, PHP 8.1+.

## Widget settings

**Live Product Search**
- Placeholder, search icon
- Search in: all products, or selected categories (sub-categories included)

**Category Search Popup**
- Display: search box or icon only; popup title and placeholder
- Category tree (checkboxes, multi-select): beside the search box (before/after) or above it; stacks above on phones
- Categories: all, or a hand-picked list (with their sub-categories) in your order; levels shown; start expanded or collapsed; hide empty
- Ticking a category includes all its sub-categories; parents show a partial state; "Clear" returns to all products

**Both (Results)**
- Number of results (1–20), group results by category
- Search in description on/off (name and SKU are always searched)
- Show image, price, SKU, "View all results" link, custom no-results text
- Style tab: search box, results list, popup and category buttons

## Performance

- Search starts after 3 characters and always waits 500 ms after the last keystroke
- Stale requests are aborted; recent results are cached in the browser
- One ranked SQL query; SKUs (including variation SKUs) come from WooCommerce's indexed `wc_product_meta_lookup` table
- Results are cached in the persistent object cache when available (auto-invalidated on product or category changes); responses are anonymous and cacheable for 5 minutes
- Assets load only on pages that use the widgets
- Visitors search anonymously (cacheable); logged-in users send their session with a REST nonce, so it also works on private and WordPress.com staging sites
- Respects "Hidden from search" and "Hide out of stock items"

## Install

Download `rdsco-woocommerce-elementor-widgets.zip` from the [latest release](../../releases/latest) and upload it in **Plugins → Add New → Upload Plugin**.

## Updates

The plugin checks this repository's GitHub Releases every 12 hours (or immediately with **Check for updates** on the Plugins screen) and updates from **wp-admin → Plugins** like any other plugin.

Optional, only if the GitHub API rate limit is ever hit on your host:

```php
// wp-config.php
define( 'RDSCO_WEW_GITHUB_TOKEN', 'github_pat_...' ); // fine-grained, read-only
```

## Releasing a new version

1. Bump the version in **both** places in `rdsco-woocommerce-elementor-widgets.php`:
   - the header: ` * Version: 1.1.0`
   - the constant: `define( 'RDSCO_WEW_VERSION', '1.1.0' );`
2. Commit and push to `main`.
3. Create the release, either way:
   - **Website:** Releases → *Draft a new release* → *Choose a tag* → type `v1.1.0` → *Create new tag on publish* (target `main`) → *Publish release*.
   - **Command line:** `git tag v1.1.0 && git push origin v1.1.0`

The **Release** workflow checks that the tag matches both versions, lints PHP, builds `rdsco-woocommerce-elementor-widgets.zip` and attaches it to the release.
