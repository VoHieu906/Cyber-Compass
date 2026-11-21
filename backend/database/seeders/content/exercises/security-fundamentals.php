<?php

/*
 * Exercises for the Security Fundamentals lessons (ARCHITECTURE D29 to D31). See
 * networking-fundamentals.php for the format and rules.
 */

return [
    'cia-triad' => [
        'questions' => [
            'triad-words' => [
                'type' => 'fill_blank',
                'prompt' => 'Complete the name of the triad.',
                'text' => 'The CIA triad stands for confidentiality, ___ and ___.',
                'answers' => [['integrity'], ['availability']],
                'explanation' => 'Confidentiality, integrity and availability: almost every incident damages at least one of them.',
                'section' => 'three-properties',
            ],
            'control-to-property' => [
                'type' => 'matching',
                'prompt' => 'Match each control to the property it mainly protects.',
                'pairs' => [
                    ['Encryption', 'Confidentiality'],
                    ['Hashing and signatures', 'Integrity'],
                    ['Backups', 'Availability'],
                ],
                'explanation' => 'Encryption keeps data unreadable to outsiders, hashes and signatures reveal changes, and backups bring systems back when they are lost.',
                'section' => 'explore-the-triad',
            ],
            'changed-invoice' => [
                'type' => 'scenario',
                'scenario' => 'An attacker breaks into a supplier\'s mailbox and changes the bank account number on an invoice before it reaches your finance team.',
                'prompt' => 'Which property was damaged?',
                'options' => ['Integrity', 'Confidentiality', 'Availability'],
                'answer' => 0,
                'explanation' => 'The information was altered by someone not authorised to change it. Integrity means data stays accurate and unaltered.',
                'section' => 'explore-the-triad',
            ],
            'confidentiality-controls' => [
                'type' => 'multi_choice',
                'prompt' => 'Which controls mainly protect confidentiality? Choose all that apply.',
                'options' => ['Encryption', 'Access control', 'Multi-factor authentication', 'Redundant servers', 'Change logging'],
                'answer' => [0, 1, 2],
                'explanation' => 'Encryption, access control and multi-factor authentication keep data away from people who should not read it. Redundancy protects availability; change logging supports integrity.',
                'section' => 'explore-the-triad',
            ],
            'lost-key' => [
                'type' => 'true_false',
                'prompt' => 'Encrypting everything can hurt availability if the key is lost.',
                'answer' => true,
                'explanation' => 'The properties pull against each other: encrypted data with no key is unusable. Good security balances the three.',
                'section' => 'they-pull-against-each-other',
            ],
        ],
    ],

    'threats-vulnerabilities-risk' => [
        'questions' => [
            'name-it' => [
                'type' => 'matching',
                'prompt' => 'Match each item to the word that describes it.',
                'pairs' => [
                    ['A server still using its default admin password', 'Vulnerability'],
                    ['A botnet trying default passwords across the internet', 'Threat'],
                    ['How likely a takeover is, combined with how bad it would be', 'Risk'],
                ],
                'explanation' => 'A vulnerability is a weakness, a threat is whatever could exploit it, and risk combines likelihood and impact.',
                'section' => 'three-words-people-mix-up',
            ],
            'score-it' => [
                'type' => 'fill_blank',
                'prompt' => 'Score the risk on the 5 by 5 scale.',
                'text' => 'With a likelihood of 3 and an impact of 4, the risk score is ___ out of 25.',
                'answers' => [['12']],
                'explanation' => 'Multiply likelihood by impact: 3 × 4 = 12.',
                'section' => 'a-quick-risk-estimate',
            ],
            'pick-a-response' => [
                'type' => 'matching',
                'prompt' => 'Match each action to the risk response it is.',
                'pairs' => [
                    ['Retire an old file-sharing service instead of securing it', 'Avoid'],
                    ['Add multi-factor authentication', 'Mitigate'],
                    ['Make a supplier contractually liable for breaches', 'Transfer'],
                    ['Have the risk owner sign off on a low risk, with a review date', 'Accept'],
                ],
                'explanation' => 'Avoiding stops the activity, mitigating adds controls, transferring moves the financial impact elsewhere, and accepting is a documented decision.',
                'section' => 'choosing-a-response',
            ],
            'after-the-patch' => [
                'type' => 'scenario',
                'scenario' => 'After patching the VPN appliance and limiting who can reach it, the likelihood of ransomware getting in that way drops from 4 to 1. The impact if it happened is still 5.',
                'prompt' => 'What is left, and what is its score?',
                'options' => ['Residual risk, scored 5', 'Inherent risk, scored 20', 'Residual risk, scored 1', 'No risk: it has been mitigated'],
                'answer' => 0,
                'explanation' => 'Mitigation lowered the likelihood, not the impact: 1 × 5 = 5. What remains after controls is the residual risk, which someone with authority accepts.',
                'section' => 'a-quick-risk-estimate',
            ],
            'unassessed' => [
                'type' => 'true_false',
                'prompt' => 'Leaving a risk unfixed is fine as long as nobody has assessed it.',
                'answer' => false,
                'explanation' => 'Accepting a risk is a conscious, documented decision by someone with authority. Ignoring a risk nobody assessed is neglect.',
                'section' => 'accepting-risk-is-a-decision',
            ],
        ],
    ],

    'defense-in-depth' => [
        'questions' => [
            'control-kinds' => [
                'type' => 'matching',
                'prompt' => 'Match each control to its kind.',
                'pairs' => [
                    ['A firewall rule that blocks a port', 'Preventive'],
                    ['An intrusion detection alert', 'Detective'],
                    ['Restoring a server from backup', 'Corrective'],
                ],
                'explanation' => 'Preventive controls stop the attack, detective controls notice it, and corrective controls limit the damage and recover.',
                'section' => 'three-kinds-of-control',
            ],
            'read-only-app' => [
                'type' => 'scenario',
                'scenario' => 'A web shop only reads product listings, but connects to its database as the database administrator. An attacker finds an injection flaw in the shop.',
                'prompt' => 'What would least privilege have changed?',
                'options' => [
                    'With a read-only account, the attacker would get the listings, not control of the whole database',
                    'Nothing: an injection flaw gives full control whatever the account',
                    'The attacker could not have found the flaw',
                ],
                'answer' => 0,
                'explanation' => 'The flaw still exists, but least privilege decides what the attacker gets through it: one table to read, or everything.',
                'section' => 'least-privilege',
            ],
            'outside-in' => [
                'type' => 'ordering',
                'prompt' => 'Put the layers an attack has to cross in order, from the outside in.',
                'items' => ['Policies and people', 'Perimeter', 'Internal network', 'Endpoint', 'Application', 'Data'],
                'explanation' => 'Training and policy come first, then the perimeter, the internal network, the endpoint and the application, with encryption and backups protecting the data at the centre.',
                'section' => 'the-layers-around-your-data',
            ],
            'controls-fail' => [
                'type' => 'true_false',
                'prompt' => 'Defense in depth assumes that every control eventually fails.',
                'answer' => true,
                'explanation' => 'That is the point of layering independent controls: no single layer has to be perfect.',
                'section' => 'layers-not-walls',
            ],
            'preventive' => [
                'type' => 'multi_choice',
                'prompt' => 'Which of these are preventive controls? Choose all that apply.',
                'options' => ['Multi-factor authentication', 'Input validation', 'Log monitoring', 'Incident response'],
                'answer' => [0, 1],
                'explanation' => 'Multi-factor authentication and input validation stop attacks before they succeed. Monitoring is detective; incident response is corrective.',
                'section' => 'three-kinds-of-control',
            ],
        ],
    ],

    'security-frameworks' => [
        'questions' => [
            'which-framework' => [
                'type' => 'matching',
                'prompt' => 'Match each question to the framework that answers it.',
                'pairs' => [
                    ['What should we do first?', 'CIS Controls'],
                    ['Are we covering everything, and how mature are we?', 'NIST CSF'],
                    ['How would an attacker do this, and would we see it?', 'MITRE ATT&CK'],
                ],
                'explanation' => 'The CIS Controls are a prioritised list of safeguards, NIST CSF describes outcomes to cover, and ATT&CK catalogues how real attackers operate.',
                'section' => 'three-you-will-meet-early',
            ],
            'csf-functions' => [
                'type' => 'multi_choice',
                'prompt' => 'Which of these are functions of NIST CSF 2.0? Choose all that apply.',
                'options' => ['Govern', 'Detect', 'Recover', 'Exploit', 'Persist'],
                'answer' => [0, 1, 2],
                'explanation' => 'The six functions are Govern, Identify, Protect, Detect, Respond and Recover. Exploit and Persist describe attacker behaviour, not a defender\'s framework.',
                'section' => 'three-you-will-meet-early',
            ],
            'technique-to-tactic' => [
                'type' => 'matching',
                'prompt' => 'Match each ATT&CK technique to its tactic.',
                'pairs' => [
                    ['Phishing (T1566)', 'Initial Access'],
                    ['Create Account (T1136)', 'Persistence'],
                    ['Brute Force (T1110)', 'Credential Access'],
                ],
                'explanation' => 'Phishing gets the attacker in, a new account keeps access, and guessing passwords steals credentials.',
                'section' => 'how-attck-is-organised',
            ],
            'tactic-and-technique' => [
                'type' => 'true_false',
                'prompt' => 'In ATT&CK, a tactic is the attacker\'s goal and a technique is the method used to reach it.',
                'answer' => true,
                'explanation' => 'Initial Access is a tactic (the goal); Phishing is one technique for reaching it.',
                'section' => 'how-attck-is-organised',
            ],
            'id-prefixes' => [
                'type' => 'fill_blank',
                'prompt' => 'Complete the ID formats.',
                'text' => 'ATT&CK technique IDs start with ___, such as T1110, and tactic IDs start with ___, such as TA0006.',
                'answers' => [['T'], ['TA']],
                'explanation' => 'Techniques are numbered T followed by digits; tactics are numbered TA followed by digits.',
                'section' => 'how-attck-is-organised',
            ],
            'tag-the-alert' => [
                'type' => 'scenario',
                'scenario' => 'A SOC analyst sees an alert: on a file server, a scheduled task was created that runs an unknown program at every start-up.',
                'prompt' => 'Which ATT&CK tactic does this activity serve?',
                'options' => ['Persistence', 'Initial Access', 'Credential Access'],
                'answer' => 0,
                'explanation' => 'Making the system run the attacker\'s program at start-up keeps access after a reboot: that is Persistence (technique T1053).',
                'section' => 'how-attck-is-organised',
            ],
        ],
    ],
];
