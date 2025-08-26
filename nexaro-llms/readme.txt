=== Nexaro LLM Summaries — SEO for AI ===
Contributors: nexaro
Donate link: https://nexaro.ir
Tags: llm, ai, seo, sitemap, json, txt, summaries
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Per-page machine-readable summaries for LLMs with llms.txt/json endpoints and LLM sitemaps to help AI understand your site.

== Description ==
Nexaro LLM Summaries — SEO for AI exposes per-page summaries via `llms.txt` and optional `llms.json`, injects an alternate link in the page head, and generates LLM sitemaps. It features an editor meta box with validation, draft generation, version history, and a bulk manager with a polished admin UI.

= Highlights =
- Per-page `llms.txt` and `llms.json` endpoints
- LLM sitemaps at `/llms-sitemap.xml` and `/llms-sitemap.json`
- Head `<link rel="alternate" type="text/plain">` injection
- Editor meta box with validation, history, and draft generator
- Admin settings for headers, sitemap slugs, validation limits
- Safe headers: X-Robots-Tag, Cache-Control, optional CORS
- Internationalized, RTL-first, accessible, dark-mode aware

= Security & Performance =
- No external calls, uses post meta and the Options API
- Strict nonces and capability checks
- Efficient queries and early routing

== Installation ==
1. Upload the `nexaro-llms` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Visit Settings → Nexaro LLM to configure options.
4. Edit a page and use the "LLM Summary" meta box to add a summary.

== Frequently Asked Questions ==
= What is `llms.txt`? =
A plain-text, machine-readable summary designed for LLMs and AI crawlers.

= Will it affect my front-end? =
No. Only a harmless `<link rel="alternate">` tag is injected when a summary exists.

= Can I disable JSON endpoints? =
Yes. Toggle in Settings.

== Screenshots ==
1. Meta box with validation and history
2. Settings screen
3. Bulk manager

== Changelog ==
= 1.0.1 =
- Initial public release

== Upgrade Notice ==
= 1.0.1 =
Initial release with LLM endpoints and sitemaps.