# Predictive Dialer Platform

Production-oriented starter implementation for an Asterisk + MariaDB + PHP predictive dialer.

## What Is Included

- MariaDB schema with campaigns, vendors, prompts, leads, calls, DTMF, logs, Asterisk realtime tables, and engine heartbeats.
- PHP MVC web panel with authentication, campaign APIs, CSV import, dashboard APIs, lead editing, vendor management, and uploads.
- PHP CLI background engine for campaign scheduling, dialing, AMI event handling, call reconciliation, DTMF storage, and lead state updates.
- Asterisk AMI, PJSIP realtime, ODBC, and dialplan templates.

## Runtime Model

PHP is the only application language in this project.

- Web panel: PHP MVC through Nginx + PHP-FPM.
- Background worker: `php web/bin/dialer-worker.php`.
- Telephony: Asterisk AMI and PJSIP.
- Database: MariaDB.

## Bangladesh Number Normalization

The importer accepts common Bangladesh formats and stores canonical numbers as digits:

- `01721111111` -> `8801721111111`
- `+8801721111111` -> `8801721111111`
- `8801721111111` -> `8801721111111`

## Test

```bash
for f in $(find web -name '*.php' -type f | sort); do php -l "$f" || exit 1; done
systemctl is-active nginx php8.3-fpm mariadb asterisk predictive-dialer-engine
```

## Compliance Reminder

Before dialing real leads, verify Bangladeshi telemarketing, consent, DNC/suppression, call time window, and recording/IVR disclosure requirements for your business case.
