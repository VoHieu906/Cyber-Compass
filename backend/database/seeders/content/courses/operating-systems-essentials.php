<?php

return [
    'slug' => 'operating-systems-essentials',
    'domain' => 'operating-systems',
    'title' => 'Operating Systems Essentials',
    'summary' => 'Processes, memory, file systems and privilege: what the operating system does and why attackers care.',
    'description' => 'Malware runs as a process, exploits abuse memory, persistence hides in the file system, and privilege escalation targets the boundary between user and kernel. This course explains those operating system concepts in the terms security work uses.',
    'difficulty' => 1,
    'published_at' => '2026-11-06',
    'objectives' => [
        'Explain processes, threads and how memory is laid out',
        'Describe how file systems store data and metadata',
        'Explain user mode, kernel mode and privilege escalation',
    ],
    'skills' => ['process-memory', 'privilege-model'],
    'prerequisites' => [],
    'chapters' => [
        [
            'title' => 'Inside the operating system',
            'lessons' => [
                [
                    'slug' => 'processes-and-memory',
                    'title' => 'Processes and memory',
                    'minutes' => 15,
                    'summary' => 'What a running program looks like to the operating system, and how its memory is organised.',
                    'objectives' => ['Distinguish a program, a process and a thread', 'Describe the main regions of process memory', 'Explain why memory safety bugs are dangerous'],
                    'takeaways' => [
                        'A process is a running program with its own memory space; threads share that space.',
                        'Process memory has code, data, heap and stack regions.',
                        'Writing past a buffer can overwrite control data and let an attacker take over execution.',
                    ],
                    'resources' => [
                        ['Operating Systems: Three Easy Pieces (free book)', 'https://pages.cs.wisc.edu/~remzi/OSTEP/'],
                    ],
                    'sections' => [
                        ['prose', 'Programs, processes and threads', <<<'MD'
A **program** is a file on disk. When you run it, the operating system creates a **process**: the program's code loaded into memory, plus its own address space, open files and a process ID (PID). A process can run several **threads**, which share its memory but run independently.
MD],
                        ['prose', 'How memory is laid out', <<<'MD'
- **Code** (text): the program's instructions, normally read-only.
- **Data**: global variables.
- **Heap**: memory allocated while the program runs; it grows as needed.
- **Stack**: function calls, local variables and return addresses.
MD],
                        ['callout', 'Why buffer overflows matter', 'If a program copies more data into a stack buffer than it can hold, the extra bytes can overwrite the saved return address. An attacker who controls those bytes can redirect execution. Protections such as stack canaries, non-executable memory and ASLR make this harder, and memory-safe languages prevent it outright.', 'warning'],
                    ],
                ],
                [
                    'slug' => 'file-systems',
                    'title' => 'File systems',
                    'minutes' => 12,
                    'summary' => 'How files and their metadata are stored, and what that means for evidence.',
                    'objectives' => ['Explain files, directories and metadata', 'Interpret file timestamps', 'Explain why deleted data can often be recovered'],
                    'takeaways' => [
                        'A file system maps file names to data blocks and stores metadata such as owner, permissions and timestamps.',
                        'Timestamps record modification, access and change times, which help build a timeline.',
                        'Deleting a file usually removes its reference, not the data itself.',
                    ],
                    'resources' => [
                        ['stat(1) manual page', 'https://man7.org/linux/man-pages/man1/stat.1.html'],
                    ],
                    'sections' => [
                        ['prose', 'Names, data and metadata', <<<'MD'
A **file system** (ext4, NTFS, APFS) organises storage into files and directories. Alongside the content it keeps **metadata**: size, owner, permissions and timestamps. On Linux this lives in an **inode**; the directory entry just links a name to it.
MD],
                        ['code', 'Reading metadata', <<<'SH'
stat /etc/passwd
# Access: last read       Modify: content changed       Change: metadata changed
SH, 'bash'],
                        ['callout', 'Deleted is not gone', 'Deleting a file normally just frees its blocks for reuse. Until they are overwritten, forensic tools can often recover the content, which is why secure disposal uses encryption or physical destruction.', 'info'],
                    ],
                ],
                [
                    'slug' => 'privilege-levels',
                    'title' => 'Privilege levels and escalation',
                    'minutes' => 12,
                    'summary' => 'User mode, kernel mode and the boundary attackers try to cross.',
                    'objectives' => ['Explain user mode and kernel mode', 'Describe how system calls cross the boundary', 'Give common routes to privilege escalation'],
                    'takeaways' => [
                        'Applications run in user mode; the kernel runs with full hardware access in kernel mode.',
                        'Programs ask the kernel for privileged work through system calls.',
                        'Privilege escalation turns limited access into administrator or kernel access.',
                    ],
                    'resources' => [
                        ['MITRE ATT&CK: Privilege Escalation', 'https://attack.mitre.org/tactics/TA0004/'],
                    ],
                    'sections' => [
                        ['prose', 'Two modes', <<<'MD'
The CPU enforces a boundary. Normal programs run in **user mode** and cannot touch hardware or other processes' memory directly. The **kernel** runs in **kernel mode** with full access. When a program needs to open a file or send a packet, it makes a **system call**, and the kernel checks permissions before doing the work.
MD],
                        ['prose', 'Escalation routes', <<<'MD'
**Privilege escalation** is how an attacker goes from a normal account to administrator (root or SYSTEM). Common routes:

- misconfigurations, such as a script run by root that any user can edit;
- programs that run with elevated rights (setuid binaries, services) and have exploitable bugs;
- credentials left in files, history or environment variables;
- unpatched kernel vulnerabilities.
MD],
                        ['callout', 'Defense', 'Patch promptly, remove unnecessary elevated programs, audit sudo rules and service configurations, and keep administrative credentials out of reach of normal accounts.', 'tip'],
                    ],
                ],
            ],
        ],
    ],
];
