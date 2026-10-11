# Changelog

All KloxoNext changes over [Kloxo Next Generation](https://github.com/KloxoNGCommunity/kloxo).

## 2026-10-11

### Web - nginx cache
- nginx-proxy: an edited page or file is served on the next reload. Apache sent
  `Expires`/`Cache-Control: max-age` of 7 days for every answer (also HTML pages,
  `ExpiresDefault`) and nginx kept them that long; it also served the old copy while
  refreshing an expired one. The micro cache is now really 1 second (Apache's headers
  still reach the browser for static files), an expired copy is fetched again.
- Apache sends `Expires` for static files only (images, CSS, JS, fonts), no longer for pages;
  typo `accesss` for GIF fixed.
- nginx + PHP-FPM: answers that set a cookie (a login, a session) were cached and the same
  cookie went to the next visitors within that second - never cached now. Visitors logged in
  to a site (WordPress, Joomla, shops, PHP sessions, HTTP authentication) bypass the cache;
  answers marked private / no-store are not stored.
- nginx on AlmaLinux 9 (1.20) did not start: `http2 on;` needs nginx 1.25.1; older versions
  get `listen 443 ssl http2`.
- The nginx caches are emptied once after the update (pages stored under the old rules).

### DNS - subdomains
- A subdomain added from the panel is published in its domain's zone again (it answered after
  about 10 seconds in the tests; before, not at all). The parent zone is rewritten at the end
  of the request, and PHP-FPM runs that step in `/`: the driver list (`../file/driver/...`)
  was not found and no zone file was written. The same affected the mail maps rebuild and the
  Cloudflare sync; all three now run in the panel's directory.
- The DNS page of a domain also lists the records of its subdomains, read only, as they are
  published in the zone (`www.blog`, `mail.blog`, ...), with a link to each subdomain.
- The DNS page of a subdomain shows only its own records: NS and ns1-ns4 (never published for
  a subdomain, the domain's zone has them) are removed, also from older subdomains, and the
  NS record can no longer be added there.

### Mail - spam filter
- SpamAssassin and Bogofilter are now wired into the Postfix + Dovecot mail: every delivered
  message is checked (global Sieve filter at delivery), the account's Sieve script applies its
  settings - on/off, score, subject tag, white / black list, Junk folder / keep / delete.
- New page *Mail > Spam Filter*: engine (SpamAssassin / Bogofilter / none, same as
  *Switch Programs*), status (service, rule date, how much was learned, accounts with the
  filter on), settings (largest message, DNS block lists, Bayes auto-learn, daily rule
  updates, Bogofilter cutoffs, learning from Junk) and tools (GTUBE test, update rules now).
- Learning: moving a message into / out of *Junk* in any mail program trains the filter
  (IMAPSieve); *Train as spam / ham* in the panel runs as the mail user (it ran as root and
  could leave the Bayes database unreadable for delivery).
- A mail account's *Spam* page: what to do with spam, links to its white and black lists
  (addresses or domains, validated). Spam goes to *Junk* unless chosen otherwise.
- `sh /script/setup-spam` configures everything (called by `setup-mail`); Dovecot 2.3 (EL)
  and 2.4 (Ubuntu 26.04); the panel adds the subject tag, so SpamAssassin's own `[SPAM]`
  rewrite of AlmaLinux is turned off.
- The panel's message list for training also reads the Dovecot 2.4 Inbox (`.INBOX`).

### Security
- An object name that is not below the current parent in a panel URL (for example another
  client's mail account) is refused. The panel used to build an empty stand-in for it, and
  saving or deleting that stand-in overwrote or deleted the real record (and its files) of the
  other owner.

## 2026-10-10 (later)

- Firewall > CSF Settings: every csf.conf option, the csf lists and the csf/lfd tools from the
  panel (backup and automatic rollback, SSH/panel ports always kept open).
- Server page: Firewall button in the Security group.
- nexus is the skin of every account (clients, domains, mail accounts).
- The old feather and simplicity skins are removed; nexus is the only skin.
- Panel backup and restore work again on PHP 8 / Postfix / Ubuntu (SonarCloud follow-up).
- Security hardening: SQL escaping everywhere, shell argument quoting, file manager kept inside
  its root, stricter document root validation.
- Adding a domain to a client: usage counters no longer break on PHP 8.
- New design for the page of a new domain (quick links, publishing steps, mail settings),
  the "account suspended" page and the "site not configured" page; existing domains still
  showing the untouched KloxoNG placeholder get the new page once.
- LxGuard: no TypeError on PHP 8, no qmail commands on Postfix servers.

## 2026-10-10

### Installation
- The installer opens the ports of the services it sets up in the firewall (web, FTP with the
  passive range, mail, DNS, SSH, panel; 7779 on a slave) and switches the firewall on.
  Ports already open stay open; `--no-firewall` skips the step.

### Web servers
- Ubuntu: Apache (also behind nginx as proxy) and lighttpd drivers. Apache runs on the Red Hat
  `/etc/httpd` tree through an `apache2` service drop-in; lighttpd loads only the modules its
  build has.
- AWStats scripts are copied (not linked) to the stats folder, so lighttpd/Hiawatha serve them.
- lighttpd: the stats password now protects the whole `stats.<domain>` host (it was open).
- AWStats on AlmaLinux/Rocky 10: the official release is installed when there is no package.

### Security
- Firewall page (*Admin > Security > Firewall*): CSF when installed, otherwise firewalld/ufw;
  open ports with *Close* / *Open*, allow/deny lists, every change applied immediately.
- Turning the firewall on while firewalld is stopped now keeps the panel ports open.
- Jailed shell for client users (SSH, SFTP, panel terminal) and a real web terminal (ttyd).
- Jail mounts no longer propagate to the host (they could hide `/usr/local/lxlabs`).

### DNS
- Permanent custom DNS zones, e.g. reverse zones with `$GENERATE`.
- Cloudflare DNS/proxy per domain, managed through the Cloudflare API from the panel.
- A stray zone named `cfdomain` no longer stops BIND (fixed and repaired by cleanup).

### Servers
- Slave servers: a slave recognises its own IP/hostname ("Machine does not exist in DB" fixed).
- The slave backend no longer crashes when the master deletes objects on it (PHP 8).

### Panel
- `cp.<domain>` page in the webmail design (https only); one design for every error page.
- nexus sidebar: task-oriented sections, everything else under *More*; logo no longer squeezed.
- *Server > Services* lists the services KloxoNext runs, with their real state.
- Mail Queue: *Delete* and *Flush* work with Postfix.
- Traceroute installed when missing, safer command, readable results.
- PHP 8: dates read from the database no longer break `getdate()`/`date()`.
- `rc.local` is executable: no "not marked executable" flood, panel boot commands run.

## 2026-10-09

### Panel
- PHP 8 fixes: client creation, new-domain blank page, in-panel phpinfo.
- Queued service restarts run again; web/mail drivers kept across cleanup.
- Panel port changes are applied (kloxo-web restarted outside the panel process).
- phpMyAdmin single sign-on: working logout, client/database links sign in.
- nexus skin: dark theme covers legacy inline colours, resizable textareas, home page layout,
  collapsed sidebar flyouts.
- Update from GitHub (`sh /script/upcp`).

### DNS
- Subdomains live in the parent domain's zone; MX priorities 0-4.

### Mail (phase 4)
- Postfix + Dovecot mail driver (SMTP 25/465/587, IMAP/POP3 with TLS, LMTP, Sieve, quota,
  forwards, catch-all, autoresponders, DKIM) on AlmaLinux 9/10 and Ubuntu 26.04.
- Roundcube 1.7 layout, SnappyMail, webmail chooser page.

### Websites
- Per-domain PHP selection on Apache (proxy_fcgi), the default *php* follows the newest branch.

### Base (phases 1-3)
- Multi-OS support (dnf/apt): AlmaLinux/Rocky 9-10 and Ubuntu 26.04, services from the
  distribution repositories.
- Panel on PHP 8.4 with its own nginx server; SHA-512 passwords.
- Native PHP packages (Remi / Ubuntu) behind the historical `/opt/phpXY` paths.
- Automatic updates of phpMyAdmin, Roundcube and SnappyMail on the newest PHP.
- nexus skin (responsive, light/dark) and new login page.
