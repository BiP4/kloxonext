<?php 

$driver['hostdeny'] = "linux";
//$driver['dns'] = array("none", "bind", "djbdns", "pdns", "nsd", "mydns", "yadifa");
$driver['dns'] = array("none", "bind", "djbdns", "pdns", "nsd", "yadifa");
$driver['pop3'] = array("none", "dovecot", "courier");
//$driver['imap4'] = array("none", "courier", "dovecot");
$driver['smtp'] = array("none", "postfix", "qmail");
$driver['spam'] = array("none", "bogofilter", "spamassassin");
$driver['mailinglist'] = "ezmlm";
$driver['ffile'] = "linux";
$driver['easyinstaller'] = "linux";
$driver['all_easyinstaller'] = "linux";
$driver['pserver'] = "linux";
$driver['rubyrails'] = "linux";
$driver['uuser'] = "linux";
$driver['ftpuser'] = "pureftp";
/*
$driver['web'] = array("none", "apache", "lighttpd", "nginx", "hiawatha",  
	"lighttpdproxy", "nginxproxy", "hiawathaproxy");
*/
$driver['web'] = array("none", "apache", "lighttpd", "nginx", "hiawatha", "lighttpdproxy", "nginxproxy", "hiawathaproxy");
$driver['webcache'] = array("none", "varnish", "trafficserver", "squid");
$driver['mmail'] = array("postfix", "qmail");
$driver['cron'] = "linux";
$driver['llog'] = "linux";
$driver['davuser'] = 'lighttpd';
$driver['sshconfig'] = 'linux';

$driver['phpmodule'] = "linux";

// KloxoNext - on Debian/Ubuntu only the drivers adapted to its paths are offered
// (apache/lighttpd/hiawatha templates write to RHEL locations such as /etc/httpd)
if (class_exists('OsPlatform') && OsPlatform::isDebian()) {
	$driver['web'] = array("none", "nginx");
	$driver['webcache'] = array("none", "varnish");
	$driver['dns'] = array("none", "bind");
}
