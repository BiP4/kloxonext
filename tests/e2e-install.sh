#!/bin/bash
# KloxoNext - full install + functional checks inside a systemd container.
#   bash tests/e2e-install.sh <container-name>   (container started with /src mounted)
c="$1"
docker exec "$c" bash -c '
git config --global --add safe.directory "*"
bash /src/kloxonext-install.sh --yes --admin-password=KnTest-2026 > /root/install.log 2>&1
echo "INSTALL EXIT $?" >> /root/install.log
K=/usr/local/lxlabs/kloxo
cd $K/httpdocs
IP=$(ip -4 -o addr show scope global | awk "{print \$4}" | cut -d/ -f1 | head -1)
sh /script/add --parent-class=client --parent-name=admin --class=dnstemplate --name=default.dnst --v-webipaddress=$IP --v-mmailipaddress=$IP --v-nameserver_f=ns1.kloxonext.test --v-secnameserver_f=ns2.kloxonext.test >/dev/null 2>&1
T=$(mysql -uroot -p"$(lxphp.exe ../bin/common/mp.php)" -N -e "select nname from kloxo.dnstemplate limit 1")
BR=$(ls -d /opt/php[0-9][0-9]m | sed "s#/opt/##" | tr "\n" " ")
for b in $BR; do
	sh /script/add --parent-class=client --parent-name=admin --class=domain --name=$b.test --v-docroot=$b.test --v-dnstemplate_name=$T --v-password=Demo-Pass-2026 >/dev/null 2>&1
	sh /script/update --class=web --name=$b.test --subaction=webfeatures --v-php_selected=$b >/dev/null 2>&1
	echo "<?php echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION . \"m \" . PHP_SAPI . \" \" . get_current_user();" > /home/admin/$b.test/v.php
	chown admin:admin /home/admin/$b.test/v.php
done
sh /script/fixphp >/dev/null 2>&1; sh /script/restart-web -y >/dev/null 2>&1; sleep 4
echo "=== RESULT $(. /etc/os-release; echo $PRETTY_NAME)"
grep -E "INSTALL EXIT|Panel PHP|Domain PHP|Third-party PHP" /root/install.log
for s in kloxo-web kloxo-php kloxo-apps mariadb named php-fpm pure-ftpd httpd apache2 nginx; do systemctl list-unit-files $s.service >/dev/null 2>&1 && printf "  %-11s %s\n" $s "$(systemctl is-active $s)"; done
echo "  panel login: $(curl -s -o /dev/null -w %{http_code} http://127.0.0.1:7778/login/)"
echo "  phpMyAdmin:  $(curl -sk -o /dev/null -w %{http_code} https://127.0.0.1:7777/thirdparty/phpMyAdmin/)"
for b in $BR; do echo "  $b.test -> $(curl -s -H "Host: $b.test" http://127.0.0.1/v.php | head -c 60)"; done
bash /src/tests/mailtest.sh
echo "  php exceptions: $(grep -c "^20" $K/log/php-exceptions.log 2>/dev/null || echo 0)"
grep -v "^#" $K/log/php-exceptions.log 2>/dev/null | head -5
'
