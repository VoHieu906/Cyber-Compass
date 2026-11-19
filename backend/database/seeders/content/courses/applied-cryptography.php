<?php

return [
    'slug' => 'applied-cryptography',
    'domain' => 'cryptography',
    'title' => 'Applied Cryptography Basics',
    'summary' => 'Hashing, encryption and TLS: what each one guarantees, and the mistakes that break them.',
    'description' => 'You do not need to invent algorithms to use cryptography well, but you do need to know which tool solves which problem. This course explains hashing and password storage, symmetric and asymmetric encryption, and how TLS combines them to protect web traffic.',
    'difficulty' => 2,
    'published_at' => '2026-11-05',
    'objectives' => [
        'Explain what a hash function guarantees and how passwords should be stored',
        'Compare symmetric and asymmetric encryption',
        'Describe how TLS protects a connection',
    ],
    'skills' => ['hashing', 'encryption', 'tls'],
    'prerequisites' => ['security-fundamentals'],
    'chapters' => [
        [
            'title' => 'Building blocks',
            'lessons' => [
                [
                    'slug' => 'hashing-and-passwords',
                    'title' => 'Hashing and password storage',
                    'minutes' => 15,
                    'summary' => 'One-way functions, what they are good for, and why passwords need slow hashes and salts.',
                    'objectives' => ['Describe the properties of a cryptographic hash', 'Explain why fast hashes are wrong for passwords', 'Explain the purpose of a salt'],
                    'takeaways' => [
                        'A hash maps any input to a fixed-size output and cannot be reversed.',
                        'Passwords must be stored with slow, salted algorithms such as Argon2id or bcrypt.',
                        'A unique salt per password stops attackers cracking many hashes at once.',
                    ],
                    'resources' => [
                        ['OWASP Password Storage Cheat Sheet', 'https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html'],
                    ],
                    'sections' => [
                        ['prose', 'What a hash does', <<<'MD'
A cryptographic **hash function** such as SHA-256 turns any input into a fixed-size value. It is **one-way** (you cannot get the input back), **deterministic** (the same input always gives the same hash) and **collision-resistant** (it is infeasible to find two inputs with the same hash). Changing one character of the input changes the whole output.
MD],
                        ['code', 'Hashes in practice', <<<'SH'
echo -n "hello" | sha256sum
# 2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824

sha256sum ubuntu.iso   # compare with the published checksum to verify a download
SH, 'bash'],
                        ['prose', 'Passwords need slow hashes', <<<'MD'
SHA-256 is designed to be fast, and a graphics card can compute billions of hashes per second, which makes guessing passwords cheap. Password storage uses deliberately slow algorithms such as **Argon2id**, **scrypt** or **bcrypt**, with a work factor you can raise over time.

Each password also gets a random **salt** stored next to its hash. Two users with the same password then get different hashes, and precomputed tables become useless.
MD],
                        ['callout', 'Hashing is not encryption', 'Encryption is reversible with a key; hashing is not reversible at all. If a system can email you your old password, it stored it wrongly.', 'warning'],
                    ],
                ],
                [
                    'slug' => 'symmetric-asymmetric-encryption',
                    'title' => 'Symmetric and asymmetric encryption',
                    'minutes' => 15,
                    'summary' => 'One shared key versus a key pair, and why real systems use both.',
                    'objectives' => ['Explain symmetric encryption and its key distribution problem', 'Explain public and private keys', 'Explain digital signatures'],
                    'takeaways' => [
                        'Symmetric encryption (AES) uses one shared secret key and is fast.',
                        'Asymmetric encryption (RSA, elliptic curves) uses a public key and a private key.',
                        'A signature made with a private key proves who signed and that nothing changed.',
                    ],
                    'resources' => [
                        ['NIST: Block Cipher Techniques (AES)', 'https://csrc.nist.gov/projects/block-cipher-techniques'],
                    ],
                    'sections' => [
                        ['prose', 'Symmetric: one shared key', <<<'MD'
In **symmetric** encryption the same key encrypts and decrypts. **AES** is the standard and is fast enough to encrypt disks and network traffic in real time. The hard part is sharing the key: both sides need it, and anyone who intercepts it can read everything.
MD],
                        ['prose', 'Asymmetric: a key pair', <<<'MD'
In **asymmetric** encryption each party has two linked keys. The **public key** can be shared with anyone; the **private key** never leaves its owner. Data encrypted with the public key can only be decrypted with the private key. Asymmetric algorithms are much slower, so they are used to agree on or exchange a symmetric key rather than to encrypt bulk data.
MD],
                        ['prose', 'Signatures', <<<'MD'
Running the process the other way gives a **digital signature**: the owner signs a hash of a message with the private key, and anyone can verify it with the public key. A valid signature proves who signed and that the message was not altered. Software updates, TLS certificates and Git commits all rely on signatures.
MD],
                        ['callout', 'Protect the private key', 'Every guarantee depends on the private key staying private. Keys belong in a secrets manager or hardware module, never in a code repository.', 'warning'],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Protecting traffic',
            'lessons' => [
                [
                    'slug' => 'tls-overview',
                    'title' => 'How TLS protects a connection',
                    'minutes' => 15,
                    'summary' => 'What the padlock in the browser actually means, step by step.',
                    'objectives' => ['List the guarantees TLS provides', 'Outline the TLS 1.3 handshake', 'Explain the role of certificates and certificate authorities'],
                    'takeaways' => [
                        'TLS gives confidentiality, integrity and server authentication for a connection.',
                        'The handshake uses asymmetric cryptography to agree on symmetric session keys.',
                        'Certificates bind a domain name to a public key and are signed by a trusted certificate authority.',
                    ],
                    'resources' => [
                        ['RFC 8446: TLS 1.3', 'https://www.rfc-editor.org/rfc/rfc8446'],
                    ],
                    'sections' => [
                        ['steps', 'The TLS 1.3 handshake, simplified', [
                            'The client sends a ClientHello with the TLS versions and cipher suites it supports, plus its key share.',
                            'The server replies with a ServerHello, its own key share and its certificate.',
                            'Both sides combine the key shares to derive the same symmetric session keys, which never cross the network.',
                            'The client checks the certificate: it names the right domain, is unexpired and chains to a trusted authority.',
                            'Application data now flows encrypted with the session keys.',
                        ]],
                        ['prose', 'Certificates and trust', <<<'MD'
A **certificate** says "this public key belongs to example.com" and is signed by a **certificate authority** (CA). Browsers and operating systems ship with a list of trusted CAs. If the signature chain leads back to one of them and the name matches, the browser trusts the connection.
MD],
                        ['code', 'Inspect a certificate', <<<'SH'
openssl s_client -connect example.com:443 -servername example.com </dev/null \
  | openssl x509 -noout -subject -issuer -dates
SH, 'bash'],
                        ['callout', 'The padlock is not a safety rating', 'TLS proves you are talking privately to the named domain. Phishing sites have valid certificates too: check the domain itself, not just the padlock.', 'warning'],
                    ],
                ],
            ],
        ],
    ],
];
