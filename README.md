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
git clone https://github.com/BiP4/kloxonext.git /root/kloxonext
bash /root/kloxonext/kloxonext-install.sh
```

Options (passed through to `kloxo/install/setup.sh`):

| Option                    | Meaning                                                        |
|---------------------------|----------------------------------------------------------------|
| `--php="84 85"`           | PHP branches for websites (default: 8.4 + newest available)   |
| `--admin-password=...`    | password of `admin` (default: random, printed once at the end) |
| `--install-type=slave`    | install as a slave node                                        |
| `--yes`                   | do not ask for confirmation                                    |
| `--no-firewall`           | do not open the ports / switch on the firewall                 |

The panel listens on `https://<server>:7777` and `http://<server>:7778`.

The installer opens the ports of the services it sets up in the server firewall and switches
it on (firewalld on AlmaLinux/Rocky, ufw on Ubuntu, CSF when installed): web 80/443, FTP 20/21
plus the pure-ftpd passive range (45000-65000), mail 25/465/587/110/143/993/995, DNS 53 TCP/UDP,
SSH (its configured port) and the panel 7777/7778; a slave also gets 7779. Ports already open
stay open. On an installed server: `sh /script/firewall install-defaults master|slave`.
A provider firewall / security group must allow the same ports.

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
It is the only skin: the old *simplicity* and *feather* skins were removed (accounts that used
them are switched to nexus).

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

## Web servers

| Driver        | AlmaLinux / Rocky | Ubuntu 26.04 |
|---------------|:-----------------:|:------------:|
| nginx         | ✓                 | ✓ (default)  |
| Apache        | ✓ (default)       | ✓            |
| nginx → Apache proxy | ✓          | ✓            |
| lighttpd      | ✓                 | ✓            |
| Hiawatha, lighttpd/Hiawatha proxy | ✓ | –         |

On Ubuntu, Apache runs on the same `/etc/httpd` tree as on EL (`sh /script/apache-debian-layout`
builds it and points the `apache2` service at it), so the driver and templates are shared;
lighttpd only loads the modules its build has (`/script/lighttpd-modules`). The driver is
switched in *Server > Switch Program*; cleanup installs and configures the new one.

Statistics (AWStats) are served on `stats.<domain>`, protected by the domain's stats password
on every web server. On AlmaLinux/Rocky 10, where EPEL has no package, the official release is
installed (pinned and checksummed).

## Security

* **Firewall** – *Admin > Security > Firewall*: CSF when installed, otherwise firewalld/ufw.
  Open ports (with *Close* / *Open*), allow and deny lists with comments; every change is applied
  at once. SSH and the panel ports can never be closed (no lock-out).
* **CSF settings** – when CSF is installed, *Firewall > CSF Settings* edits every option of
  `csf.conf` (grouped by section, with the help text of the file, searchable), the csf lists
  (allow, deny, ignore, pignore, dyndns, blocklists ...) and offers the usual tools (restart,
  enable/disable, search an IP, allow/block, temporary bans, rules, lfd log), like the csf
  module of Webmin. Every change keeps a backup; if csf does not restart with it, the previous
  file is put back.
* **Jailed shell for clients** – SSH/SFTP access for a client is locked into a jail containing
  only its home directory and read-only system programs (`sh /script/kn-jail enable|disable|status <user>`).
  Jail mounts are private and never reach the host's mount table.
* **Web terminal** – *Server > SSH Terminal* is a real terminal (ttyd behind the panel, one-time
  token per connection); a client gets its jailed shell.
* **Error pages** – one design for every HTTP error page of the panel and the websites;
  `cp.<domain>` only links to the panel and webmail, over https.

## DNS

* BIND driver; subdomains live in the parent domain's zone; MX priorities 0-4.
* **Custom zones** – *Admin > Server > Custom DNS Zones*: permanent zones written by hand (for example
  reverse zones with `$GENERATE`), validated with `named-checkzone` and kept across updates
  (`sh /script/dns-custom list|save|delete|apply`).
* **Cloudflare** – a client chooses, per domain, whether the DNS is served locally or by
  Cloudflare. With a Cloudflare API token (*Cloudflare account*) the domain's records are pushed
  to its Cloudflare zone through the API on every DNS change, and the names to proxy (orange
  cloud: `@`, `www`, ...) are set from the panel. Manual push:
  `lxphp.exe ../bin/misc/cloudflare-sync.php <domain>|--all` (from `httpdocs`).

## Servers (master / slave)

Install the slave with `--install-type=slave`, then on the master *Servers > Add server*
with the slave's IP or hostname and its admin password. Port 7779 of the slave must be
reachable from the master. A slave recognises its own IP/hostname, so the objects the master
creates on it are handled locally.

## Other panel pages

* *Server > Services* – the services KloxoNext actually runs, with their real systemd state.
* *Mail Queue* – list, delete and flush messages (Postfix `postsuper`/`postqueue`).
* *Traceroute* – installed on demand, readable results.
* The nexus sidebar is task oriented; every other page of the panel stays under *More*.

## Project status

| Phase | Scope                                                                  | Status        |
|-------|------------------------------------------------------------------------|---------------|
| 1     | OS abstraction (dnf/apt), installer, PHP 8.4 panel, nginx panel server | done          |
| 2     | Modern responsive skin, login page                                     | done          |
| 3     | Automatic third-party updates on the newest PHP                        | done          |
| 4     | Postfix + Dovecot mail driver                                          | done          |
| 4b    | Apache/lighttpd web drivers on Ubuntu (RHEL paths)                     | done          |
| 5     | Firewall, custom/reverse DNS zones, Cloudflare DNS, jailed shell, web terminal | done  |

Not done yet: Hiawatha on Ubuntu; wiring a content filter (SpamAssassin/Bogofilter) into
Postfix — Sieve already files messages they flag into *Junk*.

## Changes

See [CHANGELOG.md](CHANGELOG.md).

## Tests

`tests/container-phase1.sh` – smoke test run inside fresh AlmaLinux 9, AlmaLinux 10 and Ubuntu 26.04 containers.
`tests/e2e-install.sh <container>` – full install in a systemd container, then domains with per-domain PHP and mail (`tests/mailtest.sh`).

Example (smoke test in a fresh container):

```bash
docker run --rm -v "$PWD":/src almalinux:9 bash /src/tests/container-phase1.sh
```

## License

AGPLv3, like Kloxo.
