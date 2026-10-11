
veidmark='envID:'
namemark='Name:'

# POSIX sh (run as "sh vzpid.sh <pid>")
getveid()
{
    pid=$1

		test -f /proc/${pid}/status || return
		cat /proc/${pid}/status | \
		awk -v pid=${pid} 'BEGIN{veid=0} /^'${namemark}'|^'${veidmark}'/{
			if ($1 == "'${namemark}'") {
				name = $2;
			} else if ($1 == "'${veidmark}'") {
				veid = $2;
			}
		}
	END{
		printf("%s\n", veid);
	}'
}
										
getveid $1
