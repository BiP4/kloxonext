# KloxoNext

A web hosting control panel based on [Kloxo Next Generation](https://github.com/KloxoNGCommunity/kloxo),
rebuilt for current Linux distributions and PHP.

## Supported systems

| Distribution            | Package manager | PHP source                         |
|-------------------------|-----------------|------------------------------------|
| AlmaLinux / Rocky 9.x   | dnf             | Remi (`phpXY-php-*`), 7.4 – 8.5    |
| AlmaLinux / Rocky 10.x  | dnf             | Remi (`phpXY-php-*`), 7.4 – 8.5    |
| Ubuntu 26.04 LTS        | apt             | Ubuntu archive (8.5), ondrej/php when it supports 26.04 |

Every service comes from the distribution repositories (plus EPEL/Remi on EL): MariaDB, nginx,
Apache, BIND, Pure-FTPd, Postfix, Dovecot. No custom Kloxo RPM repository is needed.

## Install

On a fresh server with a fully qualified hostname (`hostnamectl set-hostname server1.example.com`):

```bash
git clone https://github.com/<you>/kloxonext.git /root/kloxonext
bash /root/kloxonext/kloxonext-install.sh
```

Options (passed through to `kloxo/install/setup.sh`):

| Option                    | Meaning                                                        |
|---------------------------|----------------------------------------------------------------|
| `--php="84 85"`           | PHP branches for websites (default: 8.4 + newest available)   |
| `--admin-password=...`    | password of `admin` (default: random, printed once at the end) |
| `--install-type=slave`    | install as a slave node                                        |
| `--yes`                   | do not ask for confirmation                                    |

The panel listens on `https://<server>:7777` and `http://<server>:7778`.

## PHP

* **Panel** – PHP 8.4 (`/opt/php84s`, `lxphp.exe`). Where 8.4 is not packaged (Ubuntu 26.04)
  the oldest available branch newer than 8.4 is used.
* **Websites** – every installed branch is available as `phpXYm`; the per-domain
  *PHP Selected* option works as before. The default *php* branch always follows the
  newest PHP installed on the server.
* **Third-party apps served by the panel** (phpMyAdmin) run in their own php-fpm pool
  (`kloxo-apps`, user `lxlabs`) on the newest installed PHP.

`/opt/phpXY[m|s]` is a symlink layout over the native packages (see `pscript/php-native.inc`),
so every Kloxo driver, template and script keeps its historical paths.

| Command                                   | Purpose                                         |
|-------------------------------------------|-------------------------------------------------|
| `sh /script/phpm-installer php85m`        | add a PHP branch for websites                   |
| `sh /script/phpm-remover php81m --purge`  | remove a branch                                 |
| `sh /script/php-branch-installer 85`      | choose the default *php* branch                 |
| `sh /script/phpm-updater`                 | update all PHP packages and refresh the layouts |

## Third-party applications

`sh /script/thirdparty-update` keeps these on their latest upstream release
(daily via `kloxo-thirdparty-update.timer`):

* phpMyAdmin → `/usr/local/lxlabs/kloxo/httpdocs/thirdparty/phpMyAdmin`
* Roundcube → `/home/kloxo/httpd/webmail/roundcube`
* SnappyMail (maintained successor of RainLoop) → `/home/kloxo/httpd/webmail/snappymail`

Downloads are checked against the SHA-256 published by upstream; the previous copy is kept as
`<dir>.previous`. Flags in `/usr/local/lxlabs/kloxo/etc/flag/`:

* `no-auto-update-php.flg` – do not apply PHP package updates from the timer
* `auto-install-new-php.flg` – install a newly released PHP branch automatically

## Updating the panel

```bash
sh /script/upcp        # pulls the configured git repository and runs cleanup
```

The repository is recorded in `/usr/local/lxlabs/kloxo/etc/conf/update-source.conf`.

## Interface

The default skin **nexus** is responsive (sidebar navigation that becomes a drawer on phones),
has light and dark modes, and keeps every menu entry and permission rule of the original panel.
The older *simplicity* and *feather* skins remain selectable in *Appearance*.

## Mail

Postfix + Dovecot from the distribution (qmail-toaster is not packaged for these systems):

* SMTP 25, submission 587 (STARTTLS) and 465 (TLS) with authentication through Dovecot
* IMAP/POP3 with TLS, delivery through Dovecot LMTP, Sieve, per-mailbox quota
* mailboxes in `/home/vmail/<domain>/<user>/Maildir` (user `vmail`)
* forwards, catch-all, alias domains and autoresponders from the panel; DKIM through OpenDKIM
  when *domain key* is enabled in the server mail settings

Every change made in the panel regenerates `/etc/postfix/kloxo/*` and `/etc/dovecot/kloxo/users`
from the Kloxo database (see `httpdocs/lib/php/mailmapslib.php`). `sh /script/setup-mail`
re-applies the whole configuration; it adapts to Dovecot 2.3 (EL) and 2.4 (Ubuntu 26.04).

## Project status

| Phase | Scope                                                                  | Status        |
|-------|------------------------------------------------------------------------|---------------|
| 1     | OS abstraction (dnf/apt), installer, PHP 8.4 panel, nginx panel server | done          |
| 2     | Modern responsive skin, login page                                     | done (iterating) |
| 3     | Automatic third-party updates on the newest PHP                        | done          |
| 4     | Postfix + Dovecot mail driver                                          | done          |
| 4b    | Apache/lighttpd web drivers on Ubuntu (RHEL paths)                     | planned       |

On Ubuntu the website driver is nginx; Apache, lighttpd and Hiawatha are offered on AlmaLinux/Rocky.
Spam filtering: Sieve files messages flagged by SpamAssassin/Bogofilter into *Junk*; wiring a
content filter into Postfix is not done yet.

## Tests

`tests/container-phase1.sh` – smoke test run inside fresh AlmaLinux 9, AlmaLinux 10 and Ubuntu 26.04 containers.
`tests/e2e-install.sh <container>` – full install in a systemd container, then domains with per-domain PHP and mail (`tests/mailtest.sh`).
containers:

```bash
docker run --rm -v "$PWD":/src almalinux:9 bash /src/tests/container-phase1.sh
```

## License

AGPLv3, like Kloxo.
