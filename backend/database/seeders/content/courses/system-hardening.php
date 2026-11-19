<?php

return [
    'slug' => 'system-hardening',
    'domain' => 'system-administration',
    'title' => 'System Hardening and Administration',
    'summary' => 'Reduce attack surface, lock down remote access and keep systems patched.',
    'description' => 'Most breaches exploit known weaknesses: default settings, exposed services, missing patches. This course covers the administrator\'s side of security: hardening a server against a baseline, securing SSH, and running a patch process that actually keeps up.',
    'difficulty' => 2,
    'published_at' => '2026-11-08',
    'objectives' => [
        'Apply hardening principles and benchmarks to a server',
        'Configure SSH for key-based, least-privilege access',
        'Run a risk-based patch management process',
    ],
    'skills' => ['system-hardening', 'ssh-administration', 'patch-management'],
    'prerequisites' => ['linux-command-line'],
    'chapters' => [
        [
            'title' => 'Securing servers',
            'lessons' => [
                [
                    'slug' => 'hardening-basics',
                    'title' => 'Hardening basics',
                    'minutes' => 15,
                    'summary' => 'Shrink the attack surface: remove what you do not need and lock down what remains.',
                    'objectives' => ['Explain attack surface reduction', 'Use a benchmark such as CIS as a baseline', 'Check which services are listening'],
                    'takeaways' => [
                        'Every installed package, open port and enabled account is attack surface.',
                        'CIS Benchmarks give tested, step-by-step baselines for common systems.',
                        'A host firewall should allow only the services the server exists to provide.',
                    ],
                    'resources' => [
                        ['CIS Benchmarks', 'https://www.cisecurity.org/cis-benchmarks'],
                    ],
                    'sections' => [
                        ['prose', 'Attack surface', <<<'MD'
**Hardening** means reducing a system's attack surface: everything an attacker could interact with. A fresh install often includes services, accounts and defaults chosen for convenience, not security. The principle is simple: if the server does not need it, remove or disable it.
MD],
                        ['code', 'What is exposed?', <<<'SH'
ss -tulpn                         # listening ports and the processes behind them
systemctl list-units --type=service --state=running
sudo ufw status verbose           # host firewall rules (Ubuntu)
SH, 'bash'],
                        ['steps', 'A hardening pass', [
                            'Pick a baseline, such as the CIS Benchmark for your distribution.',
                            'Remove unneeded packages and disable unneeded services.',
                            'Allow only required ports in the host firewall and deny the rest.',
                            'Disable or lock unused accounts and remove default credentials.',
                            'Record what you changed, so the next server can be built the same way.',
                        ]],
                        ['callout', 'Automate the baseline', 'Hardening by hand drifts. Configuration management tools apply the same baseline to every server and show when one falls out of line.', 'tip'],
                    ],
                ],
                [
                    'slug' => 'ssh-hardening',
                    'title' => 'Securing SSH',
                    'minutes' => 15,
                    'summary' => 'Key-based logins, no direct root access and limited users for the most common way into a Linux server.',
                    'objectives' => ['Set up key-based authentication', 'Apply secure sshd_config settings', 'Explain how to safely test SSH changes'],
                    'takeaways' => [
                        'Keys are far stronger than passwords and stop password guessing entirely.',
                        'Disable root login and password authentication once keys work.',
                        'Keep an existing session open while testing changes, so a mistake cannot lock you out.',
                    ],
                    'resources' => [
                        ['sshd_config(5) manual page', 'https://man.openbsd.org/sshd_config'],
                    ],
                    'sections' => [
                        ['code', 'Create and install a key', <<<'SH'
ssh-keygen -t ed25519 -C "lan@laptop"
ssh-copy-id lan@server.example
SH, 'bash'],
                        ['code', 'Key settings in /etc/ssh/sshd_config', <<<'CONF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
AllowUsers lan deploy
MaxAuthTries 3
CONF, 'text'],
                        ['steps', 'Applying the change safely', [
                            'Keep your current SSH session open.',
                            'Check the configuration syntax with sudo sshd -t.',
                            'Reload the service with sudo systemctl reload ssh.',
                            'Open a second terminal and confirm you can still log in with your key.',
                            'Only then close the original session.',
                        ]],
                    ],
                ],
                [
                    'slug' => 'patch-management',
                    'title' => 'Patch management',
                    'minutes' => 12,
                    'summary' => 'A repeatable process for finding, prioritising and applying updates.',
                    'objectives' => ['Describe the steps of a patch management cycle', 'Prioritise patches by severity and exploitation', 'Explain why an asset inventory comes first'],
                    'takeaways' => [
                        'You cannot patch what you do not know you have, so inventory comes first.',
                        'Prioritise vulnerabilities that are being actively exploited, not just high CVSS scores.',
                        'Test, deploy in stages, and verify that the patch actually applied.',
                    ],
                    'resources' => [
                        ['CISA Known Exploited Vulnerabilities Catalog', 'https://www.cisa.gov/known-exploited-vulnerabilities-catalog'],
                    ],
                    'sections' => [
                        ['steps', 'The patch cycle', [
                            'Inventory: know every system and the software on it.',
                            'Monitor: follow vendor advisories and vulnerability feeds.',
                            'Prioritise: rank by severity, exposure and whether exploitation is happening.',
                            'Test: apply to a staging system or a small group first.',
                            'Deploy: roll out in stages with a rollback plan.',
                            'Verify: confirm versions changed and the vulnerability is gone.',
                        ]],
                        ['prose', 'Prioritising', <<<'MD'
A **CVSS** score describes how severe a vulnerability could be, but not whether anyone is using it. Lists such as CISA's Known Exploited Vulnerabilities catalogue show which flaws are actively exploited. An internet-facing system with an exploited vulnerability comes before an internal system with a higher score.
MD],
                        ['code', 'Checking for updates on Debian and Ubuntu', <<<'SH'
sudo apt update
apt list --upgradable
sudo apt upgrade
SH, 'bash'],
                    ],
                ],
            ],
        ],
    ],
];
