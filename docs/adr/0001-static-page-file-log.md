# Static page, file log, no database

The landing page is static HTML/CSS/JS. Invitation Requests are appended to `storage/requests.jsonl` by `submit.php`, not stored in MySQL.

SMTP credentials and CRM keys must stay off the page — the brief forbids sending mail from the browser. A file log satisfies “do not lose the request if CRM is down”: success for the Guest means the line was written; email and CRM retry later from the same file.

Volume is tens of Guests for one date. A database adds hosting setup and no operational gain for that scale.
