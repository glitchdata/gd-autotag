# GD AutoTag History

A curated log of notable changes and milestones for the GD AutoTag plugin. This complements the changelog you might keep in release notes by focusing on architectural or workflow-impacting events.

## 2025-12-04
- Introduced a selectable sitemap location control (public_html vs uploads) so administrators no longer need to type filesystem paths.
- Hardened the sitemap generator with writable-path fallbacks plus clearer notices that reference locations relative to `public_html`.
- Added dedicated WordPress admin submenu items for every plugin tab and renamed the "Advanced" tab to "AI" to reflect its focus on provider integrations.

## 2025-11
- Shipped the Sitemap tab with manual generation, search-engine pings, and include/exclude toggles for posts, pages, categories, and WooCommerce products.
- Expanded PostCategorizer heuristics to auto-label event-oriented and trend/insight articles, including hook points to override the detected categories.
- Delivered the Analytics tab with Google Analytics 4 settings (Measurement ID, Property ID, Measurement Protocol secret) for tracking GD AutoTag activity.

## 2025-10
- Completed the rebrand from "wp-plugin" to "gd-autotag", updating file names, plugin headers, docs, tests, and language assets.
- Added the `plugin-update-checker` Git submodule and supporting bootstrap scripts so releases can be discovered directly from GitHub.
- Tightened repository hygiene (.gitignore updates, asset naming) while refreshing the README and docs to reflect the new identity.

> **Tip:** Keep this file updated whenever you deliver a feature that affects deployment, configuration, or platform integration. It makes release retrospectives and stakeholder updates much easier.
