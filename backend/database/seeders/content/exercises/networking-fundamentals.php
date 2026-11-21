<?php

/*
 * Exercises for the Networking Fundamentals lessons (ARCHITECTURE D29 to D31), loaded by
 * ExerciseLoader. Keyed by lesson slug, then by a stable question key: change a key only for a
 * question that changes meaning. Every question names the section that teaches it ('section' is
 * the section key, its heading as a slug). Formats per type: Learning\Services\QuestionTypes.
 * These are graded questions; they never repeat the lesson's ungraded knowledge checks.
 */

return [
    'intro-to-computer-networks' => [
        'questions' => [
            'device-roles' => [
                'type' => 'matching',
                'prompt' => 'Match each device to what it does.',
                'pairs' => [
                    ['Host', 'Creates and consumes data'],
                    ['Switch', 'Forwards frames inside one local network by MAC address'],
                    ['Router', 'Forwards packets between networks by IP address'],
                    ['Firewall', 'Decides which traffic may cross a boundary'],
                ],
                'explanation' => 'Hosts are the endpoints. Switches work inside one network with MAC addresses, routers join networks with IP addresses, and firewalls sit at boundaries applying rules.',
                'section' => 'the-devices-in-the-path',
            ],
            'protocols' => [
                'type' => 'true_false',
                'prompt' => 'Protocols are shared rules about how to format, address and deliver data.',
                'answer' => true,
                'explanation' => 'That is what lets a laptop, a web server and a thermostat from different makers talk to each other: they follow the same protocols.',
                'section' => 'what-a-network-is',
            ],
            'lan-or-wan' => [
                'type' => 'single_choice',
                'prompt' => 'A company links the networks of its Hanoi and Ho Chi Minh City offices so staff can reach the same servers. What is that link?',
                'options' => ['A WAN', 'A LAN', 'A switch'],
                'answer' => 0,
                'explanation' => 'Each office has its own LAN. A network that connects LANs across distance is a WAN.',
                'section' => 'lans-wans-and-the-internet',
            ],
            'packet-path' => [
                'type' => 'ordering',
                'prompt' => 'Put the stops in the order a request from an office laptop to a public web server passes them.',
                'items' => ['Laptop', 'Switch', 'Router', 'Firewall', 'Routers across the internet', 'Web server'],
                'explanation' => 'The switch carries the frame to the gateway router, the router sends it out of the network, the firewall at the boundary lets it through, and routers on the internet forward it hop by hop to the server.',
                'section' => 'follow-a-packet',
            ],
            'block-at-boundary' => [
                'type' => 'scenario',
                'scenario' => 'A web server in your data centre receives connection attempts from the internet on a port that should never be open to the public.',
                'prompt' => 'Which device can stop that traffic before it reaches the server\'s network?',
                'options' => ['The firewall at the network boundary', 'The switch the server is plugged into', 'A router run by the attacker\'s internet provider'],
                'answer' => 0,
                'explanation' => 'Firewalls sit at boundaries and decide which traffic may cross. Knowing which device sees which traffic tells you where an attack can be stopped.',
                'section' => 'why-this-matters-for-security',
            ],
        ],
    ],

    'osi-model' => [
        'questions' => [
            'layer-order' => [
                'type' => 'ordering',
                'prompt' => 'Put the OSI layers in order, from layer 1 to layer 7.',
                'items' => ['Physical', 'Data link', 'Network', 'Transport', 'Session', 'Presentation', 'Application'],
                'explanation' => 'From the bottom: Physical, Data link, Network, Transport, Session, Presentation, Application ("Please Do Not Throw Sausage Pizza Away").',
                'section' => 'the-seven-layers',
            ],
            'protocol-layers' => [
                'type' => 'matching',
                'prompt' => 'Match each protocol to its OSI layer.',
                'pairs' => [
                    ['HTTP', 'Layer 7, application'],
                    ['TLS', 'Layer 6, presentation'],
                    ['TCP', 'Layer 4, transport'],
                    ['IP', 'Layer 3, network'],
                    ['Ethernet', 'Layer 2, data link'],
                ],
                'explanation' => 'HTTP serves applications, TLS encrypts and formats data, TCP delivers between processes using ports, IP addresses and routes packets, and Ethernet moves frames on the local network.',
                'section' => 'the-seven-layers',
            ],
            'data-units' => [
                'type' => 'fill_blank',
                'prompt' => 'Name the data unit at each layer.',
                'text' => 'At the transport layer the data unit is a ___, at the network layer a ___, and at the data link layer a ___.',
                'answers' => [['segment'], ['packet'], ['frame']],
                'explanation' => 'Each layer wraps what it receives from the layer above: data becomes a segment (TCP header), then a packet (IP header), then a frame (Ethernet header and trailer).',
                'section' => 'encapsulation',
            ],
            'syn-flood-layer' => [
                'type' => 'scenario',
                'scenario' => 'A server\'s table of half-open TCP connections fills up because of a flood of SYN packets, and real users can no longer connect.',
                'prompt' => 'Which OSI layer is under attack?',
                'options' => ['Layer 4, transport', 'Layer 2, data link', 'Layer 7, application', 'Layer 1, physical'],
                'answer' => 0,
                'explanation' => 'SYN floods abuse TCP connection setup, which belongs to the transport layer.',
                'section' => 'the-seven-layers',
            ],
            'numbers-still-used' => [
                'type' => 'true_false',
                'prompt' => 'Real networks mostly run the TCP/IP model, but people still use OSI layer numbers to say where something happens.',
                'answer' => true,
                'explanation' => 'Phrases such as "a layer 2 attack" or "a layer 7 load balancer" use OSI numbers even though the traffic runs on TCP/IP.',
                'section' => 'why-a-layered-model',
            ],
        ],
    ],

    'tcp-ip-model' => [
        'questions' => [
            'tcpip-to-osi' => [
                'type' => 'matching',
                'prompt' => 'Match each TCP/IP layer to the OSI layers it covers.',
                'pairs' => [
                    ['Application', 'OSI 5 to 7'],
                    ['Transport', 'OSI 4'],
                    ['Internet', 'OSI 3'],
                    ['Link', 'OSI 1 and 2'],
                ],
                'explanation' => 'TCP/IP folds session, presentation and application into one layer, and physical and data link into another.',
                'section' => 'four-layers-instead-of-seven',
            ],
            'handshake-order' => [
                'type' => 'ordering',
                'prompt' => 'Put the steps of opening a TCP connection in order.',
                'items' => ['The client sends SYN', 'The server replies with SYN-ACK', 'The client sends ACK', 'Both sides are ESTABLISHED'],
                'explanation' => 'SYN, SYN-ACK, ACK: after the third message both sides agree on the starting sequence numbers and the connection is open.',
                'section' => 'the-three-way-handshake',
            ],
            'handshake-numbers' => [
                'type' => 'fill_blank',
                'prompt' => 'Fill in the acknowledgement numbers.',
                'text' => 'The client sends SYN with seq=100. The server answers SYN-ACK with seq=300 and ack=___. The client then sends ACK with ack=___.',
                'answers' => [['101'], ['301']],
                'explanation' => 'Each side acknowledges the other\'s sequence number by naming the next number it expects: 100 + 1 and 300 + 1.',
                'section' => 'the-three-way-handshake',
            ],
            'tcp-properties' => [
                'type' => 'multi_choice',
                'prompt' => 'Which of these are properties of TCP? Choose all that apply.',
                'options' => ['Lost data is sent again', 'Data arrives in order', 'Nothing is set up before data is sent', 'An 8-byte header'],
                'answer' => [0, 1],
                'explanation' => 'TCP is reliable and ordered. No connection setup and the 8-byte header describe UDP.',
                'section' => 'tcp-and-udp',
            ],
            'port-scan' => [
                'type' => 'scenario',
                'scenario' => 'A firewall log shows one outside address sending a single SYN to each of ports 1 to 1024 on a server within a few seconds. None of the handshakes is completed.',
                'prompt' => 'What is the most likely explanation?',
                'options' => ['A port scan mapping which services answer', 'A user downloading a large file', 'A burst of DNS lookups'],
                'answer' => 0,
                'explanation' => 'Port scanners send SYNs to see which ports answer with SYN-ACK, then drop the connection. One source touching many ports in seconds is the signature.',
                'section' => 'attackers-use-the-handshake-too',
            ],
        ],
    ],

    'ip-addressing' => [
        'questions' => [
            'binary-octets' => [
                'type' => 'fill_blank',
                'prompt' => 'Convert the octets to decimal.',
                'text' => 'The octet 11000000 is ___ in decimal, and 10101000 is ___.',
                'answers' => [['192'], ['168']],
                'explanation' => '11000000 is 128 + 64 = 192. 10101000 is 128 + 32 + 8 = 168.',
                'section' => '192168120-in-binary',
            ],
            'special-ranges' => [
                'type' => 'matching',
                'prompt' => 'Match each address to what it tells you.',
                'pairs' => [
                    ['10.20.30.40', 'Private: inside an organisation'],
                    ['127.0.0.1', 'Loopback: never leaves the machine'],
                    ['169.254.10.5', 'Link-local: the device could not reach DHCP'],
                    ['8.8.8.8', 'Public: routed on the internet'],
                ],
                'explanation' => '10.0.0.0/8 is private, 127.0.0.0/8 is loopback, 169.254.0.0/16 is link-local, and an address outside the special ranges is public.',
                'section' => 'ranges-worth-recognising',
            ],
            'octet-range' => [
                'type' => 'true_false',
                'prompt' => 'Each octet of an IPv4 address can hold any value from 0 to 255.',
                'answer' => true,
                'explanation' => 'An octet is 8 bits, which can hold 256 different values: 0 to 255.',
                'section' => 'anatomy-of-an-ipv4-address',
            ],
            'ipv6-length' => [
                'type' => 'single_choice',
                'prompt' => 'How long is an IPv6 address?',
                'options' => ['128 bits', '32 bits', '64 bits', '48 bits'],
                'answer' => 0,
                'explanation' => 'IPv4 addresses are 32 bits; IPv6 uses 128 bits, written as eight groups of hexadecimal.',
                'section' => 'ipv6-in-one-paragraph',
            ],
            'private-addresses' => [
                'type' => 'multi_choice',
                'prompt' => 'Which of these are private addresses? Choose all that apply.',
                'options' => ['192.168.4.7', '172.31.0.9', '172.32.0.9', '11.0.0.1'],
                'answer' => [0, 1],
                'explanation' => '192.168.0.0/16 is private, and 172.16.0.0/12 covers 172.16.0.0 to 172.31.255.255. 172.32.0.9 is just outside it, and 11.0.0.1 is outside 10.0.0.0/8.',
                'section' => 'ranges-worth-recognising',
            ],
            'night-login' => [
                'type' => 'scenario',
                'scenario' => 'The VPN gateway log shows a successful login for the finance manager from 203.0.113.45 at 03:10. She says she was asleep.',
                'prompt' => 'What does the source address tell you?',
                'options' => ['It is a public address, so the login came from the internet', 'It is a private address, so it came from inside the office', 'It is the loopback address, so the gateway logged in to itself'],
                'answer' => 0,
                'explanation' => '203.0.113.45 is not in a private, loopback or link-local range, so the connection came from the internet: a strong reason to treat the account as compromised.',
                'section' => 'reading-logs',
            ],
        ],
    ],

    'subnetting' => [
        'questions' => [
            'size-of-27' => [
                'type' => 'fill_blank',
                'prompt' => 'Work out the size of a /27.',
                'text' => 'A /27 leaves ___ host bits, so each subnet has ___ addresses and ___ usable hosts.',
                'answers' => [['5'], ['32'], ['30']],
                'explanation' => '32 - 27 = 5 host bits, 2^5 = 32 addresses, minus the network and broadcast addresses leaves 30.',
                'section' => 'working-out-a-26',
            ],
            'mask-to-prefix' => [
                'type' => 'single_choice',
                'prompt' => 'Which prefix length matches the mask 255.255.255.240?',
                'options' => ['/28', '/24', '/26', '/30'],
                'answer' => 0,
                'explanation' => '240 is 11110000: four more ones after the first 24, so 28 network bits.',
                'section' => 'network-part-and-host-part',
            ],
            'network-and-broadcast' => [
                'type' => 'single_choice',
                'prompt' => 'A host has the address 192.168.1.130/26. What are its network and broadcast addresses?',
                'options' => [
                    'Network 192.168.1.128, broadcast 192.168.1.191',
                    'Network 192.168.1.64, broadcast 192.168.1.127',
                    'Network 192.168.1.0, broadcast 192.168.1.255',
                    'Network 192.168.1.128, broadcast 192.168.1.255',
                ],
                'answer' => 0,
                'explanation' => 'Subnets of a /26 hold 64 addresses and start at .0, .64, .128 and .192. 130 falls in the block from .128 to .191.',
                'section' => 'working-out-a-26',
            ],
            'subnet-method' => [
                'type' => 'ordering',
                'prompt' => 'Put the steps for finding the network of an address such as 192.168.1.70/26 in order.',
                'items' => [
                    'Subtract the prefix length from 32 to get the host bits',
                    'Work out how many addresses each subnet holds',
                    'Find the block the address falls in',
                    'Read the network and broadcast addresses at the edges of that block',
                ],
                'explanation' => 'Host bits give the block size, the block size gives the subnet boundaries, and the address\'s block gives its network (first address) and broadcast (last address).',
                'section' => 'working-out-a-26',
            ],
            'reserved-addresses' => [
                'type' => 'true_false',
                'prompt' => 'In a subnet, the network address and the broadcast address cannot be given to hosts.',
                'answer' => true,
                'explanation' => 'Those two are reserved, which is why a /26 has 64 addresses but 62 usable hosts.',
                'section' => 'working-out-a-26',
            ],
            'phished-laptop' => [
                'type' => 'scenario',
                'scenario' => 'An attacker phishes a staff laptop on 10.0.10.0/24. The database at 10.0.20.6 accepts connections only from the file server\'s subnet, and every subnet crossing passes the firewall.',
                'prompt' => 'Can the attacker connect to the database straight from the laptop?',
                'options' => [
                    'No: both the firewall rules and the database\'s own rule block it',
                    'Yes: everything inside the company network can reach everything else',
                    'Yes, because the laptop and database share a switch',
                ],
                'answer' => 0,
                'explanation' => 'Segmentation puts staff, servers and guests in different subnets, so a compromised device has to pass a firewall to reach anything else. The database adds its own restriction on top.',
                'section' => 'segmentation-in-practice',
            ],
        ],
    ],

    'dns-and-dhcp' => [
        'questions' => [
            'resolution-order' => [
                'type' => 'ordering',
                'prompt' => 'A resolver with an empty cache looks up www.example.com. Put the steps in order.',
                'items' => [
                    'The resolver asks a root server',
                    'The root server refers it to the .com servers',
                    'A .com server refers it to the authoritative servers for example.com',
                    'The authoritative server answers with the address',
                    'The resolver caches the answer and returns it',
                ],
                'explanation' => 'Resolution walks down the tree: root, top-level domain, then the domain\'s authoritative servers, which hold the real record. The answer is cached for its TTL.',
                'section' => 'resolving-wwwexamplecom',
            ],
            'dhcp-order' => [
                'type' => 'ordering',
                'prompt' => 'Put the four DHCP messages in order.',
                'items' => ['Discover', 'Offer', 'Request', 'Acknowledge'],
                'explanation' => 'The client discovers servers, a server offers an address, the client requests it, and the server acknowledges with the lease (DORA).',
                'section' => 'dhcp-getting-an-address',
            ],
            'lease-contents' => [
                'type' => 'multi_choice',
                'prompt' => 'Besides an IP address, what does a DHCP lease give a laptop? Choose all that apply.',
                'options' => ['The subnet mask', 'The default gateway', 'The DNS servers', 'The laptop\'s MAC address'],
                'answer' => [0, 1, 2],
                'explanation' => 'The lease carries the mask, gateway and DNS servers. The MAC address belongs to the laptop\'s network card; DHCP does not assign it.',
                'section' => 'dhcp-getting-an-address',
            ],
            'ttl-meaning' => [
                'type' => 'fill_blank',
                'prompt' => 'Complete the sentence about caching.',
                'text' => 'A DNS answer with a TTL of 3600 may be cached for ___ seconds, which is one ___.',
                'answers' => [['3600'], ['hour', '1 hour', 'one hour']],
                'explanation' => 'TTL is the number of seconds an answer may be cached: 3600 seconds is one hour.',
                'section' => 'resolving-wwwexamplecom',
            ],
            'discover-broadcast' => [
                'type' => 'true_false',
                'prompt' => 'A new laptop broadcasts its DHCP Discover because it has no IP address yet and does not know where the server is.',
                'answer' => true,
                'explanation' => 'It sends from 0.0.0.0 to 255.255.255.255, so every device on the local network, including any DHCP server, hears it.',
                'section' => 'dhcp-getting-an-address',
            ],
            'odd-queries' => [
                'type' => 'scenario',
                'scenario' => 'A workstation sends thousands of DNS queries an hour for long, random-looking names under one unfamiliar domain, such as a8f3k2q9x7.data.example.net.',
                'prompt' => 'What should a defender suspect first?',
                'options' => ['Data being smuggled out through DNS (DNS tunnelling)', 'A normal software update', 'A rogue DHCP server'],
                'answer' => 0,
                'explanation' => 'DNS tunnelling hides data in query names sent to a domain the attacker controls. Unusual volumes of long, random names are what defenders watch for.',
                'section' => 'how-they-get-abused',
            ],
        ],
    ],
];
