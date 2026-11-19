<?php

return [
    'slug' => 'security-fundamentals',
    'domain' => 'security-fundamentals',
    'title' => 'Security Fundamentals',
    'summary' => 'The principles, vocabulary and frameworks shared by every security role.',
    'description' => 'Before tools and techniques comes a way of thinking. This course covers what security is protecting, how to reason about threats and risk, why layered defenses work, and how frameworks help teams organise their controls.',
    'difficulty' => 1,
    'published_at' => '2026-11-03',
    'objectives' => [
        'Explain confidentiality, integrity and availability with real examples',
        'Distinguish threats, vulnerabilities and risks',
        'Describe defense in depth and least privilege',
        'Recognise the purpose of common security frameworks',
    ],
    'skills' => ['security-principles', 'risk-management', 'security-frameworks'],
    'prerequisites' => [],
    'chapters' => [
        [
            'title' => 'Core principles',
            'lessons' => [
                [
                    'slug' => 'cia-triad',
                    'title' => 'The CIA triad',
                    'minutes' => 10,
                    'summary' => 'Confidentiality, integrity and availability: the three properties security protects.',
                    'objectives' => ['Define confidentiality, integrity and availability', 'Classify an incident by the property it affects', 'Give a control that protects each property'],
                    'takeaways' => [
                        'Confidentiality keeps data from people who should not see it.',
                        'Integrity keeps data accurate and unaltered by unauthorised changes.',
                        'Availability keeps systems and data usable when they are needed.',
                    ],
                    'resources' => [
                        ['NIST glossary: confidentiality, integrity, availability', 'https://csrc.nist.gov/glossary'],
                    ],
                    'sections' => [
                        ['prose', 'Three properties', <<<'MD'
Security exists to protect information and the systems that handle it. Almost every incident damages at least one of three properties, together known as the **CIA triad**.
MD],
                        ['diagram', 'Explore the triad', 'cia-triad', 'Select each property to see what it means, an example of it being broken, and controls that protect it.', [
                            'properties' => [
                                ['key' => 'confidentiality', 'name' => 'Confidentiality', 'definition' => 'Only authorised people can read the information.', 'example' => 'A stolen laptop with an unencrypted disk exposes customer records.', 'controls' => ['Encryption', 'Access control', 'Multi-factor authentication']],
                                ['key' => 'integrity', 'name' => 'Integrity', 'definition' => 'Information stays accurate and is only changed by authorised people.', 'example' => 'An attacker changes the bank account number on an invoice.', 'controls' => ['Hashing and signatures', 'Change logging', 'Input validation']],
                                ['key' => 'availability', 'name' => 'Availability', 'definition' => 'Systems and information are usable when they are needed.', 'example' => 'Ransomware encrypts file servers and halts operations.', 'controls' => ['Backups', 'Redundancy', 'DDoS protection']],
                            ],
                        ]],
                        ['callout', 'They pull against each other', 'More controls usually mean less convenience. Encrypting everything protects confidentiality but a lost key destroys availability. Good security balances the three for what the business actually needs.', 'info'],
                    ],
                ],
                [
                    'slug' => 'threats-vulnerabilities-risk',
                    'title' => 'Threats, vulnerabilities and risk',
                    'minutes' => 12,
                    'summary' => 'The vocabulary used to decide which problems to fix first.',
                    'objectives' => ['Distinguish a threat, a vulnerability and a risk', 'Estimate risk from likelihood and impact', 'Choose a response: mitigate, transfer, avoid or accept'],
                    'takeaways' => [
                        'A vulnerability is a weakness; a threat is something that could exploit it; risk combines likelihood and impact.',
                        'Risk can be mitigated, transferred, avoided or accepted, but never eliminated.',
                        'Prioritising by risk keeps effort on the problems that matter most.',
                    ],
                    'resources' => [
                        ['NIST SP 800-30: Guide for Conducting Risk Assessments', 'https://csrc.nist.gov/pubs/sp/800/30/r1/final'],
                    ],
                    'sections' => [
                        ['prose', 'Three words people mix up', <<<'MD'
- A **vulnerability** is a weakness: an unpatched server, a weak password, an open port.
- A **threat** is anything that could exploit it: a criminal group, a malicious insider, a flood.
- **Risk** is the chance that a threat exploits a vulnerability, combined with how bad the result would be.
MD],
                        ['steps', 'A quick risk estimate', [
                            'Describe the scenario: ransomware enters through an unpatched VPN appliance.',
                            'Rate the likelihood from 1 (rare) to 5 (almost certain): the flaw is public and actively exploited, so 4.',
                            'Rate the impact from 1 (minor) to 5 (severe): operations would stop for days, so 5.',
                            'Multiply for a score out of 25: 4 x 5 = 20, which is critical.',
                            'Choose a response: mitigate by patching tonight.',
                        ]],
                        ['callout', 'Accepting risk is a decision', 'Leaving a low risk unfixed is fine when it is a conscious, documented choice by someone with the authority to make it. Ignoring a risk nobody assessed is not.', 'info'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Building defenses',
            'lessons' => [
                [
                    'slug' => 'defense-in-depth',
                    'title' => 'Defense in depth and least privilege',
                    'minutes' => 12,
                    'summary' => 'Why no single control is enough, and why every account should have only what it needs.',
                    'objectives' => ['Explain defense in depth with layered controls', 'Apply least privilege to an account or service', 'Classify controls as preventive, detective or corrective'],
                    'takeaways' => [
                        'Layers of independent controls mean one failure does not lead to a breach.',
                        'Least privilege limits how far an attacker can go with a stolen account.',
                        'Preventive controls stop attacks, detective controls notice them, corrective controls recover.',
                    ],
                    'resources' => [
                        ['CIS Critical Security Controls', 'https://www.cisecurity.org/controls'],
                    ],
                    'sections' => [
                        ['prose', 'Layers, not walls', <<<'MD'
Every control eventually fails: a firewall rule is misconfigured, a user clicks a phishing link. **Defense in depth** layers independent controls so an attacker has to get past several of them. A typical set: a firewall at the edge, multi-factor authentication on accounts, endpoint protection on laptops, encryption on data and monitoring across all of it.
MD],
                        ['prose', 'Least privilege', <<<'MD'
Give every user, service and process only the access it needs, for only as long as it needs it. A web application that only reads from a database should not connect as a database administrator. When that application is compromised, least privilege decides whether the attacker gets one table or everything.
MD],
                        ['prose', 'Three kinds of control', <<<'MD'
- **Preventive** controls stop an attack: firewalls, MFA, input validation.
- **Detective** controls notice one in progress: log monitoring, intrusion detection, alerts.
- **Corrective** controls limit damage and recover: backups, incident response, patching.
MD],
                    ],
                ],
                [
                    'slug' => 'security-frameworks',
                    'title' => 'Security frameworks',
                    'minutes' => 10,
                    'summary' => 'How NIST CSF, CIS Controls and MITRE ATT&CK help teams organise their work.',
                    'objectives' => ['Describe the functions of the NIST Cybersecurity Framework', 'Explain what the CIS Controls prioritise', 'Explain how MITRE ATT&CK describes attacker behaviour'],
                    'takeaways' => [
                        'NIST CSF 2.0 organises security into six functions: Govern, Identify, Protect, Detect, Respond, Recover.',
                        'The CIS Controls are a prioritised list of practical safeguards.',
                        'MITRE ATT&CK catalogues real attacker tactics and techniques.',
                    ],
                    'resources' => [
                        ['NIST Cybersecurity Framework', 'https://www.nist.gov/cyberframework'],
                        ['MITRE ATT&CK', 'https://attack.mitre.org/'],
                    ],
                    'sections' => [
                        ['prose', 'Why frameworks exist', <<<'MD'
Security is too broad to manage from memory. Frameworks give teams a shared structure: what to cover, in what order, and how to describe progress to people outside security.
MD],
                        ['prose', 'Three you will meet early', <<<'MD'
- The **NIST Cybersecurity Framework** (version 2.0) groups outcomes into six functions: Govern, Identify, Protect, Detect, Respond and Recover.
- The **CIS Critical Security Controls** are a prioritised list of safeguards, starting with knowing which hardware and software you have.
- **MITRE ATT&CK** is a knowledge base of how real attackers operate, organised into tactics (the goal, such as persistence) and techniques (the method, such as a scheduled task).
MD],
                        ['callout', 'In daily work', 'A SOC analyst tags an alert with an ATT&CK technique ID. A security manager reports maturity against NIST CSF functions. An administrator works through the CIS Controls when hardening systems.', 'info'],
                    ],
                ],
            ],
        ],
    ],
];
