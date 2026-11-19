<?php

/*
| The eight knowledge domains. The order is the display order.
| Skills belong to exactly one domain.
*/

return [
    'networking' => [
        'name' => 'Networking',
        'description' => 'How data moves between machines: models, addressing, routing and the protocols every attack and defense depends on.',
        'skills' => [
            'osi-tcp-ip-models' => 'OSI and TCP/IP models',
            'ip-addressing' => 'IP addressing and subnetting',
            'core-protocols' => 'TCP, UDP, DNS and DHCP',
        ],
    ],
    'operating-systems' => [
        'name' => 'Operating Systems',
        'description' => 'Processes, memory, file systems and privilege: the layer every exploit ultimately targets.',
        'skills' => [
            'process-memory' => 'Processes and memory',
            'privilege-model' => 'Privilege levels and access control',
        ],
    ],
    'linux' => [
        'name' => 'Linux',
        'description' => 'The command line, permissions, users and logs on the operating system that runs most servers and security tools.',
        'skills' => [
            'linux-cli' => 'Linux command line',
            'linux-permissions' => 'File permissions and ownership',
            'log-analysis' => 'Log analysis',
        ],
    ],
    'programming' => [
        'name' => 'Programming Fundamentals',
        'description' => 'Enough Python to automate repetitive work, parse data and talk to services over HTTP.',
        'skills' => [
            'python-scripting' => 'Python scripting',
            'data-parsing' => 'Parsing text and structured data',
        ],
    ],
    'web-security' => [
        'name' => 'Web Security',
        'description' => 'How web applications work, how they break, and how common vulnerabilities are prevented.',
        'skills' => [
            'http-fundamentals' => 'HTTP fundamentals',
            'injection-prevention' => 'Injection prevention',
            'owasp-top-10' => 'OWASP Top 10',
        ],
    ],
    'cryptography' => [
        'name' => 'Cryptography',
        'description' => 'Hashing, encryption and the protocols that protect data in transit and at rest.',
        'skills' => [
            'hashing' => 'Hashing and password storage',
            'encryption' => 'Symmetric and asymmetric encryption',
            'tls' => 'TLS',
        ],
    ],
    'system-administration' => [
        'name' => 'System Administration',
        'description' => 'Keeping systems configured, patched and hardened, which prevents most incidents before they start.',
        'skills' => [
            'system-hardening' => 'System hardening',
            'ssh-administration' => 'Secure remote administration',
            'patch-management' => 'Patch management',
        ],
    ],
    'security-fundamentals' => [
        'name' => 'Security Fundamentals',
        'description' => 'The vocabulary and principles of security: risk, threats, controls and the frameworks teams use to organise them.',
        'skills' => [
            'security-principles' => 'Security principles',
            'risk-management' => 'Risk assessment',
            'security-frameworks' => 'Security frameworks',
        ],
    ],
];
