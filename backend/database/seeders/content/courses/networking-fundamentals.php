<?php

return [
    'slug' => 'networking-fundamentals',
    'domain' => 'networking',
    'title' => 'Networking Fundamentals',
    'summary' => 'How data moves from one machine to another: layers, addresses, and the protocols underneath every connection.',
    'description' => 'Every security role reads network traffic, firewall rules or logs full of IP addresses. This course builds the mental model you need for all of them: the layered models, how a TCP connection starts, how addresses and subnets work, and how DNS and DHCP make networks usable.',
    'difficulty' => 1,
    'published_at' => '2026-11-01',
    'objectives' => [
        'Explain what happens at each layer of the OSI and TCP/IP models',
        'Describe how a TCP connection is established',
        'Read IPv4 addresses and CIDR notation and calculate subnet sizes',
        'Explain how DNS and DHCP work and why attackers target them',
    ],
    'skills' => ['osi-tcp-ip-models', 'ip-addressing', 'core-protocols'],
    'prerequisites' => [],
    'chapters' => [
        [
            'title' => 'How networks are built',
            'lessons' => [
                [
                    'slug' => 'intro-to-computer-networks',
                    'title' => 'Introduction to computer networks',
                    'minutes' => 12,
                    'summary' => 'What a network is, the devices that make one up, and why security starts here.',
                    'objectives' => ['Name the main network devices and what each one does', 'Tell a LAN from a WAN', 'Explain what a packet is'],
                    'takeaways' => [
                        'A network is a set of devices that exchange data using shared rules called protocols.',
                        'Switches connect devices within a network; routers connect networks to each other.',
                        'Data travels in small packets, each carrying addressing information.',
                    ],
                    'resources' => [
                        ['RFC 791: Internet Protocol', 'https://www.rfc-editor.org/rfc/rfc791'],
                    ],
                    'sections' => [
                        ['prose', 'What a network is', <<<'MD'
A computer network is a group of devices that exchange data by following shared rules. Those rules are called **protocols**: agreements about how to format, address and deliver data.

Your laptop, a web server and a smart thermostat all speak the same core protocols, which is why they can talk to each other across the internet.
MD],
                        ['prose', 'The devices in the path', <<<'MD'
- **Hosts** create and consume data: laptops, phones, servers.
- **Switches** connect hosts inside one local network and forward data to the right port using hardware (MAC) addresses.
- **Routers** connect separate networks and forward data between them using IP addresses.
- **Firewalls** sit at boundaries and decide which traffic is allowed through.
MD],
                        ['scene-3d', 'Follow a packet', [
                            'title' => 'A request leaving the office for a web server',
                            'description' => 'Six devices in a row. On the left, inside the office: a laptop, a switch and a router. In the middle: a firewall at the edge of the office network. On the right: the internet, drawn as a wire sphere, and a web server. Small dots travel along the cables from the laptop to the server to show packets moving. The guided steps stop at each device: the switch forwards by MAC address inside the office, the router forwards by IP address and translates the private address, the firewall checks its rules, routers across the internet pass the packet hop by hop, and the server answers the same way back.',
                            'aspect' => '16:9',
                            'camera' => ['position' => [0, 3.2, 10], 'target' => [0, 0.6, 0], 'fov' => 50],
                            'objects' => [
                                ['id' => 'laptop', 'shape' => 'box', 'size' => [1.2, 0.15, 0.8], 'position' => [-6.5, 0.4, 0], 'color' => '#64748b', 'text' => 'Laptop', 'info' => ['title' => 'Laptop (host)', 'text' => 'Starts the request. Its browser asks for a web page, and the operating system splits the request into packets addressed to the server.']],
                                ['id' => 'switch', 'shape' => 'box', 'size' => [1.4, 0.3, 0.7], 'position' => [-4, 0.4, 0], 'color' => '#0ea5e9', 'text' => 'Switch', 'info' => ['title' => 'Switch', 'text' => 'Connects devices inside the office. It forwards frames to the right port by MAC address and never looks at IP addresses.']],
                                ['id' => 'router', 'shape' => 'cylinder', 'size' => [1, 0.45, 1], 'position' => [-1.5, 0.4, 0], 'color' => '#14b8a6', 'text' => 'Router', 'info' => ['title' => 'Router', 'text' => 'Connects the office network to the internet. It forwards packets by IP address and, with NAT, swaps the laptop\'s private address for the office\'s public one.']],
                                ['id' => 'firewall', 'shape' => 'box', 'size' => [0.5, 1.4, 1.4], 'position' => [1, 0.7, 0], 'color' => '#f97316', 'text' => 'Firewall', 'info' => ['title' => 'Firewall', 'text' => 'Sits at the boundary and checks each connection against rules. Outbound web traffic is allowed; unsolicited traffic from outside is dropped.']],
                                ['id' => 'internet', 'shape' => 'sphere', 'size' => [1.8, 1.8, 1.8], 'position' => [3.8, 0.9, 0], 'color' => '#94a3b8', 'wireframe' => true, 'text' => 'Internet', 'animation' => ['type' => 'spin', 'axis' => 'y', 'speed' => 0.3], 'info' => ['title' => 'The internet', 'text' => 'Thousands of networks run by different organisations. Routers along the way each pass the packet one hop closer to its destination.']],
                                ['id' => 'server', 'shape' => 'box', 'size' => [0.9, 1.7, 0.9], 'position' => [6.6, 0.85, 0], 'color' => '#334155', 'text' => 'Web server', 'info' => ['title' => 'Web server', 'text' => 'Receives the request on port 443 (HTTPS) and sends the page back. The reply retraces the path in reverse.']],
                                ['id' => 'lan-label', 'shape' => 'label', 'text' => 'Office network', 'position' => [-4, 2.2, 0]],
                            ],
                            'links' => [
                                ['from' => 'laptop', 'to' => 'switch', 'flow' => ['count' => 2]],
                                ['from' => 'switch', 'to' => 'router', 'flow' => ['count' => 2]],
                                ['from' => 'router', 'to' => 'firewall', 'flow' => ['count' => 2]],
                                ['from' => 'firewall', 'to' => 'internet', 'dashed' => true, 'flow' => ['count' => 3, 'speed' => 1.3]],
                                ['from' => 'internet', 'to' => 'server', 'dashed' => true, 'flow' => ['count' => 3, 'speed' => 1.3]],
                            ],
                            'steps' => [
                                ['title' => 'The whole path', 'text' => 'A request from the laptop crosses five hops to reach the web server. Select any device to read what it does, or step through the journey.'],
                                ['title' => 'Inside the office: the switch', 'text' => 'The laptop sends the packet, wrapped in a frame, to its default gateway. The switch reads the destination MAC address and forwards the frame to the router\'s port.', 'highlight' => ['laptop', 'switch'], 'camera' => ['position' => [-5, 2.5, 5], 'target' => [-5, 0.4, 0]]],
                                ['title' => 'Leaving the network: the router', 'text' => 'The router reads the destination IP address, sees it is not local, and forwards the packet towards the internet. With NAT, it replaces the laptop\'s private source address with the office\'s public address.', 'highlight' => ['router'], 'camera' => ['position' => [-1.5, 2.5, 5], 'target' => [-1.5, 0.4, 0]]],
                                ['title' => 'At the boundary: the firewall', 'text' => 'The firewall allows the connection because outbound HTTPS is permitted, and remembers it so the reply can come back in. Traffic nobody inside asked for is dropped here.', 'highlight' => ['firewall'], 'camera' => ['position' => [1, 2.5, 5], 'target' => [1, 0.6, 0]]],
                                ['title' => 'Across the internet', 'text' => 'Routers run by different organisations each forward the packet one hop closer. None of them needs to know the whole route, only the next hop.', 'highlight' => ['internet'], 'camera' => ['position' => [4, 2.5, 5.5], 'target' => [4, 0.8, 0]]],
                                ['title' => 'Arrival and the reply', 'text' => 'The server receives the request on port 443 and answers. The reply retraces the path: internet, firewall, router (which translates the address back), switch, laptop.', 'highlight' => ['server', 'laptop'], 'camera' => ['position' => [0, 3.2, 10], 'target' => [0, 0.6, 0]]],
                            ],
                        ]],
                        ['prose', 'LANs, WANs and the internet', <<<'MD'
A **LAN** (local area network) covers one place: a home, an office floor, a lab. A **WAN** (wide area network) connects LANs across distance. The internet is a vast network of networks, joined by routers run by many different organisations.
MD],
                        ['callout', 'Why this matters for security', 'Almost every attack crosses a network. Knowing which device sees which traffic tells you where you can detect it, and where you can stop it.', 'info'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A laptop sends a file to a printer on the same office network. Which device forwards that traffic?',
                                    'options' => [
                                        ['text' => 'The switch', 'correct' => true],
                                        ['text' => 'The router', 'correct' => false],
                                        ['text' => 'The firewall at the internet edge', 'correct' => false],
                                    ],
                                    'explanation' => 'Traffic between devices on the same local network goes through the switch, using MAC addresses. It never reaches the router, so a firewall at the edge would not see it.',
                                ],
                                [
                                    'prompt' => 'Which devices decide where to send data based on IP addresses?',
                                    'options' => [
                                        ['text' => 'Routers', 'correct' => true],
                                        ['text' => 'Switches', 'correct' => false],
                                        ['text' => 'Hosts such as laptops', 'correct' => false],
                                    ],
                                    'explanation' => 'Routers forward packets between networks by IP address. Switches work with MAC addresses inside one network.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'osi-model',
                    'title' => 'The OSI model',
                    'minutes' => 18,
                    'summary' => 'The seven-layer model used to describe, troubleshoot and secure network communication.',
                    'objectives' => ['List the seven OSI layers in order', 'Place common protocols and devices on the right layer', 'Use the model to reason about where an attack happens'],
                    'takeaways' => [
                        'The OSI model splits communication into seven layers, from physical signals up to applications.',
                        'Each layer adds its own header as data moves down the stack (encapsulation).',
                        'Security tools are often described by the layer they inspect, such as a layer 7 firewall.',
                    ],
                    'resources' => [
                        ['Cloudflare: What is the OSI model?', 'https://www.cloudflare.com/learning/ddos/glossary/open-systems-interconnection-model-osi/'],
                    ],
                    'sections' => [
                        ['prose', 'Why a layered model', <<<'MD'
Networking is complex, so engineers split it into layers. Each layer does one job and relies on the layer below it. The **OSI model** (Open Systems Interconnection) has seven layers. Real networks mostly run the simpler TCP/IP model, but OSI layer numbers are still how people talk: "a layer 2 attack", "a layer 7 load balancer".
MD],
                        ['layer-stack', 'The seven layers', [
                            'tags_label' => 'Example protocols',
                            'layers' => [
                                ['tag' => '7', 'name' => 'Application', 'meta' => 'Data', 'description' => 'Network services used directly by applications.', 'tags' => ['HTTP', 'DNS', 'SMTP', 'SSH'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'SQL injection and cross-site scripting abuse the application itself.']],
                                ['tag' => '6', 'name' => 'Presentation', 'meta' => 'Data', 'description' => 'Formats, encodes and encrypts data so both sides can read it.', 'tags' => ['TLS', 'JPEG', 'UTF-8'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'Downgrade attacks try to force weaker encryption.']],
                                ['tag' => '5', 'name' => 'Session', 'meta' => 'Data', 'description' => 'Opens, maintains and closes conversations between applications.', 'tags' => ['RPC', 'NetBIOS'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'Session hijacking takes over an established conversation.']],
                                ['tag' => '4', 'name' => 'Transport', 'meta' => 'Segment', 'description' => 'Delivers data between processes using ports, reliably (TCP) or not (UDP).', 'tags' => ['TCP', 'UDP'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'SYN floods exhaust a server\'s half-open connections; port scans map services.']],
                                ['tag' => '3', 'name' => 'Network', 'meta' => 'Packet', 'description' => 'Addresses and routes packets between networks.', 'tags' => ['IP', 'ICMP'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'IP spoofing forges the source address of packets.']],
                                ['tag' => '2', 'name' => 'Data link', 'meta' => 'Frame', 'description' => 'Moves frames between devices on the same local network using MAC addresses.', 'tags' => ['Ethernet', 'Wi-Fi (802.11)', 'ARP'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'ARP spoofing redirects local traffic through an attacker\'s machine.']],
                                ['tag' => '1', 'name' => 'Physical', 'meta' => 'Bit', 'description' => 'Turns bits into signals on a cable, fibre or radio.', 'tags' => ['Copper', 'Fibre', 'Radio'], 'note' => ['tone' => 'warning', 'label' => 'Attack at this layer', 'text' => 'Wiretapping and jamming work on the signal itself.']],
                            ],
                        ]],
                        ['step-through', 'Encapsulation', [
                            'steps' => [
                                ['title' => 'The application has data', 'text' => 'Your browser has an HTTP request to send: "give me the page at /login". At this point it is just data.', 'parts' => [['label' => 'HTTP request', 'tone' => 'accent']], 'state' => [['label' => 'Layer', 'value' => '7 Application'], ['label' => 'Data unit', 'value' => 'Data'], ['label' => 'Added', 'value' => 'Nothing yet']]],
                                ['title' => 'Transport adds a TCP header', 'text' => 'The transport layer wraps the data in a **segment**. Its header carries the **ports** (which program on each machine) and sequence numbers so the data can be reassembled in order.', 'parts' => [['label' => 'TCP header', 'tone' => 'muted'], ['label' => 'HTTP request', 'tone' => 'accent']], 'state' => [['label' => 'Layer', 'value' => '4 Transport'], ['label' => 'Data unit', 'value' => 'Segment'], ['label' => 'Added', 'value' => 'Ports 51515 → 443']]],
                                ['title' => 'Network adds an IP header', 'text' => 'The network layer wraps the segment in a **packet**. Its header carries the source and destination **IP addresses**, which routers use to move it between networks.', 'parts' => [['label' => 'IP header', 'tone' => 'muted'], ['label' => 'TCP header', 'tone' => 'muted'], ['label' => 'HTTP request', 'tone' => 'accent']], 'state' => [['label' => 'Layer', 'value' => '3 Network'], ['label' => 'Data unit', 'value' => 'Packet'], ['label' => 'Added', 'value' => '192.168.1.20 → 203.0.113.10']]],
                                ['title' => 'Data link adds a frame header and trailer', 'text' => 'The data link layer wraps the packet in a **frame** for the next hop only. The header carries **MAC addresses**; the trailer carries a checksum so the receiver can detect corruption.', 'parts' => [['label' => 'Ethernet header', 'tone' => 'muted'], ['label' => 'IP header', 'tone' => 'muted'], ['label' => 'TCP header', 'tone' => 'muted'], ['label' => 'HTTP request', 'tone' => 'accent'], ['label' => 'Trailer', 'tone' => 'muted']], 'state' => [['label' => 'Layer', 'value' => '2 Data link'], ['label' => 'Data unit', 'value' => 'Frame'], ['label' => 'Added', 'value' => 'MAC addresses, checksum']]],
                                ['title' => 'Physical sends bits', 'text' => 'The physical layer turns the frame into signals: voltage on copper, light in fibre, radio waves for Wi-Fi. The receiver reverses every step, removing one wrapper per layer on the way up.', 'parts' => [['label' => '1011 0010 0110 …', 'tone' => 'plain']], 'state' => [['label' => 'Layer', 'value' => '1 Physical'], ['label' => 'Data unit', 'value' => 'Bits'], ['label' => 'Added', 'value' => 'Nothing: just signals']]],
                            ],
                        ]],
                        ['callout', 'A way to remember the order', 'From layer 1 up: Physical, Data link, Network, Transport, Session, Presentation, Application. Many people use the phrase "Please Do Not Throw Sausage Pizza Away".', 'tip'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'An attacker on your Wi-Fi sends fake ARP replies so other devices send their traffic through the attacker\'s laptop. Which layer is being attacked?',
                                    'options' => [
                                        ['text' => 'Layer 2, data link', 'correct' => true],
                                        ['text' => 'Layer 3, network', 'correct' => false],
                                        ['text' => 'Layer 7, application', 'correct' => false],
                                    ],
                                    'explanation' => 'ARP maps IP addresses to MAC addresses on the local network, which is the data link layer\'s job. ARP spoofing poisons that mapping.',
                                ],
                                [
                                    'prompt' => 'Which header is replaced at every hop as a packet crosses routers?',
                                    'options' => [
                                        ['text' => 'The data link (Ethernet) header', 'correct' => true],
                                        ['text' => 'The IP header\'s destination address', 'correct' => false],
                                        ['text' => 'The TCP header\'s ports', 'correct' => false],
                                    ],
                                    'explanation' => 'A frame only travels one hop, so each router removes the old frame and builds a new one with new MAC addresses. The destination IP address stays the same end to end (NAT aside).',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'tcp-ip-model',
                    'title' => 'The TCP/IP model and the handshake',
                    'minutes' => 18,
                    'summary' => 'The four-layer model the internet actually runs on, and how a TCP connection begins.',
                    'objectives' => ['Map the TCP/IP layers to the OSI layers', 'Explain the difference between TCP and UDP', 'Walk through the TCP three-way handshake'],
                    'takeaways' => [
                        'TCP/IP has four layers: link, internet, transport and application.',
                        'TCP is connection-oriented and reliable; UDP is connectionless and lighter.',
                        'A TCP connection starts with SYN, SYN-ACK, ACK.',
                    ],
                    'resources' => [
                        ['RFC 9293: Transmission Control Protocol', 'https://www.rfc-editor.org/rfc/rfc9293'],
                    ],
                    'sections' => [
                        ['layer-stack', 'Four layers instead of seven', [
                            'tags_label' => 'Protocols',
                            'layers' => [
                                ['tag' => '4', 'name' => 'Application', 'meta' => 'OSI 5–7', 'description' => 'Everything an application needs to talk to another: web pages, name lookups, remote shells, email. TCP/IP folds the OSI session and presentation layers in here.', 'facts' => [['label' => 'OSI layers', 'value' => '5 Session, 6 Presentation, 7 Application']], 'tags' => ['HTTP', 'DNS', 'SSH', 'TLS', 'SMTP'], 'note' => ['tone' => 'info', 'label' => 'Security view', 'text' => 'Most incidents you will investigate start here: phishing links, injection, stolen credentials.']],
                                ['tag' => '3', 'name' => 'Transport', 'meta' => 'OSI 4', 'description' => 'Delivers data between programs, identified by port numbers. TCP adds connections and reliability; UDP does not.', 'facts' => [['label' => 'OSI layers', 'value' => '4 Transport']], 'tags' => ['TCP', 'UDP', 'QUIC'], 'note' => ['tone' => 'info', 'label' => 'Security view', 'text' => 'Firewall rules are mostly written here and below: allow TCP port 443, block UDP port 161.']],
                                ['tag' => '2', 'name' => 'Internet', 'meta' => 'OSI 3', 'description' => 'Addresses packets with IP addresses and routes them between networks, hop by hop.', 'facts' => [['label' => 'OSI layers', 'value' => '3 Network']], 'tags' => ['IPv4', 'IPv6', 'ICMP'], 'note' => ['tone' => 'info', 'label' => 'Security view', 'text' => 'Source addresses can be forged, so an IP address alone never proves who sent a packet.']],
                                ['tag' => '1', 'name' => 'Link', 'meta' => 'OSI 1–2', 'description' => 'Moves frames across one physical network: a cable, a Wi-Fi network, a fibre link.', 'facts' => [['label' => 'OSI layers', 'value' => '1 Physical, 2 Data link']], 'tags' => ['Ethernet', 'Wi-Fi', 'ARP'], 'note' => ['tone' => 'info', 'label' => 'Security view', 'text' => 'Anyone on the same local network can attack this layer, which is why guest Wi-Fi is kept separate.']],
                            ],
                        ]],
                        ['comparison', 'TCP and UDP', [
                            'columns' => ['TCP', 'UDP'],
                            'rows' => [
                                ['label' => 'Connection', 'values' => ['Set up first with a three-way handshake', 'None: each datagram stands alone']],
                                ['label' => 'Delivery', 'values' => ['Guaranteed: lost data is resent', 'Best effort: lost data stays lost']],
                                ['label' => 'Order', 'values' => ['Delivered in order', 'May arrive out of order']],
                                ['label' => 'Overhead', 'values' => ['Higher: 20+ byte header, acknowledgements', 'Lower: 8-byte header']],
                                ['label' => 'Typical use', 'values' => ['Web (HTTP/1.1, HTTP/2), email, SSH, file transfer', 'DNS lookups, video calls, games, HTTP/3 (over QUIC)']],
                                ['label' => 'Seen in attacks', 'values' => ['SYN floods, port scans', 'Amplification attacks using DNS or NTP, UDP floods']],
                            ],
                        ]],
                        ['sequence', 'The three-way handshake', [
                            'actors' => [['id' => 'client', 'name' => 'Client'], ['id' => 'server', 'name' => 'Server']],
                            'messages' => [
                                ['from' => 'client', 'to' => 'server', 'label' => 'SYN', 'detail' => 'seq=100', 'explanation' => 'The client asks to open a connection and picks a random initial sequence number, here 100.', 'states' => ['client' => 'SYN-SENT', 'server' => 'LISTEN']],
                                ['from' => 'server', 'to' => 'client', 'label' => 'SYN-ACK', 'detail' => 'seq=300 ack=101', 'explanation' => 'The server agrees, picks its own sequence number (300) and acknowledges the client\'s by expecting 101 next.', 'states' => ['client' => 'SYN-SENT', 'server' => 'SYN-RECEIVED']],
                                ['from' => 'client', 'to' => 'server', 'label' => 'ACK', 'detail' => 'seq=101 ack=301', 'explanation' => 'The client acknowledges the server\'s sequence number. Both sides now agree on the starting numbers.', 'states' => ['client' => 'ESTABLISHED', 'server' => 'ESTABLISHED']],
                            ],
                        ]],
                        ['callout', 'Attackers use the handshake too', 'A SYN flood sends many SYN packets and never completes the handshake, filling the server\'s table of half-open connections. Port scanners send SYNs to see which ports answer with SYN-ACK.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A server receives thousands of SYN packets per second, answers each with SYN-ACK, and never gets the final ACK. What is happening?',
                                    'options' => [
                                        ['text' => 'A SYN flood filling its half-open connection table', 'correct' => true],
                                        ['text' => 'Normal traffic from many users', 'correct' => false],
                                        ['text' => 'A DNS amplification attack', 'correct' => false],
                                    ],
                                    'explanation' => 'Each unanswered SYN-ACK leaves a half-open connection waiting. Enough of them exhaust the server\'s table, so real clients cannot connect. Defences include SYN cookies and rate limiting.',
                                ],
                                [
                                    'prompt' => 'Which of these usually run over UDP? Choose all that apply.',
                                    'options' => [
                                        ['text' => 'A DNS lookup', 'correct' => true],
                                        ['text' => 'A video call', 'correct' => true],
                                        ['text' => 'An SSH session', 'correct' => false],
                                        ['text' => 'Sending an email with SMTP', 'correct' => false],
                                    ],
                                    'explanation' => 'DNS lookups and real-time media favour UDP: a late answer is worse than a lost one. SSH and SMTP need every byte in order, so they use TCP.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Addressing and core services',
            'lessons' => [
                [
                    'slug' => 'ip-addressing',
                    'title' => 'IP addressing',
                    'minutes' => 15,
                    'summary' => 'How IPv4 addresses are written, which ranges are private, and what IPv6 changes.',
                    'objectives' => ['Read an IPv4 address and its binary form', 'Recognise private and special address ranges', 'Explain why IPv6 exists'],
                    'takeaways' => [
                        'An IPv4 address is 32 bits, written as four decimal octets.',
                        '10.0.0.0/8, 172.16.0.0/12 and 192.168.0.0/16 are private ranges that are not routed on the internet.',
                        'IPv6 uses 128-bit addresses written in hexadecimal.',
                    ],
                    'resources' => [
                        ['RFC 1918: Address Allocation for Private Internets', 'https://www.rfc-editor.org/rfc/rfc1918'],
                    ],
                    'sections' => [
                        ['prose', 'Anatomy of an IPv4 address', <<<'MD'
An IPv4 address is a 32-bit number. We write it as four **octets** of 8 bits each, in decimal, separated by dots: `192.168.1.20`. Each octet ranges from 0 to 255, because 8 bits can hold 256 values.

Subnetting is much easier once you can see the bits, so here is the same address in binary.
MD],
                        ['bitfield', '192.168.1.20 in binary', [
                            'show_decimal' => true,
                            'rows' => [
                                ['label' => '192.168.1.20', 'fields' => [
                                    ['label' => 'Octet 1', 'bits' => '11000000', 'tone' => 'accent'],
                                    ['label' => 'Octet 2', 'bits' => '10101000', 'tone' => 'accent'],
                                    ['label' => 'Octet 3', 'bits' => '00000001', 'tone' => 'accent'],
                                    ['label' => 'Octet 4', 'bits' => '00010100', 'tone' => 'attn'],
                                ]],
                            ],
                        ]],
                        ['comparison', 'Ranges worth recognising', [
                            'columns' => ['Private', 'Loopback', 'Link-local'],
                            'rows' => [
                                ['label' => 'Ranges', 'values' => ['`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`', '`127.0.0.0/8`', '`169.254.0.0/16`']],
                                ['label' => 'What it means', 'values' => ['Used inside organisations and translated (NAT) at the edge; never routed on the internet', 'Traffic to `127.0.0.1` never leaves the machine', 'Assigned automatically when a device cannot reach a DHCP server']],
                                ['label' => 'Seen in a log', 'values' => ['The source was inside the network, or came in over a VPN', 'A program on the same machine made the connection', 'A device that failed to get an address: check DHCP']],
                            ],
                        ]],
                        ['callout', 'Reading logs', 'A login from a private address came from inside the network or through a VPN. A login from a public address came from the internet. That single fact changes how you investigate it.', 'info'],
                        ['prose', 'IPv6 in one paragraph', <<<'MD'
IPv4 has about 4.3 billion addresses, which ran out. **IPv6** uses 128 bits, written as eight groups of hexadecimal such as `2001:db8::1` (runs of zeros collapse to `::`). Many networks run both, which means firewall rules must cover both.
MD],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A failed login in your logs came from `172.20.4.9`. What does that tell you?',
                                    'options' => [
                                        ['text' => 'It came from inside the network or over a VPN', 'correct' => true],
                                        ['text' => 'It came directly from the internet', 'correct' => false],
                                        ['text' => 'It came from the server itself', 'correct' => false],
                                    ],
                                    'explanation' => '`172.20.4.9` is inside `172.16.0.0/12` (172.16.0.0 to 172.31.255.255), a private range. Private addresses are not routed on the internet, so the attempt came from an internal device or a VPN user.',
                                ],
                                [
                                    'prompt' => 'What is `00001010` in decimal?',
                                    'options' => [
                                        ['text' => '10', 'correct' => true],
                                        ['text' => '8', 'correct' => false],
                                        ['text' => '12', 'correct' => false],
                                    ],
                                    'explanation' => 'The bits that are set are worth 8 and 2. 8 + 2 = 10.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'subnetting',
                    'title' => 'Subnetting',
                    'minutes' => 25,
                    'summary' => 'Splitting an address into network and host parts with masks and CIDR notation.',
                    'objectives' => ['Convert between subnet masks and CIDR prefixes', 'Calculate how many hosts fit in a subnet', 'Find the network and broadcast address of a subnet'],
                    'takeaways' => [
                        'The prefix length (/24) says how many leading bits identify the network.',
                        'A subnet with h host bits holds 2^h - 2 usable hosts.',
                        'Subnets let you separate systems so firewalls can control traffic between them.',
                    ],
                    'resources' => [
                        ['RFC 4632: Classless Inter-domain Routing (CIDR)', 'https://www.rfc-editor.org/rfc/rfc4632'],
                    ],
                    'sections' => [
                        ['prose', 'Network part and host part', <<<'MD'
A **subnet mask** marks which bits of an address belong to the network. `255.255.255.0` is 24 ones followed by 8 zeros, so the first 24 bits are the network and the last 8 identify hosts. **CIDR notation** writes the same thing as a prefix length: `192.168.1.0/24`.
MD],
                        ['bitfield', 'A /26 bit by bit', [
                            'rows' => [
                                ['label' => 'Address 192.168.1.70', 'fields' => [
                                    ['label' => 'Network (26 bits)', 'bits' => '11000000101010000000000101', 'tone' => 'accent'],
                                    ['label' => 'Host (6 bits)', 'bits' => '000110', 'tone' => 'attn'],
                                ]],
                                ['label' => 'Mask /26 = 255.255.255.192', 'fields' => [
                                    ['label' => 'Ones: network', 'bits' => '11111111111111111111111111', 'tone' => 'accent'],
                                    ['label' => 'Zeros: host', 'bits' => '000000', 'tone' => 'muted'],
                                ]],
                                ['label' => 'Network 192.168.1.64', 'fields' => [
                                    ['label' => 'Same network bits', 'bits' => '11000000101010000000000101', 'tone' => 'accent'],
                                    ['label' => 'Host bits all 0', 'bits' => '000000', 'tone' => 'muted'],
                                ]],
                                ['label' => 'Broadcast 192.168.1.127', 'fields' => [
                                    ['label' => 'Same network bits', 'bits' => '11000000101010000000000101', 'tone' => 'accent'],
                                    ['label' => 'Host bits all 1', 'bits' => '111111', 'tone' => 'muted'],
                                ]],
                            ],
                        ]],
                        ['steps', 'Working out a /26', [
                            'A /26 leaves 32 - 26 = 6 host bits.',
                            '6 host bits give 2^6 = 64 addresses per subnet.',
                            'Two are reserved: the network address and the broadcast address, so 62 hosts are usable.',
                            'Subnets of 64 start at .0, .64, .128 and .192.',
                            'For 192.168.1.70/26 the network is 192.168.1.64 and the broadcast is 192.168.1.127.',
                        ]],
                        ['calculator', 'Try other subnets', ['tool' => 'cidr', 'initial' => '192.168.1.70/26']],
                        ['code', 'Check your work with Python', <<<'PY'
import ipaddress

net = ipaddress.ip_network("192.168.1.70/26", strict=False)
print(net)                      # 192.168.1.64/26
print(net.broadcast_address)    # 192.168.1.127
print(net.num_addresses - 2)    # 62 usable hosts
PY, 'python'],
                        ['callout', 'Segmentation is a security control', 'Putting servers, staff laptops and guest Wi-Fi in different subnets means an attacker who compromises one device cannot reach everything else without passing a router or firewall.', 'info'],
                        ['topology', 'Segmentation in practice', [
                            'aspect' => '4:3',
                            'zones' => [
                                ['id' => 'staff', 'label' => 'Staff 10.0.10.0/24', 'tone' => 'trusted', 'x' => 52, 'y' => 0, 'w' => 48, 'h' => 18],
                                ['id' => 'servers', 'label' => 'Servers 10.0.20.0/24', 'tone' => 'neutral', 'x' => 52, 'y' => 37, 'w' => 48, 'h' => 24],
                                ['id' => 'guest', 'label' => 'Guest Wi-Fi 10.0.30.0/24', 'tone' => 'untrusted', 'x' => 52, 'y' => 80, 'w' => 48, 'h' => 20],
                            ],
                            'devices' => [
                                ['id' => 'internet', 'label' => 'Internet', 'kind' => 'internet', 'x' => 2, 'y' => 49],
                                ['id' => 'fw', 'label' => 'Firewall', 'kind' => 'firewall', 'x' => 26, 'y' => 49, 'detail' => 'The gateway (.1) of each subnet. It routes between the three subnets and the internet, and applies rules at every crossing: staff may reach the file server, guests may reach only the internet, and nothing from outside may start a connection inward.'],
                                ['id' => 'sw-staff', 'label' => 'Staff switch', 'kind' => 'switch', 'x' => 64, 'y' => 9],
                                ['id' => 'laptop', 'label' => 'Staff laptop', 'kind' => 'laptop', 'x' => 92, 'y' => 9, 'address' => '10.0.10.21'],
                                ['id' => 'sw-servers', 'label' => 'Server switch', 'kind' => 'switch', 'x' => 64, 'y' => 49],
                                ['id' => 'files', 'label' => 'File server', 'kind' => 'server', 'x' => 92, 'y' => 40, 'address' => '10.0.20.5'],
                                ['id' => 'db', 'label' => 'Database', 'kind' => 'database', 'x' => 92, 'y' => 59, 'address' => '10.0.20.6', 'detail' => 'Accepts connections only from the file server\'s subnet. Even a staff laptop cannot reach it directly, so a phished laptop is not one step from the data.'],
                                ['id' => 'ap', 'label' => 'Guest access point', 'kind' => 'access-point', 'x' => 64, 'y' => 90],
                                ['id' => 'phone', 'label' => 'Visitor phone', 'kind' => 'phone', 'x' => 92, 'y' => 90, 'address' => '10.0.30.57', 'detail' => 'Can reach the internet and nothing internal. If it is infected, it cannot scan or attack the staff or server subnets.'],
                            ],
                            'links' => [
                                ['from' => 'internet', 'to' => 'fw'],
                                ['from' => 'fw', 'to' => 'sw-staff'],
                                ['from' => 'fw', 'to' => 'sw-servers'],
                                ['from' => 'fw', 'to' => 'ap'],
                                ['from' => 'sw-staff', 'to' => 'laptop'],
                                ['from' => 'sw-servers', 'to' => 'files'],
                                ['from' => 'sw-servers', 'to' => 'db'],
                                ['from' => 'ap', 'to' => 'phone', 'style' => 'wireless'],
                            ],
                        ]],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'How many usable host addresses does a /28 subnet have?',
                                    'options' => [
                                        ['text' => '14', 'correct' => true],
                                        ['text' => '16', 'correct' => false],
                                        ['text' => '28', 'correct' => false],
                                        ['text' => '30', 'correct' => false],
                                    ],
                                    'explanation' => '32 - 28 = 4 host bits, so 2^4 = 16 addresses. Take away the network and broadcast addresses and 14 are usable.',
                                ],
                                [
                                    'prompt' => 'Which network does `10.0.5.200/22` belong to?',
                                    'options' => [
                                        ['text' => '10.0.4.0/22', 'correct' => true],
                                        ['text' => '10.0.5.0/22', 'correct' => false],
                                        ['text' => '10.0.0.0/22', 'correct' => false],
                                    ],
                                    'explanation' => 'A /22 groups third octets in blocks of 4 (0, 4, 8, …). 5 falls in the block starting at 4, so the network is 10.0.4.0/22 (10.0.4.0 to 10.0.7.255). Try it in the calculator above.',
                                ],
                                [
                                    'prompt' => 'In the segmented network above, a visitor\'s phone is infected. What can it reach?',
                                    'options' => [
                                        ['text' => 'Only the internet', 'correct' => true],
                                        ['text' => 'The staff laptops', 'correct' => false],
                                        ['text' => 'The file server and database', 'correct' => false],
                                    ],
                                    'explanation' => 'Guest traffic must pass the firewall to reach any other subnet, and the rules allow guests only out to the internet. That is the point of segmentation.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'dns-and-dhcp',
                    'title' => 'DNS and DHCP',
                    'minutes' => 20,
                    'summary' => 'The two services that make networks usable, and the ways attackers abuse them.',
                    'objectives' => ['Trace a DNS lookup from a browser to an authoritative server', 'Explain the four steps of DHCP', 'Describe common DNS and DHCP attacks'],
                    'takeaways' => [
                        'DNS turns names into IP addresses through a chain of resolvers and authoritative servers.',
                        'DHCP hands out addresses with Discover, Offer, Request, Acknowledge.',
                        'Both protocols trust their answers by default, which attackers exploit.',
                    ],
                    'resources' => [
                        ['Cloudflare: What is DNS?', 'https://www.cloudflare.com/learning/dns/what-is-dns/'],
                        ['RFC 2131: Dynamic Host Configuration Protocol', 'https://www.rfc-editor.org/rfc/rfc2131'],
                    ],
                    'sections' => [
                        ['tree', 'Names form a tree', [
                            'nodes' => [
                                ['id' => 'root', 'label' => 'Root', 'note' => '.', 'detail' => 'The top of the tree. 13 root server names (a to m) are run by 12 organisations, with hundreds of copies worldwide. They know where every top-level domain is.'],
                                ['id' => 'com', 'label' => 'com', 'parent' => 'root', 'note' => 'top-level domain', 'detail' => 'A top-level domain (TLD), run by a registry. Its servers know which name servers are responsible for each domain under .com.'],
                                ['id' => 'org', 'label' => 'org', 'parent' => 'root', 'note' => 'top-level domain'],
                                ['id' => 'vn', 'label' => 'vn', 'parent' => 'root', 'note' => 'country code TLD', 'detail' => 'Country code top-level domains, such as .vn for Vietnam, are run by a national registry.'],
                                ['id' => 'example', 'label' => 'example.com', 'parent' => 'com', 'note' => 'domain', 'detail' => 'A domain. Its owner chooses the authoritative name servers, which hold the real records: addresses, mail servers and so on.'],
                                ['id' => 'www', 'label' => 'www.example.com', 'parent' => 'example', 'note' => 'host name'],
                                ['id' => 'mail', 'label' => 'mail.example.com', 'parent' => 'example', 'note' => 'host name'],
                                ['id' => 'edu-vn', 'label' => 'edu.vn', 'parent' => 'vn', 'note' => 'second level'],
                                ['id' => 'uit', 'label' => 'uit.edu.vn', 'parent' => 'edu-vn', 'note' => 'domain'],
                            ],
                        ]],
                        ['sequence', 'Resolving www.example.com', [
                            'actors' => [
                                ['id' => 'laptop', 'name' => 'Laptop'],
                                ['id' => 'resolver', 'name' => 'Resolver'],
                                ['id' => 'root', 'name' => 'Root'],
                                ['id' => 'tld', 'name' => '.com TLD'],
                                ['id' => 'auth', 'name' => 'Authoritative'],
                            ],
                            'messages' => [
                                ['from' => 'laptop', 'to' => 'resolver', 'label' => 'Query', 'detail' => 'A?', 'explanation' => 'The browser needs the address (an A record) for www.example.com. It asks the operating system, which checks its cache. On a miss it sends the question to its recursive resolver, usually run by the organisation or the internet provider.'],
                                ['from' => 'resolver', 'to' => 'root', 'label' => 'Query', 'detail' => 'A?', 'explanation' => 'The resolver has nothing cached either, so it starts at the top: a root server, whose addresses it has built in.'],
                                ['from' => 'root', 'to' => 'resolver', 'label' => 'Referral', 'detail' => '.com', 'explanation' => 'The root server does not know the answer, but it knows who is responsible for .com, and sends back a referral to those servers.'],
                                ['from' => 'resolver', 'to' => 'tld', 'label' => 'Query', 'detail' => 'A?', 'explanation' => 'The resolver asks a .com server the same question.'],
                                ['from' => 'tld', 'to' => 'resolver', 'label' => 'Referral', 'detail' => 'example.com', 'explanation' => 'The .com server refers it to the authoritative name servers that the owner of example.com chose.'],
                                ['from' => 'resolver', 'to' => 'auth', 'label' => 'Query', 'detail' => 'A?', 'explanation' => 'The resolver asks the authoritative server, which holds the actual records.'],
                                ['from' => 'auth', 'to' => 'resolver', 'label' => 'Answer', 'detail' => '203.0.113.10', 'explanation' => 'The authoritative server for example.com answers with the A record, the address (a documentation address here), and a TTL of 3600: how many seconds the answer may be cached.'],
                                ['from' => 'resolver', 'to' => 'laptop', 'label' => 'Answer', 'detail' => '203.0.113.10', 'explanation' => 'The resolver caches the answer and returns it. For the next hour, anyone asking this resolver for www.example.com gets the cached answer without the chain.'],
                            ],
                        ]],
                        ['code', 'Try it yourself', <<<'SH'
dig www.example.com +short
dig www.example.com +trace
SH, 'bash'],
                        ['sequence', 'DHCP: getting an address', [
                            'actors' => [['id' => 'client', 'name' => 'New laptop'], ['id' => 'server', 'name' => 'DHCP server']],
                            'messages' => [
                                ['from' => 'client', 'to' => 'server', 'label' => 'Discover', 'detail' => 'broadcast', 'explanation' => 'The laptop has no address yet, so it broadcasts to the whole local network from 0.0.0.0 to 255.255.255.255 (UDP port 68 to 67): is there a DHCP server?', 'states' => ['client' => 'INIT']],
                                ['from' => 'server', 'to' => 'client', 'label' => 'Offer', 'detail' => '192.168.1.50', 'explanation' => 'A DHCP server offers an address from its pool, with the lease time.', 'states' => ['client' => 'SELECTING']],
                                ['from' => 'client', 'to' => 'server', 'label' => 'Request', 'detail' => 'broadcast: .50 please', 'explanation' => 'The laptop asks for the offered address. It broadcasts again, so any other server that made an offer knows it was declined.', 'states' => ['client' => 'REQUESTING']],
                                ['from' => 'server', 'to' => 'client', 'label' => 'Acknowledge', 'detail' => 'lease 24 h', 'explanation' => 'The server confirms. The lease also carries the subnet mask, the default gateway and the DNS servers, so the laptop can now reach everything else.', 'states' => ['client' => 'BOUND']],
                            ],
                        ]],
                        ['callout', 'How they get abused', 'DNS spoofing returns false answers that send users to an attacker\'s server. A rogue DHCP server can hand out itself as the gateway and see all traffic. DNS is also used to smuggle data out (DNS tunnelling), which is why defenders watch for unusual query patterns.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'An attacker plugs a small device into a meeting room socket and answers DHCP requests faster than the real server, naming itself as the default gateway. What does the attacker gain?',
                                    'options' => [
                                        ['text' => 'Victims send their outbound traffic through the attacker\'s device', 'correct' => true],
                                        ['text' => 'The attacker can read the DNS server\'s records', 'correct' => false],
                                        ['text' => 'Nothing, because DHCP only hands out IP addresses', 'correct' => false],
                                    ],
                                    'explanation' => 'A DHCP lease also sets the gateway and DNS servers. A rogue server that names itself as gateway sees, and can alter, the victims\' traffic. Switch features such as DHCP snooping block answers from unauthorised ports.',
                                ],
                                [
                                    'prompt' => 'Who holds the real address records for www.example.com?',
                                    'options' => [
                                        ['text' => 'The authoritative name servers for example.com', 'correct' => true],
                                        ['text' => 'The root servers', 'correct' => false],
                                        ['text' => 'Your organisation\'s recursive resolver', 'correct' => false],
                                    ],
                                    'explanation' => 'Root and .com servers only refer the resolver onwards. The resolver keeps a cached copy for the TTL, but the source of truth is the domain\'s authoritative servers.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
    ],
];
