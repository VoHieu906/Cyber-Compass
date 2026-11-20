<?php

/*
| The initial knowledge assessment (ARCHITECTURE §6.1, D24).
|
| A fixed, transparent questionnaire: 5 questions per domain at difficulties 1, 1, 2, 2, 3. The
| difficulty is the question's scoring weight. Questions are matched by their key: keep a key when
| fixing a typo, use a new key when the question changes meaning. A question removed from this file
| is archived (past attempts still show it); it is never deleted. Changing the number of options of
| a question that has been answered is refused: give it a new key instead.
|
| `answer` is the zero-based index of the correct option. None of these questions repeat a lesson's
| practice check (those ship with the lesson and are ungraded).
*/

return [
    'assessment' => [
        'slug' => 'initial-knowledge',
        'title' => 'Cybersecurity knowledge assessment',
        'description' => 'Forty multiple-choice questions, five in each of eight domains, from foundations to applied. Your result is a score per domain, calculated with the formula shown on the result page, and it is used to shape your roadmap. There is no time limit, and your answers save as you go.',
    ],

    'questions' => [
        // Networking
        'net-https-port' => [
            'domain' => 'networking', 'difficulty' => 1,
            'prompt' => 'Which TCP port does HTTPS use by default?',
            'options' => ['80', '443', '22', '53'],
            'answer' => 1,
            'explanation' => 'HTTPS listens on TCP 443 by default. Port 80 is plain HTTP, 22 is SSH and 53 is DNS.',
        ],
        'net-dns-purpose' => [
            'domain' => 'networking', 'difficulty' => 1,
            'prompt' => 'What does DNS do?',
            'options' => [
                'Assigns IP addresses to devices when they join a network',
                'Encrypts traffic between a browser and a website',
                'Translates names such as example.com into IP addresses',
                'Chooses the route a packet takes between networks',
            ],
            'answer' => 2,
            'explanation' => 'DNS resolves human-readable names to IP addresses. Handing out addresses is DHCP, encryption is TLS, and choosing routes is the job of routers.',
        ],
        'net-private-range' => [
            'domain' => 'networking', 'difficulty' => 2,
            'prompt' => 'Which of these IPv4 addresses is in a private (RFC 1918) range?',
            'options' => ['172.16.8.1', '172.32.0.1', '192.169.1.1', '11.0.0.1'],
            'answer' => 0,
            'explanation' => 'The private ranges are 10.0.0.0/8, 172.16.0.0/12 (172.16.0.0 to 172.31.255.255) and 192.168.0.0/16. 172.32.0.1 is just outside the /12, and 192.169.1.1 is not 192.168.',
        ],
        'net-ttl-expired' => [
            'domain' => 'networking', 'difficulty' => 2,
            'prompt' => 'A router receives a packet whose TTL drops to 0. What does it do?',
            'options' => [
                'Forwards it to its default gateway',
                'Resets the TTL to 64 and forwards it',
                'Returns the packet to the sender unchanged',
                'Discards it and usually sends an ICMP Time Exceeded message to the sender',
            ],
            'answer' => 3,
            'explanation' => 'TTL stops packets from looping forever. When it reaches 0 the router drops the packet and normally reports ICMP Time Exceeded, which is exactly how traceroute discovers each hop.',
        ],
        'net-subnet-split' => [
            'domain' => 'networking', 'difficulty' => 3,
            'prompt' => 'You split 192.168.10.0/24 into equal subnets that each need at least 50 usable host addresses, using the longest prefix that still fits. How many subnets do you get?',
            'options' => ['2', '4', '6', '8'],
            'answer' => 1,
            'explanation' => 'A /26 has 64 addresses and 62 usable hosts, enough for 50; a /27 has only 30 usable. A /24 holds four /26 subnets (.0, .64, .128 and .192).',
        ],

        // Operating systems
        'os-kernel-role' => [
            'domain' => 'operating-systems', 'difficulty' => 1,
            'prompt' => 'What is the main job of an operating system kernel?',
            'options' => [
                'Drawing windows and menus on the screen',
                'Checking downloaded files for viruses',
                'Compiling programs before they run',
                'Managing hardware and giving programs controlled access to it',
            ],
            'answer' => 3,
            'explanation' => 'The kernel manages the CPU, memory, devices and files, and programs reach them only through it. Desktops, antivirus and compilers are ordinary programs running on top.',
        ],
        'os-process-vs-program' => [
            'domain' => 'operating-systems', 'difficulty' => 1,
            'prompt' => 'What is the difference between a program and a process?',
            'options' => [
                'A process is a running instance of a program, with its own memory and state',
                'A program runs in the kernel, a process runs in user space',
                'They are two names for the same thing',
                'A process is a program that has been installed',
            ],
            'answer' => 0,
            'explanation' => 'A program is the file on disk. Each time it runs, the operating system creates a process with its own memory, open files and identity, so one program can have many processes.',
        ],
        'os-system-call' => [
            'domain' => 'operating-systems', 'difficulty' => 2,
            'prompt' => 'Why does an application make a system call to read a file instead of reading the disk itself?',
            'options' => [
                'System calls are faster than accessing the disk directly',
                'Files are always encrypted and only system calls can decrypt them',
                'Code in user mode cannot touch hardware, so the kernel performs the privileged work and checks permissions',
                'The disk can only be read by one program at a time',
            ],
            'answer' => 2,
            'explanation' => 'CPUs separate user mode from kernel mode. Applications run in user mode and must ask the kernel, which enforces file permissions before it touches the device.',
        ],
        'os-virtual-memory' => [
            'domain' => 'operating-systems', 'difficulty' => 2,
            'prompt' => 'From a security point of view, what does virtual memory give each process?',
            'options' => [
                'Unlimited memory, because it can use the disk',
                'Its own address space, so it cannot read another process\'s memory directly',
                'Automatic encryption of everything it stores',
                'Protection from crashing when it runs out of memory',
            ],
            'answer' => 1,
            'explanation' => 'Each process sees its own address space, mapped by the kernel. One process cannot simply read another\'s memory; it needs a kernel-mediated, permission-checked mechanism.',
        ],
        'os-aslr' => [
            'domain' => 'operating-systems', 'difficulty' => 3,
            'prompt' => 'Address space layout randomization (ASLR) mainly makes which kind of attack harder?',
            'options' => [
                'Guessing a user\'s password',
                'Reading files the attacker has no permission for',
                'Intercepting traffic on the local network',
                'Exploits that jump to code or data at a known memory address',
            ],
            'answer' => 3,
            'explanation' => 'Memory corruption exploits often need the address of a library, the stack or the heap. ASLR places them at random addresses on each run, so a hard-coded address usually misses.',
        ],

        // Linux
        'linux-pwd' => [
            'domain' => 'linux', 'difficulty' => 1,
            'prompt' => 'Which command prints the directory you are currently in?',
            'options' => ['ls', 'cd', 'pwd', 'whoami'],
            'answer' => 2,
            'explanation' => '`pwd` prints the working directory. `ls` lists its contents, `cd` changes it, and `whoami` prints your user name.',
        ],
        'linux-root' => [
            'domain' => 'linux', 'difficulty' => 1,
            'prompt' => 'On Linux, what is special about the account with UID 0?',
            'options' => [
                'It is root, the superuser, which bypasses normal permission checks',
                'It is the first user created during installation',
                'It is a guest account with no password',
                'It is reserved for system services and cannot log in',
            ],
            'answer' => 0,
            'explanation' => 'UID 0 is root, whatever the account is called. The kernel skips most permission checks for it, which is why attackers aim for it.',
        ],
        'linux-perms-640' => [
            'domain' => 'linux', 'difficulty' => 2,
            'prompt' => 'This file belongs to user alice and group staff. What can bob, who is in the staff group but is not alice, do with it?',
            'code' => '-rw-r----- 1 alice staff 4096 report.txt',
            'options' => ['Nothing', 'Read it, but not change it', 'Read and change it', 'Read, change and execute it'],
            'answer' => 1,
            'explanation' => 'The three permission groups are owner `rw-`, group `r--` and others `---`. bob is not the owner but is in the group, so he gets `r--`: read only.',
        ],
        'linux-grep-auth' => [
            'domain' => 'linux', 'difficulty' => 2,
            'prompt' => 'Which command shows only the lines containing "Failed password" in /var/log/auth.log?',
            'options' => [
                'find /var/log/auth.log "Failed password"',
                'tail -n "Failed password" /var/log/auth.log',
                'grep "Failed password" /var/log/auth.log',
                'cat /var/log/auth.log | sort',
            ],
            'answer' => 2,
            'explanation' => '`grep` prints the lines that match a pattern. `find` searches for files by name or attribute, `tail -n` takes a number of lines, and `sort` only reorders.',
        ],
        'linux-suid-search' => [
            'domain' => 'linux', 'difficulty' => 3,
            'prompt' => 'After getting a shell as an ordinary user, an attacker runs this command. What are they looking for?',
            'code' => 'find / -perm -4000 -type f 2>/dev/null',
            'options' => [
                'Files they are allowed to write to',
                'Recently modified files that might contain passwords',
                'Hidden files whose names start with a dot',
                'Setuid programs, which run with their owner\'s privileges (often root) and may allow privilege escalation',
            ],
            'answer' => 3,
            'explanation' => '`-perm -4000` matches the setuid bit. A setuid program runs as its owner, so a vulnerable or misusable setuid-root program can give the attacker root. `2>/dev/null` just hides permission errors.',
        ],

        // Programming
        'prog-loop-sum' => [
            'domain' => 'programming', 'difficulty' => 1,
            'prompt' => 'What does this Python code print?',
            'code' => "total = 0\nfor n in [2, 4, 6]:\n    total += n\nprint(total)",
            'options' => ['6', '12', '246', 'An error'],
            'answer' => 1,
            'explanation' => 'The loop adds each number to `total`: 0 + 2 + 4 + 6 = 12. The values are integers, so they are added, not joined as text.',
        ],
        'prog-dictionary' => [
            'domain' => 'programming', 'difficulty' => 1,
            'prompt' => 'Which data structure fits best for counting failed logins per user name?',
            'options' => ['A list of numbers', 'A single string', 'A tuple', 'A dictionary (map) from user name to count'],
            'answer' => 3,
            'explanation' => 'A dictionary maps each key (the user name) to a value (the count) and finds a key directly, without scanning a list.',
        ],
        'prog-index-error' => [
            'domain' => 'programming', 'difficulty' => 2,
            'prompt' => 'What happens when this Python code runs?',
            'code' => "items = [\"a\", \"b\", \"c\"]\nprint(items[3])",
            'options' => [
                'It raises an IndexError, because indexes start at 0 and the last one is 2',
                'It prints "c"',
                'It prints an empty string',
                'It prints None',
            ],
            'answer' => 0,
            'explanation' => 'Python lists are indexed from 0, so three items have indexes 0, 1 and 2. Asking for index 3 raises IndexError. Off-by-one mistakes like this are a classic source of bugs.',
        ],
        'prog-command-injection' => [
            'domain' => 'programming', 'difficulty' => 2,
            'prompt' => 'A script runs this line with a file name typed by the user. What is the risk?',
            'code' => 'os.system("cat " + filename)',
            'options' => [
                'The script may run slowly on large files',
                'Nothing, because cat only reads files',
                'A name such as `x; rm -rf ~` makes the shell run extra commands (command injection)',
                'The file could be printed in the wrong encoding',
            ],
            'answer' => 2,
            'explanation' => '`os.system` passes the whole string to a shell, so `;`, `&&` or `|` in the input start new commands. Pass arguments as a list (for example with `subprocess.run([...])`) so input is never parsed by a shell.',
        ],
        'prog-timing-compare' => [
            'domain' => 'programming', 'difficulty' => 3,
            'prompt' => 'Why is comparing a secret API token with `==` considered risky?',
            'options' => [
                'Because `==` converts both values to numbers first',
                'Because the comparison usually stops at the first different character, so response times can reveal how much of a guess was right',
                'Because `==` stores the token in a log file',
                'Because `==` is case-insensitive for strings',
            ],
            'answer' => 1,
            'explanation' => 'An ordinary comparison returns as soon as two characters differ. Measured over many requests, that timing difference lets an attacker recover a secret one character at a time. Use a constant-time comparison such as `hmac.compare_digest`.',
        ],

        // Web security
        'web-https-adds' => [
            'domain' => 'web-security', 'difficulty' => 1,
            'prompt' => 'What does HTTPS add to HTTP?',
            'options' => [
                'Encryption and proof of the server\'s identity, using TLS',
                'Faster page loading through compression',
                'Protection against every web application vulnerability',
                'Automatic login to websites',
            ],
            'answer' => 0,
            'explanation' => 'HTTPS is HTTP over TLS: traffic is encrypted and integrity-protected, and the certificate proves which server you reached. It does nothing about flaws inside the application itself, such as injection.',
        ],
        'web-httponly' => [
            'domain' => 'web-security', 'difficulty' => 1,
            'prompt' => 'What does the HttpOnly flag on a cookie do?',
            'options' => [
                'Sends the cookie only over HTTP, never HTTPS',
                'Deletes the cookie when the browser closes',
                'Stops JavaScript in the page from reading the cookie',
                'Encrypts the cookie\'s value',
            ],
            'answer' => 2,
            'explanation' => 'HttpOnly hides the cookie from `document.cookie`, so an injected script cannot steal a session cookie. Sending only over HTTPS is the separate Secure flag.',
        ],
        'web-sqli-fix' => [
            'domain' => 'web-security', 'difficulty' => 2,
            'prompt' => 'A login query is built like this. Which change fixes the vulnerability?',
            'code' => "query = \"SELECT * FROM users WHERE name = '\" + name + \"'\"",
            'options' => [
                'Limit the name field to 20 characters',
                'Hide database error messages from users',
                'Run the query over HTTPS',
                'Use a parameterized query (prepared statement) so input is passed as data, never as SQL',
            ],
            'answer' => 3,
            'explanation' => 'Joining input into SQL lets a value like `\' OR \'1\'=\'1` change the query. Parameters keep data and code apart, so the input can never alter the query\'s structure. Length limits and hidden errors only make the attack slightly harder.',
        ],
        'web-stored-xss' => [
            'domain' => 'web-security', 'difficulty' => 2,
            'prompt' => 'A comment form saves text containing a script tag, and the script then runs in the browser of everyone who views the page. What is this?',
            'options' => ['Cross-site request forgery', 'Stored cross-site scripting', 'SQL injection', 'Clickjacking'],
            'answer' => 1,
            'explanation' => 'The script is stored on the server and served to every visitor: stored XSS. The fix is to encode output for its context (and add a Content Security Policy), so user text is shown as text, never run as code.',
        ],
        'web-csrf-defense' => [
            'domain' => 'web-security', 'difficulty' => 3,
            'prompt' => 'A bank\'s transfer endpoint trusts the session cookie alone. A page on another site silently submits a transfer form to it while the victim is logged in. Which defense stops this?',
            'options' => [
                'Serving the bank over HTTPS',
                'A stronger password policy',
                'An anti-CSRF token that the attacker\'s page cannot know, checked on every state-changing request',
                'Limiting the length of form fields',
            ],
            'answer' => 2,
            'explanation' => 'The browser attaches the cookie automatically, so the forged request looks authenticated. A per-session token the other site cannot read, together with SameSite cookies, lets the server reject it. HTTPS and passwords do not change what the browser sends.',
        ],

        // Cryptography
        'crypto-symmetric' => [
            'domain' => 'cryptography', 'difficulty' => 1,
            'prompt' => 'In symmetric encryption, which keys do the sender and receiver use?',
            'options' => [
                'The sender uses a public key and the receiver a private key',
                'Each uses a different key that is generated per message',
                'Both use the same secret key',
                'No key is needed; the algorithm is secret',
            ],
            'answer' => 2,
            'explanation' => 'Symmetric ciphers such as AES use one shared secret key to encrypt and decrypt. Public and private key pairs are asymmetric cryptography. Security must come from the key, never from a secret algorithm.',
        ],
        'crypto-hash-purpose' => [
            'domain' => 'cryptography', 'difficulty' => 1,
            'prompt' => 'What is a cryptographic hash function such as SHA-256 used for?',
            'options' => [
                'Making a fixed-size fingerprint of data, to detect any change to it',
                'Encrypting data so it can be decrypted later with a key',
                'Compressing files to save space',
                'Generating key pairs for signatures',
            ],
            'answer' => 0,
            'explanation' => 'A hash turns any input into a fixed-size digest; a tiny change in the input gives a completely different digest. It cannot be reversed, so it is not encryption.',
        ],
        'crypto-password-storage' => [
            'domain' => 'cryptography', 'difficulty' => 2,
            'prompt' => 'How should a web application store its users\' passwords?',
            'options' => [
                'Encrypted with AES, so they can be recovered if needed',
                'As SHA-256 hashes without a salt',
                'Encoded in Base64',
                'With a slow, salted password hash such as Argon2 or bcrypt',
            ],
            'answer' => 3,
            'explanation' => 'Password hashes must be one-way, salted (so identical passwords give different hashes) and slow (so guessing billions per second is impossible). Encryption can be undone with the key, fast hashes are cracked quickly, and Base64 is just an encoding.',
        ],
        'crypto-signature' => [
            'domain' => 'cryptography', 'difficulty' => 2,
            'prompt' => 'Alice signs a document with her private key. What can Bob verify with Alice\'s public key?',
            'options' => [
                'That nobody else has read the document',
                'That Alice signed it and it has not changed since',
                'The date Alice wrote the document',
                'Alice\'s private key',
            ],
            'answer' => 1,
            'explanation' => 'A digital signature gives integrity and authenticity: only the private key could have made it, and any change breaks it. It does not encrypt the document, so it says nothing about who read it.',
        ],
        'crypto-ecb' => [
            'domain' => 'cryptography', 'difficulty' => 3,
            'prompt' => 'A team encrypts bitmap images with AES in ECB mode, and the outline of each image is still visible in the encrypted file. Why?',
            'options' => [
                'The AES key is too short',
                'ECB only encrypts every other block',
                'ECB encrypts identical plaintext blocks to identical ciphertext blocks, so patterns survive',
                'Images cannot be encrypted with block ciphers',
            ],
            'answer' => 2,
            'explanation' => 'ECB encrypts each block on its own with the same key, so repeated areas such as a plain background produce repeated ciphertext. Modes such as GCM add a nonce and chaining so equal inputs never give equal outputs.',
        ],

        // System administration
        'sysadmin-patching' => [
            'domain' => 'system-administration', 'difficulty' => 1,
            'prompt' => 'Why should security updates be applied promptly?',
            'options' => [
                'Updates always make systems faster',
                'They fix known vulnerabilities that attackers can already exploit',
                'Old versions stop working after a fixed date',
                'Updates remove the need for backups',
            ],
            'answer' => 1,
            'explanation' => 'Once a fix is published, attackers study it and start exploiting unpatched systems, often within days. Patching quickly closes that window.',
        ],
        'sysadmin-backup-321' => [
            'domain' => 'system-administration', 'difficulty' => 1,
            'prompt' => 'What does the 3-2-1 backup rule recommend?',
            'options' => [
                'Back up three times a day, keep two days, test one',
                'Three administrators, two passwords, one backup server',
                'Three backups per year, two on site, one in the cloud',
                'Three copies of the data, on two different kinds of storage, with one copy off site',
            ],
            'answer' => 3,
            'explanation' => 'Three copies on two kinds of media with one off site means a single failure, fire or ransomware attack on one location cannot destroy every copy.',
        ],
        'sysadmin-ssh-keys' => [
            'domain' => 'system-administration', 'difficulty' => 2,
            'prompt' => 'Which change to SSH most reduces the risk of password guessing on an internet-facing server?',
            'options' => [
                'Turn off password login and allow key-based authentication only',
                'Move SSH to a different port',
                'Show a warning banner before login',
                'Allow only one session at a time',
            ],
            'answer' => 0,
            'explanation' => 'With password login off, there is no password to guess: an attacker needs the private key. A different port only reduces noise from automated scans.',
        ],
        'sysadmin-unused-service' => [
            'domain' => 'system-administration', 'difficulty' => 2,
            'prompt' => 'A new server runs a mail service that nobody uses. Why remove it instead of leaving it idle?',
            'options' => [
                'Idle services slow down the CPU',
                'Mail services are illegal on servers',
                'Every running service adds attack surface, and unused ones are rarely patched or watched',
                'Removing it frees up the server\'s IP address',
            ],
            'answer' => 2,
            'explanation' => 'An attacker can reach a listening service whether anyone uses it or not, and forgotten services miss updates and monitoring. Hardening means running only what is needed.',
        ],
        'sysadmin-central-logs' => [
            'domain' => 'system-administration', 'difficulty' => 3,
            'prompt' => 'Why send server logs to a central log server instead of keeping them only on each host?',
            'options' => [
                'Central storage makes logs smaller',
                'An attacker who takes over a host can delete or alter its local logs, while copies already sent elsewhere survive and can be correlated',
                'Hosts cannot keep logs for more than a day',
                'Central logs remove the need for monitoring',
            ],
            'answer' => 1,
            'explanation' => 'Intruders routinely clear local logs to hide their tracks. Forwarding logs as they are written keeps evidence out of the attacker\'s reach and lets you connect events across many machines.',
        ],

        // Security fundamentals
        'secfund-phishing' => [
            'domain' => 'security-fundamentals', 'difficulty' => 1,
            'prompt' => 'An email that appears to come from your IT department asks you to confirm your password on a linked page. What is this most likely to be?',
            'options' => ['Phishing', 'A routine password audit', 'A denial-of-service attack', 'A software update'],
            'answer' => 0,
            'explanation' => 'Phishing impersonates a trusted sender to make you hand over credentials. Real IT teams never ask for your password; report the email instead of clicking.',
        ],
        'secfund-mfa' => [
            'domain' => 'security-fundamentals', 'difficulty' => 1,
            'prompt' => 'Why is multi-factor authentication stronger than a password alone?',
            'options' => [
                'It makes passwords longer',
                'It encrypts the password before sending it',
                'A stolen password is not enough: the attacker also needs another factor, such as your phone or a security key',
                'It changes your password automatically every day',
            ],
            'answer' => 2,
            'explanation' => 'MFA combines factors of different kinds (something you know, have or are). Phishing or a leak may expose the password, but the attacker still lacks the second factor.',
        ],
        'secfund-authn-authz' => [
            'domain' => 'security-fundamentals', 'difficulty' => 2,
            'prompt' => 'What is the difference between authentication and authorization?',
            'options' => [
                'They mean the same thing',
                'Authentication confirms who you are; authorization decides what you are allowed to do',
                'Authorization confirms who you are; authentication decides what you can do',
                'Authentication is for users, authorization is for servers',
            ],
            'answer' => 1,
            'explanation' => 'Logging in authenticates you. Every later check, such as whether you may open another user\'s record, is authorization. Many serious bugs are authorization checks that were forgotten.',
        ],
        'secfund-zero-day' => [
            'domain' => 'security-fundamentals', 'difficulty' => 2,
            'prompt' => 'What is a zero-day vulnerability?',
            'options' => [
                'A flaw that is fixed on the same day it is found',
                'A vulnerability with a severity score of zero',
                'A bug that only appears at midnight',
                'A flaw that is exploited before the vendor has a fix available',
            ],
            'answer' => 3,
            'explanation' => 'The defenders have had "zero days" to patch. Until a fix ships, protection relies on other layers: detection, segmentation and limiting privileges.',
        ],
        'secfund-ir-containment' => [
            'domain' => 'security-fundamentals', 'difficulty' => 3,
            'prompt' => 'Ransomware is spreading across file servers and has been confirmed. In the incident response lifecycle, what should happen next?',
            'options' => [
                'Restore every server from backup immediately',
                'Write the lessons-learned report',
                'Contain it: isolate the affected systems to stop the spread',
                'Wipe and reinstall the first server that was infected',
            ],
            'answer' => 2,
            'explanation' => 'After detection and analysis comes containment, then eradication and recovery, then lessons learned. Restoring before containing just gives the ransomware fresh systems to encrypt.',
        ],
    ],
];
