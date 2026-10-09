# KloxoNext - mail checks run inside an installed test container (see e2e-install.sh)
# EL ships libcurl-minimal (no smtp/imap): use the full libcurl for the checks
command -v dnf >/dev/null && { curl --version | grep -q imap || dnf -q -y swap libcurl-minimal libcurl >/dev/null 2>&1 ; }

D=$(ls -d /home/admin/php*m.test 2>/dev/null | head -1 | xargs -n1 basename)
sh /script/add --parent-class=mmail --parent-name=$D --class=mailaccount --name=box1 --v-password=Mail-Pass-2026 >/dev/null 2>&1
sh /script/add --parent-class=mmail --parent-name=$D --class=mailaccount --name=box2 --v-password=Mail-Pass-2026 >/dev/null 2>&1
printf "From: x@localhost\r\nTo: box1@$D\r\nSubject: inbound-25\r\n\r\nhi\r\n" > /tmp/m1
printf "From: box1@$D\r\nTo: box2@$D\r\nSubject: submission-587\r\n\r\nhi\r\n" > /tmp/m2
curl -s smtp://127.0.0.1:25 --mail-from x@localhost --mail-rcpt box1@$D -T /tmp/m1
curl -s -k --ssl-reqd smtp://127.0.0.1:587 -u box1@$D:Mail-Pass-2026 --mail-from box1@$D --mail-rcpt box2@$D -T /tmp/m2
sleep 4

# older curl (EL9) prints FETCH literals only in verbose mode ('< ' prefix)
subj() {
	curl -s -v -k "imaps://127.0.0.1/INBOX" -u "$1:Mail-Pass-2026" -X "FETCH 1:* (BODY[HEADER.FIELDS (SUBJECT)])" 2>&1 \
		| tr -d '\r' | sed 's/^< //' | grep -E '^Subject: (inbound-25|submission-587)$' | head -1
}

echo "  mail box1 (port 25 -> IMAPS): $(subj box1@$D)"
echo "  mail box2 (587 AUTH -> IMAPS): $(subj box2@$D)"
for s in postfix dovecot opendkim; do printf "  %-11s %s\n" $s "$(systemctl is-active $s)"; done
