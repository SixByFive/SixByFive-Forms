# SixByFive Forms

A lightweight WordPress enquiry form plugin. No page builders, no subscriptions, no bloat — free to use.

## Features

- Drop-in shortcode `[sbf_form]` — place it anywhere
- Submissions stored in a dedicated database table
- Admin screen with status tabs (New / Read / Actioned / Archived), search, bulk delete, and pagination
- Email notifications to your team and an auto-reply to the enquirer
- Spam protection via honeypot field, time-window token, and IP rate limiting
- GitHub-powered auto-updates — get new versions straight from the WordPress dashboard
- No third-party services, no upsells, no licence keys

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Installation

1. Download the latest release from the [Releases](../../releases) page
2. In WordPress, go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin

## Auto-updates

Once installed, go to **SBF Forms → Settings** and enter:

| Field | Value |
|---|---|
| GitHub username | Your GitHub username or `SixByFive` to recieve updates from us |
| GitHub repository | `SixByFive-Forms` (or your fork name) |
| GitHub token | Only required for private repositories |

WordPress will then show available updates on **Dashboard → Updates** just like any other plugin.

## Shortcode

```
[sbf_form]
```

With optional attributes:

```
[sbf_form title="Get in touch" subtitle="We'll respond within 24 hours." button_text="Send message"]
```

## Settings

Go to **SBF Forms → Settings** to configure:

- **Notification email** — where new enquiry alerts are sent
- **From name / From email** — used as the sender on outgoing emails
- **GitHub auto-update** credentials (see above)

## Licence

[MIT](LICENSE) — free to use, modify, and distribute.
