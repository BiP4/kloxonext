# Changelog

All KloxoNext changes over [Kloxo Next Generation](https://github.com/KloxoNGCommunity/kloxo).

## 2026-10-10 (later)

- Firewall > CSF Settings: every csf.conf option, the csf lists and the csf/lfd tools from the
  panel (backup and automatic rollback, SSH/panel ports always kept open).
- Server page: Firewall button in the Security group.
- nexus is the skin of every account (clients, domains, mail accounts).
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
