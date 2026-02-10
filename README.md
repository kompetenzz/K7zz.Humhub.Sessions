# HumHub Sessions Module

Multi-backend video conferencing module for [HumHub](https://www.humhub.com). Integrates video meetings directly into Spaces and User profiles with support for multiple conferencing providers.

## Requirements

- HumHub >= 1.17.0
- PHP >= 8.1

## Supported Backends

| Feature | BigBlueButton | Jitsi Meet | Zoom | OpenTalk |
|---|:---:|:---:|:---:|:---:|
| Waiting Room | x | x | x | x |
| Public Join | x | x | x | x |
| Recordings | x | | x | x* |
| Presentations | x | | | |
| Camera Background | x | | | |
| Iframe Embed | x | x | | x |
| Breakout Rooms | x | | x | x |
| SIP Dial-in | | | | x |

*OpenTalk recording support depends on server version.

### BigBlueButton

Open-source web conferencing. Requires a BBB server with API URL and shared secret. Supports layout selection (Smart, Presentation Focus, Video Focus), per-session chat/webcam/microphone restrictions, custom welcome messages, and max participant limits.

### Jitsi Meet

Free, open-source video conferencing. Works with the public `meet.jit.si` server or self-hosted instances. Optional JWT authentication for private rooms. No account required.

### Zoom

Enterprise video conferencing via Zoom's REST API. Requires a Server-to-Server OAuth app (Account ID, Client ID, Client Secret). Supports auto-recording (local/cloud), breakout rooms, and alternative hosts.

### OpenTalk

German open-source, GDPR-compliant video conferencing with REST API. Requires API URL and token. Supports SIP dial-in, chat, screen sharing, and meeting timer.

## Installation

1. Copy or clone this module into your HumHub custom modules directory:
   ```
   /path/to/humhub/protected/modules/  (standard)
   ```
   or for Docker setups with custom module paths as configured in your `common.php`.

2. Enable the module in **Administration > Modules**.

3. Configure at least one backend under **Administration > Sessions Configuration**.

## Configuration

### Global Settings

Navigate to **Administration > Sessions Configuration**:

- **General:** Toggle top navigation link and customize its label.
- **Allowed Backends:** Enable/disable backends globally. Only configured backends (with valid credentials) can be activated.
- **Backend Settings:** Configure credentials and defaults for each backend (API URLs, secrets, tokens).

### Space / Profile Settings

Each Space or User profile can override global settings:

- Show/hide the sessions navigation link
- Customize the navigation label
- Use global backend settings or select a custom subset
- Set a default backend for new sessions

## Permissions

The module provides three granular permissions:

| Permission | Description | Default |
|---|---|---|
| **Administer Sessions** | Full control over all sessions | Space Admin, Space Owner |
| **Start Session** | Create and start new sessions | Space Admin, Space Moderator |
| **Join Session** | Join existing sessions | All members |

Permissions are managed per Space/Profile under the standard HumHub permission settings.

## Session Features

- **Public Join:** Generate a public link with a unique token for anonymous participants (no HumHub account required).
- **Waiting Room:** Require moderator approval before participants can enter.
- **Mute on Entry:** Participants join with microphone muted.
- **Recordings:** Enable recording (where supported by the backend).
- **Attendee/Moderator Assignment:** Assign specific users as attendees or moderators, or use permission-based access.
- **Join Can Start:** Allow attendees to start the meeting without a moderator present.
- **Soft Deletes:** Sessions are soft-deleted and can be restored.

## Adding a Custom Backend

The module uses auto-discovery. To add a new backend:

1. Create a directory under `plugins/yourbackend/`.
2. Implement `VideoBackendInterface` in a class named `YourbackendBackend.php`.
3. Optionally add a `YourbackendSettingsForm.php` for backend-specific admin settings.
4. The backend will be automatically discovered and available for configuration.

## Running Tests

```bash
# From inside the Docker container or your HumHub environment:
cd /path/to/modules/sessions/tests
/path/to/humhub/protected/vendor/bin/codecept run unit -v
```

The test suite includes 63 tests covering models, forms, services, and the full form-to-API roundtrip.

## License

This module is proprietary software. All rights reserved.
