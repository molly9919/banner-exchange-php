# Banner Exchange PHP/MySQL

A simple, extensible **banner exchange** application in plain PHP with a MySQL backend and a one-page web installer.

## What is included

- Web installer (`public/install.php`) that:
  - asks for MySQL credentials,
  - supports a custom table prefix (multiple installs in the same DB),
  - creates all base tables,
  - generates `config/config.php`.
- Core exchange engine with support for:
  - impressions and click exchange,
  - global ratio (default 1:1) + per-size override capability,
  - formats: gif, jpg, png, swf, html/text,
  - categories, countries, weekdays, and time-window targeting,
  - banner alt tag,
  - local or remote banner URL.
- User and Admin authentication.
- Basic user panel for:
  - adding banners,
  - viewing credits and stats.
- Basic admin panel for:
  - platform stats,
  - global settings (ratio, mode, max banners).
- Public endpoints:
  - `serve.php?size=...&user=...` to serve banners,
  - `click.php?token=...` to track clicks and redirect,
  - `public_stats.php` for public summary/toplist.

## Quick start

1. Deploy the project to a server with PHP 8.1+ and MySQL 5.7+/8.
2. Set your web root to the `public/` directory.
3. Open `http://your-domain/install.php` and complete the setup form.
4. After installation, remove or protect `install.php`.

## Embed exchange code on user websites

Example:

```html
<iframe src="https://your-domain/serve.php?size=468x60&user=42" width="468" height="60" frameborder="0" scrolling="no"></iframe>
```

> `user` is the owner ID of the page where the exchange unit is displayed; credits are assigned to that account.

## Notes

This repository is a working base intended for fast customization. Some advanced enterprise-level items (for example: hourly/daily/monthly charting, moderator ACL, bulk mail campaigns, full backup/restore UI) are partially prepared through schema/settings and can be implemented incrementally as needed.
