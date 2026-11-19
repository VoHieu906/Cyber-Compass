<?php

/*
| Career paths. `domains` maps domain => required level (1 foundation, 2 working, 3 deep).
| `stages` is the roadmap template: topics in learning order, grouped into stages.
*/

return [
    'soc-analyst' => [
        'title' => 'SOC Analyst',
        'summary' => 'Monitor alerts, investigate suspicious activity and escalate real incidents.',
        'description' => 'A Security Operations Center (SOC) analyst is often the first person to notice an attack. You triage alerts from monitoring tools, investigate logs and network activity to separate real threats from noise, and escalate confirmed incidents with clear evidence. It is one of the most common entry points into security.',
        'responsibilities' => [
            'Triage alerts from SIEM and endpoint detection tools',
            'Investigate logs, network traffic and host activity',
            'Decide whether activity is benign, suspicious or malicious',
            'Escalate incidents with a clear timeline and evidence',
            'Tune detection rules to reduce false positives',
        ],
        'domains' => ['networking' => 2, 'linux' => 2, 'security-fundamentals' => 2, 'operating-systems' => 1, 'programming' => 1, 'web-security' => 1],
        'skills' => ['core-protocols', 'ip-addressing', 'log-analysis', 'linux-cli', 'security-principles', 'security-frameworks', 'python-scripting'],
        'stages' => [
            ['security-principles', 'networking-basics', 'linux-shell'],
            ['addressing-and-services', 'linux-investigation', 'os-internals'],
            ['security-controls', 'python-scripting', 'web-basics'],
        ],
    ],
    'security-engineer' => [
        'title' => 'Security Engineer',
        'summary' => 'Design, build and maintain the controls that keep systems secure.',
        'description' => 'Security engineers turn security requirements into working systems: network segmentation, identity and access management, encryption, logging pipelines and automation. The role sits between architecture and operations and rewards broad technical knowledge plus the ability to script.',
        'responsibilities' => [
            'Design and implement security controls across networks and systems',
            'Manage encryption, certificates and secrets',
            'Build and maintain logging and detection infrastructure',
            'Automate security checks and configuration',
            'Review designs and changes for security risks',
        ],
        'domains' => ['networking' => 2, 'system-administration' => 2, 'cryptography' => 2, 'security-fundamentals' => 2, 'programming' => 2],
        'skills' => ['ip-addressing', 'system-hardening', 'encryption', 'tls', 'security-frameworks', 'python-scripting'],
        'stages' => [
            ['security-principles', 'networking-basics', 'linux-shell'],
            ['addressing-and-services', 'crypto-building-blocks', 'python-scripting', 'security-controls'],
            ['server-hardening', 'tls'],
        ],
    ],
    'penetration-tester' => [
        'title' => 'Penetration Tester',
        'summary' => 'Find and demonstrate vulnerabilities before attackers do, with permission.',
        'description' => 'Penetration testers are hired to attack systems, applications and networks under an agreed scope, then explain what they found and how to fix it. The work demands solid networking and web knowledge, comfort on Linux, scripting ability and clear report writing.',
        'responsibilities' => [
            'Plan tests within an agreed scope and rules of engagement',
            'Discover and exploit vulnerabilities in applications and networks',
            'Demonstrate business impact without causing damage',
            'Write reports with evidence, risk ratings and remediation advice',
            'Retest to confirm fixes',
        ],
        'domains' => ['web-security' => 3, 'networking' => 2, 'linux' => 2, 'programming' => 2, 'operating-systems' => 2],
        'skills' => ['http-fundamentals', 'owasp-top-10', 'injection-prevention', 'core-protocols', 'linux-cli', 'python-scripting', 'privilege-model'],
        'stages' => [
            ['security-principles', 'networking-basics', 'linux-shell', 'python-scripting'],
            ['addressing-and-services', 'web-basics', 'os-internals'],
            ['injection-attacks', 'linux-investigation', 'crypto-building-blocks'],
        ],
    ],
    'security-administrator' => [
        'title' => 'Security Administrator',
        'summary' => 'Keep systems, accounts and access configured securely day to day.',
        'description' => 'Security administrators run the everyday controls: user accounts and permissions, system hardening, patching, firewall rules and backups. The role suits people who like keeping systems reliable and who want security built into operations rather than added afterwards.',
        'responsibilities' => [
            'Manage user accounts, groups and access rights',
            'Harden and patch servers and workstations',
            'Maintain firewall and remote access configurations',
            'Review logs for policy violations and anomalies',
            'Document configurations and procedures',
        ],
        'domains' => ['system-administration' => 3, 'linux' => 2, 'networking' => 2, 'security-fundamentals' => 1],
        'skills' => ['system-hardening', 'ssh-administration', 'patch-management', 'linux-permissions', 'linux-cli', 'ip-addressing'],
        'stages' => [
            ['linux-shell', 'networking-basics', 'security-principles'],
            ['linux-investigation', 'addressing-and-services', 'os-internals', 'security-controls'],
            ['server-hardening'],
        ],
    ],
    'incident-responder' => [
        'title' => 'Incident Responder',
        'summary' => 'Contain attacks, find out what happened and get the organisation back on its feet.',
        'description' => 'When an incident is confirmed, incident responders take over: they contain the attacker, collect evidence, determine the scope and root cause, and coordinate recovery. The role combines deep technical investigation with calm communication under pressure.',
        'responsibilities' => [
            'Contain active incidents and limit damage',
            'Collect and preserve evidence from hosts and networks',
            'Reconstruct the attacker\'s actions and timeline',
            'Coordinate recovery with IT and business teams',
            'Lead post-incident reviews and improve defenses',
        ],
        'domains' => ['security-fundamentals' => 2, 'linux' => 2, 'operating-systems' => 2, 'networking' => 2, 'programming' => 1],
        'skills' => ['log-analysis', 'process-memory', 'privilege-model', 'core-protocols', 'risk-management', 'python-scripting'],
        'stages' => [
            ['security-principles', 'networking-basics', 'linux-shell'],
            ['os-internals', 'linux-investigation', 'addressing-and-services'],
            ['security-controls', 'python-scripting'],
        ],
    ],
    'digital-forensics-analyst' => [
        'title' => 'Digital Forensics Analyst',
        'summary' => 'Recover and analyse digital evidence so it holds up to scrutiny.',
        'description' => 'Forensics analysts examine disks, memory and logs to answer precise questions: what happened, when, and who did it. Evidence must be handled so that it stays intact and defensible. The role needs deep operating system knowledge, patience and careful documentation.',
        'responsibilities' => [
            'Acquire and preserve disk images, memory and logs',
            'Maintain chain of custody for evidence',
            'Analyse file systems, artefacts and timelines',
            'Recover deleted or hidden data',
            'Write findings that technical and legal readers can rely on',
        ],
        'domains' => ['operating-systems' => 3, 'linux' => 2, 'programming' => 2, 'cryptography' => 1, 'security-fundamentals' => 1],
        'skills' => ['process-memory', 'privilege-model', 'log-analysis', 'linux-cli', 'data-parsing', 'hashing'],
        'stages' => [
            ['os-internals', 'linux-shell', 'security-principles'],
            ['linux-investigation', 'python-scripting', 'crypto-building-blocks'],
            ['networking-basics'],
        ],
    ],
    'cloud-security-engineer' => [
        'title' => 'Cloud Security Engineer',
        'summary' => 'Secure workloads, identities and data on cloud platforms.',
        'description' => 'Cloud security engineers protect systems that run on providers such as AWS, Azure and Google Cloud. They design network boundaries and identity policies, encrypt data, automate secure configuration and monitor for misconfigurations, which cause most cloud breaches.',
        'responsibilities' => [
            'Design secure network and identity configurations in the cloud',
            'Enforce encryption for data in transit and at rest',
            'Automate secure infrastructure configuration',
            'Detect and fix misconfigurations',
            'Monitor cloud activity logs for threats',
        ],
        'domains' => ['networking' => 2, 'cryptography' => 2, 'system-administration' => 2, 'web-security' => 2, 'security-fundamentals' => 2],
        'skills' => ['ip-addressing', 'encryption', 'tls', 'system-hardening', 'http-fundamentals', 'security-frameworks'],
        'stages' => [
            ['security-principles', 'networking-basics', 'linux-shell'],
            ['addressing-and-services', 'crypto-building-blocks', 'web-basics', 'security-controls'],
            ['tls', 'server-hardening'],
        ],
    ],
];
