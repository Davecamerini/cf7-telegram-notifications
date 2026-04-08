# CF7 Telegram Notifications

Send Contact Form 7 submissions to a Telegram channel/chat, including formatted form data and uploaded attachments.

## Features

- Sends a Telegram message whenever a CF7 form is submitted
- Includes:
  - Site name
  - Form title and ID
  - Submission timestamp
  - Submitted fields
- Sends uploaded files as Telegram document attachments
- Admin settings page with:
  - Bot API token
  - Channel/Chat ID
  - Trigger scope (all forms or one specific form)
  - Optional filtering:
    - Skip empty fields
    - Exclude specific field names
- Built-in **Send Test to Telegram** button
- Top-level WordPress admin menu with custom icon

## Requirements

- WordPress
- [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
- A Telegram bot (created via [@BotFather](https://t.me/BotFather))
- Bot added to your target Telegram channel/group with permission to post

## Installation

1. Copy the `cf7-telegram-notifications` folder into your WordPress plugins directory:
   - `wp-content/plugins/cf7-telegram-notifications`
2. Activate **CF7 Telegram Notifications** from **Plugins** in WordPress admin.
3. Make sure Contact Form 7 is active.

## Configuration

Go to **CF7 Telegram Notifications** in the WordPress admin sidebar.

Set:

- **Bot API Token**  
  Example format: `123456789:AA...`
- **Channel / Chat ID**  
  Use either:
  - a channel username like `@mychannel`
  - or a numeric chat ID
- **Form Trigger Scope**
  - `All forms`
  - `Only one specific form` + select form
- **Skip Empty Fields** (optional)
- **Excluded Field Names** (optional, comma-separated)  
  Example: `acceptance, privacy, marketing`

Click **Save Settings**.

Then use **Send Test to Telegram** to verify configuration.

## Telegram Setup Notes

- For channels, the bot must be an admin (or have posting permissions).
- Private channels/groups may require numeric chat IDs.
- If messages are not delivered:
  - verify bot token
  - verify chat/channel ID
  - confirm the bot is in the destination and allowed to post

## How it Works

- The plugin listens to Contact Form 7 successful send event (`wpcf7_mail_sent`).
- It builds a formatted Telegram message from submitted fields.
- Internal CF7 meta keys are excluded automatically.
- Uploaded files are sent as Telegram documents.

## Filtering Behavior

- If **Skip Empty Fields** is enabled, blank values are omitted.
- **Excluded Field Names** are matched by field name (case-insensitive).
- Internal CF7 system fields are always skipped.

## Troubleshooting

- **No Telegram message received**
  - Check bot token and chat ID
  - Use the test button
  - Ensure bot has posting permission in target destination
- **Attachments not sent**
  - Ensure the form has file upload fields
  - Ensure uploads are actually present in the submission
  - Some server environments may restrict upload handling
- **Wrong form triggering**
  - Check Form Trigger Scope and selected form ID

## Security Notes

- Settings page requires `manage_options`.
- Test action uses nonce verification.
- Input settings are sanitized before saving.

## Changelog

### 1.0.0

- Initial plugin release
- CF7 -> Telegram submission notifications
- Attachment sending support
- Form scope targeting
- Optional field filtering
- Test message action
- Admin menu icon support

