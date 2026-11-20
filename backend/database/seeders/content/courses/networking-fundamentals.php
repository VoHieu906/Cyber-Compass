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
                    'minutes' => 10,
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
                        ['prose', 'LANs, WANs and the internet', <<<'MD'
A **LAN** (local area network) covers one place: a home, an office floor, a lab. A **WAN** (wide area network) connects LANs across distance. The internet is a vast network of networks, joined by routers run by many different organisations.
MD],
                        ['callout', 'Why this matters for security', 'Almost every attack crosses a network. Knowing which device sees which traffic tells you where you can detect it, and where you can stop it.', 'info'],
                    ],
                ],
                [
                    'slug' => 'osi-model',
                    'title' => 'The OSI model',
                    'minutes' => 15,
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
                        ['prose', 'Encapsulation', <<<'MD'
When you send data, each layer wraps it with its own header on the way down: the transport layer adds ports, the network layer adds IP addresses, the data link layer adds MAC addresses. The receiver unwraps the layers in reverse order. This wrapping is called **encapsulation**.
MD],
                        ['callout', 'A way to remember the order', 'From layer 1 up: Physical, Data link, Network, Transport, Session, Presentation, Application. Many people use the phrase "Please Do Not Throw Sausage Pizza Away".', 'tip'],
                    ],
                ],
                [
                    'slug' => 'tcp-ip-model',
                    'title' => 'The TCP/IP model and the handshake',
                    'minutes' => 15,
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
                        ['prose', 'Four layers instead of seven', <<<'MD'
The TCP/IP model groups the OSI layers into four:

- **Link**: OSI layers 1 and 2. Ethernet, Wi-Fi.
- **Internet**: OSI layer 3. IP, ICMP.
- **Transport**: OSI layer 4. TCP, UDP.
- **Application**: OSI layers 5 to 7. HTTP, DNS, SSH.
MD],
                        ['prose', 'TCP and UDP', <<<'MD'
**TCP** sets up a connection, numbers every byte, retransmits anything lost and delivers data in order. Web traffic, email and SSH use it.

**UDP** just sends datagrams with no connection and no delivery guarantee. It is faster and simpler, so DNS lookups, video calls and games often use it.
MD],
                        ['sequence', 'The three-way handshake', [
                            'actors' => [['id' => 'client', 'name' => 'Client'], ['id' => 'server', 'name' => 'Server']],
                            'messages' => [
                                ['from' => 'client', 'to' => 'server', 'label' => 'SYN', 'detail' => 'seq=100', 'explanation' => 'The client asks to open a connection and picks a random initial sequence number, here 100.', 'states' => ['client' => 'SYN-SENT', 'server' => 'LISTEN']],
                                ['from' => 'server', 'to' => 'client', 'label' => 'SYN-ACK', 'detail' => 'seq=300 ack=101', 'explanation' => 'The server agrees, picks its own sequence number (300) and acknowledges the client\'s by expecting 101 next.', 'states' => ['client' => 'SYN-SENT', 'server' => 'SYN-RECEIVED']],
                                ['from' => 'client', 'to' => 'server', 'label' => 'ACK', 'detail' => 'seq=101 ack=301', 'explanation' => 'The client acknowledges the server\'s sequence number. Both sides now agree on the starting numbers.', 'states' => ['client' => 'ESTABLISHED', 'server' => 'ESTABLISHED']],
                            ],
                        ]],
                        ['callout', 'Attackers use the handshake too', 'A SYN flood sends many SYN packets and never completes the handshake, filling the server\'s table of half-open connections. Port scanners send SYNs to see which ports answer with SYN-ACK.', 'warning'],
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
An IPv4 address is a 32-bit number. We write it as four **octets** of 8 bits each, in decimal, separated by dots: `192.168.1.20`. Each octet ranges from 0 to 255.

In binary, `192.168.1.20` is `11000000.10101000.00000001.00010100`. Subnetting is much easier once you can see the bits.
MD],
                        ['prose', 'Ranges worth recognising', <<<'MD'
- **Private**: `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`. Used inside organisations and translated (NAT) at the edge.
- **Loopback**: `127.0.0.0/8`. Traffic to `127.0.0.1` never leaves the machine.
- **Link-local**: `169.254.0.0/16`. Assigned automatically when DHCP fails.
MD],
                        ['callout', 'Reading logs', 'A login from a private address came from inside the network or through a VPN. A login from a public address came from the internet. That single fact changes how you investigate it.', 'info'],
                        ['prose', 'IPv6 in one paragraph', <<<'MD'
IPv4 has about 4.3 billion addresses, which ran out. **IPv6** uses 128 bits, written as eight groups of hexadecimal such as `2001:db8::1` (runs of zeros collapse to `::`). Many networks run both, which means firewall rules must cover both.
MD],
                    ],
                ],
                [
                    'slug' => 'subnetting',
                    'title' => 'Subnetting',
                    'minutes' => 20,
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
                        ['steps', 'Working out a /26', [
                            'A /26 leaves 32 - 26 = 6 host bits.',
                            '6 host bits give 2^6 = 64 addresses per subnet.',
                            'Two are reserved: the network address and the broadcast address, so 62 hosts are usable.',
                            'Subnets of 64 start at .0, .64, .128 and .192.',
                            'For 192.168.1.70/26 the network is 192.168.1.64 and the broadcast is 192.168.1.127.',
                        ]],
                        ['code', 'Check your work with Python', <<<'PY'
import ipaddress

net = ipaddress.ip_network("192.168.1.70/26", strict=False)
print(net)                      # 192.168.1.64/26
print(net.broadcast_address)    # 192.168.1.127
print(net.num_addresses - 2)    # 62 usable hosts
PY, 'python'],
                        ['callout', 'Segmentation is a security control', 'Putting servers, staff laptops and guest Wi-Fi in different subnets means an attacker who compromises one device cannot reach everything else without passing a router or firewall.', 'info'],
                    ],
                ],
                [
                    'slug' => 'dns-and-dhcp',
                    'title' => 'DNS and DHCP',
                    'minutes' => 15,
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
                        ['steps', 'Resolving www.example.com', [
                            'The browser asks the operating system, which checks its cache.',
                            'On a miss, the query goes to a recursive resolver, usually run by the ISP or the organisation.',
                            'The resolver asks a root server, which points it to the .com servers.',
                            'The .com servers point it to the authoritative servers for example.com.',
                            'The authoritative server answers with the IP address, which is cached along the way.',
                        ]],
                        ['code', 'Try it yourself', <<<'SH'
dig www.example.com +short
dig www.example.com +trace
SH, 'bash'],
                        ['prose', 'DHCP: getting an address', <<<'MD'
When a device joins a network it has no address yet. DHCP solves this in four messages, often remembered as **DORA**: the client broadcasts a **Discover**, a server makes an **Offer**, the client **Requests** it, and the server **Acknowledges**. The lease includes the address, mask, default gateway and DNS servers.
MD],
                        ['callout', 'How they get abused', 'DNS spoofing returns false answers that send users to an attacker\'s server. A rogue DHCP server can hand out itself as the gateway and see all traffic. DNS is also used to smuggle data out (DNS tunnelling), which is why defenders watch for unusual query patterns.', 'warning'],
                    ],
                ],
            ],
        ],
    ],
];
