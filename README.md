# SixByFive Forms

A lightweight WordPress enquiry form plugin. No page builders, no subscriptions, no bloat.

Built and maintained by [SixByFive](https://dev.sixbyfive.com).

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.4+ |
| PHP | 8.1+ |

---

## Features

- Drop-in shortcode `[sbf_form]` — place it on any page or widget area
- Submissions stored in a dedicated custom database table (not post meta)
- Admin screen with status workflow, search, bulk delete, and pagination
- Email notifications to the site team and an auto-reply to the enquirer
- Spam protection: honeypot field, time-window token, IP rate limiting
- GitHub-powered auto-updates — updates appear in the WordPress dashboard
- No third-party services, no upsells, no licence keys

---

## File structure

```
sixbyfive-forms/
├── assets/
│   ├── css/
│   │   ├── form.css        # Front-end form styles (.sbf-* classes)
│   │   └── admin.css       # Admin screen styles
│   ├── images/
│   │   ├── sbf-forms-icon-128.png
│   │   └── sbf-forms-logo.png
│   └── js/
│       └── form.js         # Client-side validation and AJAX submission
├── inc/
│   ├── db.php              # Table creation (activation hook) and table name helper
│   ├── spam.php            # Honeypot, time-window token, IP rate limiting
│   ├── email.php           # Admin notification and enquirer auto-reply
│   ├── form.php            # Shortcode, form HTML, AJAX handler, sanitisation
│   ├── admin.php           # Admin menu, enquiries list page, settings page
│   └── updater.php         # GitHub auto-updater class (SBF_GitHub_Updater)
└── sixbyfive-forms.php     # Plugin entry point — constants, requires, activation hook
```

---

## Installation

1. Download the latest release from the [Releases](../../releases) page
2. In WordPress go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin

The database table (`wp_sbf_enquiries`) is created automatically on activation.

---

## Shortcode

```
[sbf_form]
```

With optional attributes:

```
[sbf_form title="Get in touch" subtitle="We'll respond within 24 hours." button_text="Send message"]
```

| Attribute | Default |
|---|---|
| `title` | `Make an enquiry` |
| `subtitle` | `We'll get back to you as soon as possible.` |
| `button_text` | `Send enquiry` |

Set any attribute to an empty string to hide that element:

```
[sbf_form title="" subtitle=""]
```

---

## Form fields

| Field | Required | Notes |
|---|---|---|
| Full name | Yes | |
| Email address | Yes | Validated server-side with `is_email()` |
| Phone number | No | |
| Organisation | No | Local authority, company name, etc. |
| Enquiry type | Yes | Select: Placement, Join our team, Family/parent, Commissioning authority, Media/press, Other |
| Message | Yes | Textarea |
| Privacy consent | Yes | Checkbox — submission is rejected server-side without it |

---

## Submission flow

1. User submits the form — JavaScript validates required fields and email format client-side
2. AJAX POST to `admin-ajax.php` with nonce, honeypot field, and time-window token
3. Server validates nonce → checks honeypot → verifies token → checks rate limit → sanitises and validates fields
4. On success: saved to `wp_sbf_enquiries`, admin notification email sent, enquirer auto-reply sent
5. Success or error message displayed inline without page reload

---

## Spam protection

Three independent layers, all server-side:

| Layer | How it works |
|---|---|
| **Honeypot** | Hidden `sbf_website` field — bots fill it in, humans never see it. Triggered submissions are silently discarded. |
| **Time-window token** | A `wp_hash()` token generated when the form loads. Valid for the current 5-minute window plus the previous one. Prevents replay attacks from cached pages. |
| **IP rate limiting** | Max 3 submissions per IP per hour using WordPress transients. Returns HTTP 429 when exceeded. |

---

## Email notifications

Two emails are sent on every successful submission:

**Admin notification** — sent to the address configured in Settings (falls back to the WordPress admin email):
- Subject: `[Site name] New enquiry from <name>`
- Body: formatted table of all fields + "View in dashboard" button

**Enquirer auto-reply** — sent to the email address the visitor provided:
- Subject: `We've received your enquiry — <site name>`
- Body: personalised confirmation with a copy of their message

Both emails use `Content-Type: text/html` and are sent via WordPress `wp_mail()`. The From name and From email are configurable in Settings.

---

## Admin screen

Go to **SBF Forms → Enquiries** to view all submissions.

### Status tabs

Enquiries move through four statuses:

| Status | Meaning |
|---|---|
| **New** | Just arrived — unread |
| **Read** | Opened and reviewed |
| **Actioned** | Responded to or dealt with |
| **Archived** | No longer active |

The menu badge shows the count of **New** enquiries.

### Per-row actions

Each row shows inline links to move the enquiry to any other status. Available actions depend on the current status (the current status is not shown as an option).

### Bulk delete

Select rows using the checkbox column, then choose **Delete** from the Bulk actions menu and click Apply.

### Search

Searches across name, email, and message fields. Filters combine with the status tab.

### Pagination

20 rows per page.

---

## Settings

Go to **SBF Forms → Settings**.

| Setting | Description |
|---|---|
| **Notification email** | Where new enquiry alerts are sent. Defaults to the WordPress admin email. |
| **From name** | Sender name on outgoing emails. Defaults to the site name. |
| **From email** | Sender address on outgoing emails. Defaults to the WordPress admin email. |
| **GitHub username** | GitHub account hosting the plugin repo. Default: `SixByFive`. |
| **GitHub repository** | Repo name (not the full URL). Default: `SixByFive-Forms`. |
| **GitHub token** | Personal access token — only required for private repositories. |

---

## Auto-updates

The plugin checks GitHub releases for new versions. Updates appear in **Dashboard → Updates** exactly like a wordpress.org plugin.

By default updates come from `SixByFive/SixByFive-Forms`. If you maintain a custom fork, point the GitHub username and repository fields to your own fork in Settings.

For private repositories, generate a token at **GitHub → Settings → Developer settings → Personal access tokens** and paste it into the GitHub token field.

---

## Database schema

Table name: `wp_sbf_enquiries` (respects `$wpdb->prefix`)

| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Auto-increment primary key |
| `created_at` | `DATETIME` | Submission timestamp (server time) |
| `status` | `VARCHAR(20)` | `new` / `read` / `actioned` / `archived` |
| `name` | `VARCHAR(150)` | |
| `email` | `VARCHAR(255)` | |
| `phone` | `VARCHAR(50)` | |
| `organisation` | `VARCHAR(255)` | |
| `enquiry_type` | `VARCHAR(100)` | |
| `message` | `TEXT` | |
| `ip_address` | `VARCHAR(45)` | IPv4 or IPv6; Cloudflare `CF-Connecting-IP` used when present |
| `user_agent` | `VARCHAR(255)` | |

Indexed on `status`, `created_at`, and `email`.

---

## Theme integration (CCG Theme)

When the CCG Theme is active alongside this plugin, it loads `assets/css/ccg-forms.css` automatically. This file overrides form colours and spacing to match the CCG brand palette without modifying the plugin's own CSS.

The CCG contact page uses:

```
[sbf_form title="Make an enquiry" subtitle="We'll get back to you as soon as possible." button_text="Send enquiry"]
```

---

## CSS classes reference

All classes are prefixed `sbf-` to avoid conflicts.

| Class | Element |
|---|---|
| `.sbf-wrap` | Outermost container |
| `.sbf-title` | Form heading |
| `.sbf-subtitle` | Form subheading |
| `.sbf-form` | `<form>` element |
| `.sbf-row--2` | Two-column grid row |
| `.sbf-field` | Field wrapper (label + input) |
| `.sbf-req` | Required asterisk `*` |
| `.sbf-error` | Applied to invalid inputs |
| `.sbf-checkbox` | Consent checkbox label wrapper |
| `.sbf-submit-row` | Submit button row |
| `.sbf-btn` | Submit button |
| `.sbf-btn:disabled` | Button while submitting |
| `.sbf-status` | Status message area (hidden by default) |
| `.sbf-status--success` | Success state (shown on success) |
| `.sbf-status--error` | Error state (shown on failure) |
| `.sbf-dark` | Applied to `.sbf-wrap` to switch to dark-background variant |

---

## Licence

MIT — free to use, modify, and distribute.
