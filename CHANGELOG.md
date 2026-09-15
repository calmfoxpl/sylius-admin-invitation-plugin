# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Invite administrators by e-mail from *Administrators → Invite* and with `calmfox:admin:invite`; the account stays disabled and without a password until the invitation is accepted.
- Acceptance page where the invitee sets their name and password and is logged in.
- Password policy (minimum length, `PasswordStrength` score, optional breach check) with a browser-side secure password generator.
- *Account access* card and grid action: resend a pending invitation or send a password reset link.
- Sylius' native "Forgot password?" link opens the plugin's page, so the password policy applies there too (`replace_native_password_reset`).
- Access rules for the plugin's public pages are applied by route name; no `access_control` entries are needed.
- `InvitationAcceptedEvent` to take over the response after acceptance.
- English and Polish translations.
