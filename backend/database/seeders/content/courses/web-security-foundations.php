<?php

return [
    'slug' => 'web-security-foundations',
    'domain' => 'web-security',
    'title' => 'Web Security Foundations',
    'summary' => 'How web applications talk, where they break, and how injection and cross-site scripting are prevented.',
    'description' => 'Web applications are the most exposed part of most organisations. Starting from how HTTP requests and responses work, this course surveys the OWASP Top 10 and then looks closely at the two vulnerability classes every developer and tester must understand: SQL injection and cross-site scripting.',
    'difficulty' => 2,
    'published_at' => '2026-11-04',
    'objectives' => [
        'Read and explain an HTTP request and response',
        'Describe the categories in the OWASP Top 10',
        'Explain how SQL injection works and how parameterised queries prevent it',
        'Explain the types of XSS and how output encoding prevents them',
    ],
    'skills' => ['http-fundamentals', 'owasp-top-10', 'injection-prevention'],
    'prerequisites' => ['networking-fundamentals'],
    'chapters' => [
        [
            'title' => 'How the web works',
            'lessons' => [
                [
                    'slug' => 'http-essentials',
                    'title' => 'HTTP essentials',
                    'minutes' => 15,
                    'summary' => 'Methods, headers, status codes and cookies, read the way a tester reads them.',
                    'objectives' => ['Identify the parts of an HTTP request and response', 'Explain what common methods and status codes mean', 'Explain how cookies keep a user signed in'],
                    'takeaways' => [
                        'HTTP is a text-based request and response protocol; HTTPS is HTTP inside TLS.',
                        'Methods state intent (GET reads, POST creates) and status codes report the outcome.',
                        'Session cookies identify a signed-in user, so they must be protected with HttpOnly, Secure and SameSite.',
                    ],
                    'resources' => [
                        ['MDN: An overview of HTTP', 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Overview'],
                    ],
                    'sections' => [
                        ['code', 'A request', <<<'HTTP'
POST /api/login HTTP/1.1
Host: shop.example
Content-Type: application/json
Cookie: session=4f2c...

{"email":"lan@example.com","password":"..."}
HTTP, 'http'],
                        ['prose', 'Reading it', <<<'MD'
The first line has the **method** (`POST`), the **path** and the protocol version. Then come **headers**: `Host` names the site, `Content-Type` describes the body, `Cookie` carries data the server set earlier. A blank line separates the headers from the **body**.

The response starts with a **status code**: 2xx means success, 3xx a redirect, 4xx a client error (401 not signed in, 403 forbidden, 404 not found) and 5xx a server error.
MD],
                        ['prose', 'Cookies and sessions', <<<'MD'
HTTP is stateless: each request stands alone. To keep you signed in, the server sets a **session cookie** that the browser sends back on every request. Anyone who steals that cookie is you, as far as the server knows. That is why it should be marked:

- `HttpOnly`, so JavaScript cannot read it;
- `Secure`, so it is only sent over HTTPS;
- `SameSite`, so other sites cannot make the browser send it.
MD],
                        ['callout', 'Never trust the client', 'Everything in a request, including hidden form fields, headers and cookies, can be changed by the user with a proxy tool. Every check that matters has to happen on the server.', 'warning'],
                    ],
                ],
                [
                    'slug' => 'owasp-top-10-overview',
                    'title' => 'The OWASP Top 10',
                    'minutes' => 12,
                    'summary' => 'The most widely used list of critical web application risks, category by category.',
                    'objectives' => ['Explain what the OWASP Top 10 is and how it is compiled', 'Describe the major risk categories', 'Use the list to prioritise testing'],
                    'takeaways' => [
                        'The OWASP Top 10 ranks the most critical web application security risks using data from real applications.',
                        'Broken access control has led the list since 2021.',
                        'It is an awareness document and a starting point, not a complete standard.',
                    ],
                    'resources' => [
                        ['OWASP Top 10', 'https://owasp.org/www-project-top-ten/'],
                    ],
                    'sections' => [
                        ['prose', 'What the list is', <<<'MD'
The **OWASP Top 10** is published by the Open Worldwide Application Security Project. It ranks the most critical risk categories in web applications, based on data contributed by security companies and a community survey. It is updated every few years.
MD],
                        ['prose', 'Categories you will see again and again', <<<'MD'
- **Broken access control**: users can act outside their permissions, such as viewing another customer's order by changing an ID in the URL.
- **Cryptographic failures**: sensitive data sent or stored without proper encryption.
- **Injection**: untrusted input interpreted as code or a query, including SQL injection and XSS.
- **Security misconfiguration**: default passwords, verbose error messages, unnecessary features left on.
- **Vulnerable and outdated components**: libraries with known flaws.
- **Identification and authentication failures**: weak passwords allowed, no lockout, broken session handling.
MD],
                        ['callout', 'Access control is checked per request', 'Hiding a button is not access control. Every request that reads or changes data must check, on the server, that this user is allowed to do this to this record.', 'warning'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Injection attacks',
            'lessons' => [
                [
                    'slug' => 'sql-injection',
                    'title' => 'SQL injection',
                    'minutes' => 18,
                    'summary' => 'How user input ends up running as SQL, and the one habit that prevents it.',
                    'objectives' => ['Explain how string concatenation creates SQL injection', 'Show how a parameterised query prevents it', 'List secondary defenses such as least privilege'],
                    'takeaways' => [
                        'SQL injection happens when input is concatenated into a query and the database runs it as code.',
                        'Parameterised queries send the query and the data separately, so data is never executed.',
                        'Least-privilege database accounts limit the damage if a flaw slips through.',
                    ],
                    'resources' => [
                        ['OWASP SQL Injection Prevention Cheat Sheet', 'https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html'],
                    ],
                    'sections' => [
                        ['code', 'The vulnerable pattern', <<<'PY'
# Never do this: the input becomes part of the SQL text.
query = "SELECT * FROM users WHERE email = '" + email + "'"
cursor.execute(query)
PY, 'python'],
                        ['steps', 'What an attacker sends', [
                            "The attacker types ' OR '1'='1 into the email field.",
                            "The query becomes: SELECT * FROM users WHERE email = '' OR '1'='1'",
                            "'1'='1' is always true, so the condition matches every row.",
                            'The application returns or logs in as the first user, often an administrator.',
                        ]],
                        ['code', 'The fix: a parameterised query', <<<'PY'
# The query and the data travel separately; the database never parses the email as SQL.
cursor.execute("SELECT * FROM users WHERE email = %s", (email,))
PY, 'python'],
                        ['callout', 'Escaping is not the fix', 'Trying to strip quotes or escape characters by hand misses edge cases. Use parameterised queries or an ORM that uses them, and treat any string-built query as a bug.', 'warning'],
                    ],
                ],
                [
                    'slug' => 'cross-site-scripting',
                    'title' => 'Cross-site scripting (XSS)',
                    'minutes' => 15,
                    'summary' => 'When a page runs an attacker\'s script in a victim\'s browser, and how encoding output stops it.',
                    'objectives' => ['Distinguish stored, reflected and DOM-based XSS', 'Explain what an attacker can do with XSS', 'Apply output encoding and Content Security Policy'],
                    'takeaways' => [
                        'XSS lets an attacker run JavaScript in other users\' browsers, inside your site.',
                        'Encoding output for its context (HTML, attribute, URL, JavaScript) is the primary defense.',
                        'A Content Security Policy limits the damage when encoding is missed.',
                    ],
                    'resources' => [
                        ['OWASP Cross Site Scripting Prevention Cheat Sheet', 'https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html'],
                    ],
                    'sections' => [
                        ['prose', 'Three kinds', <<<'MD'
- **Stored XSS**: the script is saved, for example in a comment, and runs for everyone who views it.
- **Reflected XSS**: the script is in a link; the page echoes it back to whoever opens the link.
- **DOM-based XSS**: the page's own JavaScript copies untrusted data into the page, such as writing `location.hash` into `innerHTML`.
MD],
                        ['code', 'Vulnerable and safe', <<<'JS'
// Vulnerable: the browser parses the comment as HTML, including any <script> or onerror.
element.innerHTML = comment;

// Safe: the browser treats the comment as text.
element.textContent = comment;
JS, 'javascript'],
                        ['prose', 'Why it is serious', <<<'MD'
Script injected into your site runs with your site's permissions. It can read the page, act as the user, change what they see, and send data to the attacker. If session cookies are not `HttpOnly`, it can steal them outright.
MD],
                        ['callout', 'Frameworks help, until you bypass them', 'React, Vue and template engines encode output by default. Functions such as dangerouslySetInnerHTML or v-html switch that protection off, so treat them as security-sensitive code.', 'tip'],
                    ],
                ],
            ],
        ],
    ],
];
