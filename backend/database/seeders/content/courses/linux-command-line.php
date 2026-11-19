<?php

return [
    'slug' => 'linux-command-line',
    'domain' => 'linux',
    'title' => 'Linux Command Line for Security',
    'summary' => 'Navigate, inspect and control a Linux system from the shell, the way analysts and administrators do.',
    'description' => 'Most servers, cloud workloads and security tools run on Linux, and most of the work happens in a terminal. This course covers moving around the file system, reading and changing permissions, understanding users and processes, and finding evidence in logs.',
    'difficulty' => 1,
    'published_at' => '2026-11-02',
    'objectives' => [
        'Navigate the Linux file system and find files from the shell',
        'Read and change file permissions and ownership',
        'Inspect users, groups and running processes',
        'Search system logs for security-relevant events',
    ],
    'skills' => ['linux-cli', 'linux-permissions', 'log-analysis'],
    'prerequisites' => [],
    'chapters' => [
        [
            'title' => 'Working in the shell',
            'lessons' => [
                [
                    'slug' => 'linux-filesystem-navigation',
                    'title' => 'Navigating the file system',
                    'minutes' => 12,
                    'summary' => 'The directory tree, where things live, and the commands to move around and find files.',
                    'objectives' => ['Describe the purpose of key directories like /etc, /var and /home', 'Use pwd, cd, ls and find', 'Tell absolute paths from relative ones'],
                    'takeaways' => [
                        'Everything starts at /; configuration lives in /etc and logs in /var/log.',
                        'ls -la shows hidden files, owners and permissions.',
                        'find searches by name, size, owner or modification time.',
                    ],
                    'resources' => [
                        ['find(1) manual page', 'https://man7.org/linux/man-pages/man1/find.1.html'],
                    ],
                    'sections' => [
                        ['prose', 'One tree, starting at /', <<<'MD'
Linux has a single directory tree that starts at the root, `/`. A few directories matter constantly in security work:

- `/etc` holds system configuration, such as `/etc/passwd` and `/etc/ssh/sshd_config`.
- `/var/log` holds logs.
- `/home` holds users' personal directories; `/root` is the administrator's.
- `/tmp` is writable by everyone, which makes it a favourite place for attackers to drop files.
MD],
                        ['code', 'Moving around', <<<'SH'
pwd                 # where am I?
cd /var/log         # absolute path: starts at /
cd ..               # relative path: one level up
ls -la              # long listing, including hidden files
SH, 'bash'],
                        ['code', 'Finding files', <<<'SH'
find /home -name "*.sh"              # scripts in home directories
find /tmp -type f -mmin -60          # files changed in the last hour
find / -perm -4000 2>/dev/null       # setuid programs, a classic privilege escalation check
SH, 'bash'],
                        ['callout', 'Hidden is not secret', 'Files whose names start with a dot are only hidden from a plain ls. Always use ls -a when you investigate a directory.', 'tip'],
                    ],
                ],
                [
                    'slug' => 'linux-file-permissions',
                    'title' => 'File permissions and ownership',
                    'minutes' => 15,
                    'summary' => 'Read rwx permissions, change them safely, and spot dangerous settings.',
                    'objectives' => ['Read a permission string such as -rwxr-x---', 'Use chmod with symbolic and numeric modes', 'Recognise risky permissions such as world-writable files'],
                    'takeaways' => [
                        'Each file has an owner, a group, and read/write/execute bits for owner, group and others.',
                        'Numeric modes add up: read 4, write 2, execute 1.',
                        'World-writable files and unexpected setuid binaries are red flags.',
                    ],
                    'resources' => [
                        ['chmod(1) manual page', 'https://man7.org/linux/man-pages/man1/chmod.1.html'],
                    ],
                    'sections' => [
                        ['prose', 'Reading a permission string', <<<'MD'
`ls -l` shows something like `-rwxr-x--- 1 alice devs 2048 deploy.sh`. After the first character (the file type), there are three groups of three:

- `rwx` for the **owner** (alice): read, write, execute.
- `r-x` for the **group** (devs): read and execute.
- `---` for **others**: nothing.
MD],
                        ['steps', 'From letters to numbers', [
                            'Give each permission a value: read is 4, write is 2, execute is 1.',
                            'Add them per group: rwx = 7, r-x = 5, --- = 0.',
                            'Write the three digits together: rwxr-x--- is 750.',
                        ]],
                        ['code', 'Changing permissions and owners', <<<'SH'
chmod 750 deploy.sh          # owner rwx, group r-x, others none
chmod o-w shared.txt         # remove write for others
chown alice:devs deploy.sh   # change owner and group
SH, 'bash'],
                        ['callout', 'Never fix a problem with 777', 'chmod 777 makes a file readable, writable and executable by every user. It hides a permissions bug by creating a security hole.', 'warning'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Users, processes and evidence',
            'lessons' => [
                [
                    'slug' => 'linux-users-and-processes',
                    'title' => 'Users, groups and processes',
                    'minutes' => 15,
                    'summary' => 'Who is on the system, what they can do, and what is running right now.',
                    'objectives' => ['Read /etc/passwd and /etc/group', 'Explain what sudo does', 'List and investigate running processes'],
                    'takeaways' => [
                        'Accounts are listed in /etc/passwd; password hashes live in /etc/shadow, readable only by root.',
                        'sudo lets permitted users run commands as root, and logs that they did.',
                        'ps and top show running processes; an unknown process owned by root deserves attention.',
                    ],
                    'resources' => [
                        ['ps(1) manual page', 'https://man7.org/linux/man-pages/man1/ps.1.html'],
                    ],
                    'sections' => [
                        ['prose', 'Accounts and groups', <<<'MD'
Every account has a line in `/etc/passwd` with its username, user ID (UID), group ID, home directory and login shell. UID 0 is **root**, which can do anything. Groups, listed in `/etc/group`, grant shared access. Membership of the `sudo` or `wheel` group usually means administrative rights.
MD],
                        ['code', 'Who am I, and who else is here?', <<<'SH'
whoami
id                       # my UID, GID and groups
awk -F: '$3 == 0' /etc/passwd   # every account with UID 0 (should only be root)
who                      # users logged in right now
SH, 'bash'],
                        ['code', 'What is running?', <<<'SH'
ps aux --sort=-%cpu | head    # busiest processes
ps -ef --forest              # parent and child relationships
ss -tulpn                    # which processes are listening on which ports
SH, 'bash'],
                        ['callout', 'Look at the parent', 'A shell whose parent is a web server process is a strong sign of a compromised web application. The process tree often tells the story faster than the process name.', 'info'],
                    ],
                ],
                [
                    'slug' => 'linux-logs',
                    'title' => 'Finding evidence in logs',
                    'minutes' => 15,
                    'summary' => 'Where Linux records security events and how to search them quickly.',
                    'objectives' => ['Locate authentication logs on common distributions', 'Search logs with grep and journalctl', 'Recognise a brute-force pattern in SSH logs'],
                    'takeaways' => [
                        'Authentication events go to /var/log/auth.log (Debian, Ubuntu) or /var/log/secure (RHEL family).',
                        'journalctl queries the systemd journal by unit, time and priority.',
                        'Many failed logins from one address in a short time indicate brute forcing.',
                    ],
                    'resources' => [
                        ['journalctl(1) manual page', 'https://man7.org/linux/man-pages/man1/journalctl.1.html'],
                    ],
                    'sections' => [
                        ['prose', 'Where the logs are', <<<'MD'
Traditional logs are text files under `/var/log`. On systems using systemd, the **journal** also collects logs from every service, and `journalctl` queries it. For security work the most useful sources are authentication logs, the SSH service, sudo usage and the web server's access logs.
MD],
                        ['code', 'Searching', <<<'SH'
grep "Failed password" /var/log/auth.log | tail
journalctl -u ssh --since "1 hour ago"
journalctl -p warning --since today
SH, 'bash'],
                        ['code', 'Counting failed logins by source address', <<<'SH'
grep "Failed password" /var/log/auth.log \
  | grep -oE "from [0-9.]+" \
  | sort | uniq -c | sort -rn | head
SH, 'bash'],
                        ['callout', 'Attackers clean up', 'Gaps in a log, a log file that is suddenly empty, or a cleared shell history are evidence in their own right. Forwarding logs to a separate server keeps a copy that an intruder cannot easily edit.', 'warning'],
                    ],
                ],
            ],
        ],
    ],
];
