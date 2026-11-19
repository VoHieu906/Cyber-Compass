<?php

return [
    'slug' => 'python-for-security',
    'domain' => 'programming',
    'title' => 'Python for Security Automation',
    'summary' => 'Write small Python scripts that parse logs, extract indicators and query web services.',
    'description' => 'Security work is full of repetitive tasks: searching logs, pulling IP addresses out of reports, checking a list of URLs. This course teaches the Python you need to automate them safely, with every example built around a realistic security task.',
    'difficulty' => 1,
    'published_at' => '2026-11-07',
    'objectives' => [
        'Write Python scripts with variables, loops, functions and files',
        'Parse log lines and extract indicators with regular expressions',
        'Make HTTP requests and handle the responses safely',
    ],
    'skills' => ['python-scripting', 'data-parsing'],
    'prerequisites' => [],
    'chapters' => [
        [
            'title' => 'Scripting essentials',
            'lessons' => [
                [
                    'slug' => 'python-basics-for-automation',
                    'title' => 'Python basics for automation',
                    'minutes' => 15,
                    'summary' => 'Variables, lists, dictionaries, loops and functions, applied to a list of hosts.',
                    'objectives' => ['Use lists and dictionaries to hold data', 'Write loops and functions', 'Read a file line by line'],
                    'takeaways' => [
                        'Lists hold ordered items; dictionaries map keys to values.',
                        'Functions make a script reusable and testable.',
                        'Opening files with a with block closes them automatically.',
                    ],
                    'resources' => [
                        ['The Python Tutorial', 'https://docs.python.org/3/tutorial/'],
                    ],
                    'sections' => [
                        ['prose', 'Why Python', <<<'MD'
Python reads almost like English, ships with a large standard library and is used by many security tools. A twenty-line script can save hours of copying and pasting.
MD],
                        ['code', 'Counting hosts by status', <<<'PY'
hosts = [
    {"name": "web-01", "status": "patched"},
    {"name": "web-02", "status": "outdated"},
    {"name": "db-01", "status": "outdated"},
]

def count_by_status(items):
    counts = {}
    for item in items:
        counts[item["status"]] = counts.get(item["status"], 0) + 1
    return counts

print(count_by_status(hosts))   # {'patched': 1, 'outdated': 2}
PY, 'python'],
                        ['code', 'Reading a file', <<<'PY'
with open("hosts.txt", encoding="utf-8") as f:
    for line in f:
        host = line.strip()
        if host and not host.startswith("#"):
            print(host)
PY, 'python'],
                        ['callout', 'Run scripts on copies', 'When a script works on evidence such as logs or disk images, point it at a copy. A bug in your script should never be able to alter the original.', 'tip'],
                    ],
                ],
                [
                    'slug' => 'parsing-logs-with-python',
                    'title' => 'Parsing logs with Python',
                    'minutes' => 18,
                    'summary' => 'Use regular expressions to turn raw log lines into data you can count and sort.',
                    'objectives' => ['Write a regular expression with capture groups', 'Extract fields from authentication log lines', 'Summarise results with Counter'],
                    'takeaways' => [
                        'Regular expressions match patterns; capture groups pull out the parts you need.',
                        'collections.Counter counts items in one line.',
                        'Structured output, such as CSV or JSON, is easier to share than raw text.',
                    ],
                    'resources' => [
                        ['Python re module', 'https://docs.python.org/3/library/re.html'],
                    ],
                    'sections' => [
                        ['code', 'A typical SSH log line', <<<'LOG'
Nov 20 08:14:02 web-01 sshd[2211]: Failed password for invalid user admin from 203.0.113.45 port 51122 ssh2
LOG, 'text'],
                        ['code', 'Extracting and counting', <<<'PY'
import re
from collections import Counter

pattern = re.compile(r"Failed password for (?:invalid user )?(\S+) from (\d+\.\d+\.\d+\.\d+)")
by_ip = Counter()
by_user = Counter()

with open("auth.log", encoding="utf-8", errors="replace") as f:
    for line in f:
        match = pattern.search(line)
        if match:
            user, ip = match.groups()
            by_user[user] += 1
            by_ip[ip] += 1

for ip, count in by_ip.most_common(5):
    print(f"{ip:<16} {count}")
PY, 'python'],
                        ['steps', 'Reading the pattern', [
                            'Failed password for matches the literal text.',
                            '(?:invalid user )? optionally matches a phrase without capturing it.',
                            '(\S+) captures the username: one or more non-space characters.',
                            '(\d+\.\d+\.\d+\.\d+) captures an IPv4 address.',
                        ]],
                        ['callout', 'Logs are untrusted input', 'Attackers control parts of what gets logged, such as usernames and user agents. Treat log content as data: never pass it to eval or a shell command.', 'warning'],
                    ],
                ],
                [
                    'slug' => 'http-requests-with-python',
                    'title' => 'Talking to web services',
                    'minutes' => 15,
                    'summary' => 'Make HTTP requests from Python, read JSON responses and handle failures.',
                    'objectives' => ['Send a GET request with the standard library', 'Parse a JSON response', 'Handle timeouts and errors'],
                    'takeaways' => [
                        'urllib.request is built in; the third-party requests library is a popular alternative.',
                        'Always set a timeout so a slow server cannot hang your script.',
                        'Keep API keys in environment variables, never in the code.',
                    ],
                    'resources' => [
                        ['Python urllib.request', 'https://docs.python.org/3/library/urllib.request.html'],
                    ],
                    'sections' => [
                        ['code', 'Checking which URLs respond', <<<'PY'
import json
import urllib.error
import urllib.request

def status_of(url: str) -> str:
    request = urllib.request.Request(url, headers={"User-Agent": "lab-checker/1.0"})
    try:
        with urllib.request.urlopen(request, timeout=5) as response:
            return str(response.status)
    except urllib.error.HTTPError as error:
        return str(error.code)
    except (urllib.error.URLError, TimeoutError) as error:
        return f"unreachable ({error})"

for url in ["https://example.com", "https://example.com/missing"]:
    print(url, status_of(url))
PY, 'python'],
                        ['code', 'Reading JSON', <<<'PY'
import os

token = os.environ["API_TOKEN"]          # set outside the script, never committed
request = urllib.request.Request(
    "https://api.example.com/v1/alerts",
    headers={"Authorization": f"Bearer {token}", "Accept": "application/json"},
)
with urllib.request.urlopen(request, timeout=10) as response:
    alerts = json.load(response)
PY, 'python'],
                        ['callout', 'Only test what you are allowed to test', 'Scripts that send requests at scale look like attacks. Only point them at systems you own or have written permission to test.', 'warning'],
                    ],
                ],
            ],
        ],
    ],
];
