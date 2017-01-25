# util-mailstats

Miscellaneous scripts to gather statistics from postfix logs. The necessary MySQL/Maria DDL is provided in the `schema-` files. You'll need to define `MAILSTATS_DBHOST`, `MAILSTATS_DBUSER`, `MAILSTATS_DBPASS`, `MAILSTATS_DBNAME`, and `MAILSTATS_RECIPIENT`.

## mailstats-dnsbl-rejections.php

A daily cron job that parses the postfix maillog for lines indicating DNSBL rejections, e.g.

`Jan 24 17:46:08 mail postfix/smtpd[9762]: NOQUEUE: reject: RCPT from unknown[47.90.x.xx]: 554 5.7.1 Service unavailable; Client host [47.90.x.xx] blocked using sbl-xbl.spamhaus.org; https://www.spamhaus.org/query/ip/47.90.x.xx; from=<Ains88@nate.com> to=<kaa16324@xx> proto=SMTP helo=<47.90.x.xx>`

The number of rejections per DNSBL is tallied, emailed to the administrator, and recorded in a database for later review. Useful for evaluating how well various RBLs are performing in your environment, and whether or not you want to continue using them.

## mailstats-no-such-user.php

A daily cron job that parses the postfix maillog for rejections due to `5.5.1 No such user` (or any arbitrary message) and records them to a database. Sender, recipient, and origin MTA are recorded to the database and an email summary is sent. Useful for identifying RFC-ignorant senders, as 5.5.1 is a permanent failure.
