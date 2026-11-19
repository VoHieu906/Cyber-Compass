<?php

/*
| Roadmap topics. A topic groups lessons (in order) and may require other topics first.
| Careers arrange these topics into stages (careers.php); personal roadmaps are generated from that in Phase 4.
*/

return [
    'networking-basics' => [
        'domain' => 'networking', 'level' => 1,
        'title' => 'Networking basics',
        'summary' => 'What networks are made of, the OSI and TCP/IP models, and how connections start.',
        'lessons' => ['intro-to-computer-networks', 'osi-model', 'tcp-ip-model'],
        'requires' => [],
    ],
    'addressing-and-services' => [
        'domain' => 'networking', 'level' => 1,
        'title' => 'Addressing and core services',
        'summary' => 'IP addresses, subnets, DNS and DHCP.',
        'lessons' => ['ip-addressing', 'subnetting', 'dns-and-dhcp'],
        'requires' => ['networking-basics'],
    ],
    'linux-shell' => [
        'domain' => 'linux', 'level' => 1,
        'title' => 'Linux shell essentials',
        'summary' => 'Moving around the file system and managing permissions.',
        'lessons' => ['linux-filesystem-navigation', 'linux-file-permissions'],
        'requires' => [],
    ],
    'linux-investigation' => [
        'domain' => 'linux', 'level' => 2,
        'title' => 'Investigating a Linux system',
        'summary' => 'Users, processes and logs as sources of evidence.',
        'lessons' => ['linux-users-and-processes', 'linux-logs'],
        'requires' => ['linux-shell'],
    ],
    'security-principles' => [
        'domain' => 'security-fundamentals', 'level' => 1,
        'title' => 'Security principles',
        'summary' => 'The CIA triad, threats, vulnerabilities and risk.',
        'lessons' => ['cia-triad', 'threats-vulnerabilities-risk'],
        'requires' => [],
    ],
    'security-controls' => [
        'domain' => 'security-fundamentals', 'level' => 1,
        'title' => 'Security controls and frameworks',
        'summary' => 'Defense in depth, least privilege and the frameworks that organise controls.',
        'lessons' => ['defense-in-depth', 'security-frameworks'],
        'requires' => ['security-principles'],
    ],
    'web-basics' => [
        'domain' => 'web-security', 'level' => 2,
        'title' => 'Web application basics',
        'summary' => 'HTTP and the OWASP Top 10.',
        'lessons' => ['http-essentials', 'owasp-top-10-overview'],
        'requires' => ['networking-basics'],
    ],
    'injection-attacks' => [
        'domain' => 'web-security', 'level' => 2,
        'title' => 'Injection attacks',
        'summary' => 'SQL injection and cross-site scripting, and how to prevent them.',
        'lessons' => ['sql-injection', 'cross-site-scripting'],
        'requires' => ['web-basics'],
    ],
    'crypto-building-blocks' => [
        'domain' => 'cryptography', 'level' => 2,
        'title' => 'Cryptographic building blocks',
        'summary' => 'Hashing, password storage, and symmetric and asymmetric encryption.',
        'lessons' => ['hashing-and-passwords', 'symmetric-asymmetric-encryption'],
        'requires' => ['security-principles'],
    ],
    'tls' => [
        'domain' => 'cryptography', 'level' => 2,
        'title' => 'TLS',
        'summary' => 'How TLS protects connections, and the role of certificates.',
        'lessons' => ['tls-overview'],
        'requires' => ['crypto-building-blocks', 'addressing-and-services'],
    ],
    'os-internals' => [
        'domain' => 'operating-systems', 'level' => 1,
        'title' => 'Operating system internals',
        'summary' => 'Processes, memory, file systems and privilege levels.',
        'lessons' => ['processes-and-memory', 'file-systems', 'privilege-levels'],
        'requires' => [],
    ],
    'python-scripting' => [
        'domain' => 'programming', 'level' => 1,
        'title' => 'Python scripting',
        'summary' => 'Automating log parsing and web requests with Python.',
        'lessons' => ['python-basics-for-automation', 'parsing-logs-with-python', 'http-requests-with-python'],
        'requires' => [],
    ],
    'server-hardening' => [
        'domain' => 'system-administration', 'level' => 2,
        'title' => 'Server hardening',
        'summary' => 'Baselines, SSH security and patch management.',
        'lessons' => ['hardening-basics', 'ssh-hardening', 'patch-management'],
        'requires' => ['linux-shell', 'security-controls'],
    ],
];
