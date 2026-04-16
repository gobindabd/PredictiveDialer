# Asterisk Telephony Integration

The dialer manages SIP trunks, SIP extensions, and outbound routing rules from MariaDB.

## PJSIP realtime tables

PJSIP endpoint objects are loaded from the database through Asterisk Realtime Architecture.
The dialplan is **not** managed via realtime — it lives in static `extensions.conf` files.

Tables used:

| Table                  | Purpose                                   |
|------------------------|-------------------------------------------|
| `ps_endpoints`         | PJSIP endpoint configuration              |
| `ps_auths`             | SIP authentication credentials            |
| `ps_aors`              | Address of Record / contact registration  |
| `ps_endpoint_id_ips`   | IP-based endpoint identification          |

Sorcery mapping (`/etc/asterisk/sorcery.conf`):

```ini
[res_pjsip]
endpoint=realtime,ps_endpoints
auth=realtime,ps_auths
aor=realtime,ps_aors

[res_pjsip_endpoint_identifier_ip]
identify=realtime,ps_endpoint_id_ips
```

extconfig mapping (`/etc/asterisk/extconfig.conf`):

```ini
[settings]
ps_endpoints => odbc,asterisk
ps_auths => odbc,asterisk
ps_aors => odbc,asterisk
ps_endpoint_id_ips => odbc,asterisk
```

## Outbound registrations

Outbound registrations are **not** managed via realtime. The web panel generates
`/etc/asterisk/pjsip_runtime_registrations.conf` directly from the vendor rows in MariaDB
each time a trunk is saved or updated.

Do not edit that file manually — it is regenerated on every trunk save.

The main `/etc/asterisk/pjsip.conf` should contain only:

```ini
; Global settings and UDP transport
[global]
type=global
user_agent=PredictiveDialer

[transport-udp]
type=transport
protocol=udp
bind=0.0.0.0:5060

; Runtime-generated vendor registrations
#include pjsip_runtime_registrations.conf
```

## Dialplan

The dialplan is managed **statically** in `/etc/asterisk/extensions.conf`. There is no
realtime dialplan switch. See `asterisk/extensions.conf.example` for the required contexts.

Two contexts are required:

- `predictive-outbound` — originated by the dialer engine; dials out through the trunk
  passed in the `TRUNK_NAME` channel variable.
- `predictive-call` — the answered-call context; plays the prompt, collects one DTMF digit,
  fires `UserEvent(PredictiveDtmf, ...)`.

## Extensions and routing rules

The web panel stores SIP extensions in `sip_extensions`. Saving an extension writes matching
PJSIP rows to `ps_endpoints`, `ps_auths`, and `ps_aors`, then issues `pjsip reload`.

Outbound routing rules are stored in `routing_rules`. They define which trunk to use for a
given dial pattern along with optional digit strip/prepend transforms. The rules are consumed
by the dialer engine when selecting a vendor; they are not written to any Asterisk table.

## Verify

```bash
asterisk -rx "odbc show"
asterisk -rx "pjsip show endpoint <trunk_name>"
asterisk -rx "pjsip show registrations"
asterisk -rx "dialplan show predictive-outbound"
```
