# Deployment Notes

## SIP trunk

Use the SIP trunk values only in runtime configuration. For the values you supplied:

- SIP username / IP number goes in `SIP_TRUNK_USERNAME`.
- SIP password goes in `SIP_TRUNK_PASSWORD`.
- SIP domain goes in `SIP_TRUNK_DOMAIN`.
- The vendor `trunk_name` in the web panel should match the Asterisk endpoint name, for example `bdtrunk`.

Do not put those values in Git.

## Create database

```bash
mysql -u root -p < sql/schema.sql
```

## Create admin user

```bash
php web/bin/create-admin.php admin admin@example.com 'strong-password'
```

## Add vendor in UI

Create a vendor with:

- Name: Bangladesh SIP Trunk
- Trunk name: `bdtrunk`
- Dial prefix: empty
- Max concurrent calls: start low, for example `2`
- CPS limit: start at `0.5` or `1`

## Asterisk

Copy the example files into your Asterisk config, replacing placeholders with runtime secrets:

```bash
cp asterisk/manager.conf.example /etc/asterisk/manager.d/predictive-dialer.conf
cp asterisk/pjsip.conf.example /etc/asterisk/pjsip.d/bdtrunk.conf
cp asterisk/extensions.conf.example /etc/asterisk/extensions.d/predictive-dialer.conf
asterisk -rx "module reload res_pjsip.so"
asterisk -rx "dialplan reload"
asterisk -rx "manager reload"
```

Exact include paths vary by Asterisk installation.

## PHP engine

```bash
cd /opt/predictive-dialer
cp .env.example .env
systemctl enable --now predictive-dialer-engine
```

## Test before live dialing

1. Create a vendor with max calls set to `1`.
2. Upload a CSV with one test Bangladesh mobile number you control.
3. Start a campaign manually.
4. Watch `/calls/active` and Asterisk CLI.
5. Confirm `calls.billsec`, `calls.ended_at`, and DTMF rows are written.
