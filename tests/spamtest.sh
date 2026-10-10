#!/bin/bash
# KloxoNext - spam filter end to end, inside a TEST container only (switches the engine,
# trains Bogofilter, empties the Junk folder of the account):
#   Postfix -> Dovecot LMTP -> Sieve filter (SpamAssassin, Bogofilter) -> Junk / Inbox,
#   then learning through IMAP (MOVE into / out of Junk).
# usage: tests/spamtest.sh <account@domain>
ACC="${1:-info@sqlt10031.test}"
U="${ACC%@*}"; DOM="${ACC#*@}"
H="/home/vmail/${DOM}/${U}"

if [[ -x /usr/libexec/dovecot/imap ]] ; then IMAPBIN=/usr/libexec/dovecot/imap ; else IMAPBIN=/usr/lib/dovecot/imap ; fi

send() {   # send <subject> <body>
	printf 'From: Sender <sender@example.org>\nTo: %s\nSubject: %s\nMessage-ID: <kn-%s-%s@example.org>\nDate: %s\n\n%s\n' \
		"${ACC}" "$1" "$RANDOM" "$(date +%s%N)" "$(date -R)" "$2" | /usr/sbin/sendmail -f sender@example.org "${ACC}"
}

wait_queue() {
	for i in $(seq 1 30) ; do
		[[ "$(postqueue -p 2>/dev/null | tail -1)" == "Mail queue is empty" ]] && break
		sleep 1
	done
	sleep 1
}

last_in() {   # last_in <maildir> -> newest message file
	ls -t "$1"/new/* "$1"/cur/* 2>/dev/null | head -1
}

show() {
	local f="$1"
	[[ -n "${f}" ]] || { echo "   (no message)" ; return ; }
	sed -n '/^$/q;/^Subject:\|^X-Spam-Flag\|^X-Spam-Level\|^X-Bogosity/p' "${f}" | cut -c1-110 | sed 's/^/   /'
}

GTUBE='XJS*C4JDBQADN1.NSBN3*2IDNEN*GTUBE-STANDARD-ANTI-UBE-TEST-EMAIL*C.34X'

for ENG in spamassassin bogofilter ; do
	echo "================ ${ENG}"
	sh /script/setup-spam apply "${ENG}" | tail -2
	doveconf -n 2>&1 | grep -E 'mail_plugins = .*imap' | sed 's/^/   imap plugins: /'
	echo "-- sieve of ${ACC}:"
	sed 's/^/   /' "${H}/sieve/kloxo.sieve"
	ls -la "${H}/.kloxo-spam" >/dev/null 2>&1 && echo "   flag ~/.kloxo-spam: present"

	rm -rf "${H}/Maildir/.Junk/new/"* "${H}/Maildir/.Junk/cur/"*
	n0=$(ls "${H}/Maildir/new" | wc -l)

	if [[ "${ENG}" == "spamassassin" ]] ; then
		send "gtube test" "${GTUBE}"
		send "hello friend" "Meeting tomorrow at ten, see you in the office. Regards, Ana"
	else
		# untrained Bogofilter knows nothing: teach it first (as the panel / Junk move would)
		for i in 1 2 3 4 5 6 ; do
			printf 'Subject: cheap pills %s\n\nBuy cheap viagra pills casino lottery winner prize click now offer %s\n' $i $i | runuser -u vmail -- /etc/dovecot/kloxo/spam/bin/kn-spam-learn spam
			printf 'Subject: project notes %s\n\nThe meeting about the project budget is moved to Thursday, please review the notes %s\n' $i $i | runuser -u vmail -- /etc/dovecot/kloxo/spam/bin/kn-spam-learn ham
		done
		send "cheap pills offer" "Buy cheap viagra pills casino lottery winner prize click now offer"
		send "project notes" "The meeting about the project budget is moved to Thursday, please review the notes"
	fi

	wait_queue
	echo "-- Junk folder:"
	show "$(last_in "${H}/Maildir/.Junk")"
	echo "-- Inbox (newest):"
	show "$(last_in "${H}/Maildir")"
	echo "   inbox new messages: $(( $(ls "${H}/Maildir/new" | wc -l) - n0 ))"

	echo "-- filter status:"
	sh /script/setup-spam status | grep -E '^(ENGINE|DOVECOT|SPAMD|BOGO_SPAM|BOGO_HAM|SA_BAYES)' | sed 's/^/   /'
done

echo "================ learning from IMAP (MOVE into Junk / out of Junk), engine bogofilter"
before=$(runuser -u vmail -- bogoutil -w /var/lib/kloxo-spam/bogofilter .MSG_COUNT | awk '$1==".MSG_COUNT"{print $2"/"$3}')
printf 'a select INBOX\nb move 1:1 Junk\nc select Junk\nd move 1:1 INBOX\ne logout\n' \
	| runuser -u vmail -- env USER="${ACC}" HOME="${H}" "${IMAPBIN}" -u "${ACC}" 2>&1 | grep -E '^(b|d) ' | sed 's/^/   /'
sleep 2
after=$(runuser -u vmail -- bogoutil -w /var/lib/kloxo-spam/bogofilter .MSG_COUNT | awk '$1==".MSG_COUNT"{print $2"/"$3}')
echo "   bogofilter spam/ham messages: before ${before}, after ${after}"
journalctl -u dovecot --since "-2min" --no-pager 2>/dev/null | grep -iE 'sieve|error|warn' | tail -6 | sed 's/^/   log: /'
grep -iE 'sieve|error' /var/log/maillog 2>/dev/null | tail -4 | sed 's/^/   maillog: /'
