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
                    'minutes' => 12,
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
                        ['knowledge-check', 'Classify the incident', [
                            'questions' => [
                                [
                                    'prompt' => 'A flood of traffic takes the online shop offline for six hours. Which property was damaged?',
                                    'options' => [
                                        ['text' => 'Availability', 'correct' => true],
                                        ['text' => 'Confidentiality', 'correct' => false],
                                        ['text' => 'Integrity', 'correct' => false],
                                    ],
                                    'explanation' => 'Nothing was read or changed, but customers could not use the shop. Denial of service attacks target availability.',
                                ],
                                [
                                    'prompt' => 'An employee emails a spreadsheet of customer passwords to their personal address. Which property was damaged?',
                                    'options' => [
                                        ['text' => 'Confidentiality', 'correct' => true],
                                        ['text' => 'Integrity', 'correct' => false],
                                        ['text' => 'Availability', 'correct' => false],
                                    ],
                                    'explanation' => 'The data left the people allowed to see it. It was not altered and is still available, so this is a confidentiality breach.',
                                ],
                                [
                                    'prompt' => 'Ransomware encrypts the file server and the attackers also copy the files first, threatening to publish them. Which properties are damaged? Choose all that apply.',
                                    'options' => [
                                        ['text' => 'Availability', 'correct' => true],
                                        ['text' => 'Confidentiality', 'correct' => true],
                                        ['text' => 'Neither: the files still exist', 'correct' => false],
                                    ],
                                    'explanation' => 'Encrypting the files makes them unusable (availability), and copying them out exposes them (confidentiality). Modern ransomware often does both, which is why backups alone are not enough.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'threats-vulnerabilities-risk',
                    'title' => 'Threats, vulnerabilities and risk',
                    'minutes' => 18,
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
                        ['comparison', 'Three words people mix up', [
                            'columns' => ['Vulnerability', 'Threat', 'Risk'],
                            'rows' => [
                                ['label' => 'What it is', 'values' => ['A weakness', 'Anything that could exploit a weakness', 'How likely harm is, combined with how bad it would be']],
                                ['label' => 'Example', 'values' => ['An unpatched VPN appliance', 'A ransomware group scanning for that flaw', 'High: the flaw is exploited daily and operations would stop']],
                                ['label' => 'Can you change it?', 'values' => ['Yes: patch, reconfigure, train', 'Rarely: you can deter or detect it, not remove it', 'Yes, by lowering the likelihood or the impact']],
                            ],
                        ]],
                        ['step-through', 'A quick risk estimate', [
                            'steps' => [
                                ['title' => 'Describe the scenario', 'text' => 'Ransomware enters through an unpatched VPN appliance. Write it as a sentence: a **threat** exploiting a **vulnerability**, causing a **harm**.', 'state' => [['label' => 'Likelihood', 'value' => '?'], ['label' => 'Impact', 'value' => '?'], ['label' => 'Score', 'value' => '?'], ['label' => 'Rating', 'value' => 'Not rated']]],
                                ['title' => 'Rate the likelihood', 'text' => 'From 1 (rare) to 5 (almost certain). The flaw is public and actively exploited, and the appliance faces the internet: **4**.', 'state' => [['label' => 'Likelihood', 'value' => '4'], ['label' => 'Impact', 'value' => '?'], ['label' => 'Score', 'value' => '?'], ['label' => 'Rating', 'value' => 'Not rated']]],
                                ['title' => 'Rate the impact', 'text' => 'From 1 (minor) to 5 (severe). Encrypted file servers would stop operations for days: **5**.', 'state' => [['label' => 'Likelihood', 'value' => '4'], ['label' => 'Impact', 'value' => '5'], ['label' => 'Score', 'value' => '?'], ['label' => 'Rating', 'value' => 'Not rated']]],
                                ['title' => 'Score it', 'text' => 'Multiply for a score out of 25: 4 × 5 = 20. On a common scale, 15 and above is critical, so this goes to the top of the list.', 'parts' => [['label' => 'Likelihood 4', 'tone' => 'muted'], ['label' => '×', 'tone' => 'plain'], ['label' => 'Impact 5', 'tone' => 'muted'], ['label' => '= 20', 'tone' => 'attn']], 'state' => [['label' => 'Likelihood', 'value' => '4'], ['label' => 'Impact', 'value' => '5'], ['label' => 'Score', 'value' => '20'], ['label' => 'Rating', 'value' => 'Critical']]],
                                ['title' => 'Respond, then re-assess', 'text' => 'Mitigate by patching tonight and restricting who can reach the VPN. The impact if it happened is unchanged, but the likelihood drops to 1. What remains is the **residual risk**, which someone with authority accepts.', 'parts' => [['label' => 'Likelihood 1', 'tone' => 'muted'], ['label' => '×', 'tone' => 'plain'], ['label' => 'Impact 5', 'tone' => 'muted'], ['label' => '= 5', 'tone' => 'accent']], 'state' => [['label' => 'Likelihood', 'value' => '1'], ['label' => 'Impact', 'value' => '5'], ['label' => 'Score', 'value' => '5'], ['label' => 'Rating', 'value' => 'Low (residual)']]],
                            ],
                        ]],
                        ['flow', 'Choosing a response', [
                            'direction' => 'down',
                            'nodes' => [
                                ['id' => 'assessed', 'label' => 'Risk assessed', 'kind' => 'start'],
                                ['id' => 'appetite', 'label' => 'Above what the business will tolerate?', 'kind' => 'decision', 'detail' => 'The risk appetite is set by leadership, not by the security team: how much risk the organisation is willing to carry to do its work.'],
                                ['id' => 'stop', 'label' => 'Can you stop the risky activity?', 'kind' => 'decision'],
                                ['id' => 'reduce', 'label' => 'Cheaper to reduce it yourself?', 'kind' => 'decision'],
                                ['id' => 'avoid', 'label' => 'Avoid: stop doing it', 'kind' => 'end', 'detail' => 'Example: retire the old file-sharing service instead of securing it.'],
                                ['id' => 'mitigate', 'label' => 'Mitigate: add controls', 'kind' => 'step', 'detail' => 'Example: patch, add multi-factor authentication, segment the network. Then re-assess what is left.'],
                                ['id' => 'transfer', 'label' => 'Transfer: insurance or contract', 'kind' => 'end', 'detail' => 'Example: cyber insurance, or a contract that makes a supplier liable. The financial impact moves; the incident can still happen.'],
                                ['id' => 'accept', 'label' => 'Accept: document and review', 'kind' => 'end', 'detail' => 'A named owner signs off, with a date to look at it again. Accepting is a decision, not neglect.'],
                            ],
                            'edges' => [
                                ['from' => 'assessed', 'to' => 'appetite'],
                                ['from' => 'appetite', 'to' => 'accept', 'label' => 'No'],
                                ['from' => 'appetite', 'to' => 'stop', 'label' => 'Yes'],
                                ['from' => 'stop', 'to' => 'avoid', 'label' => 'Yes'],
                                ['from' => 'stop', 'to' => 'reduce', 'label' => 'No'],
                                ['from' => 'reduce', 'to' => 'mitigate', 'label' => 'Yes'],
                                ['from' => 'reduce', 'to' => 'transfer', 'label' => 'No'],
                                ['from' => 'mitigate', 'to' => 'appetite', 'label' => 'Residual risk'],
                            ],
                        ]],
                        ['callout', 'Accepting risk is a decision', 'Leaving a low risk unfixed is fine when it is a conscious, documented choice by someone with the authority to make it. Ignoring a risk nobody assessed is not.', 'info'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'Your web server runs an outdated version of its software with a known flaw. Is that a threat, a vulnerability or a risk?',
                                    'options' => [
                                        ['text' => 'A vulnerability', 'correct' => true],
                                        ['text' => 'A threat', 'correct' => false],
                                        ['text' => 'A risk', 'correct' => false],
                                    ],
                                    'explanation' => 'The outdated software is a weakness. The threat is whoever might exploit it, and the risk is how likely that is combined with the damage it would do.',
                                ],
                                [
                                    'prompt' => 'A company buys cyber insurance to cover the cost of a data breach. Which response is that?',
                                    'options' => [
                                        ['text' => 'Transfer', 'correct' => true],
                                        ['text' => 'Mitigate', 'correct' => false],
                                        ['text' => 'Avoid', 'correct' => false],
                                        ['text' => 'Accept', 'correct' => false],
                                    ],
                                    'explanation' => 'Insurance moves the financial impact to someone else. It does not make a breach less likely, so it is often combined with mitigation.',
                                ],
                            ],
                        ]],
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
                    'minutes' => 16,
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
                        ['scene-3d', 'The layers around your data', [
                            'title' => 'Defense in depth: layers around the data',
                            'description' => 'The data sits at the centre as a gold cube, surrounded by five see-through shells, one inside the other. From the outside in: policies and people, the perimeter, the internal network, endpoints and applications. An attacker, drawn as a red cone outside all the shells, sends red dots towards the data, and every dot has to pass through all five shells. The guided steps peel the shells away one at a time, explaining what each layer does and how it can stop or slow the attack, until only the data and its own protections, encryption and backups, are left.',
                            'aspect' => '16:9',
                            'camera' => ['position' => [0, 4.2, 11], 'target' => [0, 0.6, 0], 'fov' => 45],
                            'objects' => [
                                ['id' => 'data', 'shape' => 'box', 'size' => [1, 1, 1], 'position' => [0, 0, 0], 'color' => '#f59e0b', 'emissive' => true, 'text' => 'Data', 'animation' => ['type' => 'spin', 'axis' => 'y', 'speed' => 0.4], 'info' => ['title' => 'The data', 'text' => 'What the attacker wants. Its own protections are the last layer: encryption makes stolen copies useless, and tested backups bring it back after ransomware.']],
                                ['id' => 'application', 'shape' => 'sphere', 'size' => [2.4, 2.4, 2.4], 'color' => '#22c55e', 'wireframe' => true, 'opacity' => 0.45, 'text' => 'Application', 'info' => ['title' => 'Application layer', 'text' => 'Multi-factor authentication, input validation and least-privilege database accounts.']],
                                ['id' => 'endpoint', 'shape' => 'sphere', 'size' => [3.8, 3.8, 3.8], 'color' => '#14b8a6', 'wireframe' => true, 'opacity' => 0.4, 'text' => 'Endpoint', 'info' => ['title' => 'Endpoint layer', 'text' => 'Patching, endpoint detection and response (EDR), disk encryption and no local admin rights.']],
                                ['id' => 'network', 'shape' => 'sphere', 'size' => [5.2, 5.2, 5.2], 'color' => '#0ea5e9', 'wireframe' => true, 'opacity' => 0.35, 'text' => 'Internal network', 'info' => ['title' => 'Internal network', 'text' => 'Segmentation into subnets, internal firewall rules and network monitoring.']],
                                ['id' => 'perimeter', 'shape' => 'sphere', 'size' => [6.6, 6.6, 6.6], 'color' => '#f97316', 'wireframe' => true, 'opacity' => 0.3, 'text' => 'Perimeter', 'info' => ['title' => 'Perimeter', 'text' => 'The edge firewall, email filtering and a VPN with multi-factor authentication.']],
                                ['id' => 'policies', 'shape' => 'sphere', 'size' => [8, 8, 8], 'color' => '#a78bfa', 'wireframe' => true, 'opacity' => 0.25, 'text' => 'Policies and people', 'info' => ['title' => 'Policies and people', 'text' => 'Security policies, awareness training and a culture where reporting a suspicious email is easy.']],
                                ['id' => 'attacker', 'shape' => 'cone', 'size' => [0.6, 0.9, 0.6], 'position' => [7.5, 0, 0], 'rotation' => [0, 0, 90], 'color' => '#ef4444', 'text' => 'Attacker', 'info' => ['title' => 'Attacker', 'text' => 'Has to defeat every layer between them and the data. Each layer is a separate chance to be stopped or noticed.']],
                            ],
                            'links' => [
                                ['from' => 'attacker', 'to' => 'data', 'color' => '#ef4444', 'dashed' => true, 'flow' => ['count' => 3, 'speed' => 0.6, 'color' => '#ef4444']],
                            ],
                            'steps' => [
                                ['title' => 'Five layers and the data', 'text' => 'An attack has to cross every shell to reach the data. Step through to peel them away from the outside in.'],
                                ['title' => 'Policies and people', 'text' => 'The attack starts with a phishing email. Awareness training is a layer: a trained employee who reports the email can stop the attack before any technology is involved.', 'highlight' => ['policies']],
                                ['title' => 'Perimeter', 'text' => 'Email filtering catches many phishing messages, and the edge firewall blocks direct connections from outside. This one got through: the link was new and looked clean.', 'hide' => ['policies'], 'highlight' => ['perimeter'], 'camera' => ['position' => [0, 4.5, 11], 'target' => [0, 0.6, 0]]],
                                ['title' => 'Internal network', 'text' => 'The attacker now controls one laptop. Segmentation means it cannot reach the servers directly, and network monitoring can notice it scanning.', 'hide' => ['perimeter'], 'highlight' => ['network'], 'camera' => ['position' => [0, 4, 9], 'target' => [0, 0.5, 0]]],
                                ['title' => 'Endpoint', 'text' => 'Endpoint detection flags the unusual process on the laptop, and because the user has no admin rights, the malware cannot install itself deeply.', 'hide' => ['network'], 'highlight' => ['endpoint'], 'camera' => ['position' => [0, 3.5, 7], 'target' => [0, 0.4, 0]]],
                                ['title' => 'Application', 'text' => 'The stolen password alone is not enough: the application asks for a second factor the attacker does not have.', 'hide' => ['endpoint'], 'highlight' => ['application'], 'camera' => ['position' => [0, 3, 5.5], 'target' => [0, 0.3, 0]]],
                                ['title' => 'The data itself', 'text' => 'If everything else fails, encryption makes stolen copies unreadable and tested backups restore what ransomware destroys. No single layer had to be perfect.', 'hide' => ['application'], 'highlight' => ['data'], 'camera' => ['position' => [0, 2.5, 4.5], 'target' => [0, 0, 0]]],
                            ],
                        ]],
                        ['prose', 'Least privilege', <<<'MD'
Give every user, service and process only the access it needs, for only as long as it needs it. A web application that only reads from a database should not connect as a database administrator. When that application is compromised, least privilege decides whether the attacker gets one table or everything.
MD],
                        ['comparison', 'Three kinds of control', [
                            'columns' => ['Preventive', 'Detective', 'Corrective'],
                            'rows' => [
                                ['label' => 'Goal', 'values' => ['Stop the attack', 'Notice the attack', 'Limit the damage and recover']],
                                ['label' => 'Acts', 'values' => ['Before', 'During', 'After']],
                                ['label' => 'Examples', 'values' => ['Firewalls, multi-factor authentication, input validation', 'Log monitoring, intrusion detection, alerts', 'Backups, incident response, patching']],
                            ],
                        ]],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A reporting service only reads from the sales database. Which account should it use?',
                                    'options' => [
                                        ['text' => 'An account that can only read the tables it reports on', 'correct' => true],
                                        ['text' => 'The database administrator account, so it never breaks', 'correct' => false],
                                        ['text' => 'A shared account used by every internal service', 'correct' => false],
                                    ],
                                    'explanation' => 'Least privilege: if the reporting service is compromised, the attacker gets read access to a few tables, not control of the whole database.',
                                ],
                                [
                                    'prompt' => 'Which of these are detective controls? Choose all that apply.',
                                    'options' => [
                                        ['text' => 'An alert when an account logs in from two countries within an hour', 'correct' => true],
                                        ['text' => 'An intrusion detection system on the server network', 'correct' => true],
                                        ['text' => 'Multi-factor authentication', 'correct' => false],
                                        ['text' => 'Restoring from last night\'s backup', 'correct' => false],
                                    ],
                                    'explanation' => 'Alerts and intrusion detection notice an attack in progress. Multi-factor authentication prevents one; restoring a backup corrects the damage afterwards.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'security-frameworks',
                    'title' => 'Security frameworks',
                    'minutes' => 14,
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
                        ['comparison', 'Three you will meet early', [
                            'columns' => ['NIST CSF 2.0', 'CIS Controls v8.1', 'MITRE ATT&CK'],
                            'rows' => [
                                ['label' => 'What it is', 'values' => ['A framework of security outcomes for any organisation', 'A prioritised list of practical safeguards', 'A knowledge base of how real attackers operate']],
                                ['label' => 'Organised as', 'values' => ['Six functions: Govern, Identify, Protect, Detect, Respond, Recover', '18 controls; safeguards grouped into Implementation Groups 1 to 3', 'Tactics (the goal) and techniques (the method), each with an ID such as T1110']],
                                ['label' => 'Answers', 'values' => ['Are we covering everything, and how mature are we?', 'What should we do first?', 'How would an attacker do this, and would we see it?']],
                                ['label' => 'Used by', 'values' => ['Managers reporting to leadership', 'Administrators hardening systems', 'SOC analysts and threat hunters']],
                            ],
                        ]],
                        ['tree', 'How ATT&CK is organised', [
                            'nodes' => [
                                ['id' => 'enterprise', 'label' => 'Enterprise ATT&CK', 'note' => 'matrix'],
                                ['id' => 'initial', 'label' => 'Initial Access', 'parent' => 'enterprise', 'note' => 'TA0001', 'detail' => 'A tactic: the attacker\'s goal at this stage, getting into the network.'],
                                ['id' => 'phishing', 'label' => 'Phishing', 'parent' => 'initial', 'note' => 'T1566', 'detail' => 'A technique for that goal: messages that trick a user into running malware or giving away credentials.'],
                                ['id' => 'persistence', 'label' => 'Persistence', 'parent' => 'enterprise', 'note' => 'TA0003', 'detail' => 'The goal of keeping access after a reboot or a password change.'],
                                ['id' => 'schtask', 'label' => 'Scheduled Task/Job', 'parent' => 'persistence', 'note' => 'T1053', 'detail' => 'Making the system run the attacker\'s program on a schedule or at start-up.'],
                                ['id' => 'account', 'label' => 'Create Account', 'parent' => 'persistence', 'note' => 'T1136', 'detail' => 'Adding an account the attacker controls, so access survives even if the original foothold is found.'],
                                ['id' => 'creds', 'label' => 'Credential Access', 'parent' => 'enterprise', 'note' => 'TA0006', 'detail' => 'The goal of stealing account names and passwords.'],
                                ['id' => 'brute', 'label' => 'Brute Force', 'parent' => 'creds', 'note' => 'T1110', 'detail' => 'Guessing passwords, or trying leaked passwords against many accounts.'],
                                ['id' => 'dumping', 'label' => 'OS Credential Dumping', 'parent' => 'creds', 'note' => 'T1003', 'detail' => 'Reading credentials out of the operating system\'s memory or files.'],
                            ],
                        ]],
                        ['callout', 'In daily work', 'A SOC analyst tags an alert with an ATT&CK technique ID. A security manager reports maturity against NIST CSF functions. An administrator works through the CIS Controls when hardening systems.', 'info'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'An alert fires: many failed logins against many accounts, each tried with the same common password. Which ATT&CK technique is that?',
                                    'options' => [
                                        ['text' => 'Brute Force (T1110)', 'correct' => true],
                                        ['text' => 'Phishing (T1566)', 'correct' => false],
                                        ['text' => 'Create Account (T1136)', 'correct' => false],
                                    ],
                                    'explanation' => 'Trying one common password against many accounts is password spraying, a sub-technique of Brute Force (T1110.003).',
                                ],
                                [
                                    'prompt' => 'Which NIST CSF 2.0 function covers restoring systems from backup after an incident?',
                                    'options' => [
                                        ['text' => 'Recover', 'correct' => true],
                                        ['text' => 'Respond', 'correct' => false],
                                        ['text' => 'Protect', 'correct' => false],
                                    ],
                                    'explanation' => 'Respond is about containing and analysing the incident; Recover is about bringing systems and operations back.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
    ],
];
