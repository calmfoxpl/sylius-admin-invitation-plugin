# Sylius Admin Invitation Plugin

[![Build](https://github.com/calmfoxpl/sylius-admin-invitation-plugin/actions/workflows/build.yml/badge.svg)](https://github.com/calmfoxpl/sylius-admin-invitation-plugin/actions/workflows/build.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Invite administrators to the Sylius 2 panel by e-mail instead of handing out passwords. Type an address, and the invitee receives a link. On the linked page they set their own name and a strong password, and then land in the panel already logged in.

## Features

- **Invitations:** an *Invite* button on *Administrators* opens a form with a single e-mail field. The account stays disabled and without a password until the invitation is accepted.
- **Strong passwords:** a minimum length plus Symfony's `PasswordStrength` score, optionally checked against known data breaches. A *Generate a secure password* button creates one in the browser with the Web Crypto API.
- **Account access:** a card on the administrator's edit page and an action on the grid:
  - an account that has not accepted its invitation yet gets it again,
  - an active account gets a link to set a new password.
- **"Forgot password?" with the same rules:** the link from Sylius' own reset e-mail opens the plugin's page, so the password policy applies there too.
- **Native look:** pages reuse the admin login screen templates through Twig Hooks, and e-mails look like Sylius' admin e-mails. Everything can be overridden (see [Appearance](#appearance)).
- **No schema changes and no security configuration:** tokens use Sylius' password reset fields, and public pages are opened by route name.
- **Console:** `bin/console calmfox:admin:invite <email>` invites the first administrator of a new installation or resends an invitation.
- **Extension point:** `InvitationAcceptedEvent` lets other packages take over after acceptance. For example, [calmfox/sylius-admin-two-factor-plugin](https://github.com/calmfoxpl/sylius-admin-two-factor-plugin) sends the new administrator to two-factor setup.
- **Translations:** English and Polish.

## Requirements

| | Version |
|---|---|
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Sylius | 2.1, 2.2 |
| Symfony | 7.1 or newer |

## Installation

1. Require the package:

    ```bash
    composer require calmfox/sylius-admin-invitation-plugin
    ```

2. Register the bundle in `config/bundles.php`:

    ```php
    Calmfox\SyliusAdminInvitationPlugin\CalmfoxSyliusAdminInvitationPlugin::class => ['all' => true],
    ```

3. Import the configuration, e.g. in `config/packages/calmfox_sylius_admin_invitation.yaml`:

    ```yaml
    imports:
        - { resource: '@CalmfoxSyliusAdminInvitationPlugin/config/config.yaml' }
    ```

4. Import the routes, e.g. in `config/routes/calmfox_sylius_admin_invitation.yaml`:

    ```yaml
    calmfox_sylius_admin_invitation_admin:
        resource: '@CalmfoxSyliusAdminInvitationPlugin/config/routes/admin.yaml'
        prefix: '/%sylius_admin.path_name%'
    ```

5. Clear the cache: `bin/console cache:clear`.

## Configuration

All options are optional; these are the defaults:

```yaml
calmfox_sylius_admin_invitation:
    ttl: P3D                              # validity of an invitation link (ISO 8601 interval)
    password_reset_ttl: P1D               # validity of a password reset link; keep it equal to Sylius' admin reset token TTL
    replace_native_password_reset: true   # open Sylius' own "Forgot password?" link on the plugin's page
    firewall: admin                       # firewall the invitee is logged into after accepting
    password:
        min_length: 12                    # at least 8
        min_strength: 3                   # PasswordStrength score: 1 weak, 2 medium, 3 strong, 4 very strong
        not_compromised: false            # reject passwords found in data breaches (calls haveibeenpwned.com)
        generated_length: 20              # length of the generated password
```

## Appearance

The plugin looks native. To give it your own look, change your application and leave the plugin untouched:

- **E-mails in your shop's mail layout:** create `templates/bundles/CalmfoxSyliusAdminInvitationPlugin/email/layout.html.twig` containing only `{% extends '@SyliusCore/Email/layout.html.twig' %}`.
- **Restyle parts of an e-mail:** create `templates/bundles/CalmfoxSyliusAdminInvitationPlugin/email/invitation.html.twig` (or `password_reset.html.twig`). In it, extend the original with `{% extends '@!CalmfoxSyliusAdminInvitationPlugin/email/invitation.html.twig' %}` and override only the blocks you need: `heading`, `lead`, `action`, `details`.
  - Invitation variables: `adminUser`, `invitationUrl`, `localeCode`.
  - Password reset variables: `adminUser`, `resetUrl`, `validUntil`, `localeCode`.
- **Pages:** override any template under `templates/bundles/CalmfoxSyliusAdminInvitationPlugin/`, or change the hookables in `sylius_twig_hooks`:
  - `calmfox_admin_invitation.admin_user.create.*`: the invite form,
  - `calmfox_admin_invitation.accept_invitation.*`: the acceptance page,
  - `calmfox_admin_invitation.password_reset.*`: the password reset page,
  - `calmfox_account_access` in `sylius_admin.admin_user.update.content.form.sections#right`: the account access card.
- **Texts:** override the `calmfox_admin_invitation.*` translation keys.

## Extending

- **After acceptance:** listen to `Calmfox\SyliusAdminInvitationPlugin\Event\InvitationAcceptedEvent::NAME` and call `setResponse()` to redirect the new administrator elsewhere.
- **Sending:** decorate `Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface`.

## How it works

The invite route reuses Sylius' `sylius.controller.admin_user::createAction` with `event: invite`. Once the account is saved, a listener on `sylius.admin_user.post_invite` sends the e-mail. If the mail cannot be sent, the account is kept, and the panel explains how to resend the invitation.

The acceptance page looks the account up by token and checks that it is still pending (disabled, no password) and not expired. It then stores the name and the hashed password, clears the token, enables the account and logs the user in with `Security::login()`.

## Development

Tests run against [Sylius Test Application](https://github.com/Sylius/TestApplication) with MySQL:

```bash
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console assets:install vendor/sylius/test-application/public
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:schema:create

vendor/bin/ecs check          # coding standard
vendor/bin/phpstan analyse    # static analysis, level max
vendor/bin/phpunit            # unit and functional tests
```

The database defaults to `root:root@127.0.0.1`. To use a different one, set `DATABASE_URL` in `tests/TestApplication/.env.local`.

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## License

[MIT](LICENSE)
