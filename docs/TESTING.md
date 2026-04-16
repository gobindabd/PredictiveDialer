# Testing The Dialer

## 1. Check services

```bash
systemctl is-active nginx php8.3-fpm mariadb asterisk predictive-dialer-engine
```

All should print `active`.

## 2. Check web panel

Open the URL stored in:

```bash
cat /root/predictive-dialer-admin.txt
```

Login with the generated admin username and password from that file.

## 3. Check database

```bash
mysql -u root predictive_dialer -e "SHOW TABLES;"
```

## 4. Check Asterisk AMI

```bash
asterisk -rx "manager show settings"
```

Expected:

```text
Manager (AMI): Yes
TCP Bindaddress: 127.0.0.1:5038
```

## 5. Check Asterisk ODBC

```bash
isql -v predictive_dialer
asterisk -rx "odbc show"
```

Expected:

```text
Connected!
Name: asterisk
Number of active connections: 1
```

## 6. Check SIP registration

```bash
asterisk -rx "pjsip show registrations"
```

Expected:

```text
bdtrunk-reg/sip:110.76.128.122 ... Registered
```

If the status is `Rejected`, the registration object loaded but the SIP carrier rejected the credentials or registration policy.

## 7. Run code tests

```bash
cd /usr/src/software/project
for f in $(find web -name '*.php' -type f | sort); do php -l "$f" || exit 1; done
```

## 8. Test a one-lead campaign safely

Start with vendor capacity set very low:

- Max concurrent calls: `1`
- CPS: `0.5`

Upload a CSV:

```csv
phone_number,first_name,last_name
8801720039748,Test,Lead
```

Then:

1. Create a campaign in the web panel.
2. Upload the CSV.
3. Attach an audio prompt if required.
4. Start the campaign.
5. Watch active calls in `/calls/active`.
6. Watch Asterisk:

```bash
asterisk -rvvv
pjsip set logger on
```

7. Verify database call rows:

```bash
mysql -u root predictive_dialer -e "SELECT id,destination,status,dialed_at,answered_at,ended_at,billsec FROM calls ORDER BY id DESC LIMIT 10;"
```

## Important

Do not bulk dial until the one-lead test completes successfully and the carrier confirms your allowed CPS and concurrent call limits.

## 9. Reuse dialed leads

Open a campaign and use:

- `Edit` beside a lead to change its phone number or name.
- `Reset` beside a lead to make that single lead dialable again.
- `Reuse dialed leads` to reset all terminal leads in the campaign.

Resetting a lead changes the lead back to `pending`, clears attempts and lead lifecycle timestamps, and keeps old rows in `calls` for history.
