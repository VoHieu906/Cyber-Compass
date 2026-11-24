<?php

return [
    'slug' => 'networking-fundamentals',
    'domain' => 'networking',
    'title' => 'Networking Fundamentals',
    'summary' => 'How data moves from one machine to another: devices, layers, addresses, routing, and the protocols underneath every connection.',
    'description' => 'Every security role reads network traffic, firewall rules or logs full of IP addresses. This course builds that mental model from the cable up: the devices and layered models, the local network and its switches and VLANs, addressing, routing and NAT, how TCP and UDP deliver data, how to troubleshoot a broken connection, and how to read the traffic itself. In November 2026 it grew from 6 to 20 lessons: if you finished the earlier version, your 6 completed lessons still count, and 14 new ones are waiting.',
    'difficulty' => 1,
    'published_at' => '2026-11-01',
    'objectives' => [
        'Explain what happens at each layer of the OSI and TCP/IP models',
        'Describe how switches, VLANs and ARP move traffic inside a local network',
        'Read IPv4 and IPv6 addresses, calculate subnets and follow a packet through routing and NAT',
        'Explain how TCP delivers data reliably and how to troubleshoot a connection layer by layer',
        'Explain how DNS, DHCP and Wi-Fi work, and capture and read traffic safely',
    ],
    'skills' => ['osi-tcp-ip-models', 'ip-addressing', 'core-protocols'],
    'prerequisites' => [],
    'graph' => [
        'version' => 1,
        'problem' => 'Get data between two machines that have never met, reliably and safely, and find where it breaks when it does not.',
        'layout' => 'manual',
        'lanes' => [
            ['id' => 'built', 'label' => 'How networks are built'],
            ['id' => 'local', 'label' => 'The local network'],
            ['id' => 'addressing', 'label' => 'Addressing and routing'],
            ['id' => 'transport', 'label' => 'Transport and diagnostics'],
            ['id' => 'services', 'label' => 'Services and seeing traffic'],
        ],
        'placement' => [
            'intro-to-computer-networks' => ['col' => 0, 'row' => 0, 'lane' => 'built'],
            'osi-model' => ['col' => 1, 'row' => 0, 'lane' => 'built'],
            'tcp-ip-model' => ['col' => 2, 'row' => 0, 'lane' => 'built'],
            'encapsulation-and-pdus' => ['col' => 3, 'row' => 0, 'lane' => 'built'],
            'network-devices-and-media' => ['col' => 1, 'row' => 1, 'lane' => 'built'],
            'ethernet-and-mac-addresses' => ['col' => 0, 'row' => 0, 'lane' => 'local'],
            'switching-and-the-mac-table' => ['col' => 1, 'row' => 0, 'lane' => 'local'],
            'vlans' => ['col' => 2, 'row' => 0, 'lane' => 'local'],
            'ip-addressing' => ['col' => 0, 'row' => 0, 'lane' => 'addressing'],
            'subnetting' => ['col' => 1, 'row' => 0, 'lane' => 'addressing'],
            'routing-basics' => ['col' => 2, 'row' => 0, 'lane' => 'addressing'],
            'nat-and-private-addresses' => ['col' => 3, 'row' => 0, 'lane' => 'addressing'],
            'arp' => ['col' => 1, 'row' => 1, 'lane' => 'addressing'],
            'ipv6-essentials' => ['col' => 2, 'row' => 1, 'lane' => 'addressing'],
            'tcp-and-udp-in-depth' => ['col' => 1, 'row' => 0, 'lane' => 'transport'],
            'icmp-and-troubleshooting' => ['col' => 2, 'row' => 0, 'lane' => 'transport'],
            'wifi-essentials' => ['col' => 1, 'row' => 0, 'lane' => 'services'],
            'application-protocols' => ['col' => 2, 'row' => 0, 'lane' => 'services'],
            'dns-and-dhcp' => ['col' => 3, 'row' => 0, 'lane' => 'services'],
            'packet-capture-basics' => ['col' => 3, 'row' => 1, 'lane' => 'services'],
        ],
        'edges' => [
            ['from' => 'intro-to-computer-networks', 'to' => 'osi-model', 'type' => 'builds-on'],
            ['from' => 'intro-to-computer-networks', 'to' => 'network-devices-and-media', 'type' => 'builds-on'],
            ['from' => 'osi-model', 'to' => 'tcp-ip-model', 'type' => 'contrasts-with'],
            ['from' => 'tcp-ip-model', 'to' => 'encapsulation-and-pdus', 'type' => 'builds-on'],
            ['from' => 'encapsulation-and-pdus', 'to' => 'ethernet-and-mac-addresses', 'type' => 'requires'],
            ['from' => 'ethernet-and-mac-addresses', 'to' => 'switching-and-the-mac-table', 'type' => 'requires'],
            ['from' => 'switching-and-the-mac-table', 'to' => 'vlans', 'type' => 'builds-on'],
            ['from' => 'tcp-ip-model', 'to' => 'ip-addressing', 'type' => 'requires'],
            ['from' => 'ip-addressing', 'to' => 'subnetting', 'type' => 'requires'],
            ['from' => 'subnetting', 'to' => 'routing-basics', 'type' => 'requires', 'label' => 'prefixes'],
            ['from' => 'routing-basics', 'to' => 'nat-and-private-addresses', 'type' => 'builds-on'],
            ['from' => 'ip-addressing', 'to' => 'ipv6-essentials', 'type' => 'builds-on'],
            ['from' => 'ip-addressing', 'to' => 'arp', 'type' => 'requires'],
            ['from' => 'ethernet-and-mac-addresses', 'to' => 'arp', 'type' => 'requires'],
            ['from' => 'ip-addressing', 'to' => 'tcp-and-udp-in-depth', 'type' => 'builds-on'],
            ['from' => 'routing-basics', 'to' => 'icmp-and-troubleshooting', 'type' => 'requires'],
            ['from' => 'tcp-and-udp-in-depth', 'to' => 'application-protocols', 'type' => 'requires', 'label' => 'ports'],
            ['from' => 'application-protocols', 'to' => 'dns-and-dhcp', 'type' => 'builds-on'],
            ['from' => 'ethernet-and-mac-addresses', 'to' => 'wifi-essentials', 'type' => 'builds-on'],
            ['from' => 'icmp-and-troubleshooting', 'to' => 'packet-capture-basics', 'type' => 'builds-on'],
        ],
    ],
    'chapters' => [
        [
            'title' => 'How networks are built',
            'lessons' => [
                [
                    'slug' => 'intro-to-computer-networks',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'What sits between my laptop and a server, and what does each piece do?',
                        'shape' => 'pipeline',
                        'nodes' => [
                            ['id' => 'host', 'label' => 'Your laptop', 'note' => 'A host creates the data and will consume the answer.', 'section' => 'the-devices-in-the-path'],
                            ['id' => 'switch', 'label' => 'Switch', 'note' => 'Forwards inside the local network by hardware (MAC) address.', 'section' => 'the-devices-in-the-path', 'lesson' => 'switching-and-the-mac-table'],
                            ['id' => 'router', 'label' => 'Router', 'note' => 'Forwards between networks by IP address.', 'section' => 'the-devices-in-the-path', 'lesson' => 'routing-basics'],
                            ['id' => 'firewall', 'label' => 'Firewall', 'note' => 'Decides which traffic may cross the boundary.', 'section' => 'the-devices-in-the-path'],
                            ['id' => 'internet', 'label' => 'The internet', 'note' => 'Many networks run by different organisations, joined by routers.', 'section' => 'lans-wans-and-the-internet'],
                            ['id' => 'server', 'label' => 'Server', 'note' => 'Another host. The reply makes the same trip back.', 'section' => 'follow-a-packet'],
                        ],
                        'edges' => [],
                    ],
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
                    'slug' => 'network-devices-and-media',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'Which box and which medium does which job on a network?',
                        'shape' => 'matrix',
                        'rows' => ['Switch', 'Router', 'Access point', 'Firewall'],
                        'cols' => ['Works at', 'Decides using'],
                        'nodes' => [
                            ['id' => 'sw-at', 'label' => 'Layer 2 (data link)', 'section' => 'what-each-device-reads', 'cell' => [0, 0]],
                            ['id' => 'sw-uses', 'label' => 'Destination MAC and its MAC table', 'section' => 'what-each-device-reads', 'lesson' => 'switching-and-the-mac-table', 'cell' => [0, 1]],
                            ['id' => 'rt-at', 'label' => 'Layer 3 (network)', 'section' => 'what-each-device-reads', 'cell' => [1, 0]],
                            ['id' => 'rt-uses', 'label' => 'Destination IP and its routing table', 'section' => 'what-each-device-reads', 'lesson' => 'routing-basics', 'cell' => [1, 1]],
                            ['id' => 'ap-at', 'label' => 'Layer 2, between radio and cable', 'section' => 'what-each-device-reads', 'cell' => [2, 0]],
                            ['id' => 'ap-uses', 'label' => 'MAC addresses, for wireless clients', 'section' => 'what-each-device-reads', 'lesson' => 'wifi-essentials', 'cell' => [2, 1]],
                            ['id' => 'fw-at', 'label' => 'Layers 3 and 4, often up to 7', 'section' => 'what-each-device-reads', 'cell' => [3, 0]],
                            ['id' => 'fw-uses', 'label' => 'Rules on addresses, ports and state', 'section' => 'what-each-device-reads', 'cell' => [3, 1]],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'Network devices and media',
                    'minutes' => 15,
                    'summary' => 'What switches, routers, access points and firewalls each decide, and what travels over copper, fibre and radio.',
                    'objectives' => ['Tell switches, routers, access points and firewalls apart by what they read', 'Compare copper, fibre and Wi-Fi as media', 'Recognise the devices hidden inside a home router'],
                    'takeaways' => [
                        'A switch forwards frames by MAC address inside one network; a router forwards packets by IP address between networks.',
                        'An access point bridges wireless clients onto the wired network; a firewall enforces rules between zones of different trust.',
                        'Copper and fibre need physical access to tap; anyone within radio range can capture Wi-Fi frames, so encryption matters.',
                    ],
                    'resources' => [
                        ['Cloudflare: What is a router?', 'https://www.cloudflare.com/learning/network-layer/what-is-a-router/'],
                        ['Cloudflare: What is a LAN?', 'https://www.cloudflare.com/learning/network-layer/what-is-a-lan/'],
                    ],
                    'sections' => [
                        ['prose', 'Boxes on the path', <<<'MD'
Every computer, phone or server that sends and receives data is a **host**. It connects through a **network interface** (a NIC), wired or wireless, and every interface has its own hardware address.

Between hosts sit devices whose only job is to move traffic. Each one reads a different part of what passes through it, and that decides what it can do: a device that only reads hardware addresses cannot block a web site, and a device that reads IP addresses cannot tell which person is using a laptop.
MD],
                        ['comparison', 'What each device reads', [
                            'columns' => ['Switch', 'Router', 'Access point', 'Firewall'],
                            'rows' => [
                                ['label' => 'Works at', 'values' => ['Layer 2 (data link)', 'Layer 3 (network)', 'Layer 2, between radio and cable', 'Layers 3 and 4, often up to 7']],
                                ['label' => 'Decides using', 'values' => ['Destination MAC address and its table of which port leads where', 'Destination IP address and its routing table', 'MAC addresses, like a switch for wireless clients', 'Rules about addresses, ports, connection state and sometimes content']],
                                ['label' => 'Connects', 'values' => ['Devices inside one local network', 'Different networks, such as the office and the internet', 'Wireless devices to the wired network', 'Zones of different trust: inside, outside, servers, guests']],
                                ['label' => 'Security role', 'values' => ['Port security and VLANs keep devices apart', 'The natural place for access lists between networks', 'Encrypts the radio link with WPA2 or WPA3', 'Enforces what may cross between zones, and logs it']],
                            ],
                        ]],
                        ['comparison', 'Cable, fibre or radio', [
                            'columns' => ['Copper (twisted pair)', 'Fibre', 'Wi-Fi'],
                            'rows' => [
                                ['label' => 'Signal', 'values' => ['Electrical pulses on twisted pairs of wire', 'Pulses of light in a glass strand', 'Radio waves in the 2.4, 5 or 6 GHz bands']],
                                ['label' => 'Typical reach', 'values' => ['Up to 100 m per Ethernet run', 'Hundreds of metres to many kilometres', 'Tens of metres indoors, through walls']],
                                ['label' => 'Who can listen', 'values' => ['Someone with access to the cable or a switch port', 'Someone who physically taps the fibre, which is hard to do unnoticed', 'Anyone within range with a wireless card']],
                            ],
                        ]],
                        ['topology', 'A small office', [
                            'aspect' => '16:9',
                            'zones' => [
                                ['id' => 'office', 'label' => 'Office network', 'tone' => 'trusted', 'x' => 34, 'y' => 0, 'w' => 66, 'h' => 100],
                            ],
                            'devices' => [
                                ['id' => 'internet', 'label' => 'Internet', 'kind' => 'internet', 'x' => 2, 'y' => 46],
                                ['id' => 'fw', 'label' => 'Firewall', 'kind' => 'firewall', 'x' => 20, 'y' => 46, 'detail' => 'Sits between the internet and the office. Unsolicited connections from outside are refused; staff traffic out is allowed and logged.'],
                                ['id' => 'router', 'label' => 'Router', 'kind' => 'router', 'x' => 42, 'y' => 46, 'detail' => 'The office default gateway. It sends anything not addressed to the office network towards the internet.'],
                                ['id' => 'switch', 'label' => 'Switch', 'kind' => 'switch', 'x' => 64, 'y' => 46, 'detail' => 'Every wired device and the access point plug in here. It delivers each frame only to the port where the destination lives.'],
                                ['id' => 'pc', 'label' => 'Desktop', 'kind' => 'workstation', 'x' => 90, 'y' => 12, 'address' => '192.168.1.21'],
                                ['id' => 'srv', 'label' => 'File server', 'kind' => 'server', 'x' => 90, 'y' => 46, 'address' => '192.168.1.5'],
                                ['id' => 'ap', 'label' => 'Access point', 'kind' => 'access-point', 'x' => 72, 'y' => 84, 'detail' => 'Turns radio into Ethernet frames for the switch. Clients joining it land on the same office network as the desktop.'],
                                ['id' => 'laptop', 'label' => 'Laptop', 'kind' => 'laptop', 'x' => 92, 'y' => 84, 'address' => '192.168.1.40'],
                            ],
                            'links' => [
                                ['from' => 'internet', 'to' => 'fw'],
                                ['from' => 'fw', 'to' => 'router'],
                                ['from' => 'router', 'to' => 'switch'],
                                ['from' => 'switch', 'to' => 'pc'],
                                ['from' => 'switch', 'to' => 'srv'],
                                ['from' => 'switch', 'to' => 'ap'],
                                ['from' => 'ap', 'to' => 'laptop', 'style' => 'wireless'],
                            ],
                        ]],
                        ['callout', 'A home router is several devices in one box', 'The box from your internet provider is a router, a small switch, a Wi-Fi access point, a firewall that translates addresses (NAT), and a DHCP server. In an office these are usually separate devices, which is why the roles in this lesson are taught separately.', 'info'],
                        ['prose', 'Hubs, and why you rarely see them', <<<'MD'
A **hub** is a layer 1 device: it repeats every signal out of every port. Every device sees every frame, and only one can talk at a time. Switches replaced hubs because they send each frame only where it needs to go, which is faster and stops casual eavesdropping on the wire.
MD],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'You need to stop guest devices from reaching the office file server, which is on a different network. Which device is built to make that decision?',
                                    'options' => [
                                        ['text' => 'A firewall (or a router with access lists) between the two networks', 'correct' => true],
                                        ['text' => 'The switch the file server is plugged into', 'correct' => false],
                                        ['text' => 'The Wi-Fi access point', 'correct' => false],
                                    ],
                                    'explanation' => 'Traffic between networks passes a layer 3 device, and that is where rules about addresses and ports are enforced. A switch only moves frames within one network.',
                                ],
                                [
                                    'prompt' => 'Which medium can an attacker in the car park listen to without touching anything inside the building?',
                                    'options' => [
                                        ['text' => 'Wi-Fi', 'correct' => true],
                                        ['text' => 'Fibre', 'correct' => false],
                                        ['text' => 'Copper twisted pair', 'correct' => false],
                                    ],
                                    'explanation' => 'Radio leaves the building. Anyone in range can capture the frames, so Wi-Fi relies on encryption (WPA2 or WPA3) rather than on physical access.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'osi-model',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you reason about a stack of protocols one layer at a time?',
                        'shape' => 'layers',
                        'nodes' => [
                            ['id' => 'l7', 'label' => '7 Application', 'note' => 'Services programs use directly: HTTP, DNS, SSH.', 'section' => 'the-seven-layers', 'lesson' => 'application-protocols'],
                            ['id' => 'l6', 'label' => '6 Presentation', 'note' => 'Formats, encodes and encrypts data so both sides can read it.', 'section' => 'the-seven-layers'],
                            ['id' => 'l5', 'label' => '5 Session', 'note' => 'Opens, keeps and closes conversations between programs.', 'section' => 'the-seven-layers'],
                            ['id' => 'l4', 'label' => '4 Transport', 'note' => 'Process to process with ports, reliably (TCP) or not (UDP).', 'section' => 'the-seven-layers', 'lesson' => 'tcp-and-udp-in-depth'],
                            ['id' => 'l3', 'label' => '3 Network', 'note' => 'Addresses and routes packets between networks.', 'section' => 'the-seven-layers', 'lesson' => 'ip-addressing'],
                            ['id' => 'l2', 'label' => '2 Data link', 'note' => 'Moves frames between devices on one link.', 'section' => 'the-seven-layers', 'lesson' => 'ethernet-and-mac-addresses'],
                            ['id' => 'l1', 'label' => '1 Physical', 'note' => 'Bits as signals on copper, fibre or radio.', 'section' => 'the-seven-layers', 'lesson' => 'network-devices-and-media'],
                        ],
                        'edges' => [],
                    ],
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
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do the protocols the internet actually runs map to the seven-layer theory?',
                        'shape' => 'layers',
                        'nodes' => [
                            ['id' => 'app', 'label' => 'Application (OSI 5 to 7)', 'note' => 'Web pages, name lookups, remote shells, email.', 'section' => 'four-layers-instead-of-seven', 'lesson' => 'application-protocols'],
                            ['id' => 'transport', 'label' => 'Transport (OSI 4)', 'note' => 'Programs identified by ports; TCP adds connections and reliability.', 'section' => 'four-layers-instead-of-seven', 'lesson' => 'tcp-and-udp-in-depth'],
                            ['id' => 'internet', 'label' => 'Internet (OSI 3)', 'note' => 'IP addresses and routing, hop by hop.', 'section' => 'four-layers-instead-of-seven', 'lesson' => 'routing-basics'],
                            ['id' => 'link', 'label' => 'Link (OSI 1 and 2)', 'note' => 'Frames across one physical network.', 'section' => 'four-layers-instead-of-seven', 'lesson' => 'ethernet-and-mac-addresses'],
                        ],
                        'edges' => [],
                    ],
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
                [
                    'slug' => 'encapsulation-and-pdus',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How does each layer wrap the data handed down from the layer above?',
                        'shape' => 'stack',
                        'nodes' => [
                            ['id' => 'data', 'label' => 'Application data', 'note' => 'Pushed first: an HTTP request, a DNS question.', 'section' => 'what-each-header-carries'],
                            ['id' => 'segment', 'label' => '+ TCP header: a segment', 'note' => 'Ports, sequence and acknowledgement numbers.', 'section' => 'what-each-header-carries', 'lesson' => 'tcp-and-udp-in-depth'],
                            ['id' => 'packet', 'label' => '+ IP header: a packet', 'note' => 'Source and destination IP addresses, time to live.', 'section' => 'what-each-header-carries', 'lesson' => 'ip-addressing'],
                            ['id' => 'frame', 'label' => '+ Ethernet header: a frame', 'note' => 'Added last, removed first: the receiver unwraps from the top.', 'section' => 'unwrapping-at-the-server', 'lesson' => 'ethernet-and-mac-addresses'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'Encapsulation and PDUs',
                    'minutes' => 15,
                    'summary' => 'What each layer adds to your data, what the pieces are called, and why packet size limits matter.',
                    'objectives' => ['Name the protocol data unit at each layer', 'Describe what each header carries', 'Explain the MTU and what happens when a packet is too large'],
                    'takeaways' => [
                        'Each layer wraps the data from the layer above in its own header: data, segment, packet, frame.',
                        'On the way up, each layer reads and removes only its own header, then hands the rest upward.',
                        'Ethernet carries at most 1500 bytes per frame (its MTU); larger packets are fragmented or dropped.',
                    ],
                    'resources' => [
                        ['RFC 791: Internet Protocol', 'https://www.rfc-editor.org/rfc/rfc791'],
                        ['RFC 1191: Path MTU Discovery', 'https://www.rfc-editor.org/rfc/rfc1191'],
                    ],
                    'sections' => [
                        ['prose', 'Every layer adds its own header', <<<'MD'
The OSI lesson showed data being wrapped on the way down. Here we look at what each wrapper actually holds. The unit of data at each layer has a name, its **protocol data unit** (PDU), and you will meet these names in every tool and log:

- the application produces **data** (an HTTP request, a DNS query)
- the transport layer turns it into a **segment** (TCP) or **datagram** (UDP)
- the network layer turns that into a **packet**
- the link layer turns that into a **frame**, which becomes bits on the medium
MD],
                        ['comparison', 'What each header carries', [
                            'columns' => ['Unit', 'Header holds', 'Header size'],
                            'rows' => [
                                ['label' => 'Application', 'values' => ['Data', 'Whatever the protocol needs: an HTTP method and path, a DNS question', 'Varies']],
                                ['label' => 'Transport', 'values' => ['Segment (TCP) or datagram (UDP)', 'Source and destination ports; for TCP also sequence and acknowledgement numbers and flags', 'TCP 20 to 60 bytes, UDP 8 bytes']],
                                ['label' => 'Network', 'values' => ['Packet', 'Source and destination IP addresses, time to live, which transport protocol is inside', 'IPv4 20 to 60 bytes, IPv6 40 bytes']],
                                ['label' => 'Link', 'values' => ['Frame', 'Source and destination MAC addresses, the type of packet inside, and a checksum trailer', 'Ethernet 14 bytes plus a 4-byte trailer']],
                            ],
                        ]],
                        ['step-through', 'Unwrapping at the server', [
                            'steps' => [
                                ['title' => 'A frame arrives', 'text' => 'The network card checks the trailer\'s checksum and the destination MAC address. A corrupted frame, or one for another machine, is dropped here and nothing above ever sees it.', 'parts' => [['label' => 'Ethernet', 'tone' => 'attn'], ['label' => 'IP'], ['label' => 'TCP'], ['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Unit', 'value' => 'Frame'], ['label' => 'Read', 'value' => 'MAC addresses, EtherType']]],
                                ['title' => 'The link layer removes its header', 'text' => 'The type field said IPv4, so the packet inside goes to the IP layer. The Ethernet header is gone: MAC addresses never travel further than one link.', 'parts' => [['label' => 'IP', 'tone' => 'attn'], ['label' => 'TCP'], ['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Unit', 'value' => 'Packet'], ['label' => 'Read', 'value' => 'IP addresses, TTL, protocol']]],
                                ['title' => 'The network layer removes its header', 'text' => 'The destination IP is this server\'s, and the protocol field says TCP, so the segment goes up to TCP.', 'parts' => [['label' => 'TCP', 'tone' => 'attn'], ['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Unit', 'value' => 'Segment'], ['label' => 'Read', 'value' => 'Ports, sequence numbers, flags']]],
                                ['title' => 'The transport layer delivers to a program', 'text' => 'Destination port 80 belongs to the web server, so TCP puts the bytes, in order, into that program\'s socket.', 'parts' => [['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Unit', 'value' => 'Data'], ['label' => 'Read', 'value' => 'The HTTP request itself']]],
                            ],
                        ]],
                        ['prose', 'Size limits: the MTU', <<<'MD'
Every link has a **maximum transmission unit** (MTU): the largest packet one frame can carry. On Ethernet it is **1500 bytes**. Take away a 20-byte IPv4 header and a 20-byte TCP header and 1460 bytes of your data fit in one segment, which TCP announces as its maximum segment size.

A packet larger than the next link's MTU is either split into **fragments** by an IPv4 router or, if the sender set "don't fragment", dropped with an ICMP message telling the sender to send smaller ones. IPv6 routers never fragment: the sender must find the path's MTU itself. Tunnels and VPNs add their own headers, which is why a VPN link often has a smaller MTU, and why "large pages hang, small ones load" is a classic MTU fault.
MD],
                        ['code', 'See the MTU on Linux', <<<'SH'
ip link show dev eth0        # look for "mtu 1500"
tracepath 203.0.113.10       # reports the path MTU hop by hop
SH, 'bash'],
                        ['callout', 'Headers inside headers', 'A tunnel puts a whole packet inside another packet: VPNs, GRE, and DNS tunnelling used to smuggle data out. A firewall that reads only the outer header sees only the tunnel, which is why tunnels are either allowed on purpose or blocked.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A tool shows you a unit with source and destination ports, sequence numbers and the SYN flag. Which PDU are you looking at?',
                                    'options' => [
                                        ['text' => 'A TCP segment', 'correct' => true],
                                        ['text' => 'An Ethernet frame', 'correct' => false],
                                        ['text' => 'An IP packet', 'correct' => false],
                                    ],
                                    'explanation' => 'Ports, sequence numbers and flags live in the TCP header, so this is a segment. The packet carrying it adds IP addresses; the frame adds MAC addresses.',
                                ],
                                [
                                    'prompt' => 'Why do MAC addresses not survive across the internet, while IP addresses do?',
                                    'options' => [
                                        ['text' => 'Each link removes the frame and builds a new one for the next hop, while the packet inside travels end to end', 'correct' => true],
                                        ['text' => 'Routers encrypt MAC addresses', 'correct' => false],
                                        ['text' => 'MAC addresses are only used by Wi-Fi', 'correct' => false],
                                    ],
                                    'explanation' => 'A frame lives for one link. Every router unwraps it, decides on the next hop from the IP header, and wraps the same packet in a new frame with new MAC addresses.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
        [
            'title' => 'The local network',
            'lessons' => [
                [
                    'slug' => 'ethernet-and-mac-addresses',
                    'architecture' => ['version' => 1, 'problem' => 'How does a frame reach the right machine on a link it shares with others?', 'shape' => null, 'nodes' => [], 'edges' => []],
                    'title' => 'Ethernet frames and MAC addresses',
                    'minutes' => 15,
                    'summary' => 'How a frame finds the right machine on a shared link, and why a MAC address proves very little.',
                    'objectives' => ['Read the fields of an Ethernet frame', 'Tell unicast, multicast and broadcast frames apart', 'Explain why MAC addresses are a weak identity'],
                    'takeaways' => [
                        'A MAC address is 48 bits: the first 24 usually identify the manufacturer, the rest the interface.',
                        'The destination MAC decides who processes a frame; ff:ff:ff:ff:ff:ff reaches everyone on the network.',
                        'MAC addresses can be changed in software and are randomised by many phones, so they do not prove who someone is.',
                    ],
                    'resources' => [
                        ['RFC 7042: IANA considerations for IEEE 802 parameters', 'https://www.rfc-editor.org/rfc/rfc7042'],
                        ['IEEE registration authority: OUI lookup', 'https://regauth.standards.ieee.org/standards-ra-web/pub/view.html'],
                    ],
                    'sections' => [
                        ['prose', 'A frame on the wire', <<<'MD'
Ethernet (and Wi-Fi, which borrows its addressing) moves **frames** between interfaces on the same local network. A frame starts with three header fields: the **destination MAC address**, the **source MAC address**, and the **EtherType**, which says what is inside: `0x0800` for IPv4, `0x86DD` for IPv6, `0x0806` for ARP. Then comes the payload, up to 1500 bytes, and a 4-byte **frame check sequence**, a checksum. A frame whose checksum does not match is dropped silently: Ethernet never asks for a resend, that is left to TCP.
MD],
                        ['bitfield', 'Inside a MAC address', [
                            'rows' => [
                                ['label' => '00:00:5e:00:53:01', 'fields' => [
                                    ['label' => 'OUI (vendor)', 'bits' => '000000000000000001011110', 'tone' => 'accent'],
                                    ['label' => 'Interface', 'bits' => '000000000101001100000001', 'tone' => 'attn'],
                                ]],
                                ['label' => 'First octet, 0x00', 'fields' => [
                                    ['label' => 'Rest of the octet', 'bits' => '000000', 'tone' => 'muted'],
                                    ['label' => 'U/L 0: universal', 'bits' => '0', 'tone' => 'accent'],
                                    ['label' => 'I/G 0: individual', 'bits' => '0', 'tone' => 'attn'],
                                ]],
                            ],
                        ]],
                        ['prose', 'Two bits that matter', <<<'MD'
The example `00:00:5e:00:53:01` is from a block reserved for documentation. Its first 24 bits, the **OUI**, are registered to an organisation; the last 24 are chosen by it. Two bits of the first octet carry meaning: the lowest bit (**I/G**) set to 1 makes it a group (multicast) address, and the next one (**U/L**) set to 1 means the address was assigned locally rather than burned in by the manufacturer. Phones that randomise their MAC address per network set that bit.
MD],
                        ['comparison', 'Who processes a frame', [
                            'columns' => ['Unicast', 'Multicast', 'Broadcast'],
                            'rows' => [
                                ['label' => 'Destination MAC', 'values' => ['One interface, with the I/G bit 0', 'A group, with the I/G bit 1, such as 01:00:5e:… for IPv4 multicast', '`ff:ff:ff:ff:ff:ff`']],
                                ['label' => 'Who reads it', 'values' => ['Only the interface with that address', 'Interfaces that joined the group', 'Every device on the local network']],
                                ['label' => 'Example', 'values' => ['A laptop sending a web request to its gateway', 'IPv6 neighbour discovery, mDNS printer discovery', 'An ARP request, a DHCP discover']],
                            ],
                        ]],
                        ['code', 'Your interfaces and their MAC addresses', <<<'SH'
ip -br link                  # name, state and MAC address of every interface
ip link show dev eth0        # "link/ether" is the MAC address
SH, 'bash'],
                        ['callout', 'A MAC address is not an identity', 'Any interface\'s address can be changed with one command (`ip link set dev eth0 address …`), and phones use a different random address on each network. Allowing devices onto a network by MAC address alone stops accidents, not attackers. Use 802.1X authentication when the identity of a device matters.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A frame has the destination `ff:ff:ff:ff:ff:ff`. Which devices on the local network process it?',
                                    'options' => [
                                        ['text' => 'All of them', 'correct' => true],
                                        ['text' => 'Only the default gateway', 'correct' => false],
                                        ['text' => 'None: it is an invalid address', 'correct' => false],
                                    ],
                                    'explanation' => 'All ones is the broadcast address. Every device on the network reads the frame, which is how ARP and DHCP find something before they know its address.',
                                ],
                                [
                                    'prompt' => 'Your Wi-Fi only admits devices whose MAC address is on an allow list. Why is that a weak control?',
                                    'options' => [
                                        ['text' => 'An attacker can watch the air for an allowed address and copy it', 'correct' => true],
                                        ['text' => 'MAC addresses are encrypted on Wi-Fi', 'correct' => false],
                                        ['text' => 'Allow lists only work on wired networks', 'correct' => false],
                                    ],
                                    'explanation' => 'Source MAC addresses travel in clear in every frame, and an interface\'s address is set in software. Copying an allowed one takes seconds.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'switching-and-the-mac-table',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How can a switch send each frame only where it needs to go?',
                        'shape' => 'hub',
                        'nodes' => [
                            ['id' => 'table', 'label' => 'The MAC table', 'note' => 'Which port leads to which MAC address, learned from traffic.', 'section' => 'filling-the-mac-table'],
                            ['id' => 'learn', 'label' => 'Learn the source', 'note' => 'Every arriving frame records or refreshes its sender\'s port.', 'section' => 'what-a-switch-does-with-each-frame'],
                            ['id' => 'forward', 'label' => 'Forward', 'note' => 'A known destination goes out its one port.', 'section' => 'what-a-switch-does-with-each-frame'],
                            ['id' => 'flood', 'label' => 'Flood', 'note' => 'Every other port gets a copy until the destination answers.', 'section' => 'what-a-switch-does-with-each-frame'],
                            ['id' => 'filter', 'label' => 'Filter', 'note' => 'A destination on the port the frame came in on: drop it.', 'section' => 'what-a-switch-does-with-each-frame'],
                            ['id' => 'age', 'label' => 'Age out', 'note' => 'Entries expire, so moved devices are relearned.', 'section' => 'filling-the-mac-table'],
                            ['id' => 'attack', 'label' => 'MAC flooding', 'note' => 'A full table makes the switch flood everything, like a hub.', 'section' => 'mac-flooding'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'Switching and the MAC table',
                    'minutes' => 15,
                    'summary' => 'How a switch learns where every device is, so each frame goes only where it needs to.',
                    'objectives' => ['Explain how a switch learns, forwards, floods and filters', 'Follow a MAC table as it fills', 'Describe MAC flooding and how port security stops it'],
                    'takeaways' => [
                        'A switch learns from the source address of every frame which port leads to which device.',
                        'Known destinations get one port; unknown and broadcast destinations are flooded to every other port.',
                        'Filling the table with fake addresses makes some switches flood everything, so port security limits addresses per port.',
                    ],
                    'resources' => [
                        ['Cloudflare: What is a network switch?', 'https://www.cloudflare.com/learning/network-layer/what-is-a-network-switch/'],
                        ['bridge(8): Linux bridge management', 'https://man7.org/linux/man-pages/man8/bridge.8.html'],
                    ],
                    'sections' => [
                        ['prose', 'A switch learns as it goes', <<<'MD'
A new switch knows nothing about the devices plugged into it. It learns by reading the **source** MAC address of every frame that arrives and writing down the port it came in on. That table, the **MAC address table** (or CAM table), is all it needs: when a frame arrives for a destination it has seen before, it sends the frame out of that one port. Entries expire after a few minutes of silence (often 300 seconds), so a device that moves to another port is learned again.
MD],
                        ['flow', 'What a switch does with each frame', [
                            'direction' => 'down',
                            'nodes' => [
                                ['id' => 'arrive', 'label' => 'Frame arrives on a port', 'kind' => 'start'],
                                ['id' => 'learn', 'label' => 'Learn: source MAC is on this port', 'detail' => 'The switch records or refreshes the entry for the source address and resets its aging timer.'],
                                ['id' => 'known', 'label' => 'Destination in the table?', 'kind' => 'decision', 'detail' => 'Broadcast and most multicast destinations never are, so they always take the flooding branch.'],
                                ['id' => 'same', 'label' => 'Same port it came in on?', 'kind' => 'decision'],
                                ['id' => 'filter', 'label' => 'Filter: drop it', 'kind' => 'end', 'detail' => 'The destination is on the segment the frame came from, so sending it back would only duplicate it.'],
                                ['id' => 'forward', 'label' => 'Forward out that one port', 'kind' => 'end'],
                                ['id' => 'flood', 'label' => 'Flood out every other port', 'kind' => 'end', 'detail' => 'Everyone else on the network receives a copy. When the destination answers, the switch learns where it is.'],
                            ],
                            'edges' => [
                                ['from' => 'arrive', 'to' => 'learn'],
                                ['from' => 'learn', 'to' => 'known'],
                                ['from' => 'known', 'to' => 'same', 'label' => 'Yes'],
                                ['from' => 'known', 'to' => 'flood', 'label' => 'No'],
                                ['from' => 'same', 'to' => 'filter', 'label' => 'Yes'],
                                ['from' => 'same', 'to' => 'forward', 'label' => 'No'],
                            ],
                        ]],
                        ['step-through', 'Filling the MAC table', [
                            'steps' => [
                                ['title' => 'A sends to B', 'text' => 'The table is empty. The switch learns that A is on port 1, does not know B, and floods the frame to ports 2 and 3. C ignores it; B accepts it.', 'parts' => [['label' => 'from A, port 1', 'tone' => 'accent'], ['label' => 'to B: unknown', 'tone' => 'attn']], 'state' => [['label' => 'MAC table', 'value' => 'A → 1'], ['label' => 'Sent to', 'value' => 'ports 2 and 3']]],
                                ['title' => 'B replies to A', 'text' => 'The switch learns that B is on port 2. It already knows A, so the reply goes out of port 1 only.', 'parts' => [['label' => 'from B, port 2', 'tone' => 'accent'], ['label' => 'to A: port 1']], 'state' => [['label' => 'MAC table', 'value' => 'A → 1, B → 2'], ['label' => 'Sent to', 'value' => 'port 1']]],
                                ['title' => 'C broadcasts', 'text' => 'C sends an ARP request to everyone. The switch learns C, and floods the broadcast as it always does.', 'parts' => [['label' => 'from C, port 3', 'tone' => 'accent'], ['label' => 'to everyone', 'tone' => 'attn']], 'state' => [['label' => 'MAC table', 'value' => 'A → 1, B → 2, C → 3'], ['label' => 'Sent to', 'value' => 'ports 1 and 2']]],
                                ['title' => 'A sends to C', 'text' => 'Every device is known now. The frame goes straight to port 3, and B never sees it.', 'parts' => [['label' => 'from A, port 1', 'tone' => 'accent'], ['label' => 'to C: port 3']], 'state' => [['label' => 'MAC table', 'value' => 'A → 1, B → 2, C → 3'], ['label' => 'Sent to', 'value' => 'port 3']]],
                            ],
                        ]],
                        ['comparison', 'Hub or switch', [
                            'columns' => ['Hub', 'Switch'],
                            'rows' => [
                                ['label' => 'Sends a frame to', 'values' => ['Every port', 'The destination\'s port, once learned']],
                                ['label' => 'Who can capture traffic', 'values' => ['Every device on the hub', 'Only the two ends, unless a port is set to mirror traffic']],
                                ['label' => 'Talking at once', 'values' => ['One device at a time; others wait or collide', 'Every port in both directions at once (full duplex)']],
                            ],
                        ]],
                        ['code', 'A Linux bridge is a software switch', <<<'SH'
bridge fdb show br br0       # the forwarding database: MAC address and port
SH, 'bash'],
                        ['callout', 'MAC flooding', 'An attacker can send thousands of frames from made-up source addresses until the table is full. Some switches then flood every frame, so the attacker\'s port receives traffic meant for others. Port security limits how many addresses a port may learn and shuts the port, or drops the extras, when the limit is passed.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A switch receives a frame for a MAC address that is not in its table. What does it do?',
                                    'options' => [
                                        ['text' => 'Sends it out of every port except the one it came in on', 'correct' => true],
                                        ['text' => 'Drops it', 'correct' => false],
                                        ['text' => 'Sends it back to the sender with an error', 'correct' => false],
                                    ],
                                    'explanation' => 'Unknown destinations are flooded. When the destination answers, the switch learns its port from the reply\'s source address and stops flooding.',
                                ],
                                [
                                    'prompt' => 'Where does a switch learn which port leads to a device?',
                                    'options' => [
                                        ['text' => 'From the source MAC address of frames that device sends', 'correct' => true],
                                        ['text' => 'From the destination MAC address of frames sent to the device', 'correct' => false],
                                        ['text' => 'From a DHCP server', 'correct' => false],
                                    ],
                                    'explanation' => 'Only a frame\'s source address proves where a device is: it just arrived from that port.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'vlans',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How can one physical switch act as several separate networks?',
                        'shape' => 'matrix',
                        'rows' => ['Carries', 'Frames on the cable', 'Connects', 'Goes wrong when'],
                        'cols' => ['Access port', 'Trunk port'],
                        'nodes' => [
                            ['id' => 'a-carries', 'label' => 'One VLAN', 'section' => 'access-ports-and-trunk-ports', 'cell' => [0, 0]],
                            ['id' => 't-carries', 'label' => 'Many VLANs', 'section' => 'access-ports-and-trunk-ports', 'cell' => [0, 1]],
                            ['id' => 'a-frames', 'label' => 'Untagged: the host never knows', 'section' => 'access-ports-and-trunk-ports', 'cell' => [1, 0]],
                            ['id' => 't-frames', 'label' => '802.1Q tagged, except the native VLAN', 'section' => 'the-8021q-tag-for-vlan-20', 'cell' => [1, 1]],
                            ['id' => 'a-connects', 'label' => 'A host: laptop, printer, server', 'section' => 'access-ports-and-trunk-ports', 'cell' => [2, 0]],
                            ['id' => 't-connects', 'label' => 'A switch, a router or a VM host', 'note' => 'Traffic between VLANs still needs a router.', 'section' => 'access-ports-and-trunk-ports', 'lesson' => 'routing-basics', 'cell' => [2, 1]],
                            ['id' => 'a-wrong', 'label' => 'Wrong VLAN, wrong network', 'section' => 'access-ports-and-trunk-ports', 'cell' => [3, 0]],
                            ['id' => 't-wrong', 'label' => 'All VLANs allowed: hopping', 'section' => 'vlan-hopping', 'cell' => [3, 1]],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'VLANs',
                    'minutes' => 15,
                    'summary' => 'Splitting one physical switch into separate networks, and the mistakes that let traffic hop between them.',
                    'objectives' => ['Explain what a VLAN separates', 'Tell access ports and trunk ports apart', 'Describe VLAN hopping and how to prevent it'],
                    'takeaways' => [
                        'A VLAN is a separate broadcast domain on shared switches, numbered 1 to 4094.',
                        'Access ports carry one VLAN untagged; trunk ports carry many, each frame tagged with an 802.1Q tag.',
                        'Traffic between VLANs must pass a router or firewall, which is where the rules go.',
                    ],
                    'resources' => [
                        ['IEEE 802.1Q (overview)', 'https://en.wikipedia.org/wiki/IEEE_802.1Q'],
                    ],
                    'sections' => [
                        ['prose', 'One switch, several networks', <<<'MD'
Buying a separate switch for staff, servers and guests is expensive. A **virtual LAN** lets one switch behave like several: every port belongs to a VLAN, and frames never cross from one VLAN to another inside the switch. Each VLAN is its own **broadcast domain** and usually its own IP subnet. Hosts in different VLANs can only talk through a router, which is exactly where you want a decision to be made.
MD],
                        ['comparison', 'Access ports and trunk ports', [
                            'columns' => ['Access port', 'Trunk port'],
                            'rows' => [
                                ['label' => 'Carries', 'values' => ['One VLAN', 'Many VLANs']],
                                ['label' => 'Frames on the cable', 'values' => ['Untagged: the device never knows its VLAN', 'Tagged with an 802.1Q tag, except the native VLAN']],
                                ['label' => 'Connects', 'values' => ['A host: laptop, printer, server', 'Another switch, a router, a virtualisation host']],
                                ['label' => 'Goes wrong when', 'values' => ['The port is in the wrong VLAN and a device lands on the wrong network', 'All VLANs are allowed or the native VLAN is in use, which enables VLAN hopping']],
                            ],
                        ]],
                        ['bitfield', 'The 802.1Q tag for VLAN 20', [
                            'rows' => [
                                ['label' => '4-byte tag', 'fields' => [
                                    ['label' => 'TPID 0x8100', 'bits' => '1000000100000000', 'tone' => 'muted'],
                                    ['label' => 'Priority', 'bits' => '000', 'tone' => 'plain'],
                                    ['label' => 'DEI', 'bits' => '0', 'tone' => 'plain'],
                                    ['label' => 'VLAN ID 20', 'bits' => '000000010100', 'tone' => 'accent'],
                                ]],
                            ],
                        ]],
                        ['topology', 'Two VLANs on one switch', [
                            'aspect' => '16:9',
                            'zones' => [
                                ['id' => 'v10', 'label' => 'VLAN 10 staff 10.0.10.0/24', 'tone' => 'trusted', 'x' => 62, 'y' => 0, 'w' => 38, 'h' => 44],
                                ['id' => 'v20', 'label' => 'VLAN 20 servers 10.0.20.0/24', 'tone' => 'neutral', 'x' => 62, 'y' => 56, 'w' => 38, 'h' => 44],
                            ],
                            'devices' => [
                                ['id' => 'router', 'label' => 'Router / firewall', 'kind' => 'firewall', 'x' => 6, 'y' => 48, 'detail' => 'One trunk carries both VLANs to it ("router on a stick"). It is the gateway of each subnet and applies the rules between them.'],
                                ['id' => 'switch', 'label' => 'Switch', 'kind' => 'switch', 'x' => 36, 'y' => 48, 'detail' => 'The link to the router is a trunk; every other port is an access port in one VLAN.'],
                                ['id' => 'laptop', 'label' => 'Staff laptop', 'kind' => 'laptop', 'x' => 84, 'y' => 22, 'address' => '10.0.10.21'],
                                ['id' => 'server', 'label' => 'App server', 'kind' => 'server', 'x' => 84, 'y' => 78, 'address' => '10.0.20.5'],
                            ],
                            'links' => [
                                ['from' => 'router', 'to' => 'switch', 'label' => 'trunk: 10, 20'],
                                ['from' => 'switch', 'to' => 'laptop', 'label' => 'access 10'],
                                ['from' => 'switch', 'to' => 'server', 'label' => 'access 20'],
                            ],
                        ]],
                        ['code', 'A VLAN interface on Linux', <<<'SH'
sudo ip link add link eth0 name eth0.20 type vlan id 20
sudo ip addr add 10.0.20.10/24 dev eth0.20
sudo ip link set dev eth0.20 up
SH, 'bash'],
                        ['callout', 'VLAN hopping', 'Two classic tricks get frames into a VLAN an attacker is not in. Switch spoofing: the attacker\'s port negotiates itself into a trunk. Double tagging: a frame with two tags loses the outer one on a trunk whose native VLAN matches it. Defences: turn off automatic trunk negotiation, make user ports access ports, allow only the VLANs a trunk needs, and give trunks an unused native VLAN.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A staff laptop in VLAN 10 needs to reach a server in VLAN 20 on the same switch. What has to happen?',
                                    'options' => [
                                        ['text' => 'The traffic goes through a router or firewall, which routes between the two subnets', 'correct' => true],
                                        ['text' => 'Nothing: they share a switch, so they can talk directly', 'correct' => false],
                                        ['text' => 'The laptop must be plugged into a trunk port', 'correct' => false],
                                    ],
                                    'explanation' => 'A switch never moves frames between VLANs. Inter-VLAN traffic is routed, which makes the router the place to allow or deny it.',
                                ],
                                [
                                    'prompt' => 'Which port types should face ordinary user devices?',
                                    'options' => [
                                        ['text' => 'Access ports in a single VLAN', 'correct' => true],
                                        ['text' => 'Trunks that allow every VLAN', 'correct' => false],
                                        ['text' => 'Trunks whose native VLAN is VLAN 1', 'correct' => false],
                                    ],
                                    'explanation' => 'A device on an access port never sees a tag and cannot choose its VLAN. Trunks towards users invite VLAN hopping.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Addressing and routing',
            'lessons' => [
                [
                    'slug' => 'ip-addressing',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you give every machine an address that routers can reach?',
                        'shape' => 'matrix',
                        'rows' => ['Range', 'Seen in a log'],
                        'cols' => ['Private', 'Loopback', 'Link-local'],
                        'nodes' => [
                            ['id' => 'priv-range', 'label' => '10/8, 172.16/12, 192.168/16', 'note' => 'Never routed on the internet; translated at the edge.', 'section' => 'ranges-worth-recognising', 'lesson' => 'nat-and-private-addresses', 'cell' => [0, 0]],
                            ['id' => 'lo-range', 'label' => '127.0.0.0/8', 'section' => 'ranges-worth-recognising', 'cell' => [0, 1]],
                            ['id' => 'll-range', 'label' => '169.254.0.0/16', 'section' => 'ranges-worth-recognising', 'cell' => [0, 2]],
                            ['id' => 'priv-log', 'label' => 'Inside the network, or a VPN', 'section' => 'reading-logs', 'cell' => [1, 0]],
                            ['id' => 'lo-log', 'label' => 'A program on the same machine', 'section' => 'reading-logs', 'cell' => [1, 1]],
                            ['id' => 'll-log', 'label' => 'No DHCP server answered', 'note' => 'Check DHCP before anything else.', 'section' => 'reading-logs', 'lesson' => 'dns-and-dhcp', 'cell' => [1, 2]],
                        ],
                        'edges' => [],
                    ],
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
                    'slug' => 'arp',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How does a machine find the MAC address behind an IP address on its own network?',
                        'shape' => 'cycle',
                        'nodes' => [
                            ['id' => 'ask', 'label' => 'Broadcast: who has 192.168.1.1?', 'note' => 'Every machine on the link receives the request.', 'section' => 'who-has-19216811'],
                            ['id' => 'reply', 'label' => 'The owner replies, unicast', 'note' => 'ARP believes any reply, which is what spoofing abuses.', 'section' => 'who-has-19216811'],
                            ['id' => 'cache', 'label' => 'Store it in the neighbour table', 'note' => '`ip neigh` shows the entries and their states.', 'section' => 'the-neighbour-table-on-linux'],
                            ['id' => 'use', 'label' => 'Send frames to that MAC', 'section' => 'a-cycle-not-a-one-off'],
                            ['id' => 'stale', 'label' => 'The entry goes stale: ask again', 'note' => 'Entries expire, so a changed address is relearned.', 'section' => 'a-cycle-not-a-one-off'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'ARP: from IP address to MAC address',
                    'minutes' => 12,
                    'summary' => 'How a machine finds the hardware address behind an IP address on its own network, and how attackers lie about it.',
                    'objectives' => ['Explain why IP needs ARP on a local network', 'Follow an ARP request and reply', 'Describe ARP spoofing and its defences'],
                    'takeaways' => [
                        'To send a packet on the local network, a host needs the next hop\'s MAC address; ARP asks for it by broadcast.',
                        'For destinations outside the subnet, the host ARPs for its default gateway, never for the remote host.',
                        'ARP has no authentication, so a forged reply can put an attacker between two machines.',
                    ],
                    'resources' => [
                        ['RFC 826: An Ethernet Address Resolution Protocol', 'https://www.rfc-editor.org/rfc/rfc826'],
                        ['ip-neighbour(8)', 'https://man7.org/linux/man-pages/man8/ip-neighbour.8.html'],
                    ],
                    'sections' => [
                        ['prose', 'Two addresses for one trip', <<<'MD'
An IP packet names its final destination, but a frame can only reach a device on the same link, by MAC address. So before a host can send a packet it needs the MAC address of the **next hop**:

- if the destination is in its own subnet, the next hop is the destination itself
- otherwise the next hop is the **default gateway**, and the host asks for the gateway's MAC address, never the remote server's

The **Address Resolution Protocol** (ARP) answers that question for IPv4. The answers are kept in a cache, the neighbour table, so the question is not asked for every packet.
MD],
                        ['sequence', 'Who has 192.168.1.1?', [
                            'actors' => [
                                ['id' => 'laptop', 'name' => 'Laptop .20'],
                                ['id' => 'others', 'name' => 'Other hosts'],
                                ['id' => 'gateway', 'name' => 'Gateway .1'],
                            ],
                            'messages' => [
                                ['from' => 'laptop', 'to' => 'others', 'label' => 'ARP request', 'detail' => 'to ff:ff:ff:ff:ff:ff', 'explanation' => 'The laptop wants to reach the internet, so it needs its gateway\'s MAC address. It broadcasts: who has 192.168.1.1? Tell 192.168.1.20. Every device on the network receives the question.', 'states' => ['laptop' => 'waiting']],
                                ['from' => 'gateway', 'to' => 'laptop', 'label' => 'ARP reply', 'detail' => '.1 is at 00:00:5e:00:53:01', 'explanation' => 'Only the device that owns 192.168.1.1 answers, directly to the laptop. The other hosts ignore the question, though many quietly note the laptop\'s own address from it.', 'states' => ['laptop' => 'resolved']],
                                ['from' => 'laptop', 'to' => 'gateway', 'label' => 'IP packet', 'detail' => 'frame to 00:00:5e:00:53:01', 'explanation' => 'The laptop caches the answer and wraps its packet, still addressed to the remote server\'s IP, in a frame addressed to the gateway\'s MAC.', 'states' => ['laptop' => 'cached']],
                            ],
                        ]],
                        ['prose', 'A cycle, not a one-off', <<<'MD'
Cache entries do not last. On Linux an entry is **REACHABLE** while traffic confirms it, turns **STALE** after about half a minute without confirmation, and is checked again (DELAY, then PROBE) the next time it is used. If no answer comes, it becomes **FAILED** and the next packet starts a fresh request. That loop is what keeps the table right when a device is replaced or changes address.
MD],
                        ['code', 'The neighbour table on Linux', <<<'SH'
ip neigh show                       # IP, interface, MAC and state of each entry
ip neigh show dev eth0              # only one interface
sudo ip neigh flush dev eth0        # forget them all; they are relearned on use
SH, 'bash'],
                        ['callout', 'ARP spoofing', 'ARP believes any reply, even one nobody asked for. An attacker on the same network can keep telling the victim "the gateway is at my MAC address" and telling the gateway the same about the victim, then sit in the middle of their traffic. Defences: switches that check ARP against DHCP records (Dynamic ARP Inspection with DHCP snooping), static entries for a few critical hosts, and encryption such as TLS, so a man in the middle sees only ciphertext.', 'warning'],
                        ['prose', 'IPv6 does it differently', <<<'MD'
IPv6 has no ARP. **Neighbor Discovery** does the same job with ICMPv6 messages: a neighbor solicitation sent to a multicast address (the target's solicited-node address) instead of a broadcast, answered by a unicast neighbor advertisement. The idea and the weakness are the same: forged advertisements can redirect traffic, and the defence is the same kind of switch inspection.
MD],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'Your laptop, 192.168.1.20/24, sends a packet to 203.0.113.10. Whose MAC address does it ask ARP for?',
                                    'options' => [
                                        ['text' => 'Its default gateway\'s', 'correct' => true],
                                        ['text' => '203.0.113.10\'s', 'correct' => false],
                                        ['text' => 'The DNS server\'s', 'correct' => false],
                                    ],
                                    'explanation' => '203.0.113.10 is outside 192.168.1.0/24, so the next hop is the gateway. The laptop only ever needs MAC addresses of devices on its own link.',
                                ],
                                [
                                    'prompt' => 'Why does ARP spoofing work at all?',
                                    'options' => [
                                        ['text' => 'Hosts accept ARP replies without any proof of who sent them', 'correct' => true],
                                        ['text' => 'ARP replies are encrypted with a weak key', 'correct' => false],
                                        ['text' => 'Switches forward ARP to the internet', 'correct' => false],
                                    ],
                                    'explanation' => 'ARP was designed for a trusted local network. Nothing in a reply is authenticated, so a host updates its cache from whatever it hears.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'subnetting',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you split one address block into networks of the right size?',
                        'shape' => 'tree',
                        'nodes' => [
                            ['id' => 'root', 'label' => '192.168.1.0/24', 'note' => 'One block of 256 addresses.', 'section' => 'network-part-and-host-part'],
                            ['id' => 'a', 'label' => '192.168.1.0/25', 'section' => 'a-26-bit-by-bit', 'parent' => 'root'],
                            ['id' => 'b', 'label' => '192.168.1.128/25', 'section' => 'a-26-bit-by-bit', 'parent' => 'root'],
                            ['id' => 'a1', 'label' => '192.168.1.0/26', 'section' => 'working-out-a-26', 'parent' => 'a'],
                            ['id' => 'a2', 'label' => '192.168.1.64/26', 'note' => 'Holds 192.168.1.70; broadcast .127, 62 usable hosts.', 'section' => 'working-out-a-26', 'parent' => 'a'],
                            ['id' => 'b1', 'label' => '192.168.1.128/26', 'section' => 'working-out-a-26', 'parent' => 'b'],
                            ['id' => 'b2', 'label' => '192.168.1.192/26', 'note' => 'Each subnet becomes a route and a place to filter.', 'section' => 'segmentation-is-a-security-control', 'lesson' => 'routing-basics', 'parent' => 'b'],
                        ],
                        'edges' => [],
                    ],
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
                    'slug' => 'ipv6-essentials',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you address a network far too large for IPv4?',
                        'shape' => 'matrix',
                        'rows' => ['Range', 'Reachable from'],
                        'cols' => ['Global unicast', 'Link-local', 'Unique local'],
                        'nodes' => [
                            ['id' => 'g-range', 'label' => '2000::/3', 'section' => 'addresses-to-recognise', 'cell' => [0, 0]],
                            ['id' => 'll-range', 'label' => 'fe80::/10', 'note' => 'Neighbour discovery uses these in place of ARP.', 'section' => 'addresses-to-recognise', 'lesson' => 'arp', 'cell' => [0, 1]],
                            ['id' => 'ul-range', 'label' => 'fc00::/7, in practice fd00::/8', 'section' => 'addresses-to-recognise', 'cell' => [0, 2]],
                            ['id' => 'g-reach', 'label' => 'Anywhere, unless filtered', 'section' => 'addresses-to-recognise', 'cell' => [1, 0]],
                            ['id' => 'll-reach', 'label' => 'Only the same link', 'section' => 'addresses-to-recognise', 'cell' => [1, 1]],
                            ['id' => 'ul-reach', 'label' => 'Inside the organisation', 'section' => 'addresses-to-recognise', 'cell' => [1, 2]],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'IPv6 essentials',
                    'minutes' => 18,
                    'summary' => 'Reading and shortening IPv6 addresses, the address types you will meet, and what changes from IPv4.',
                    'objectives' => ['Write an IPv6 address in its shortest correct form', 'Recognise global, link-local and unique local addresses', 'Compare IPv6 with IPv4 where it matters for security'],
                    'takeaways' => [
                        'An IPv6 address is 128 bits: eight groups of four hexadecimal digits, with leading zeros and one run of zero groups dropped.',
                        'Every interface has a link-local fe80:: address; global addresses usually start with 2 or 3.',
                        'Hosts enable IPv6 by default, so firewall rules and monitoring must cover it as well as IPv4.',
                    ],
                    'resources' => [
                        ['RFC 8200: Internet Protocol, Version 6', 'https://www.rfc-editor.org/rfc/rfc8200'],
                        ['RFC 5952: A recommendation for IPv6 address text representation', 'https://www.rfc-editor.org/rfc/rfc5952'],
                    ],
                    'sections' => [
                        ['prose', 'Why IPv6 exists', <<<'MD'
IPv4's 32 bits give about 4.3 billion addresses. The global pool ran out in 2011, and the internet kept going on **NAT**, which hides many private addresses behind one public one. IPv6 fixes the shortage instead: 128 bits, enough to give every device its own globally routable address. Most networks now run both side by side.
MD],
                        ['steps', 'Shortening an address', [
                            'Start from the full form: eight groups of four hex digits, such as `2001:0db8:0000:0000:0000:0000:0000:0001`.',
                            'Drop leading zeros in every group: `2001:db8:0:0:0:0:0:1`.',
                            'Replace one run of consecutive zero groups with `::`: `2001:db8::1`. Use `::` only once, or the address becomes ambiguous.',
                            'Write letters in lower case. `2001:db8::/32` is reserved for documentation, like 203.0.113.0/24 in IPv4.',
                        ]],
                        ['bitfield', '2001:db8:0:1::1 split in half', [
                            'rows' => [
                                ['label' => 'Network prefix (/64)', 'fields' => [
                                    ['label' => '2001', 'bits' => '0010000000000001', 'tone' => 'accent'],
                                    ['label' => '0db8', 'bits' => '0000110110111000', 'tone' => 'accent'],
                                    ['label' => '0000', 'bits' => '0000000000000000', 'tone' => 'accent'],
                                    ['label' => '0001', 'bits' => '0000000000000001', 'tone' => 'accent'],
                                ]],
                                ['label' => 'Interface ID', 'fields' => [
                                    ['label' => '0000', 'bits' => '0000000000000000', 'tone' => 'attn'],
                                    ['label' => '0000', 'bits' => '0000000000000000', 'tone' => 'attn'],
                                    ['label' => '0000', 'bits' => '0000000000000000', 'tone' => 'attn'],
                                    ['label' => '0001', 'bits' => '0000000000000001', 'tone' => 'attn'],
                                ]],
                            ],
                        ]],
                        ['comparison', 'IPv4 and IPv6', [
                            'columns' => ['IPv4', 'IPv6'],
                            'rows' => [
                                ['label' => 'Address size', 'values' => ['32 bits, about 4.3 billion addresses', '128 bits']],
                                ['label' => 'Written as', 'values' => ['Dotted decimal: `192.0.2.10`', 'Hex groups with `::`: `2001:db8::10`']],
                                ['label' => 'Typical subnet', 'values' => ['Any size; /24 is common', '/64 for almost every network']],
                                ['label' => 'Finding a neighbour\'s MAC', 'values' => ['ARP, by broadcast', 'Neighbor Discovery, over ICMPv6 multicast']],
                                ['label' => 'Getting an address', 'values' => ['DHCP or set by hand', 'SLAAC from router advertisements, DHCPv6, or by hand']],
                                ['label' => 'Behind one public address', 'values' => ['Usually, through NAT', 'Rarely: hosts normally have global addresses, so a firewall does the protecting']],
                                ['label' => 'Header', 'values' => ['20 to 60 bytes, with a checksum; routers may fragment', 'Fixed 40 bytes, no checksum; routers never fragment']],
                            ],
                        ]],
                        ['comparison', 'Addresses to recognise', [
                            'columns' => ['Global unicast', 'Link-local', 'Unique local'],
                            'rows' => [
                                ['label' => 'Range', 'values' => ['`2000::/3` (starts with 2 or 3)', '`fe80::/10`', '`fc00::/7`, in practice `fd00::/8`']],
                                ['label' => 'Reachable from', 'values' => ['Anywhere, unless a firewall says otherwise', 'Only the same link; never routed', 'Inside the organisation; not routed on the internet']],
                                ['label' => 'Seen in a log', 'values' => ['A real internet address, like a public IPv4 one', 'Neighbour discovery or a misconfigured service', 'An internal host, like a private IPv4 address']],
                            ],
                        ]],
                        ['code', 'IPv6 on your machine', <<<'SH'
ip -6 addr show              # every interface has an fe80:: link-local address
ip -6 route                  # IPv6 routes, including the default
ping -6 -c 2 ::1             # loopback, like 127.0.0.1
SH, 'bash'],
                        ['callout', 'The forgotten protocol', 'IPv6 is on by default in every current operating system. A firewall policy written only for IPv4 can leave IPv6 wide open, and a rogue device sending router advertisements can make itself the gateway for every IPv6 host on a network. Write rules for both protocols, and filter router advertisements on user ports.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'What is the shortest correct form of `2001:0db8:0000:0000:0000:0000:0000:0010`?',
                                    'options' => [
                                        ['text' => '`2001:db8::10`', 'correct' => true],
                                        ['text' => '`2001:db8::1`', 'correct' => false],
                                        ['text' => '`2001:db8:::10`', 'correct' => false],
                                    ],
                                    'explanation' => 'Leading zeros go (`0db8` becomes `db8`, `0010` becomes `10`), and the run of zero groups becomes one `::`. The last group is 10 hex, not 1.',
                                ],
                                [
                                    'prompt' => 'A log shows a connection from `fe80::a1b2:c3ff:fed4:e5f6`. Where did it come from?',
                                    'options' => [
                                        ['text' => 'A device on the same local link', 'correct' => true],
                                        ['text' => 'Somewhere on the internet', 'correct' => false],
                                        ['text' => 'Another office over the company VPN', 'correct' => false],
                                    ],
                                    'explanation' => '`fe80::/10` addresses are link-local. Routers never forward them, so the sender was on the same network segment.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'routing-basics',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How does a packet cross networks that nobody controls end to end?',
                        'shape' => 'mesh',
                        'nodes' => [
                            ['id' => 'office', 'label' => 'Office', 'note' => 'The office network, 10.1.0.0/16.', 'section' => 'many-routers-many-paths', 'lesson' => 'nat-and-private-addresses', 'x' => 2, 'y' => 46],
                            ['id' => 'r1', 'label' => 'R1, the gateway', 'note' => 'Looks up each destination; the longest matching prefix wins.', 'section' => 'your-routing-table', 'x' => 24, 'y' => 46],
                            ['id' => 'r2', 'label' => 'R2', 'section' => 'many-routers-many-paths', 'x' => 48, 'y' => 12],
                            ['id' => 'r3', 'label' => 'R3', 'section' => 'many-routers-many-paths', 'x' => 48, 'y' => 82],
                            ['id' => 'r4', 'label' => 'R4', 'note' => 'Reachable two ways; the better metric wins, the other is a fallback.', 'section' => 'static-and-dynamic-routing', 'x' => 74, 'y' => 46],
                            ['id' => 'servers', 'label' => 'Servers', 'note' => 'The server network, 203.0.113.0/24.', 'section' => 'many-routers-many-paths', 'x' => 98, 'y' => 46],
                        ],
                        'edges' => [
                            ['from' => 'office', 'to' => 'r1'],
                            ['from' => 'r1', 'to' => 'r2'],
                            ['from' => 'r1', 'to' => 'r3'],
                            ['from' => 'r2', 'to' => 'r4'],
                            ['from' => 'r3', 'to' => 'r4'],
                            ['from' => 'r2', 'to' => 'r3'],
                            ['from' => 'r4', 'to' => 'servers'],
                        ],
                    ],
                    'title' => 'Routing basics',
                    'minutes' => 20,
                    'summary' => 'How routers choose the next hop for every packet, and how routes are learned or forged.',
                    'objectives' => ['Read a routing table and find the route a packet will use', 'Explain longest prefix match and the default route', 'Compare static and dynamic routing and their risks'],
                    'takeaways' => [
                        'Each router only decides the next hop; the packet\'s TTL drops by one at every router.',
                        'The most specific matching route wins; 0.0.0.0/0, the default route, matches anything left.',
                        'Dynamic protocols adapt to failures, but a false announcement can redirect traffic across the internet.',
                    ],
                    'resources' => [
                        ['Cloudflare: What is routing?', 'https://www.cloudflare.com/learning/network-layer/what-is-routing/'],
                        ['Cloudflare: What is BGP?', 'https://www.cloudflare.com/learning/security/glossary/what-is-bgp/'],
                    ],
                    'sections' => [
                        ['prose', 'Hop by hop', <<<'MD'
No router knows the whole path to a destination. Each one looks at the packet's destination IP address, picks the **next hop** from its **routing table**, and forgets about it. The next router does the same. Every router also lowers the packet's **time to live** (TTL) by one and drops the packet when it reaches zero, so a routing loop cannot keep a packet circling forever.

Your own computer routes too. Its table usually has two entries: its local subnet, reachable directly, and a **default route** that sends everything else to the gateway.
MD],
                        ['code', 'Your routing table', <<<'SH'
$ ip route
default via 192.168.1.1 dev wlan0 proto dhcp metric 600
192.168.1.0/24 dev wlan0 proto kernel scope link src 192.168.1.20 metric 600

$ ip route get 203.0.113.10       # which route would this destination use?
203.0.113.10 via 192.168.1.1 dev wlan0 src 192.168.1.20 uid 1000
SH, 'bash'],
                        ['steps', 'Longest prefix match', [
                            'Take the packet\'s destination address, for example 10.1.2.3.',
                            'Find every route whose prefix contains it: here `10.0.0.0/8`, `10.1.0.0/16` and the default `0.0.0.0/0`.',
                            'Pick the most specific one, the longest prefix: `10.1.0.0/16`.',
                            'If two routes have the same prefix, the one with the better preference (administrative distance, then metric) wins.',
                            'If nothing matches and there is no default route, the packet is dropped and the sender gets an ICMP "network unreachable".',
                        ]],
                        ['topology', 'Many routers, many paths', [
                            'aspect' => '16:9',
                            'devices' => [
                                ['id' => 'a', 'label' => 'Office network', 'kind' => 'workstation', 'x' => 2, 'y' => 46, 'address' => '10.1.0.0/16'],
                                ['id' => 'r1', 'label' => 'R1', 'kind' => 'router', 'x' => 22, 'y' => 46, 'detail' => 'The office gateway. Its default route points to both providers; if one link fails, a dynamic protocol switches traffic to the other.'],
                                ['id' => 'r2', 'label' => 'R2', 'kind' => 'router', 'x' => 46, 'y' => 14],
                                ['id' => 'r3', 'label' => 'R3', 'kind' => 'router', 'x' => 46, 'y' => 80],
                                ['id' => 'r4', 'label' => 'R4', 'kind' => 'router', 'x' => 72, 'y' => 46, 'detail' => 'Reachable two ways. Routers choose the path with the best metric and keep the other as a fallback.'],
                                ['id' => 'b', 'label' => 'Server network', 'kind' => 'server', 'x' => 94, 'y' => 46, 'address' => '203.0.113.0/24'],
                            ],
                            'links' => [
                                ['from' => 'a', 'to' => 'r1'],
                                ['from' => 'r1', 'to' => 'r2'],
                                ['from' => 'r1', 'to' => 'r3'],
                                ['from' => 'r2', 'to' => 'r4'],
                                ['from' => 'r3', 'to' => 'r4'],
                                ['from' => 'r2', 'to' => 'r3'],
                                ['from' => 'r4', 'to' => 'b'],
                            ],
                        ]],
                        ['comparison', 'Static and dynamic routing', [
                            'columns' => ['Static', 'Dynamic'],
                            'rows' => [
                                ['label' => 'Who writes the routes', 'values' => ['An administrator, by hand or configuration', 'Routers, by exchanging what they can reach']],
                                ['label' => 'When a link fails', 'values' => ['Nothing changes until someone edits it', 'Routers recalculate within seconds']],
                                ['label' => 'Typical use', 'values' => ['Small networks, default routes, fixed links', 'OSPF inside an organisation; BGP between internet providers']],
                                ['label' => 'Example', 'values' => ['`ip route add 10.2.0.0/16 via 10.1.0.254`', 'An OSPF neighbour announcing 10.2.0.0/16']],
                                ['label' => 'Risk', 'values' => ['Stale routes that nobody remembers', 'False announcements that pull traffic to the wrong place']],
                            ],
                        ]],
                        ['callout', 'Routes can lie', 'BGP trusts what neighbouring networks announce. In 2008 a provider announced a more specific route for part of YouTube\'s address space, and because longest prefix wins, much of the internet sent YouTube\'s traffic there for about two hours. Route origin validation (RPKI) lets networks reject announcements from the wrong owner.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A router has routes to `10.0.0.0/8` via A, `10.1.0.0/16` via B and `0.0.0.0/0` via C. Where does it send a packet for `10.1.2.3`?',
                                    'options' => [
                                        ['text' => 'B', 'correct' => true],
                                        ['text' => 'A', 'correct' => false],
                                        ['text' => 'C', 'correct' => false],
                                    ],
                                    'explanation' => 'All three match, but `/16` is the longest prefix, so the route via B wins.',
                                ],
                                [
                                    'prompt' => 'What stops a packet from looping forever between two misconfigured routers?',
                                    'options' => [
                                        ['text' => 'Its TTL is lowered at every hop and the packet is dropped at zero', 'correct' => true],
                                        ['text' => 'TCP notices the loop and resends the packet another way', 'correct' => false],
                                        ['text' => 'Switches remove looping packets', 'correct' => false],
                                    ],
                                    'explanation' => 'Time to live is a hop counter. When it reaches zero the router discards the packet and sends an ICMP "time exceeded" back, which is also how traceroute works.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'nat-and-private-addresses',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do many private machines share one public address?',
                        'shape' => 'pipeline',
                        'nodes' => [
                            ['id' => 'laptop', 'label' => 'Laptop', 'note' => 'Sends from 192.168.1.20, port 51000.', 'section' => 'many-devices-one-address'],
                            ['id' => 'rewrite', 'label' => 'Source rewritten', 'note' => 'The private address and port become the public address and a free port.', 'section' => 'a-request-leaving-through-nat'],
                            ['id' => 'track', 'label' => 'Mapping remembered', 'note' => 'Unsolicited inbound traffic finds no entry, but that is not a firewall rule.', 'section' => 'source-nat-on-a-linux-router-nftables'],
                            ['id' => 'server', 'label' => 'Server replies', 'note' => 'The reply goes to the public address and port.', 'section' => 'a-request-leaving-through-nat'],
                            ['id' => 'back', 'label' => 'Translated back', 'note' => 'The router looks up the mapping and delivers the reply to the laptop.', 'section' => 'a-request-leaving-through-nat'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'NAT and private addresses',
                    'minutes' => 15,
                    'summary' => 'How many private devices share one public address, and why NAT is not a firewall.',
                    'objectives' => ['Follow a connection through source NAT', 'Tell source NAT and port forwarding apart', 'Explain what NAT does and does not protect'],
                    'takeaways' => [
                        'Source NAT rewrites outgoing private addresses and ports to the router\'s public address and remembers the mapping.',
                        'Destination NAT (port forwarding) sends inbound connections on a public port to an inside host.',
                        'NAT blocks unsolicited inbound traffic only as a side effect; real protection comes from firewall rules.',
                    ],
                    'resources' => [
                        ['RFC 1918: Address Allocation for Private Internets', 'https://www.rfc-editor.org/rfc/rfc1918'],
                        ['RFC 3022: Traditional IP Network Address Translator', 'https://www.rfc-editor.org/rfc/rfc3022'],
                    ],
                    'sections' => [
                        ['prose', 'Many devices, one address', <<<'MD'
Private ranges such as `192.168.0.0/16` are never routed on the internet, yet a laptop with `192.168.1.20` can load any web page. The router at the edge performs **network address translation**: it rewrites the source of every outgoing packet to its own public address, picks a port, and records the pairing in a translation table. Replies come back to that public address and port, and the router uses the table to send them to the right inside host.
MD],
                        ['sequence', 'A request leaving through NAT', [
                            'actors' => [
                                ['id' => 'laptop', 'name' => 'Laptop'],
                                ['id' => 'router', 'name' => 'NAT router'],
                                ['id' => 'server', 'name' => 'Web server'],
                            ],
                            'messages' => [
                                ['from' => 'laptop', 'to' => 'router', 'label' => 'SYN', 'detail' => '192.168.1.20:51000 → 203.0.113.10:443', 'explanation' => 'The laptop opens a connection from its private address and a random source port.'],
                                ['from' => 'router', 'to' => 'server', 'label' => 'SYN', 'detail' => '198.51.100.7:40001 → 203.0.113.10:443', 'explanation' => 'The router rewrites the source to its public address and a port of its choosing, and records: 40001 belongs to 192.168.1.20:51000.', 'states' => ['router' => '40001 ↔ .20:51000']],
                                ['from' => 'server', 'to' => 'router', 'label' => 'SYN-ACK', 'detail' => '→ 198.51.100.7:40001', 'explanation' => 'The server only ever sees the public address and answers it.'],
                                ['from' => 'router', 'to' => 'laptop', 'label' => 'SYN-ACK', 'detail' => '→ 192.168.1.20:51000', 'explanation' => 'The router looks up port 40001, rewrites the destination back, and delivers the reply. Packets to a public port with no table entry have nowhere to go.', 'states' => ['router' => '40001 ↔ .20:51000']],
                            ],
                        ]],
                        ['comparison', 'Kinds of NAT', [
                            'columns' => ['Source NAT', 'Destination NAT'],
                            'rows' => [
                                ['label' => 'Also called', 'values' => ['Masquerading, PAT, NAT overload', 'Port forwarding']],
                                ['label' => 'Rewrites', 'values' => ['The source of outgoing connections', 'The destination of incoming connections']],
                                ['label' => 'Started by', 'values' => ['An inside host', 'Anyone on the outside']],
                                ['label' => 'Example', 'values' => ['Every laptop in the office sharing one public address', 'Public port 443 sent to an inside web server; Docker publishing a container port']],
                            ],
                        ]],
                        ['code', 'Source NAT on a Linux router (nftables)', <<<'SH'
sudo nft add table ip nat
sudo nft add chain ip nat postrouting '{ type nat hook postrouting priority srcnat; }'
sudo nft add rule ip nat postrouting oifname "wan0" masquerade
SH, 'bash'],
                        ['callout', 'NAT is not a firewall', 'Unsolicited inbound packets fail through NAT only because no mapping exists. A port forward, an automatic one opened by UPnP, or IPv6, where hosts usually have global addresses and no NAT at all, removes that accident. Protect inbound traffic with explicit firewall rules.', 'warning'],
                        ['prose', 'Where else you meet it', <<<'MD'
Containers rely on NAT too: when Docker publishes a container's port, it adds a destination NAT rule from the host's port to the container's private address. The blog post "Docker & Containers 101" (/blog/docker-containers-101) follows one such packet hop by hop.
MD],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A web server logs a visit from `198.51.100.7`, your office router\'s public address. Can it tell which office laptop made the request?',
                                    'options' => [
                                        ['text' => 'No: only the NAT router\'s translation table maps that connection to a laptop', 'correct' => true],
                                        ['text' => 'Yes: the laptop\'s private address is in the packet', 'correct' => false],
                                        ['text' => 'Yes: the laptop\'s MAC address is in the packet', 'correct' => false],
                                    ],
                                    'explanation' => 'Source NAT replaced the private address before the packet left. To attribute it, you need the router\'s NAT or firewall logs for that time and port.',
                                ],
                                [
                                    'prompt' => 'Which setup exposes an inside server to the internet?',
                                    'options' => [
                                        ['text' => 'A port forward from the router\'s public port to that server', 'correct' => true],
                                        ['text' => 'Source NAT for the office laptops', 'correct' => false],
                                        ['text' => 'Giving the server a private address', 'correct' => false],
                                    ],
                                    'explanation' => 'Destination NAT accepts connections started from outside and passes them inward. Every port forward is a door that needs a reason and a firewall rule.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Transport and diagnostics',
            'lessons' => [
                [
                    'slug' => 'tcp-and-udp-in-depth',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you deliver an ordered byte stream over a network that loses packets?',
                        'shape' => 'queue',
                        'nodes' => [
                            ['id' => 'oldest', 'label' => 'Oldest unacknowledged bytes', 'note' => 'An acknowledgement removes them from the front, and the window slides.', 'section' => 'the-sliding-window'],
                            ['id' => 'flight', 'label' => 'Newer bytes in flight', 'note' => 'A loss is resent from the front after duplicate ACKs or a timeout.', 'section' => 'acknowledging-and-resending'],
                            ['id' => 'may', 'label' => 'May send: inside the window', 'section' => 'the-sliding-window'],
                            ['id' => 'wait', 'label' => 'Must wait: beyond the window', 'note' => 'A window of 0 stops the sender: flow control.', 'section' => 'the-sliding-window'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'TCP and UDP in depth',
                    'minutes' => 20,
                    'summary' => 'Ports and sockets, and how TCP turns a lossy network into an ordered, reliable byte stream.',
                    'objectives' => ['Explain ports, sockets and the connection 4-tuple', 'Follow acknowledgements, the sliding window and retransmission', 'Read TCP flags and what scanners learn from them'],
                    'takeaways' => [
                        'A connection is identified by source address, source port, destination address and destination port.',
                        'TCP numbers every byte, acknowledges what arrived in order, and resends what did not.',
                        'The window limits how much unacknowledged data may be in flight, so a slow receiver is never overrun.',
                    ],
                    'resources' => [
                        ['RFC 9293: Transmission Control Protocol', 'https://www.rfc-editor.org/rfc/rfc9293'],
                        ['RFC 768: User Datagram Protocol', 'https://www.rfc-editor.org/rfc/rfc768'],
                        ['IANA service name and port number registry', 'https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml'],
                    ],
                    'sections' => [
                        ['prose', 'Ports and sockets', <<<'MD'
An IP address gets a packet to a machine; a **port** gets it to the right program. Ports are 16-bit numbers, 0 to 65535. IANA lists **well-known** ports (0 to 1023) such as 443 for HTTPS, **registered** ports (1024 to 49151), and a **dynamic** range for short-lived client ports. Linux picks client ports from 32768 to 60999 by default.

An address plus a port is a **socket**. A TCP connection is identified by four values, the **4-tuple**: source address, source port, destination address, destination port. That is how one web server keeps thousands of visitors on port 443 apart.
MD],
                        ['code', 'Sockets on your machine', <<<'SH'
ss -tulpn                                   # listening TCP and UDP sockets, with their programs
ss -tn state established                    # open TCP connections and their 4-tuples
cat /proc/sys/net/ipv4/ip_local_port_range  # the client port range: 32768 60999
SH, 'bash'],
                        ['sequence', 'Acknowledging and resending', [
                            'actors' => [['id' => 'sender', 'name' => 'Sender'], ['id' => 'receiver', 'name' => 'Receiver']],
                            'messages' => [
                                ['from' => 'sender', 'to' => 'receiver', 'label' => 'Data', 'detail' => 'seq=1, 1000 bytes', 'explanation' => 'TCP numbers bytes, not packets. This segment carries bytes 1 to 1000.'],
                                ['from' => 'sender', 'to' => 'receiver', 'label' => 'Data (lost)', 'detail' => 'seq=1001, 1000 bytes', 'explanation' => 'The window allows several segments in flight without waiting. This one is dropped on the way.'],
                                ['from' => 'sender', 'to' => 'receiver', 'label' => 'Data', 'detail' => 'seq=2001, 1000 bytes', 'explanation' => 'Bytes 2001 to 3000 arrive, but bytes 1001 to 2000 are missing, so the receiver cannot pass them to the program yet.'],
                                ['from' => 'receiver', 'to' => 'sender', 'label' => 'ACK', 'detail' => 'ack=1001 (again)', 'explanation' => 'Acknowledgements are cumulative: "I have everything before byte 1001". Repeating it, a duplicate ACK, tells the sender something after byte 1000 went missing.'],
                                ['from' => 'sender', 'to' => 'receiver', 'label' => 'Resend', 'detail' => 'seq=1001, 1000 bytes', 'explanation' => 'Three duplicate ACKs or a timer running out make the sender resend the missing segment. This is called retransmission.'],
                                ['from' => 'receiver', 'to' => 'sender', 'label' => 'ACK', 'detail' => 'ack=3001', 'explanation' => 'The gap is filled, so one acknowledgement now covers everything up to byte 3000, and the receiver hands all 3000 bytes to the program in order.'],
                            ],
                        ]],
                        ['step-through', 'The sliding window', [
                            'steps' => [
                                ['title' => 'The window opens', 'text' => 'The receiver advertised a window of 4000 bytes: the sender may have up to 4000 unacknowledged bytes in flight. It sends bytes 1 to 4000 straight away.', 'parts' => [['label' => '1–4000 in flight', 'tone' => 'attn'], ['label' => '4001+ must wait', 'tone' => 'muted']], 'state' => [['label' => 'Acknowledged', 'value' => 'none'], ['label' => 'In flight', 'value' => '4000 bytes']]],
                                ['title' => 'The front is acknowledged', 'text' => 'An ACK for 2001 arrives. Bytes 1 to 2000 leave the front of the queue for good, and the window slides forward by 2000 bytes.', 'parts' => [['label' => '1–2000 done', 'tone' => 'accent'], ['label' => '2001–4000 in flight', 'tone' => 'attn'], ['label' => '4001–6000 may go', 'tone' => 'plain']], 'state' => [['label' => 'Acknowledged', 'value' => 'up to 2000'], ['label' => 'In flight', 'value' => '2000 bytes']]],
                                ['title' => 'New data joins the back', 'text' => 'The sender fills the space it just gained with bytes 4001 to 6000. Data always leaves the window in the order it entered, like a queue.', 'parts' => [['label' => '2001–6000 in flight', 'tone' => 'attn'], ['label' => '6001+ must wait', 'tone' => 'muted']], 'state' => [['label' => 'Acknowledged', 'value' => 'up to 2000'], ['label' => 'In flight', 'value' => '4000 bytes']]],
                                ['title' => 'A slow receiver shrinks it', 'text' => 'The receiving program is busy, so the next ACK advertises a window of 0. The sender stops and probes now and then until the window opens again. This is flow control.', 'parts' => [['label' => 'window 0: wait', 'tone' => 'attn']], 'state' => [['label' => 'Window', 'value' => '0 bytes'], ['label' => 'Sender', 'value' => 'paused']]],
                            ],
                        ]],
                        ['comparison', 'TCP flags you will see', [
                            'columns' => ['Means', 'You see it when'],
                            'rows' => [
                                ['label' => 'SYN', 'values' => ['Open a connection, here is my starting sequence number', 'A connection begins, or a scanner probes a port']],
                                ['label' => 'ACK', 'values' => ['The acknowledgement number is valid', 'Almost every segment after the first']],
                                ['label' => 'FIN', 'values' => ['I have no more data to send', 'A connection closes politely, one direction at a time']],
                                ['label' => 'RST', 'values' => ['Abort: this connection does not exist or is refused', 'A closed port answers a SYN, or a program crashes']],
                                ['label' => 'PSH', 'values' => ['Hand the data to the program now', 'Interactive traffic such as SSH keystrokes']],
                            ],
                        ]],
                        ['prose', 'UDP leaves it to the program', <<<'MD'
UDP has none of this: no handshake, no acknowledgements, no window. Each datagram is sent once and may be lost, duplicated or reordered. Programs that use UDP handle what they need themselves: a DNS client simply asks again, a video call skips a lost frame, and QUIC, which carries HTTP/3, builds its own reliability and encryption on top of UDP.
MD],
                        ['callout', 'What a port scan reads', 'A scanner sends a SYN to each port. An open TCP port answers SYN-ACK; a closed one answers RST; a filtered one says nothing, or an ICMP "unreachable" comes back. For UDP, a closed port usually triggers an ICMP "port unreachable" and an open one often stays silent. Firewall logs full of SYNs to many ports are a scan in progress.', 'warning'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'Two browser tabs on the same laptop connect to the same server on port 443. How does the server keep the connections apart?',
                                    'options' => [
                                        ['text' => 'Each tab uses a different client source port, so the 4-tuples differ', 'correct' => true],
                                        ['text' => 'It cannot: the second tab waits for the first to close', 'correct' => false],
                                        ['text' => 'By the laptop\'s MAC address', 'correct' => false],
                                    ],
                                    'explanation' => 'Source port is part of the 4-tuple. The operating system gives each new connection its own client port.',
                                ],
                                [
                                    'prompt' => 'After acknowledging up to byte 1001, a receiver sends three more duplicate acknowledgements, all `ack=1001`. What does the sender conclude?',
                                    'options' => [
                                        ['text' => 'The segment starting at byte 1001 was probably lost, so it resends it', 'correct' => true],
                                        ['text' => 'The connection is closing', 'correct' => false],
                                        ['text' => 'The receiver wants it to send faster', 'correct' => false],
                                    ],
                                    'explanation' => 'Duplicate acknowledgements mean later segments arrived but byte 1001 did not. Fast retransmission resends it without waiting for the timer.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'icmp-and-troubleshooting',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'Where exactly does a broken connection break?',
                        'shape' => 'pipeline',
                        'nodes' => [
                            ['id' => 'link', 'label' => 'Is the interface up?', 'note' => '`ip -br link`', 'section' => 'where-does-it-break'],
                            ['id' => 'addr', 'label' => 'Do I have an address?', 'note' => '169.254.x.x means DHCP failed.', 'section' => 'where-does-it-break', 'lesson' => 'dns-and-dhcp'],
                            ['id' => 'gw', 'label' => 'Can I reach the gateway?', 'section' => 'the-routine-on-linux', 'lesson' => 'routing-basics'],
                            ['id' => 'path', 'label' => 'Can I reach the remote address?', 'note' => '`tracepath` shows the last hop that answers.', 'section' => 'how-traceroute-finds-the-path'],
                            ['id' => 'name', 'label' => 'Does the name resolve?', 'section' => 'where-does-it-break', 'lesson' => 'dns-and-dhcp'],
                            ['id' => 'port', 'label' => 'Does the port answer?', 'note' => 'Refused, timed out or connected each mean something different.', 'section' => 'what-the-symptom-means', 'lesson' => 'tcp-and-udp-in-depth'],
                            ['id' => 'app', 'label' => 'Look at the application', 'section' => 'where-does-it-break'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'ICMP and troubleshooting',
                    'minutes' => 20,
                    'summary' => 'The network\'s error messages, and a bottom-up routine for finding where a connection breaks.',
                    'objectives' => ['Explain the ICMP messages behind ping and traceroute', 'Troubleshoot a connection layer by layer with ip, ping, tracepath and ss', 'Read common error messages and what they point to'],
                    'takeaways' => [
                        'ICMP carries errors and diagnostics: echo for ping, time exceeded for traceroute, unreachable for refusals.',
                        'Work from the bottom up: link, address, gateway, path, name, port, application.',
                        '"Refused" means something answered no; "timed out" usually means something silently dropped the traffic.',
                    ],
                    'resources' => [
                        ['RFC 792: Internet Control Message Protocol', 'https://www.rfc-editor.org/rfc/rfc792'],
                        ['ss(8)', 'https://man7.org/linux/man-pages/man8/ss.8.html'],
                        ['tracepath(8)', 'https://man7.org/linux/man-pages/man8/tracepath.8.html'],
                    ],
                    'sections' => [
                        ['prose', 'The network talks back', <<<'MD'
The **Internet Control Message Protocol** (ICMP) carries the network's own messages. You will meet three kinds most:

- **echo request and reply** (types 8 and 0): what `ping` sends and expects back
- **destination unreachable** (type 3): no route, host down, port closed, or "administratively prohibited" by a firewall
- **time exceeded** (type 11): a packet's TTL reached zero at a router

Blocking all ICMP makes networks harder to fix and can break things: path MTU discovery depends on "fragmentation needed" (IPv4) and "packet too big" (IPv6) messages, and IPv6 cannot work at all without ICMPv6.
MD],
                        ['flow', 'Where does it break?', [
                            'direction' => 'down',
                            'nodes' => [
                                ['id' => 'link', 'label' => 'Is the interface up?', 'kind' => 'decision', 'detail' => '`ip -br link` should say UP. If not: cable, Wi-Fi association, or a disabled interface.'],
                                ['id' => 'addr', 'label' => 'Do I have an address?', 'kind' => 'decision', 'detail' => '`ip -br addr`. A 169.254.x.x address means DHCP failed.'],
                                ['id' => 'gw', 'label' => 'Can I reach the gateway?', 'kind' => 'decision', 'detail' => '`ip route` shows the default gateway; ping it.'],
                                ['id' => 'path', 'label' => 'Can I reach the remote address?', 'kind' => 'decision', 'detail' => 'Ping it, then `tracepath` it to see the last hop that answers.'],
                                ['id' => 'name', 'label' => 'Does the name resolve?', 'kind' => 'decision', 'detail' => '`getent hosts` or `dig`. Working by IP but not by name points at DNS.'],
                                ['id' => 'port', 'label' => 'Does the port answer?', 'kind' => 'decision', 'detail' => 'Refused, timed out or connected? `curl -v` or `nc -zv` tells you which.'],
                                ['id' => 'app', 'label' => 'Look at the application', 'kind' => 'end', 'detail' => 'The network delivered the connection. Read the application\'s own logs and errors.'],
                                ['id' => 'fix', 'label' => 'Fix this layer first', 'kind' => 'end'],
                            ],
                            'edges' => [
                                ['from' => 'link', 'to' => 'addr', 'label' => 'Yes'],
                                ['from' => 'addr', 'to' => 'gw', 'label' => 'Yes'],
                                ['from' => 'gw', 'to' => 'path', 'label' => 'Yes'],
                                ['from' => 'path', 'to' => 'name', 'label' => 'Yes'],
                                ['from' => 'name', 'to' => 'port', 'label' => 'Yes'],
                                ['from' => 'port', 'to' => 'app', 'label' => 'Yes'],
                                ['from' => 'link', 'to' => 'fix', 'label' => 'No'],
                                ['from' => 'addr', 'to' => 'fix', 'label' => 'No'],
                                ['from' => 'gw', 'to' => 'fix', 'label' => 'No'],
                                ['from' => 'path', 'to' => 'fix', 'label' => 'No'],
                                ['from' => 'name', 'to' => 'fix', 'label' => 'No'],
                                ['from' => 'port', 'to' => 'fix', 'label' => 'No'],
                            ],
                        ]],
                        ['code', 'The routine on Linux', <<<'SH'
ip -br link                          # 1. interfaces UP?
ip -br addr                          # 2. an address on the right subnet?
ip route                             # 3. a default route? note the gateway
ping -c 3 192.168.1.1                # 3. the gateway answers?
ping -c 3 203.0.113.10               # 4. the remote host answers?
tracepath 203.0.113.10               # 4. where does the path stop?
getent hosts www.example.com         # 5. does the name resolve?
curl -v https://www.example.com/     # 6. does the port and the service answer?
ss -tn state syn-sent                # connections stuck waiting for a SYN-ACK
SH, 'bash'],
                        ['sequence', 'How traceroute finds the path', [
                            'actors' => [
                                ['id' => 'you', 'name' => 'You'],
                                ['id' => 'r1', 'name' => 'Router 1'],
                                ['id' => 'r2', 'name' => 'Router 2'],
                                ['id' => 'dest', 'name' => 'Destination'],
                            ],
                            'messages' => [
                                ['from' => 'you', 'to' => 'r1', 'label' => 'Probe', 'detail' => 'TTL=1', 'explanation' => 'The first probe is sent with a TTL of 1. The first router lowers it to 0 and must drop the packet.'],
                                ['from' => 'r1', 'to' => 'you', 'label' => 'Time exceeded', 'detail' => 'from router 1', 'explanation' => 'Router 1 reports the drop with ICMP time exceeded, which reveals its address and the round-trip time.'],
                                ['from' => 'you', 'to' => 'r2', 'label' => 'Probe', 'detail' => 'TTL=2', 'explanation' => 'The next probe gets one hop further before its TTL runs out at router 2.'],
                                ['from' => 'r2', 'to' => 'you', 'label' => 'Time exceeded', 'detail' => 'from router 2', 'explanation' => 'Router 2 identifies itself the same way. A router that sends nothing shows up as `* * *` or "no reply".'],
                                ['from' => 'you', 'to' => 'dest', 'label' => 'Probe', 'detail' => 'TTL=3', 'explanation' => 'This probe reaches the destination itself.'],
                                ['from' => 'dest', 'to' => 'you', 'label' => 'Reply', 'detail' => 'port unreachable or echo reply', 'explanation' => 'The destination answers, which ends the trace. The list of addresses that answered is the path.'],
                            ],
                        ]],
                        ['comparison', 'What the symptom means', [
                            'columns' => ['Usually means', 'Check next'],
                            'rows' => [
                                ['label' => 'Connection refused', 'values' => ['The host is up, but nothing listens on that port (it sent RST)', '`ss -tlnp` on the server: is the service running and on which address?']],
                                ['label' => 'Connection timed out', 'values' => ['Something dropped the packets silently, often a firewall', 'Firewall rules on both ends and in between; `tracepath` to see where it stops']],
                                ['label' => 'No route to host', 'values' => ['No route, or an ICMP unreachable came back', '`ip route`, and whether the remote host is up']],
                                ['label' => 'Name or service not known', 'values' => ['DNS could not resolve the name', '`dig` the name; check `/etc/resolv.conf` and the DNS server']],
                                ['label' => 'Ping fails, web page loads', 'values' => ['ICMP echo is filtered; the network is fine', 'Test the real service instead of ping']],
                            ],
                        ]],
                        ['callout', 'Firewalls change what you see', 'Many hosts and networks drop ping and traceroute probes, so silence is not proof that something is down. A timeout points at a filtering device; practise reading rules in the lab "Lock down a web server" (/labs/lock-down-a-web-server), and see the System Hardening course for host firewalls.', 'info'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => '`curl` to a server reports "Connection refused" immediately. What do you know?',
                                    'options' => [
                                        ['text' => 'The server is reachable, but no program accepts connections on that port', 'correct' => true],
                                        ['text' => 'A firewall silently dropped the packets', 'correct' => false],
                                        ['text' => 'DNS failed to resolve the name', 'correct' => false],
                                    ],
                                    'explanation' => 'A refusal is an answer: the host replied with a TCP reset. A silent drop would end in a timeout, and a DNS failure happens before any connection.',
                                ],
                                [
                                    'prompt' => 'Your laptop shows the address 169.254.12.7. Which layer do you fix first?',
                                    'options' => [
                                        ['text' => 'Getting an address: DHCP did not answer', 'correct' => true],
                                        ['text' => 'DNS', 'correct' => false],
                                        ['text' => 'The web application', 'correct' => false],
                                    ],
                                    'explanation' => '169.254.0.0/16 is link-local, assigned when no DHCP server answered. Nothing above works until the host has a proper address.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
        [
            'title' => 'Services and seeing traffic',
            'lessons' => [
                [
                    'slug' => 'dns-and-dhcp',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do names become addresses, and how does a new device get an address at all?',
                        'shape' => 'tree',
                        'nodes' => [
                            ['id' => 'root', 'label' => 'Root (.)', 'note' => 'Knows where every top-level domain is.', 'section' => 'names-form-a-tree'],
                            ['id' => 'com', 'label' => 'com', 'section' => 'names-form-a-tree', 'parent' => 'root'],
                            ['id' => 'org', 'label' => 'org', 'section' => 'names-form-a-tree', 'parent' => 'root'],
                            ['id' => 'vn', 'label' => 'vn', 'section' => 'names-form-a-tree', 'parent' => 'root'],
                            ['id' => 'example', 'label' => 'example.com', 'note' => 'Its authoritative servers hold the real records.', 'section' => 'names-form-a-tree', 'parent' => 'com'],
                            ['id' => 'www', 'label' => 'www.example.com', 'note' => 'A resolver walks down the tree to find it.', 'section' => 'resolving-wwwexamplecom', 'parent' => 'example'],
                            ['id' => 'mail', 'label' => 'mail.example.com', 'section' => 'names-form-a-tree', 'parent' => 'example'],
                            ['id' => 'edu', 'label' => 'edu.vn', 'section' => 'names-form-a-tree', 'parent' => 'vn'],
                            ['id' => 'uit', 'label' => 'uit.edu.vn', 'section' => 'names-form-a-tree', 'parent' => 'edu'],
                        ],
                        'edges' => [],
                    ],
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
                [
                    'slug' => 'application-protocols',
                    'architecture' => ['version' => 1, 'problem' => 'Which service is this, and does it send secrets in clear text?', 'shape' => null, 'nodes' => [], 'edges' => []],
                    'title' => 'Application protocols at a glance',
                    'minutes' => 12,
                    'summary' => 'Which services you will meet, on which ports and transports, and which ones send secrets in clear text.',
                    'objectives' => ['Recognise common services from their port and transport', 'Tell clear-text protocols from their encrypted replacements', 'Explain why a port number alone does not prove what is running'],
                    'takeaways' => [
                        'Well-known ports are conventions: 22 SSH, 53 DNS, 80 HTTP, 443 HTTPS, 3389 RDP.',
                        'Telnet, FTP and plain HTTP send passwords readable to anyone on the path; use their encrypted replacements.',
                        'Any service can run on any port, so confirm what is listening from its traffic, not from the number.',
                    ],
                    'resources' => [
                        ['IANA service name and port number registry', 'https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.xhtml'],
                        ['RFC 6335: Procedures for the management of the service name and port number registry', 'https://www.rfc-editor.org/rfc/rfc6335'],
                    ],
                    'sections' => [
                        ['prose', 'A port is a convention', <<<'MD'
Servers listen on **well-known ports** so that clients know where to knock: a browser goes to port 443 unless told otherwise. The table below is the short list worth knowing by heart. Each protocol has its own course or lesson where it matters for security: HTTP in Web Security Foundations, TLS in Applied Cryptography, SSH in System Hardening.
MD],
                        ['comparison', 'Services to recognise', [
                            'columns' => ['Port', 'Transport', 'Notes'],
                            'rows' => [
                                ['label' => 'HTTP', 'values' => ['80', 'TCP', 'Web traffic in clear text']],
                                ['label' => 'HTTPS', 'values' => ['443', 'TCP, and UDP for HTTP/3', 'HTTP inside TLS; HTTP/3 runs over QUIC on UDP']],
                                ['label' => 'DNS', 'values' => ['53', 'UDP, and TCP for large answers', 'Encrypted variants: DNS over TLS on 853, DNS over HTTPS on 443']],
                                ['label' => 'DHCP', 'values' => ['67 server, 68 client', 'UDP', 'Starts with a broadcast on the local network; a relay agent forwards it to a server on another subnet, and renewals are unicast']],
                                ['label' => 'SSH', 'values' => ['22', 'TCP', 'Encrypted remote shell and file copy (scp, sftp)']],
                                ['label' => 'SMTP', 'values' => ['25 between servers, 587 for clients', 'TCP', 'Email delivery; STARTTLS upgrades it to encrypted']],
                                ['label' => 'IMAPS', 'values' => ['993', 'TCP', 'Reading email over TLS']],
                                ['label' => 'NTP', 'values' => ['123', 'UDP', 'Clock synchronisation; log timestamps depend on it']],
                                ['label' => 'SNMP', 'values' => ['161, traps to 162', 'UDP', 'Device monitoring; versions 1 and 2c send the "community" password in clear']],
                                ['label' => 'LDAP / LDAPS', 'values' => ['389 / 636', 'TCP', 'Directory lookups, such as Active Directory']],
                                ['label' => 'SMB', 'values' => ['445', 'TCP', 'Windows file sharing; never expose it to the internet']],
                                ['label' => 'RDP', 'values' => ['3389', 'TCP', 'Windows remote desktop; a favourite target for password guessing']],
                                ['label' => 'Syslog', 'values' => ['514', 'UDP (TCP 6514 with TLS)', 'Shipping logs to a collector']],
                            ],
                        ]],
                        ['comparison', 'Clear text and its replacement', [
                            'columns' => ['Sends in clear text', 'Use instead'],
                            'rows' => [
                                ['label' => 'Remote shell', 'values' => ['Telnet (23)', 'SSH (22)']],
                                ['label' => 'File transfer', 'values' => ['FTP (21)', 'SFTP over SSH, or HTTPS']],
                                ['label' => 'Web', 'values' => ['HTTP (80)', 'HTTPS (443)']],
                                ['label' => 'Monitoring', 'values' => ['SNMP v1 and v2c', 'SNMP v3 with authentication and encryption']],
                            ],
                        ]],
                        ['code', 'Look up and list services', <<<'SH'
getent services ssh          # ssh  22/tcp, from /etc/services
sudo ss -tlnp                # what is actually listening, and which program
SH, 'bash'],
                        ['callout', 'Ports can lie', 'Nothing stops SSH from listening on 443 or a backdoor from using port 53 to slip past a firewall. A port number is a hint. To know what really runs, look at the traffic itself, which is what the packet capture lesson shows.', 'info'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'A scan finds TCP port 3389 open on a server reachable from the internet. What is probably running, and why does it matter?',
                                    'options' => [
                                        ['text' => 'Windows Remote Desktop, a common target for password guessing', 'correct' => true],
                                        ['text' => 'DNS, which is harmless to expose', 'correct' => false],
                                        ['text' => 'NTP, used for time synchronisation', 'correct' => false],
                                    ],
                                    'explanation' => '3389 is RDP by convention. Exposed remote desktop is brute-forced constantly; it belongs behind a VPN or with strong authentication.',
                                ],
                                [
                                    'prompt' => 'Which of these send credentials readable to anyone on the network path? Choose all that apply.',
                                    'options' => [
                                        ['text' => 'Telnet', 'correct' => true],
                                        ['text' => 'FTP', 'correct' => true],
                                        ['text' => 'SSH', 'correct' => false],
                                        ['text' => 'HTTPS', 'correct' => false],
                                    ],
                                    'explanation' => 'Telnet and FTP predate encryption on the network. SSH and HTTPS encrypt everything, credentials included.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'wifi-essentials',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you connect without wires and keep the neighbours out?',
                        'shape' => 'onion',
                        'nodes' => [
                            ['id' => 'data', 'label' => 'Your data', 'note' => 'Wi-Fi encryption ends at the access point; use TLS or a VPN as well.', 'section' => 'rings-around-your-data'],
                            ['id' => 'enc', 'label' => 'Encryption', 'note' => 'The four-way handshake gives each client its own session keys.', 'section' => 'rings-around-your-data'],
                            ['id' => 'auth', 'label' => 'Authentication', 'note' => 'A shared passphrase (PSK in WPA2, SAE in WPA3) or 802.1X for each user.', 'section' => 'wpa2-and-wpa3-personal'],
                            ['id' => 'assoc', 'label' => 'Association', 'note' => 'Hiding the SSID does not hide the network.', 'section' => 'rings-around-your-data'],
                            ['id' => 'radio', 'label' => 'Radio range', 'note' => 'Assume every frame sent over the air is captured.', 'section' => 'evil-twins-and-deauthentication'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'Wi-Fi essentials',
                    'minutes' => 15,
                    'summary' => 'How Wi-Fi joins devices to a network, the rings of protection around your data, and what WPA3 changed.',
                    'objectives' => ['Describe how a device joins a Wi-Fi network', 'Compare WPA2 and WPA3', 'Recognise evil-twin and deauthentication attacks'],
                    'takeaways' => [
                        'Anyone in radio range can hear a Wi-Fi network, so its protection comes from authentication and encryption.',
                        'WPA3-Personal stops offline password guessing and requires protected management frames.',
                        'An evil twin copies a network\'s name; enterprise Wi-Fi with certificate checking stops clients from trusting it.',
                    ],
                    'resources' => [
                        ['Wi-Fi Alliance: Wi-Fi security', 'https://www.wi-fi.org/discover-wi-fi/security'],
                        ['NIST SP 800-153: Guidelines for securing wireless local area networks', 'https://csrc.nist.gov/pubs/sp/800/153/final'],
                    ],
                    'sections' => [
                        ['prose', 'Ethernet over radio', <<<'MD'
Wi-Fi (the IEEE 802.11 standards) carries frames with the same MAC addressing as Ethernet, but over radio in the 2.4, 5 and 6 GHz bands. A network announces its name, the **SSID**, and each access point radio has its own hardware address, the **BSSID**. A device joining a network **authenticates** (with WPA3, by proving it knows the password), **associates** with an access point, then derives fresh **encryption keys** in a four-way handshake. Only then does it get an address from DHCP like any wired device.
MD],
                        ['layer-stack', 'Rings around your data', [
                            'layers' => [
                                ['tag' => '1', 'name' => 'Radio range', 'description' => 'The outermost ring: anyone close enough can receive every frame on the network, encrypted or not. Walls slow radio down; they do not stop it.', 'note' => ['tone' => 'warning', 'text' => 'Assume an attacker can capture everything sent over the air.']],
                                ['tag' => '2', 'name' => 'Association', 'description' => 'A device picks a network by its SSID and joins an access point. Hiding the SSID does not help: it still appears in the frames of every client that connects.'],
                                ['tag' => '3', 'name' => 'Authentication', 'description' => 'Personal networks check a shared passphrase (PSK in WPA2, SAE in WPA3). Enterprise networks check each user or device against a RADIUS server with 802.1X, so one leaver does not mean changing everyone\'s password.', 'tags' => ['WPA2-PSK', 'WPA3-SAE', '802.1X']],
                                ['tag' => '4', 'name' => 'Encryption', 'description' => 'The four-way handshake gives each client its own session keys. Frames are then encrypted with AES (CCMP, or GCMP in newer modes).', 'tags' => ['CCMP', 'GCMP']],
                                ['tag' => '5', 'name' => 'Your data', 'description' => 'What is left at the core. Wi-Fi encryption ends at the access point, so traffic that matters should also be protected end to end with TLS or a VPN.'],
                            ],
                        ]],
                        ['comparison', 'WPA2 and WPA3 (Personal)', [
                            'columns' => ['WPA2-Personal', 'WPA3-Personal'],
                            'rows' => [
                                ['label' => 'Password check', 'values' => ['Pre-shared key and the four-way handshake', 'SAE ("Dragonfly"), a password-authenticated key exchange']],
                                ['label' => 'Captured handshake', 'values' => ['Can be cracked offline, guessing passwords at full speed', 'Useless: every guess needs a live exchange with the network']],
                                ['label' => 'Forward secrecy', 'values' => ['No: learning the password decrypts recorded traffic', 'Yes: past sessions stay protected']],
                                ['label' => 'Management frames', 'values' => ['Protection optional, so forged disconnects work', 'Protection (PMF) required']],
                            ],
                        ]],
                        ['code', 'Inspect Wi-Fi on Linux', <<<'SH'
nmcli dev wifi list          # nearby networks: SSID, BSSID, channel, security
iw dev wlan0 link            # the access point you are connected to and the signal
SH, 'bash'],
                        ['callout', 'Evil twins and deauthentication', 'An attacker can set up an access point with the same SSID as a real one, then send forged "deauthenticate" frames so clients drop off and reconnect, some of them to the impostor. WPA3 makes protected management frames mandatory, which stops the forged disconnects; enterprise Wi-Fi whose clients check the server certificate stops them trusting a fake network.', 'warning'],
                        ['prose', 'Guest networks', <<<'MD'
Visitors' devices belong on their own network: a separate SSID mapped to its own VLAN and subnet, with firewall rules that allow the internet and nothing internal. Turning on **client isolation** also stops guest devices from talking to each other.
MD],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'An attacker recorded a WPA2-Personal handshake in your car park. What can they do with it at home?',
                                    'options' => [
                                        ['text' => 'Try to crack the passphrase offline, as fast as their hardware allows', 'correct' => true],
                                        ['text' => 'Nothing: handshakes contain no usable information', 'correct' => false],
                                        ['text' => 'Read the network\'s traffic straight away, whatever the passphrase', 'correct' => false],
                                    ],
                                    'explanation' => 'A WPA2 handshake lets an attacker test passphrase guesses offline. A long random passphrase makes that hopeless; WPA3\'s SAE removes the attack.',
                                ],
                                [
                                    'prompt' => 'Why should a guest Wi-Fi network have its own VLAN and firewall rules, not just its own password?',
                                    'options' => [
                                        ['text' => 'So an infected guest device can reach the internet but nothing inside the organisation', 'correct' => true],
                                        ['text' => 'So guests get faster speeds', 'correct' => false],
                                        ['text' => 'Because VLANs encrypt the radio link', 'correct' => false],
                                    ],
                                    'explanation' => 'A password only controls who joins. Separation and rules control what a joined device can reach.',
                                ],
                            ],
                        ]],
                    ],
                ],
                [
                    'slug' => 'packet-capture-basics',
                    'architecture' => [
                        'version' => 1,
                        'problem' => 'How do you see what is really on the wire, safely and legally?',
                        'shape' => 'pipeline',
                        'nodes' => [
                            ['id' => 'permit', 'label' => 'Get permission', 'note' => 'Capture only where you are authorised to.', 'section' => 'capture-safely'],
                            ['id' => 'filter', 'label' => 'Capture filter', 'note' => 'Capture as little as you need.', 'section' => 'capture-filters-and-display-filters'],
                            ['id' => 'write', 'label' => 'Save a pcap', 'note' => 'Treat it as sensitive: it can hold passwords and cookies.', 'section' => 'tcpdump-in-practice'],
                            ['id' => 'display', 'label' => 'Display filter', 'section' => 'capture-filters-and-display-filters'],
                            ['id' => 'read', 'label' => 'Read the layers', 'section' => 'reading-one-http-request', 'lesson' => 'encapsulation-and-pdus'],
                        ],
                        'edges' => [],
                    ],
                    'title' => 'Packet capture basics',
                    'minutes' => 20,
                    'summary' => 'Capturing traffic with tcpdump and reading it layer by layer, safely and legally.',
                    'objectives' => ['Capture traffic with tcpdump using capture filters', 'Read a captured packet from frame to application data', 'Explain what encrypted traffic still reveals'],
                    'takeaways' => [
                        'A capture filter decides what is saved; a display filter decides what you look at afterwards.',
                        'Reading a packet means unwrapping it the way the receiver does: frame, packet, segment, data.',
                        'With TLS you still see addresses, ports, the server name, sizes and timing, but not the content.',
                    ],
                    'resources' => [
                        ['tcpdump(1)', 'https://www.tcpdump.org/manpages/tcpdump.1.html'],
                        ['pcap-filter(7): capture filter syntax', 'https://www.tcpdump.org/manpages/pcap-filter.7.html'],
                        ['Wireshark User\'s Guide: building display filters', 'https://www.wireshark.org/docs/wsug_html_chunked/ChWorkBuildDisplayFilterSection.html'],
                    ],
                    'sections' => [
                        ['prose', 'Seeing what is really on the wire', <<<'MD'
Logs tell you what a program decided to write down. A **packet capture** shows what actually crossed the network, which is why it settles arguments about firewalls, DNS and "the server never got my request". `tcpdump` captures on the command line; Wireshark does the same with a graphical view, and both read and write the same `.pcap` files.
MD],
                        ['steps', 'Capture safely', [
                            'Capture only on networks and systems you are authorised to monitor. Capturing other people\'s traffic without permission is illegal in most countries.',
                            'Capture as little as you need: one interface, one host or port, a limited count or time.',
                            'Treat capture files as sensitive: they can contain passwords, cookies and personal data. Store them securely and delete them when done.',
                            'Write to a file (`-w`) when you need to analyse later or share with a colleague.',
                        ]],
                        ['code', 'tcpdump in practice', <<<'SH'
sudo tcpdump -i eth0 -n port 53                         # DNS queries and answers, no name lookups
sudo tcpdump -i eth0 -n -c 20 host 203.0.113.10         # 20 packets to or from one host
sudo tcpdump -i eth0 -w web.pcap 'tcp port 443'         # save HTTPS traffic to a file
tcpdump -n -r web.pcap | head                           # read the file back
SH, 'bash'],
                        ['comparison', 'Capture filters and display filters', [
                            'columns' => ['Capture filter', 'Display filter'],
                            'rows' => [
                                ['label' => 'Applied', 'values' => ['While capturing: packets that do not match are never saved', 'Afterwards: it hides packets but keeps the file intact']],
                                ['label' => 'Syntax', 'values' => ['BPF: `tcp port 443 and host 203.0.113.10`', 'Wireshark: `tcp.port == 443 && ip.addr == 203.0.113.10`']],
                                ['label' => 'Used in', 'values' => ['tcpdump, Wireshark\'s capture options', 'Wireshark, tshark `-Y`']],
                            ],
                        ]],
                        ['step-through', 'Reading one HTTP request', [
                            'steps' => [
                                ['title' => 'Frame', 'text' => 'The outermost layer: source and destination MAC addresses and the EtherType (IPv4). It tells you which two devices on this link exchanged it, usually a host and its gateway.', 'parts' => [['label' => 'Ethernet', 'tone' => 'attn'], ['label' => 'IP'], ['label' => 'TCP'], ['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Read', 'value' => 'MAC addresses, EtherType']]],
                                ['title' => 'Packet', 'text' => 'Source and destination IP addresses and the TTL. A TTL of 64 or a little less usually means Linux, macOS or a phone; around 128 usually means Windows.', 'parts' => [['label' => 'IP', 'tone' => 'attn'], ['label' => 'TCP'], ['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Read', 'value' => '192.168.1.20 → 203.0.113.10, TTL 64']]],
                                ['title' => 'Segment', 'text' => 'Ports and flags. Destination port 80 and the PSH and ACK flags: an established connection carrying data to a web server.', 'parts' => [['label' => 'TCP', 'tone' => 'attn'], ['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Read', 'value' => '51000 → 80, flags PSH ACK']]],
                                ['title' => 'Application data', 'text' => 'Plain HTTP is readable: the method, the path, the Host header, the cookies. Over HTTPS this part is encrypted and shows as TLS records instead.', 'parts' => [['label' => 'HTTP', 'tone' => 'accent']], 'state' => [['label' => 'Read', 'value' => 'GET /login, Host: shop.example']]],
                            ],
                        ]],
                        ['callout', 'What encryption still shows', 'With TLS you cannot read the content, but you still see who talked to whom, on which ports, when, and how much. The server name is usually visible in the TLS handshake (SNI) too. That is often enough to spot beaconing malware or data leaving at night. How TLS hides the rest is in the Applied Cryptography course.', 'info'],
                        ['knowledge-check', 'Check your understanding', [
                            'questions' => [
                                [
                                    'prompt' => 'You only care about DNS traffic and want small capture files. Which kind of filter do you use?',
                                    'options' => [
                                        ['text' => 'A capture filter such as `port 53`, so nothing else is saved', 'correct' => true],
                                        ['text' => 'A display filter, applied after capturing everything', 'correct' => false],
                                        ['text' => 'Neither: tcpdump cannot filter by port', 'correct' => false],
                                    ],
                                    'explanation' => 'Capture filters run before anything is written. Display filters only hide packets in a file that already holds everything.',
                                ],
                                [
                                    'prompt' => 'A laptop sends HTTPS traffic. Which can you still see in a capture? Choose all that apply.',
                                    'options' => [
                                        ['text' => 'The server\'s IP address and port', 'correct' => true],
                                        ['text' => 'How many bytes were sent and when', 'correct' => true],
                                        ['text' => 'The password typed into the login form', 'correct' => false],
                                        ['text' => 'The full URL path and its parameters', 'correct' => false],
                                    ],
                                    'explanation' => 'TLS protects the content, including the HTTP path and form data. Addresses, ports, sizes and timing travel outside the encryption.',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ],
    ],
];
