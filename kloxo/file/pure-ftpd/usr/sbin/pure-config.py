#! /usr/bin/python

#  Original pure-config.py by Frank Denis <j at pureftpd dot org>
#  Copyright 2001 by Joshua Rodman <joshua_rodman at yahoo dot com>,
#  Modifications Copyright 2001-2009 by Matthias Andree
#
#                All Rights Reserved
#
# Permission to use, copy, modify, and distribute this software
# in original or modified form for any purpose and without fee
# is hereby granted, provided that the above copyright notice
# appear in all copies.
#
# Joshua Rodman DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS
# SOFTWARE, INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS, IN NO EVENT SHALL Joshua Rodman BE LIABLE FOR
# ANY SPECIAL, INDIRECT OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
# WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS,
# WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS
# ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
# PERFORMANCE OF THIS SOFTWARE.

"""Pure-FTPd configuration parser.

   Parse pure-ftpd configuration file to options.
   If run directly, execute the program.

"""
import re
import os
import sys

# autoconf stuff
pureftpd = '/usr/sbin/pure-ftpd'

argv = sys.argv[1:]
try:
    conffile = argv.pop(0)
except IndexError:
    print("Usage:", sys.argv[0], "<configuration file> [extra options]")
    sys.exit(1)

comment = re.compile(" *#.*")

# option_tuple members are lists as follows:
# 0 - case insensitive regex to match
# 1 - flag to use
# remaining - 'None' are placeholders for items matched by the regex
#             the matched items and literal strings are concatenated
#             into a literal option which follows the flag

option_tuple = (
    [r"IPV4Only[\s]+yes",                   "-4"                  ],
    [r"IPV6Only[\s]+yes",                   "-6"                  ],
    [r"FileSystemCharset\s+(\S+)",          "-8", None            ],
    [r"ClientCharset\s+(\S+)",              "-9", None            ],
    [r"ChrootEveryone[\s]+yes",             "-A"                  ],
    [r"TrustedGID[\s]+([\d]+)",             "-a", None            ],
    [r"BrokenClientsCompatibility[\s]+yes", "-b"                  ],
    [r"MaxClientsNumber\s+(\d+)",           "-c", None            ],
    [r"Daemonize\s+yes",                    "-B"                  ],
    [r"MaxClientsPerIP\s+(\d+)",            "-C", None            ],
    [r"VerboseLog\s+yes",                   "-d"                  ],
    [r"DisplayDotFiles\s+yes",              "-D"                  ],
    [r"AnonymousOnly\s+yes",                "-e"                  ],
    [r"MaxDiskUsage\s+(\d+)",               "-k", None            ],
    [r"NoAnonymous\s+yes",                  "-E"                  ],
    [r"SyslogFacility\s+(\S+)",             "-f", None            ],
    [r"FortunesFile\s+(\S+)",               "-F", None            ],
    [r"DontResolve\s+yes",                  "-H"                  ],
    [r"MaxIdleTime\s+(\d+)",                "-I", None            ],
    [r"LDAPConfigFile\s+(\S+)",             "-l", "ldap:",   None ],
    [r"MySQLConfigFile\s+(\S+)",            "-l", "mysql:",  None ],
    [r"PGSQLConfigFile\s+(\S+)",            "-l", "pgsql:",  None ],
    [r"PureDB\s+(\S+)",                     "-l", "puredb:", None ],
    [r"ExtAuth\s+(\S+)",                    "-l", "extauth:",None ],
    [r"PAMAuthentication\s+yes",            "-l", "pam"           ],
    [r"UnixAuthentication\s+yes",           "-l", "unix"          ],
    [r"LimitRecursion\s+(\d+)\s+(\d+)",     "-L", None, ":", None ],
    [r"AnonymousCanCreateDirs\s+yes",       "-M"                  ],
    [r"MaxLoad\s+(\d+)",                    "-m", None            ],
    [r"NATmode\s+yes",                      "-N"                  ],
    [r"CallUploadScript\s+yes",             "-o"                  ],
    [r"PassivePortRange\s+(\d+)\s+(\d+)",   "-p", None, ":", None ],
    [r"ForcePassiveIP\s+(\S+)",             "-P", None            ],
    [r"AnonymousRatio\s+(\d+)\s+(\d+)",     "-q", None, ":", None ],
    [r"UserRatio\s+(\d+)\s+(\d+)",          "-Q", None, ":", None ],
    [r"AntiWarez\s+yes",                    "-s"                  ],
    [r"Bind\s+(\S+)",                       "-S", None            ],
    [r"AnonymousBandwidth\s+([:0-9]+)",     "-t", None            ],
    [r"UserBandwidth\s+([:0-9]+)",          "-T", None            ],
    [r"Quota\s+([:0-9]+)",                  "-n", None            ],
    [r"Umask\s+(\d+):(\d+)",                "-U", None, ":", None ],
    [r"MinUID\s+(\d+)",                     "-u", None            ],
    [r"AllowUserFXP\s+yes",                 "-w"                  ],
    [r"AllowAnonymousFXP\s+yes",            "-W"                  ],
    [r"ProhibitDotFilesWrite\s+yes",        "-x"                  ],
    [r"ProhibitDotFilesRead\s+yes",         "-X"                  ],
    [r"AllowDotFiles\s+yes",                "-z"                  ],
    [r"AutoRename\s+yes",                   "-r"                  ],
    [r"AnonymousCantUpload\s+yes",          "-i"                  ],
    [r"TrustedIP\s+(\S+)",                  "-V", None            ],
    [r"LogPID\s+yes",                       "-1"                  ],
    [r"AltLog\s+(\S+)",                     "-O", None            ],
    [r"NoChmod\s+yes",                      "-R"                  ],
    [r"KeepAllFiles\s+yes",                 "-K"                  ],
    [r"CreateHomeDir\s+yes",                "-j"                  ],
    [r"NoRename\s+yes",                     "-G"                  ],
    [r"CustomerProof\s+yes",                "-Z"                  ],
    [r"NoTruncate\s+yes",                   "-0"                  ],
    [r"PIDFile\s+(\S+)",                    "-g", None            ],
    [r"TLSCipherSuite\s+(\S+)",             "-J", None            ],
    [r"PerUserLimits\s+([:0-9]+)",          "-y", None            ],
    [r"CertFile\s+(\S+)",                   "-2", None            ],
    [r"TLS\s+(\d)",                         "-Y", None            ])

for option in option_tuple:
    option[0] = re.compile(option[0], re.IGNORECASE)


def build_argument(match, controls):
    """construct an argument string
    match:    a match object containing argument values
    controls: a list controlling the format of the argument

    Return parameter as string"""

    argument = ""
    value = 1

    for control in controls:
        if control:
            # concat the literal string
            argument = argument + control
        else:
            # concat a value
            argument = argument + match.group(value)
            value = value + 1

    return argument


def parse(file_obj):
    """parse/extract a pure-ftpd configuration
    file_obj: a readlines() capable object containing the configurion

    Returns list of arguments as appropriate for exec()"""

    args = [pureftpd]

    for line in file_obj.readlines():
        if comment.search(line):
            continue

        for option in option_tuple:
            match = option[0].search(line)
            if match:
                args.append(option[1])

                if len(option) <= 2:
                    # The option takes no argument
                    continue

                argument = build_argument(match, option[2:])
                args.append(argument)

    return args


def parse_filename(filename=conffile):
    """parse/extract a pure-ftpd from a named file
    filename: text path to file

    Returns list of arguments as appropriate for exec()"""

    file_obj = open(filename)
    return parse(file_obj)

if __name__ == '__main__':
    args = parse_filename()

    if os.isatty(1):
        print("Running:", args + argv)

    os.execv(pureftpd, args + argv)
