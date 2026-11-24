# Course overview: Networking Fundamentals

> Format `cybercompass.content`, version 1. Exported 2026-11-24. Read it with the AI authoring guide (`AI_AUTHORING_GUIDE.md`, "Download AI guide" in the admin console).
> It describes each lesson as it is being edited (its draft when there is one). Target an existing section by its **uuid**; give a **key** only to a new section.

## The course

- **Slug:** `networking-fundamentals`
- **Domain:** Networking · **Difficulty:** 1 of 3
- **Summary:** How data moves from one machine to another: devices, layers, addresses, routing, and the protocols underneath every connection.
- **Skills:** OSI and TCP/IP models, IP addressing and subnetting, TCP, UDP, DNS and DHCP
- **Prerequisites:** none
- **Objectives:**
  - Explain what happens at each layer of the OSI and TCP/IP models
  - Describe how switches, VLANs and ARP move traffic inside a local network
  - Read IPv4 and IPv6 addresses, calculate subnets and follow a packet through routing and NAT
  - Explain how TCP delivers data reliably and how to troubleshoot a connection layer by layer
  - Explain how DNS, DHCP and Wi-Fi work, and capture and read traffic safely

## Related courses

These cover nearby ground. Link to them instead of teaching what they teach again.

- `web-security-foundations` **Web Security Foundations**: How web applications talk, where they break, and how injection and cross-site scripting are prevented.
  - Lessons: HTTP essentials; The OWASP Top 10; SQL injection; Cross-site scripting (XSS)
- `python-for-security` **Python for Security Automation**: Write small Python scripts that parse logs, extract indicators and query web services.
  - Lessons: Python basics for automation; Parsing logs with Python; Talking to web services
- `operating-systems-essentials` **Operating Systems Essentials**: Processes, memory, file systems and privilege: what the operating system does and why attackers care.
  - Lessons: Processes and memory; File systems; Privilege levels and escalation
- `security-fundamentals` **Security Fundamentals**: The principles, vocabulary and frameworks shared by every security role.
  - Lessons: The CIA triad; Threats, vulnerabilities and risk; Defense in depth and least privilege; Security frameworks
- `linux-command-line` **Linux Command Line for Security**: Navigate, inspect and control a Linux system from the shell, the way analysts and administrators do.
  - Lessons: Navigating the file system; File permissions and ownership; Users, groups and processes; Finding evidence in logs

## Course map

- **The course's problem:** Get data between two machines that have never met, reliably and safely, and find where it breaks when it does not.
- `intro-to-computer-networks` builds-on `osi-model`
- `intro-to-computer-networks` builds-on `network-devices-and-media`
- `osi-model` contrasts-with `tcp-ip-model`
- `tcp-ip-model` builds-on `encapsulation-and-pdus`
- `encapsulation-and-pdus` requires `ethernet-and-mac-addresses`
- `ethernet-and-mac-addresses` requires `switching-and-the-mac-table`
- `switching-and-the-mac-table` builds-on `vlans`
- `tcp-ip-model` requires `ip-addressing`
- `ip-addressing` requires `subnetting`
- `subnetting` requires `routing-basics`
- `routing-basics` builds-on `nat-and-private-addresses`
- `ip-addressing` builds-on `ipv6-essentials`
- `ip-addressing` requires `arp`
- `ethernet-and-mac-addresses` requires `arp`
- `ip-addressing` builds-on `tcp-and-udp-in-depth`
- `routing-basics` requires `icmp-and-troubleshooting`
- `tcp-and-udp-in-depth` requires `application-protocols`
- `application-protocols` builds-on `dns-and-dhcp`
- `ethernet-and-mac-addresses` builds-on `wifi-essentials`
- `icmp-and-troubleshooting` builds-on `packet-capture-basics`

## Lessons

### Chapter 1: How networks are built

#### 1. Introduction to computer networks (`intro-to-computer-networks`)

- **State:** published · **Minutes:** 12 · **Fingerprint:** `1d473fc3b110c461` (send it back as `base.lesson_fingerprint`)
- **Summary:** What a network is, the devices that make one up, and why security starts here.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Name the main network devices and what each one does → **no dedicated section found**
  2. Tell a LAN from a WAN → **no dedicated section found**
  3. Explain what a packet is → **no dedicated section found**
- **Takeaways:** A network is a set of devices that exchange data using shared rules called protocols. · Switches connect devices within a network; routers connect networks to each other. · Data travels in small packets, each carrying addressing information.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `what-a-network-is` | `367ad99a-c0bf-5530-92fa-4e7ec0a13506` | prose | What a network is | A computer network is a group of devices that exchange data by following shared rules. Those rules are called protocols: agreements about how to format, address and deliver data. Your laptop, a web se... | none |
| 2 | `the-devices-in-the-path` | `bb7fd12f-600f-5cbc-80ff-a9ded49c2d8c` | prose | The devices in the path | - Hosts create and consume data: laptops, phones, servers. - Switches connect hosts inside one local network and forward data to the right port using hardware (MAC) addresses. - Routers connect separa... | none |
| 3 | `follow-a-packet` | `9693c554-5fdb-5303-b148-e5c39ac1fc3b` | scene-3d | Follow a packet | A request from the laptop crosses five hops to reach the web server. Select any device to read what it does, or step through the journey. · The whole path · The laptop sends the packet, wrapped in a f... | none |
| 4 | `lans-wans-and-the-internet` | `f31e63d5-0393-5ed2-afba-e2e82583e7ca` | prose | LANs, WANs and the internet | A LAN (local area network) covers one place: a home, an office floor, a lab. A WAN (wide area network) connects LANs across distance. The internet is a vast network of networks, joined by routers run... | none |
| 5 | `why-this-matters-for-security` | `a2bb9fff-31b2-5ee5-9815-88f3858ccb8c` | callout | Why this matters for security | Almost every attack crosses a network. Knowing which device sees which traffic tells you where you can detect it, and where you can stop it. | none |
| 6 | `check-your-understanding` | `d3a489f0-96e3-5717-b8c2-f7d2a2bbe4e0` | knowledge-check | Check your understanding | A laptop sends a file to a printer on the same office network. Which device forwards that traffic? · The switch · The router · The firewall at the internet edge · Traffic between devices on the same l... | none |

- **Block types used:** prose, scene-3d, callout, knowledge-check. **Not used:** steps, code, diagram, media, layer-stack, sequence, flow, tree, topology, comparison, timeline, bitfield, calculator, step-through, script-3d.
- **Architecture on the course map** (pipeline): What sits between my laptop and a server, and what does each piece do?
  - `host` Your laptop → section `the-devices-in-the-path` (`bb7fd12f-600f-5cbc-80ff-a9ded49c2d8c`)
  - `switch` Switch → section `the-devices-in-the-path` (`bb7fd12f-600f-5cbc-80ff-a9ded49c2d8c`) → lesson `switching-and-the-mac-table`
  - `router` Router → section `the-devices-in-the-path` (`bb7fd12f-600f-5cbc-80ff-a9ded49c2d8c`) → lesson `routing-basics`
  - `firewall` Firewall → section `the-devices-in-the-path` (`bb7fd12f-600f-5cbc-80ff-a9ded49c2d8c`)
  - `internet` The internet → section `lans-wans-and-the-internet` (`f31e63d5-0393-5ed2-afba-e2e82583e7ca`)
  - `server` Server → section `follow-a-packet` (`9693c554-5fdb-5303-b148-e5c39ac1fc3b`)

#### 2. Network devices and media (`network-devices-and-media`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `3efc2a73c25b134c` (send it back as `base.lesson_fingerprint`)
- **Summary:** What switches, routers, access points and firewalls each decide, and what travels over copper, fibre and radio.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Tell switches, routers, access points and firewalls apart by what they read → `a-small-office`, `a-home-router-is-several-devices-in-one-box`
  2. Compare copper, fibre and Wi-Fi as media → **no dedicated section found**
  3. Recognise the devices hidden inside a home router → `a-home-router-is-several-devices-in-one-box`
- **Takeaways:** A switch forwards frames by MAC address inside one network; a router forwards packets by IP address between networks. · An access point bridges wireless clients onto the wired network; a firewall enforces rules between zones of different trust. · Copper and fibre need physical access to tap; anyone within radio range can capture Wi-Fi frames, so encryption matters.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `boxes-on-the-path` | `455b0d1e-2d6f-5f4d-8dba-c24c8a5208b0` | prose | Boxes on the path | Every computer, phone or server that sends and receives data is a host. It connects through a network interface (a NIC), wired or wireless, and every interface has its own hardware address. Between ho... | none |
| 2 | `what-each-device-reads` | `a215f469-3c3e-589a-a367-4166202be08b` | comparison | What each device reads | Works at · Layer 2 (data link) · Layer 3 (network) · Layer 2, between radio and cable · Layers 3 and 4, often up to 7 · Decides using · Destination MAC address and its table of which port leads where... | none |
| 3 | `cable-fibre-or-radio` | `94fb5548-93d0-5b84-be95-8439461c7a84` | comparison | Cable, fibre or radio | Signal · Electrical pulses on twisted pairs of wire · Pulses of light in a glass strand · Radio waves in the 2.4, 5 or 6 GHz bands · Typical reach · Up to 100 m per Ethernet run · Hundreds of metres t... | none |
| 4 | `a-small-office` | `59b6751c-32fa-5ece-b9a9-c5bf00a8e369` | topology | A small office | wireless · Office network · Internet · Firewall · Sits between the internet and the office. Unsolicited connections from outside are refused; staff traffic out is allowed and logged. · Router · The of... | none |
| 5 | `a-home-router-is-several-devices-in-one-box` | `0b536bed-7712-55c2-9e86-f8d465438702` | callout | A home router is several devices in one box | The box from your internet provider is a router, a small switch, a Wi-Fi access point, a firewall that translates addresses (NAT), and a DHCP server. In an office these are usually separate devices, w... | none |
| 6 | `hubs-and-why-you-rarely-see-them` | `922b6f67-dfee-51f9-8616-69b9c2296ea7` | prose | Hubs, and why you rarely see them | A hub is a layer 1 device: it repeats every signal out of every port. Every device sees every frame, and only one can talk at a time. Switches replaced hubs because they send each frame only where it... | none |
| 7 | `check-your-understanding` | `27162ce6-48d9-5c99-9d52-fdcfb4b35914` | knowledge-check | Check your understanding | You need to stop guest devices from reaching the office file server, which is on a different network. Which device is built to make that decision? · A firewall (or a router with access lists) between... | none |

- **Block types used:** prose, comparison, topology, callout, knowledge-check. **Not used:** steps, code, diagram, media, layer-stack, sequence, flow, tree, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (matrix): Which box and which medium does which job on a network?
  - `sw-at` Layer 2 (data link) → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`)
  - `sw-uses` Destination MAC and its MAC table → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`) → lesson `switching-and-the-mac-table`
  - `rt-at` Layer 3 (network) → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`)
  - `rt-uses` Destination IP and its routing table → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`) → lesson `routing-basics`
  - `ap-at` Layer 2, between radio and cable → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`)
  - `ap-uses` MAC addresses, for wireless clients → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`) → lesson `wifi-essentials`
  - `fw-at` Layers 3 and 4, often up to 7 → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`)
  - `fw-uses` Rules on addresses, ports and state → section `what-each-device-reads` (`a215f469-3c3e-589a-a367-4166202be08b`)

#### 3. The OSI model (`osi-model`)

- **State:** published · **Minutes:** 18 · **Fingerprint:** `fb8827a5153c6918` (send it back as `base.lesson_fingerprint`)
- **Summary:** The seven-layer model used to describe, troubleshoot and secure network communication.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. List the seven OSI layers in order → `why-a-layered-model`, `the-seven-layers`
  2. Place common protocols and devices on the right layer → **no dedicated section found**
  3. Use the model to reason about where an attack happens → **no dedicated section found**
- **Takeaways:** The OSI model splits communication into seven layers, from physical signals up to applications. · Each layer adds its own header as data moves down the stack (encapsulation). · Security tools are often described by the layer they inspect, such as a layer 7 firewall.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `why-a-layered-model` | `dfaab400-e8fd-5ef0-87fb-5efd5e71214d` | prose | Why a layered model | Networking is complex, so engineers split it into layers. Each layer does one job and relies on the layer below it. The OSI model (Open Systems Interconnection) has seven layers. Real networks mostly... | none |
| 2 | `the-seven-layers` | `1c6a50ea-28ea-5d3e-8916-a44731e938fe` | layer-stack | The seven layers | 7 · Data · Application · SQL injection and cross-site scripting abuse the application itself. · Attack at this layer · HTTP · DNS · SMTP · SSH · Network services used directly by applications. · 6 · D... | none |
| 3 | `encapsulation` | `80e595b1-c6a7-50aa-b925-b588d0592aed` | step-through | Encapsulation | Your browser has an HTTP request to send: "give me the page at /login". At this point it is just data. · HTTP request · Layer · 7 Application · Data unit · Data · Added · Nothing yet · The application... | none |
| 4 | `a-way-to-remember-the-order` | `47f12f92-47ee-538c-ab2e-41901317a72b` | callout | A way to remember the order | From layer 1 up: Physical, Data link, Network, Transport, Session, Presentation, Application. Many people use the phrase "Please Do Not Throw Sausage Pizza Away". | none |
| 5 | `check-your-understanding` | `f8127264-682c-51f7-8376-8a37ce1aac13` | knowledge-check | Check your understanding | An attacker on your Wi-Fi sends fake ARP replies so other devices send their traffic through the attacker's laptop. Which layer is being attacked? · Layer 2, data link · Layer 3, network · Layer 7, ap... | none |

- **Block types used:** prose, layer-stack, step-through, callout, knowledge-check. **Not used:** steps, code, diagram, media, sequence, flow, tree, topology, comparison, timeline, bitfield, calculator, scene-3d, script-3d.
- **Architecture on the course map** (layers): How do you reason about a stack of protocols one layer at a time?
  - `l7` 7 Application → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`) → lesson `application-protocols`
  - `l6` 6 Presentation → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`)
  - `l5` 5 Session → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`)
  - `l4` 4 Transport → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`) → lesson `tcp-and-udp-in-depth`
  - `l3` 3 Network → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`) → lesson `ip-addressing`
  - `l2` 2 Data link → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`) → lesson `ethernet-and-mac-addresses`
  - `l1` 1 Physical → section `the-seven-layers` (`1c6a50ea-28ea-5d3e-8916-a44731e938fe`) → lesson `network-devices-and-media`

#### 4. The TCP/IP model and the handshake (`tcp-ip-model`)

- **State:** published · **Minutes:** 18 · **Fingerprint:** `e54e0b610e02c682` (send it back as `base.lesson_fingerprint`)
- **Summary:** The four-layer model the internet actually runs on, and how a TCP connection begins.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Map the TCP/IP layers to the OSI layers → `four-layers-instead-of-seven`
  2. Explain the difference between TCP and UDP → **no dedicated section found**
  3. Walk through the TCP three-way handshake → **no dedicated section found**
- **Takeaways:** TCP/IP has four layers: link, internet, transport and application. · TCP is connection-oriented and reliable; UDP is connectionless and lighter. · A TCP connection starts with SYN, SYN-ACK, ACK.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `four-layers-instead-of-seven` | `42baa0c8-f798-5847-ae71-bb09638105fc` | layer-stack | Four layers instead of seven | 4 · OSI 5–7 · Application · Most incidents you will investigate start here: phishing links, injection, stolen credentials. · Security view · HTTP · DNS · SSH · TLS · SMTP · OSI layers · 5 Session, 6 P... | none |
| 2 | `tcp-and-udp` | `9e9a4d1f-cb10-5710-b0ce-dbf4820d0d57` | comparison | TCP and UDP | Connection · Set up first with a three-way handshake · None: each datagram stands alone · Delivery · Guaranteed: lost data is resent · Best effort: lost data stays lost · Order · Delivered in order ·... | none |
| 3 | `the-three-way-handshake` | `0513dd42-fdf2-562b-aa9c-d17bb36b8222` | sequence | The three-way handshake | Client · Server · SYN · seq=100 · SYN-SENT · LISTEN · The client asks to open a connection and picks a random initial sequence number, here 100. · SYN-ACK · seq=300 ack=101 · SYN-SENT · SYN-RECEIVED ·... | none |
| 4 | `attackers-use-the-handshake-too` | `8da7eb10-afb2-5e6b-83d4-516d18280555` | callout | Attackers use the handshake too | A SYN flood sends many SYN packets and never completes the handshake, filling the server's table of half-open connections. Port scanners send SYNs to see which ports answer with SYN-ACK. | none |
| 5 | `check-your-understanding` | `cda2e223-d223-581c-af97-e664032affc6` | knowledge-check | Check your understanding | A server receives thousands of SYN packets per second, answers each with SYN-ACK, and never gets the final ACK. What is happening? · A SYN flood filling its half-open connection table · Normal traffic... | none |

- **Block types used:** layer-stack, comparison, sequence, callout, knowledge-check. **Not used:** prose, steps, code, diagram, media, flow, tree, topology, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (layers): How do the protocols the internet actually runs map to the seven-layer theory?
  - `app` Application (OSI 5 to 7) → section `four-layers-instead-of-seven` (`42baa0c8-f798-5847-ae71-bb09638105fc`) → lesson `application-protocols`
  - `transport` Transport (OSI 4) → section `four-layers-instead-of-seven` (`42baa0c8-f798-5847-ae71-bb09638105fc`) → lesson `tcp-and-udp-in-depth`
  - `internet` Internet (OSI 3) → section `four-layers-instead-of-seven` (`42baa0c8-f798-5847-ae71-bb09638105fc`) → lesson `routing-basics`
  - `link` Link (OSI 1 and 2) → section `four-layers-instead-of-seven` (`42baa0c8-f798-5847-ae71-bb09638105fc`) → lesson `ethernet-and-mac-addresses`

#### 5. Encapsulation and PDUs (`encapsulation-and-pdus`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `387443a35601e49d` (send it back as `base.lesson_fingerprint`)
- **Summary:** What each layer adds to your data, what the pieces are called, and why packet size limits matter.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Name the protocol data unit at each layer → `every-layer-adds-its-own-header`, `what-each-header-carries`
  2. Describe what each header carries → `every-layer-adds-its-own-header`, `what-each-header-carries`, `size-limits-the-mtu`, `headers-inside-headers`
  3. Explain the MTU and what happens when a packet is too large → **no dedicated section found**
- **Takeaways:** Each layer wraps the data from the layer above in its own header: data, segment, packet, frame. · On the way up, each layer reads and removes only its own header, then hands the rest upward. · Ethernet carries at most 1500 bytes per frame (its MTU); larger packets are fragmented or dropped.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `every-layer-adds-its-own-header` | `21a5733f-495c-5ec0-ba48-b819640d341e` | prose | Every layer adds its own header | The OSI lesson showed data being wrapped on the way down. Here we look at what each wrapper actually holds. The unit of data at each layer has a name, its protocol data unit (PDU), and you will meet t... | none |
| 2 | `what-each-header-carries` | `19f74345-5bf3-5752-a5e7-f6b9c0ba4771` | comparison | What each header carries | Application · Data · Whatever the protocol needs: an HTTP method and path, a DNS question · Varies · Transport · Segment (TCP) or datagram (UDP) · Source and destination ports; for TCP also sequence a... | none |
| 3 | `unwrapping-at-the-server` | `0a501dae-06d9-50cf-b95e-99a36ead067a` | step-through | Unwrapping at the server | The network card checks the trailer's checksum and the destination MAC address. A corrupted frame, or one for another machine, is dropped here and nothing above ever sees it. · Ethernet · IP · TCP · H... | none |
| 4 | `size-limits-the-mtu` | `48f7e481-aa87-5d2c-9754-239fcb7b3d3c` | prose | Size limits: the MTU | Every link has a maximum transmission unit (MTU): the largest packet one frame can carry. On Ethernet it is 1500 bytes. Take away a 20-byte IPv4 header and a 20-byte TCP header and 1460 bytes of your... | none |
| 5 | `see-the-mtu-on-linux` | `b7db7ede-a770-5748-a607-006256c2bdec` | code | See the MTU on Linux | ip link show dev eth0 # look for "mtu 1500" tracepath 203.0.113.10 # reports the path MTU hop by hop | none |
| 6 | `your-interface-may-have-another-name` | `ab2fc751-2ed8-5019-940b-eaf4ec0fd6a4` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so yours is probably not eth0: a wired card is often enp0s3 or eno1, a wireless one wlp2s0. Run ip -br link to list your interfaces an... | none |
| 7 | `headers-inside-headers` | `c64fc20a-8446-5f90-abed-2b9b50909aa1` | callout | Headers inside headers | A tunnel puts a whole packet inside another packet: VPNs, GRE, and DNS tunnelling used to smuggle data out. A firewall that reads only the outer header sees only the tunnel, which is why tunnels are e... | none |
| 8 | `check-your-understanding` | `50732666-cdd2-5c21-aec6-779900c1256d` | knowledge-check | Check your understanding | A tool shows you a unit with source and destination ports, sequence numbers and the SYN flag. Which PDU are you looking at? · A TCP segment · An Ethernet frame · An IP packet · Ports, sequence numbers... | none |

- **Block types used:** prose, comparison, step-through, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, sequence, flow, tree, topology, timeline, bitfield, calculator, scene-3d, script-3d.
- **Architecture on the course map** (stack): How does each layer wrap the data handed down from the layer above?
  - `data` Application data → section `what-each-header-carries` (`19f74345-5bf3-5752-a5e7-f6b9c0ba4771`)
  - `segment` + TCP header: a segment → section `what-each-header-carries` (`19f74345-5bf3-5752-a5e7-f6b9c0ba4771`) → lesson `tcp-and-udp-in-depth`
  - `packet` + IP header: a packet → section `what-each-header-carries` (`19f74345-5bf3-5752-a5e7-f6b9c0ba4771`) → lesson `ip-addressing`
  - `frame` + Ethernet header: a frame → section `unwrapping-at-the-server` (`0a501dae-06d9-50cf-b95e-99a36ead067a`) → lesson `ethernet-and-mac-addresses`

### Chapter 2: The local network

#### 1. Ethernet frames and MAC addresses (`ethernet-and-mac-addresses`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `1173c6730cbefc19` (send it back as `base.lesson_fingerprint`)
- **Summary:** How a frame finds the right machine on a shared link, and why a MAC address proves very little.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Read the fields of an Ethernet frame → `a-frame-on-the-wire`, `who-processes-a-frame`
  2. Tell unicast, multicast and broadcast frames apart → **no dedicated section found**
  3. Explain why MAC addresses are a weak identity → `a-mac-address-is-not-an-identity`
- **Takeaways:** A MAC address is 48 bits: the first 24 usually identify the manufacturer, the rest the interface. · The destination MAC decides who processes a frame; ff:ff:ff:ff:ff:ff reaches everyone on the network. · MAC addresses can be changed in software and are randomised by many phones, so they do not prove who someone is.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `a-frame-on-the-wire` | `7494f0e8-82cc-55a0-9186-fb2d28efbe24` | prose | A frame on the wire | Ethernet (and Wi-Fi, which borrows its addressing) moves frames between interfaces on the same local network. A frame starts with three header fields: the destination MAC address, the source MAC addre... | none |
| 2 | `inside-a-mac-address` | `b7a8fec8-9d3d-55fa-9509-9324ce33b341` | bitfield | Inside a MAC address | 00:00:5e:00:53:01 · 000000000000000001011110 · OUI (vendor) · 000000000101001100000001 · Interface · First octet, 0x00 · 000000 · Rest of the octet · 0 · U/L 0: universal · 0 · I/G 0: individual | none |
| 3 | `two-bits-that-matter` | `223c0aaf-2849-5847-b072-c5325d219f1e` | prose | Two bits that matter | The example 00:00:5e:00:53:01 is from a block reserved for documentation. Its first 24 bits, the OUI, are registered to an organisation; the last 24 are chosen by it. Two bits of the first octet carry... | none |
| 4 | `who-processes-a-frame` | `4bca17e3-3ea8-5cad-9515-c4c5c790937c` | comparison | Who processes a frame | Destination MAC · One interface, with the I/G bit 0 · A group, with the I/G bit 1, such as 01:00:5e:… for IPv4 multicast · ff:ff:ff:ff:ff:ff · Who reads it · Only the interface with that address · Int... | none |
| 5 | `your-interfaces-and-their-mac-addresses` | `f00d3f5b-cdbf-5373-b8b8-4a38fea67847` | code | Your interfaces and their MAC addresses | ip -br link # name, state and MAC address of every interface ip link show dev eth0 # "link/ether" is the MAC address | none |
| 6 | `your-interface-may-have-another-name` | `a9cc437f-b174-5110-b7e3-63a3b89f7239` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so yours is probably not eth0: a wired card is often enp0s3 or eno1, a wireless one wlp2s0. Run ip -br link to list your interfaces an... | none |
| 7 | `a-mac-address-is-not-an-identity` | `9249be31-479f-5e3c-b3ba-ec687a262729` | callout | A MAC address is not an identity | Any interface's address can be changed with one command (ip link set dev eth0 address …), and phones use a different random address on each network. Allowing devices onto a network by MAC address alon... | none |
| 8 | `check-your-understanding` | `b13abccd-c15a-5463-9765-68f8b86cadc0` | knowledge-check | Check your understanding | A frame has the destination ff:ff:ff:ff:ff:ff. Which devices on the local network process it? · All of them · Only the default gateway · None: it is an invalid address · All ones is the broadcast addr... | none |

- **Block types used:** prose, bitfield, comparison, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, sequence, flow, tree, topology, timeline, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (no shape): How does a frame reach the right machine on a link it shares with others?

#### 2. Switching and the MAC table (`switching-and-the-mac-table`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `c66b17627212b4c4` (send it back as `base.lesson_fingerprint`)
- **Summary:** How a switch learns where every device is, so each frame goes only where it needs to.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Explain how a switch learns, forwards, floods and filters → `a-switch-learns-as-it-goes`, `what-a-switch-does-with-each-frame`, `filling-the-mac-table`
  2. Follow a MAC table as it fills → **no dedicated section found**
  3. Describe MAC flooding and how port security stops it → `mac-flooding`
- **Takeaways:** A switch learns from the source address of every frame which port leads to which device. · Known destinations get one port; unknown and broadcast destinations are flooded to every other port. · Filling the table with fake addresses makes some switches flood everything, so port security limits addresses per port.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `a-switch-learns-as-it-goes` | `54fe766b-cf96-5673-abc4-1dba92178cfb` | prose | A switch learns as it goes | A new switch knows nothing about the devices plugged into it. It learns by reading the source MAC address of every frame that arrives and writing down the port it came in on. That table, the MAC addre... | none |
| 2 | `what-a-switch-does-with-each-frame` | `ecaf7b11-e3ed-5fcb-90f4-baa7d8536175` | flow | What a switch does with each frame | Yes · No · Yes · No · Frame arrives on a port · Learn: source MAC is on this port · The switch records or refreshes the entry for the source address and resets its aging timer. · Destination in the ta... | none |
| 3 | `filling-the-mac-table` | `d291d090-2553-5eb1-a049-35a6bee9a68c` | step-through | Filling the MAC table | The table is empty. The switch learns that A is on port 1, does not know B, and floods the frame to ports 2 and 3. C ignores it; B accepts it. · from A, port 1 · to B: unknown · MAC table · A → 1 · Se... | none |
| 4 | `hub-or-switch` | `e0358582-7ce8-537b-860d-3bfef2f0f640` | comparison | Hub or switch | Sends a frame to · Every port · The destination's port, once learned · Who can capture traffic · Every device on the hub · Only the two ends, unless a port is set to mirror traffic · Talking at once ·... | none |
| 5 | `a-linux-bridge-is-a-software-switch` | `c9bd40a2-c078-527f-b547-51ad196485ec` | code | A Linux bridge is a software switch | bridge fdb show br br0 # the forwarding database: MAC address and port | none |
| 6 | `mac-flooding` | `b03925c4-b7ae-523c-9fb5-81409e18f394` | callout | MAC flooding | An attacker can send thousands of frames from made-up source addresses until the table is full. Some switches then flood every frame, so the attacker's port receives traffic meant for others. Port sec... | none |
| 7 | `check-your-understanding` | `93f25e2f-544c-5ec0-91a8-5953e971bab0` | knowledge-check | Check your understanding | A switch receives a frame for a MAC address that is not in its table. What does it do? · Sends it out of every port except the one it came in on · Drops it · Sends it back to the sender with an error... | none |

- **Block types used:** prose, flow, step-through, comparison, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, sequence, tree, topology, timeline, bitfield, calculator, scene-3d, script-3d.
- **Architecture on the course map** (hub): How can a switch send each frame only where it needs to go?
  - `table` The MAC table → section `filling-the-mac-table` (`d291d090-2553-5eb1-a049-35a6bee9a68c`)
  - `learn` Learn the source → section `what-a-switch-does-with-each-frame` (`ecaf7b11-e3ed-5fcb-90f4-baa7d8536175`)
  - `forward` Forward → section `what-a-switch-does-with-each-frame` (`ecaf7b11-e3ed-5fcb-90f4-baa7d8536175`)
  - `flood` Flood → section `what-a-switch-does-with-each-frame` (`ecaf7b11-e3ed-5fcb-90f4-baa7d8536175`)
  - `filter` Filter → section `what-a-switch-does-with-each-frame` (`ecaf7b11-e3ed-5fcb-90f4-baa7d8536175`)
  - `age` Age out → section `filling-the-mac-table` (`d291d090-2553-5eb1-a049-35a6bee9a68c`)
  - `attack` MAC flooding → section `mac-flooding` (`b03925c4-b7ae-523c-9fb5-81409e18f394`)

#### 3. VLANs (`vlans`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `3d3660e19ffa58bf` (send it back as `base.lesson_fingerprint`)
- **Summary:** Splitting one physical switch into separate networks, and the mistakes that let traffic hop between them.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Explain what a VLAN separates → `one-switch-several-networks`
  2. Tell access ports and trunk ports apart → `access-ports-and-trunk-ports`, `two-vlans-on-one-switch`, `vlan-hopping`
  3. Describe VLAN hopping and how to prevent it → `vlan-hopping`
- **Takeaways:** A VLAN is a separate broadcast domain on shared switches, numbered 1 to 4094. · Access ports carry one VLAN untagged; trunk ports carry many, each frame tagged with an 802.1Q tag. · Traffic between VLANs must pass a router or firewall, which is where the rules go.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `one-switch-several-networks` | `8dbd87a2-6c98-57e3-afd7-6ec63459ca3f` | prose | One switch, several networks | Buying a separate switch for staff, servers and guests is expensive. A virtual LAN lets one switch behave like several: every port belongs to a VLAN, and frames never cross from one VLAN to another in... | none |
| 2 | `access-ports-and-trunk-ports` | `16a9617c-55c1-53f7-90a2-81fe36bde251` | comparison | Access ports and trunk ports | Carries · One VLAN · Many VLANs · Frames on the cable · Untagged: the device never knows its VLAN · Tagged with an 802.1Q tag, except the native VLAN · Connects · A host: laptop, printer, server · Ano... | none |
| 3 | `the-8021q-tag-for-vlan-20` | `14461363-626d-5615-8f59-4a11caf6a9ef` | bitfield | The 802.1Q tag for VLAN 20 | 4-byte tag · 1000000100000000 · TPID 0x8100 · 000 · Priority · 0 · DEI · 000000010100 · VLAN ID 20 | none |
| 4 | `two-vlans-on-one-switch` | `263b8e26-54a6-5fe4-8f92-3ee312634c6b` | topology | Two VLANs on one switch | trunk: 10, 20 · access 10 · access 20 · VLAN 10 staff 10.0.10.0/24 · VLAN 20 servers 10.0.20.0/24 · Router / firewall · One trunk carries both VLANs to it ("router on a stick"). It is the gateway of e... | none |
| 5 | `a-vlan-interface-on-linux` | `a925840a-9966-5b05-a291-5c9feba6dd04` | code | A VLAN interface on Linux | sudo ip link add link eth0 name eth0.20 type vlan id 20 sudo ip addr add 10.0.20.10/24 dev eth0.20 sudo ip link set dev eth0.20 up | none |
| 6 | `your-interface-may-have-another-name` | `9be761ac-a537-540e-ad2f-6a5af884aea8` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so yours is probably not eth0: a wired card is often enp0s3 or eno1, a wireless one wlp2s0. Run ip -br link to list your interfaces an... | none |
| 7 | `vlan-hopping` | `05bafec4-0a67-5e53-9e71-9c20e19a0c8a` | callout | VLAN hopping | Two classic tricks get frames into a VLAN an attacker is not in. Switch spoofing: the attacker's port negotiates itself into a trunk. Double tagging: a frame with two tags loses the outer one on a tru... | none |
| 8 | `check-your-understanding` | `7bfe7ff3-b778-5253-ae46-51271520001e` | knowledge-check | Check your understanding | A staff laptop in VLAN 10 needs to reach a server in VLAN 20 on the same switch. What has to happen? · The traffic goes through a router or firewall, which routes between the two subnets · Nothing: th... | none |

- **Block types used:** prose, comparison, bitfield, topology, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, sequence, flow, tree, timeline, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (matrix): How can one physical switch act as several separate networks?
  - `a-carries` One VLAN → section `access-ports-and-trunk-ports` (`16a9617c-55c1-53f7-90a2-81fe36bde251`)
  - `t-carries` Many VLANs → section `access-ports-and-trunk-ports` (`16a9617c-55c1-53f7-90a2-81fe36bde251`)
  - `a-frames` Untagged: the host never knows → section `access-ports-and-trunk-ports` (`16a9617c-55c1-53f7-90a2-81fe36bde251`)
  - `t-frames` 802.1Q tagged, except the native VLAN → section `the-8021q-tag-for-vlan-20` (`14461363-626d-5615-8f59-4a11caf6a9ef`)
  - `a-connects` A host: laptop, printer, server → section `access-ports-and-trunk-ports` (`16a9617c-55c1-53f7-90a2-81fe36bde251`)
  - `t-connects` A switch, a router or a VM host → section `access-ports-and-trunk-ports` (`16a9617c-55c1-53f7-90a2-81fe36bde251`) → lesson `routing-basics`
  - `a-wrong` Wrong VLAN, wrong network → section `access-ports-and-trunk-ports` (`16a9617c-55c1-53f7-90a2-81fe36bde251`)
  - `t-wrong` All VLANs allowed: hopping → section `vlan-hopping` (`05bafec4-0a67-5e53-9e71-9c20e19a0c8a`)

### Chapter 3: Addressing and routing

#### 1. IP addressing (`ip-addressing`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `853c3019151cf072` (send it back as `base.lesson_fingerprint`)
- **Summary:** How IPv4 addresses are written, which ranges are private, and what IPv6 changes.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Read an IPv4 address and its binary form → `anatomy-of-an-ipv4-address`
  2. Recognise private and special address ranges → `anatomy-of-an-ipv4-address`, `reading-logs`
  3. Explain why IPv6 exists → `ipv6-in-one-paragraph`
- **Takeaways:** An IPv4 address is 32 bits, written as four decimal octets. · 10.0.0.0/8, 172.16.0.0/12 and 192.168.0.0/16 are private ranges that are not routed on the internet. · IPv6 uses 128-bit addresses written in hexadecimal.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `anatomy-of-an-ipv4-address` | `6cf89f6d-5bb3-597d-aac9-97285e757910` | prose | Anatomy of an IPv4 address | An IPv4 address is a 32-bit number. We write it as four octets of 8 bits each, in decimal, separated by dots: 192.168.1.20. Each octet ranges from 0 to 255, because 8 bits can hold 256 values. Subnett... | none |
| 2 | `192168120-in-binary` | `1a7e5ea5-ea87-5773-a03e-b3c92379751d` | bitfield | 192.168.1.20 in binary | 192.168.1.20 · 11000000 · Octet 1 · 10101000 · Octet 2 · 00000001 · Octet 3 · 00010100 · Octet 4 | none |
| 3 | `ranges-worth-recognising` | `72d1c57a-b34a-5cbc-a005-007ed8b90b5f` | comparison | Ranges worth recognising | Ranges · 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 · 127.0.0.0/8 · 169.254.0.0/16 · What it means · Used inside organisations and translated (NAT) at the edge; never routed on the internet · Traffic t... | none |
| 4 | `reading-logs` | `01b5b539-2620-5ff2-9d0f-b2883a05127a` | callout | Reading logs | A login from a private address came from inside the network or through a VPN. A login from a public address came from the internet. That single fact changes how you investigate it. | none |
| 5 | `ipv6-in-one-paragraph` | `5439940f-576d-5c17-be5b-30c34e3d764f` | prose | IPv6 in one paragraph | IPv4 has about 4.3 billion addresses, which ran out. IPv6 uses 128 bits, written as eight groups of hexadecimal such as 2001:db8::1 (runs of zeros collapse to ::). Many networks run both, which means... | none |
| 6 | `check-your-understanding` | `cbf6dc5a-e63b-5660-bf53-9b7613fb5245` | knowledge-check | Check your understanding | A failed login in your logs came from 172.20.4.9. What does that tell you? · It came from inside the network or over a VPN · It came directly from the internet · It came from the server itself · 172.2... | none |

- **Block types used:** prose, bitfield, comparison, callout, knowledge-check. **Not used:** steps, code, diagram, media, layer-stack, sequence, flow, tree, topology, timeline, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (matrix): How do you give every machine an address that routers can reach?
  - `priv-range` 10/8, 172.16/12, 192.168/16 → section `ranges-worth-recognising` (`72d1c57a-b34a-5cbc-a005-007ed8b90b5f`) → lesson `nat-and-private-addresses`
  - `lo-range` 127.0.0.0/8 → section `ranges-worth-recognising` (`72d1c57a-b34a-5cbc-a005-007ed8b90b5f`)
  - `ll-range` 169.254.0.0/16 → section `ranges-worth-recognising` (`72d1c57a-b34a-5cbc-a005-007ed8b90b5f`)
  - `priv-log` Inside the network, or a VPN → section `reading-logs` (`01b5b539-2620-5ff2-9d0f-b2883a05127a`)
  - `lo-log` A program on the same machine → section `reading-logs` (`01b5b539-2620-5ff2-9d0f-b2883a05127a`)
  - `ll-log` No DHCP server answered → section `reading-logs` (`01b5b539-2620-5ff2-9d0f-b2883a05127a`) → lesson `dns-and-dhcp`

#### 2. ARP: from IP address to MAC address (`arp`)

- **State:** published · **Minutes:** 12 · **Fingerprint:** `be9c08fbd58aca8d` (send it back as `base.lesson_fingerprint`)
- **Summary:** How a machine finds the hardware address behind an IP address on its own network, and how attackers lie about it.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Explain why IP needs ARP on a local network → **no dedicated section found**
  2. Follow an ARP request and reply → **no dedicated section found**
  3. Describe ARP spoofing and its defences → `arp-spoofing`
- **Takeaways:** To send a packet on the local network, a host needs the next hop's MAC address; ARP asks for it by broadcast. · For destinations outside the subnet, the host ARPs for its default gateway, never for the remote host. · ARP has no authentication, so a forged reply can put an attacker between two machines.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `two-addresses-for-one-trip` | `06f9677b-db02-503e-9c26-761593e131e4` | prose | Two addresses for one trip | An IP packet names its final destination, but a frame can only reach a device on the same link, by MAC address. So before a host can send a packet it needs the MAC address of the next hop: - if the de... | none |
| 2 | `who-has-19216811` | `b39db423-dd1d-59eb-a4ad-bedd4bd4028d` | sequence | Who has 192.168.1.1? | Laptop .20 · Other hosts · Gateway .1 · ARP request · to ff:ff:ff:ff:ff:ff · waiting · The laptop wants to reach the internet, so it needs its gateway's MAC address. It broadcasts: who has 192.168.1.1... | none |
| 3 | `a-cycle-not-a-one-off` | `e4c3cc30-b7d4-5aaa-b3cb-458c434b60a5` | prose | A cycle, not a one-off | Cache entries do not last. On Linux an entry is REACHABLE while traffic confirms it, turns STALE after about half a minute without confirmation, and is checked again (DELAY, then PROBE) the next time... | none |
| 4 | `the-neighbour-table-on-linux` | `9e127b3f-d8b4-5495-b5d5-8a49289b1cc9` | code | The neighbour table on Linux | ip neigh show # IP, interface, MAC and state of each entry ip neigh show dev eth0 # only one interface sudo ip neigh flush dev eth0 # forget them all; they are relearned on use | none |
| 5 | `your-interface-may-have-another-name` | `9cc97526-77d4-5d06-b42f-528579ee4d10` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so yours is probably not eth0: a wired card is often enp0s3 or eno1, a wireless one wlp2s0. Run ip -br link to list your interfaces an... | none |
| 6 | `arp-spoofing` | `82ee5729-f4b9-53bc-9675-b72fd3888983` | callout | ARP spoofing | ARP believes any reply, even one nobody asked for. An attacker on the same network can keep telling the victim "the gateway is at my MAC address" and telling the gateway the same about the victim, the... | none |
| 7 | `ipv6-does-it-differently` | `6c767970-f107-5e45-a5ac-26cefba897c5` | prose | IPv6 does it differently | IPv6 has no ARP. Neighbor Discovery does the same job with ICMPv6 messages: a neighbor solicitation sent to a multicast address (the target's solicited-node address) instead of a broadcast, answered b... | none |
| 8 | `check-your-understanding` | `9e7323c2-fded-5367-92aa-0e4afd7d9a40` | knowledge-check | Check your understanding | Your laptop, 192.168.1.20/24, sends a packet to 203.0.113.10. Whose MAC address does it ask ARP for? · Its default gateway's · 203.0.113.10's · The DNS server's · 203.0.113.10 is outside 192.168.1.0/2... | none |

- **Block types used:** prose, sequence, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, flow, tree, topology, comparison, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (cycle): How does a machine find the MAC address behind an IP address on its own network?
  - `ask` Broadcast: who has 192.168.1.1? → section `who-has-19216811` (`b39db423-dd1d-59eb-a4ad-bedd4bd4028d`)
  - `reply` The owner replies, unicast → section `who-has-19216811` (`b39db423-dd1d-59eb-a4ad-bedd4bd4028d`)
  - `cache` Store it in the neighbour table → section `the-neighbour-table-on-linux` (`9e127b3f-d8b4-5495-b5d5-8a49289b1cc9`)
  - `use` Send frames to that MAC → section `a-cycle-not-a-one-off` (`e4c3cc30-b7d4-5aaa-b3cb-458c434b60a5`)
  - `stale` The entry goes stale: ask again → section `a-cycle-not-a-one-off` (`e4c3cc30-b7d4-5aaa-b3cb-458c434b60a5`)

#### 3. Subnetting (`subnetting`)

- **State:** published · **Minutes:** 25 · **Fingerprint:** `11721c792d75d5d2` (send it back as `base.lesson_fingerprint`)
- **Summary:** Splitting an address into network and host parts with masks and CIDR notation.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Convert between subnet masks and CIDR prefixes → `network-part-and-host-part`
  2. Calculate how many hosts fit in a subnet → `network-part-and-host-part`, `working-out-a-26`
  3. Find the network and broadcast address of a subnet → `network-part-and-host-part`, `a-26-bit-by-bit`, `working-out-a-26`
- **Takeaways:** The prefix length (/24) says how many leading bits identify the network. · A subnet with h host bits holds 2^h - 2 usable hosts. · Subnets let you separate systems so firewalls can control traffic between them.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `network-part-and-host-part` | `84b14c15-80d6-5dc4-add3-7cf305aca7f5` | prose | Network part and host part | A subnet mask marks which bits of an address belong to the network. 255.255.255.0 is 24 ones followed by 8 zeros, so the first 24 bits are the network and the last 8 identify hosts. CIDR notation writ... | none |
| 2 | `a-26-bit-by-bit` | `a1fdc1ec-f634-54ac-9c73-196985e5c45e` | bitfield | A /26 bit by bit | Address 192.168.1.70 · 11000000101010000000000101 · Network (26 bits) · 000110 · Host (6 bits) · Mask /26 = 255.255.255.192 · 11111111111111111111111111 · Ones: network · 000000 · Zeros: host · Networ... | none |
| 3 | `working-out-a-26` | `69669b3b-af34-565a-94a3-3452e6df05f7` | steps | Working out a /26 | A /26 leaves 32 - 26 = 6 host bits. · 6 host bits give 2^6 = 64 addresses per subnet. · Two are reserved: the network address and the broadcast address, so 62 hosts are usable. · Subnets of 64 start a... | none |
| 4 | `try-other-subnets` | `ab9c9d83-9881-5e7d-b95f-d3458c827b03` | calculator | Try other subnets | cidr · 192.168.1.70/26 | none |
| 5 | `check-your-work-with-python` | `40615706-97c6-585d-8e46-6ad58e548c9b` | code | Check your work with Python | import ipaddress net = ipaddress.ip_network("192.168.1.70/26", strict=False) print(net) # 192.168.1.64/26 print(net.broadcast_address) # 192.168.1.127 print(net.num_addresses - 2) # 62 usable hosts | none |
| 6 | `segmentation-is-a-security-control` | `3941f466-0165-585f-83e5-b5e6ee2e5a82` | callout | Segmentation is a security control | Putting servers, staff laptops and guest Wi-Fi in different subnets means an attacker who compromises one device cannot reach everything else without passing a router or firewall. | none |
| 7 | `segmentation-in-practice` | `35f67d80-e38e-5df0-991f-80882767df20` | topology | Segmentation in practice | wireless · Staff 10.0.10.0/24 · Servers 10.0.20.0/24 · Guest Wi-Fi 10.0.30.0/24 · Internet · Firewall · The gateway (.1) of each subnet. It routes between the three subnets and the internet, and appli... | none |
| 8 | `check-your-understanding` | `7e4e681b-ef91-53b7-a386-9ed5476dc976` | knowledge-check | Check your understanding | How many usable host addresses does a /28 subnet have? · 14 · 16 · 28 · 30 · 32 - 28 = 4 host bits, so 2^4 = 16 addresses. Take away the network and broadcast addresses and 14 are usable. · Which netw... | none |

- **Block types used:** prose, bitfield, steps, calculator, code, callout, topology, knowledge-check. **Not used:** diagram, media, layer-stack, sequence, flow, tree, comparison, timeline, step-through, scene-3d, script-3d.
- **Architecture on the course map** (tree): How do you split one address block into networks of the right size?
  - `root` 192.168.1.0/24 → section `network-part-and-host-part` (`84b14c15-80d6-5dc4-add3-7cf305aca7f5`)
  - `a` 192.168.1.0/25 → section `a-26-bit-by-bit` (`a1fdc1ec-f634-54ac-9c73-196985e5c45e`)
  - `b` 192.168.1.128/25 → section `a-26-bit-by-bit` (`a1fdc1ec-f634-54ac-9c73-196985e5c45e`)
  - `a1` 192.168.1.0/26 → section `working-out-a-26` (`69669b3b-af34-565a-94a3-3452e6df05f7`)
  - `a2` 192.168.1.64/26 → section `working-out-a-26` (`69669b3b-af34-565a-94a3-3452e6df05f7`)
  - `b1` 192.168.1.128/26 → section `working-out-a-26` (`69669b3b-af34-565a-94a3-3452e6df05f7`)
  - `b2` 192.168.1.192/26 → section `segmentation-is-a-security-control` (`3941f466-0165-585f-83e5-b5e6ee2e5a82`) → lesson `routing-basics`

#### 4. IPv6 essentials (`ipv6-essentials`)

- **State:** published · **Minutes:** 18 · **Fingerprint:** `fd301a71640d2ae1` (send it back as `base.lesson_fingerprint`)
- **Summary:** Reading and shortening IPv6 addresses, the address types you will meet, and what changes from IPv4.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Write an IPv6 address in its shortest correct form → `shortening-an-address`
  2. Recognise global, link-local and unique local addresses → `why-ipv6-exists`, `addresses-to-recognise`
  3. Compare IPv6 with IPv4 where it matters for security → **no dedicated section found**
- **Takeaways:** An IPv6 address is 128 bits: eight groups of four hexadecimal digits, with leading zeros and one run of zero groups dropped. · Every interface has a link-local fe80:: address; global addresses usually start with 2 or 3. · Hosts enable IPv6 by default, so firewall rules and monitoring must cover it as well as IPv4.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `why-ipv6-exists` | `caefe411-9a56-549f-bae2-c8e0030fd134` | prose | Why IPv6 exists | IPv4's 32 bits give about 4.3 billion addresses. The global pool ran out in 2011, and the internet kept going on NAT, which hides many private addresses behind one public one. IPv6 fixes the shortage... | none |
| 2 | `shortening-an-address` | `b91780ef-11ec-5810-aa27-aca2793e5189` | steps | Shortening an address | Start from the full form: eight groups of four hex digits, such as 2001:0db8:0000:0000:0000:0000:0000:0001. · Drop leading zeros in every group: 2001:db8:0:0:0:0:0:1. · Replace one run of consecutive... | none |
| 3 | `2001db8011-split-in-half` | `1ce446ee-23c7-5ad1-8e80-4d21613d9735` | bitfield | 2001:db8:0:1::1 split in half | Network prefix (/64) · 0010000000000001 · 2001 · 0000110110111000 · 0db8 · 0000000000000000 · 0000 · 0000000000000001 · 0001 · Interface ID · 0000000000000000 · 0000 · 0000000000000000 · 0000 · 000000... | none |
| 4 | `ipv4-and-ipv6` | `323e9def-9aa7-5e09-8908-dbe924e42d64` | comparison | IPv4 and IPv6 | Address size · 32 bits, about 4.3 billion addresses · 128 bits · Written as · Dotted decimal: 192.0.2.10 · Hex groups with ::: 2001:db8::10 · Typical subnet · Any size; /24 is common · /64 for almost... | none |
| 5 | `addresses-to-recognise` | `a6dfbe5f-0832-56a1-96b1-67e7a1155292` | comparison | Addresses to recognise | Range · 2000::/3 (starts with 2 or 3) · fe80::/10 · fc00::/7, in practice fd00::/8 · Reachable from · Anywhere, unless a firewall says otherwise · Only the same link; never routed · Inside the organis... | none |
| 6 | `ipv6-on-your-machine` | `a68a3326-15c5-55e5-98b0-7ba0d7406a0c` | code | IPv6 on your machine | ip -6 addr show # every interface has an fe80:: link-local address ip -6 route # IPv6 routes, including the default ping -6 -c 2 ::1 # loopback, like 127.0.0.1 | none |
| 7 | `the-forgotten-protocol` | `8a54e062-904d-5682-8684-6926f3361781` | callout | The forgotten protocol | IPv6 is on by default in every current operating system. A firewall policy written only for IPv4 can leave IPv6 wide open, and a rogue device sending router advertisements can make itself the gateway... | none |
| 8 | `check-your-understanding` | `9215dc61-aa53-51a7-b6f5-5e0ce6fe0984` | knowledge-check | Check your understanding | What is the shortest correct form of 2001:0db8:0000:0000:0000:0000:0000:0010? · 2001:db8::10 · 2001:db8::1 · 2001:db8:::10 · Leading zeros go (0db8 becomes db8, 0010 becomes 10), and the run of zero g... | none |

- **Block types used:** prose, steps, bitfield, comparison, code, callout, knowledge-check. **Not used:** diagram, media, layer-stack, sequence, flow, tree, topology, timeline, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (matrix): How do you address a network far too large for IPv4?
  - `g-range` 2000::/3 → section `addresses-to-recognise` (`a6dfbe5f-0832-56a1-96b1-67e7a1155292`)
  - `ll-range` fe80::/10 → section `addresses-to-recognise` (`a6dfbe5f-0832-56a1-96b1-67e7a1155292`) → lesson `arp`
  - `ul-range` fc00::/7, in practice fd00::/8 → section `addresses-to-recognise` (`a6dfbe5f-0832-56a1-96b1-67e7a1155292`)
  - `g-reach` Anywhere, unless filtered → section `addresses-to-recognise` (`a6dfbe5f-0832-56a1-96b1-67e7a1155292`)
  - `ll-reach` Only the same link → section `addresses-to-recognise` (`a6dfbe5f-0832-56a1-96b1-67e7a1155292`)
  - `ul-reach` Inside the organisation → section `addresses-to-recognise` (`a6dfbe5f-0832-56a1-96b1-67e7a1155292`)

#### 5. Routing basics (`routing-basics`)

- **State:** published · **Minutes:** 20 · **Fingerprint:** `b319c8dc12c4c8b2` (send it back as `base.lesson_fingerprint`)
- **Summary:** How routers choose the next hop for every packet, and how routes are learned or forged.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Read a routing table and find the route a packet will use → `hop-by-hop`, `longest-prefix-match`
  2. Explain longest prefix match and the default route → `longest-prefix-match`, `many-routers-many-paths`, `routes-can-lie`
  3. Compare static and dynamic routing and their risks → `static-and-dynamic-routing`
- **Takeaways:** Each router only decides the next hop; the packet's TTL drops by one at every router. · The most specific matching route wins; 0.0.0.0/0, the default route, matches anything left. · Dynamic protocols adapt to failures, but a false announcement can redirect traffic across the internet.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `hop-by-hop` | `40c5919d-5413-5307-ab1e-89d8e76bfad6` | prose | Hop by hop | No router knows the whole path to a destination. Each one looks at the packet's destination IP address, picks the next hop from its routing table, and forgets about it. The next router does the same.... | none |
| 2 | `your-routing-table` | `a6f16ea6-4a77-5465-a626-143c5871d248` | code | Your routing table | $ ip route default via 192.168.1.1 dev wlan0 proto dhcp metric 600 192.168.1.0/24 dev wlan0 proto kernel scope link src 192.168.1.20 metric 600 $ ip route get 203.0.113.10 # which route would this des... | none |
| 3 | `your-interface-may-have-another-name` | `8b1c496f-9bdf-5457-8063-043a7ae94968` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so your table will show a name like wlp2s0 (wireless) or enp0s3 (wired) where this example shows wlan0. Run ip -br link to list your i... | none |
| 4 | `longest-prefix-match` | `23691b59-65cc-5945-a808-ae8380c25bd5` | steps | Longest prefix match | Take the packet's destination address, for example 10.1.2.3. · Find every route whose prefix contains it: here 10.0.0.0/8, 10.1.0.0/16 and the default 0.0.0.0/0. · Pick the most specific one, the long... | none |
| 5 | `many-routers-many-paths` | `f3711503-cd9a-5fe6-b455-03723ad807b6` | topology | Many routers, many paths | Office network · 10.1.0.0/16 · R1 · The office gateway. Its default route points to both providers; if one link fails, a dynamic protocol switches traffic to the other. · R2 · R3 · R4 · Reachable two... | none |
| 6 | `static-and-dynamic-routing` | `3e9df2c1-dc96-5069-bd80-df6797fc8df2` | comparison | Static and dynamic routing | Who writes the routes · An administrator, by hand or configuration · Routers, by exchanging what they can reach · When a link fails · Nothing changes until someone edits it · Routers recalculate withi... | none |
| 7 | `routes-can-lie` | `8e7646e6-804b-587c-8e35-0bc5db4e98c5` | callout | Routes can lie | BGP trusts what neighbouring networks announce. In 2008 a provider announced a more specific route for part of YouTube's address space, and because longest prefix wins, much of the internet sent YouTu... | none |
| 8 | `check-your-understanding` | `dec1ccc6-9bbd-5d31-8324-c282be39e532` | knowledge-check | Check your understanding | A router has routes to 10.0.0.0/8 via A, 10.1.0.0/16 via B and 0.0.0.0/0 via C. Where does it send a packet for 10.1.2.3? · B · A · C · All three match, but /16 is the longest prefix, so the route via... | none |

- **Block types used:** prose, code, callout, steps, topology, comparison, knowledge-check. **Not used:** diagram, media, layer-stack, sequence, flow, tree, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (mesh): How does a packet cross networks that nobody controls end to end?
  - `office` Office → section `many-routers-many-paths` (`f3711503-cd9a-5fe6-b455-03723ad807b6`) → lesson `nat-and-private-addresses`
  - `r1` R1, the gateway → section `your-routing-table` (`a6f16ea6-4a77-5465-a626-143c5871d248`)
  - `r2` R2 → section `many-routers-many-paths` (`f3711503-cd9a-5fe6-b455-03723ad807b6`)
  - `r3` R3 → section `many-routers-many-paths` (`f3711503-cd9a-5fe6-b455-03723ad807b6`)
  - `r4` R4 → section `static-and-dynamic-routing` (`3e9df2c1-dc96-5069-bd80-df6797fc8df2`)
  - `servers` Servers → section `many-routers-many-paths` (`f3711503-cd9a-5fe6-b455-03723ad807b6`)

#### 6. NAT and private addresses (`nat-and-private-addresses`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `fc399c558ad5d0f5` (send it back as `base.lesson_fingerprint`)
- **Summary:** How many private devices share one public address, and why NAT is not a firewall.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Follow a connection through source NAT → `a-request-leaving-through-nat`, `kinds-of-nat`
  2. Tell source NAT and port forwarding apart → `a-request-leaving-through-nat`, `kinds-of-nat`
  3. Explain what NAT does and does not protect → **no dedicated section found**
- **Takeaways:** Source NAT rewrites outgoing private addresses and ports to the router's public address and remembers the mapping. · Destination NAT (port forwarding) sends inbound connections on a public port to an inside host. · NAT blocks unsolicited inbound traffic only as a side effect; real protection comes from firewall rules.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `many-devices-one-address` | `edf4d9ef-eacc-5f15-9d2a-3af52206b4a7` | prose | Many devices, one address | Private ranges such as 192.168.0.0/16 are never routed on the internet, yet a laptop with 192.168.1.20 can load any web page. The router at the edge performs network address translation: it rewrites t... | none |
| 2 | `a-request-leaving-through-nat` | `6197e5b4-5ca9-5fe9-8912-4f52423860c7` | sequence | A request leaving through NAT | Laptop · NAT router · Web server · SYN · 192.168.1.20:51000 → 203.0.113.10:443 · The laptop opens a connection from its private address and a random source port. · SYN · 198.51.100.7:40001 → 203.0.113... | none |
| 3 | `kinds-of-nat` | `4435e1f1-83e6-547d-99aa-28a335c0ed17` | comparison | Kinds of NAT | Also called · Masquerading, PAT, NAT overload · Port forwarding · Rewrites · The source of outgoing connections · The destination of incoming connections · Started by · An inside host · Anyone on the... | none |
| 4 | `source-nat-on-a-linux-router-nftables` | `6bb58c7b-8ebe-5521-a80e-3bf29c4e0086` | code | Source NAT on a Linux router (nftables) | sudo nft add table ip nat sudo nft add chain ip nat postrouting '{ type nat hook postrouting priority srcnat; }' sudo nft add rule ip nat postrouting oifname "wan0" masquerade | none |
| 5 | `your-interface-may-have-another-name` | `6d153e6d-db27-5783-a1da-7d36cff84c04` | callout | Your interface may have another name | wan0 stands for the interface that faces the internet. Most distributions name interfaces after where the hardware sits, such as enp0s3 or eno1; run ip -br link to list yours and use that name. | none |
| 6 | `nat-is-not-a-firewall` | `c51c012b-41ec-5bf8-9814-841e4b5bdf50` | callout | NAT is not a firewall | Unsolicited inbound packets fail through NAT only because no mapping exists. A port forward, an automatic one opened by UPnP, or IPv6, where hosts usually have global addresses and no NAT at all, remo... | none |
| 7 | `where-else-you-meet-it` | `69b5e486-7a2d-577e-b3b7-86e3a5df6593` | prose | Where else you meet it | Containers rely on NAT too: when Docker publishes a container's port, it adds a destination NAT rule from the host's port to the container's private address. The blog post "Docker & Containers 101" (/... | none |
| 8 | `check-your-understanding` | `98d50f81-f467-5e48-ac1f-8ebc36cdec7e` | knowledge-check | Check your understanding | A web server logs a visit from 198.51.100.7, your office router's public address. Can it tell which office laptop made the request? · No: only the NAT router's translation table maps that connection t... | none |

- **Block types used:** prose, sequence, comparison, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, flow, tree, topology, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (pipeline): How do many private machines share one public address?
  - `laptop` Laptop → section `many-devices-one-address` (`edf4d9ef-eacc-5f15-9d2a-3af52206b4a7`)
  - `rewrite` Source rewritten → section `a-request-leaving-through-nat` (`6197e5b4-5ca9-5fe9-8912-4f52423860c7`)
  - `track` Mapping remembered → section `source-nat-on-a-linux-router-nftables` (`6bb58c7b-8ebe-5521-a80e-3bf29c4e0086`)
  - `server` Server replies → section `a-request-leaving-through-nat` (`6197e5b4-5ca9-5fe9-8912-4f52423860c7`)
  - `back` Translated back → section `a-request-leaving-through-nat` (`6197e5b4-5ca9-5fe9-8912-4f52423860c7`)

### Chapter 4: Transport and diagnostics

#### 1. TCP and UDP in depth (`tcp-and-udp-in-depth`)

- **State:** published · **Minutes:** 20 · **Fingerprint:** `c10b822eca0512a7` (send it back as `base.lesson_fingerprint`)
- **Summary:** Ports and sockets, and how TCP turns a lossy network into an ordered, reliable byte stream.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Explain ports, sockets and the connection 4-tuple → `ports-and-sockets`, `tcp-flags-you-will-see`
  2. Follow acknowledgements, the sliding window and retransmission → `the-sliding-window`, `udp-leaves-it-to-the-program`
  3. Read TCP flags and what scanners learn from them → `tcp-flags-you-will-see`, `what-a-port-scan-reads`
- **Takeaways:** A connection is identified by source address, source port, destination address and destination port. · TCP numbers every byte, acknowledges what arrived in order, and resends what did not. · The window limits how much unacknowledged data may be in flight, so a slow receiver is never overrun.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `ports-and-sockets` | `971e868e-4bc9-5b90-ad30-1ff1d971a8a4` | prose | Ports and sockets | An IP address gets a packet to a machine; a port gets it to the right program. Ports are 16-bit numbers, 0 to 65535. IANA lists well-known ports (0 to 1023) such as 443 for HTTPS, registered ports (10... | none |
| 2 | `sockets-on-your-machine` | `6a635f0e-cf89-580c-910a-6e42e69d3317` | code | Sockets on your machine | ss -tulpn # listening TCP and UDP sockets, with their programs ss -tn state established # open TCP connections and their 4-tuples cat /proc/sys/net/ipv4/ip_local_port_range # the client port range: 32... | none |
| 3 | `acknowledging-and-resending` | `aaf9a3dc-8458-54a4-a90c-58c2d0d4b031` | sequence | Acknowledging and resending | Sender · Receiver · Data · seq=1, 1000 bytes · TCP numbers bytes, not packets. This segment carries bytes 1 to 1000. · Data (lost) · seq=1001, 1000 bytes · The window allows several segments in flight... | none |
| 4 | `the-sliding-window` | `2243209b-cedf-5f46-8906-18656a453093` | step-through | The sliding window | The receiver advertised a window of 4000 bytes: the sender may have up to 4000 unacknowledged bytes in flight. It sends bytes 1 to 4000 straight away. · 1–4000 in flight · 4001+ must wait · Acknowledg... | none |
| 5 | `tcp-flags-you-will-see` | `ec3d2bda-762f-5719-8990-ea6fd8f6bf29` | comparison | TCP flags you will see | SYN · Open a connection, here is my starting sequence number · A connection begins, or a scanner probes a port · ACK · The acknowledgement number is valid · Almost every segment after the first · FIN... | none |
| 6 | `udp-leaves-it-to-the-program` | `92730e06-dec0-522d-8867-e9dfe58aab15` | prose | UDP leaves it to the program | UDP has none of this: no handshake, no acknowledgements, no window. Each datagram is sent once and may be lost, duplicated or reordered. Programs that use UDP handle what they need themselves: a DNS c... | none |
| 7 | `what-a-port-scan-reads` | `be04f6a7-3ab4-54f6-b398-7f68b3b1a150` | callout | What a port scan reads | A scanner sends a SYN to each port. An open TCP port answers SYN-ACK; a closed one answers RST; a filtered one says nothing, or an ICMP "unreachable" comes back. For UDP, a closed port usually trigger... | none |
| 8 | `check-your-understanding` | `c23b209b-63eb-5dd8-9957-ae2b893df55c` | knowledge-check | Check your understanding | Two browser tabs on the same laptop connect to the same server on port 443. How does the server keep the connections apart? · Each tab uses a different client source port, so the 4-tuples differ · It... | none |

- **Block types used:** prose, code, sequence, step-through, comparison, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, flow, tree, topology, timeline, bitfield, calculator, scene-3d, script-3d.
- **Architecture on the course map** (queue): How do you deliver an ordered byte stream over a network that loses packets?
  - `oldest` Oldest unacknowledged bytes → section `the-sliding-window` (`2243209b-cedf-5f46-8906-18656a453093`)
  - `flight` Newer bytes in flight → section `acknowledging-and-resending` (`aaf9a3dc-8458-54a4-a90c-58c2d0d4b031`)
  - `may` May send: inside the window → section `the-sliding-window` (`2243209b-cedf-5f46-8906-18656a453093`)
  - `wait` Must wait: beyond the window → section `the-sliding-window` (`2243209b-cedf-5f46-8906-18656a453093`)

#### 2. ICMP and troubleshooting (`icmp-and-troubleshooting`)

- **State:** published · **Minutes:** 20 · **Fingerprint:** `64d57518810e179b` (send it back as `base.lesson_fingerprint`)
- **Summary:** The network's error messages, and a bottom-up routine for finding where a connection breaks.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Explain the ICMP messages behind ping and traceroute → `the-network-talks-back`, `firewalls-change-what-you-see`
  2. Troubleshoot a connection layer by layer with ip, ping, tracepath and ss → **no dedicated section found**
  3. Read common error messages and what they point to → **no dedicated section found**
- **Takeaways:** ICMP carries errors and diagnostics: echo for ping, time exceeded for traceroute, unreachable for refusals. · Work from the bottom up: link, address, gateway, path, name, port, application. · "Refused" means something answered no; "timed out" usually means something silently dropped the traffic.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `the-network-talks-back` | `bf13ad42-0542-5a02-93f1-6416a164b91f` | prose | The network talks back | The Internet Control Message Protocol (ICMP) carries the network's own messages. You will meet three kinds most: - echo request and reply (types 8 and 0): what ping sends and expects back - destinatio... | none |
| 2 | `where-does-it-break` | `5b2ec156-2ac6-5be5-b9ab-8daf565dfe72` | flow | Where does it break? | Yes · Yes · Yes · Yes · Yes · Yes · No · No · No · No · No · No · Is the interface up? · ip -br link should say UP. If not: cable, Wi-Fi association, or a disabled interface. · Do I have an address? ·... | none |
| 3 | `the-routine-on-linux` | `8876c39d-d924-5e57-bbda-7e0a4916bf5c` | code | The routine on Linux | ip -br link # 1. interfaces UP? ip -br addr # 2. an address on the right subnet? ip route # 3. a default route? note the gateway ping -c 3 192.168.1.1 # 3. the gateway answers? ping -c 3 203.0.113.10... | none |
| 4 | `how-traceroute-finds-the-path` | `0dbf960a-d3c9-5bde-a8cd-aed128e7fa21` | sequence | How traceroute finds the path | You · Router 1 · Router 2 · Destination · Probe · TTL=1 · The first probe is sent with a TTL of 1. The first router lowers it to 0 and must drop the packet. · Time exceeded · from router 1 · Router 1... | none |
| 5 | `what-the-symptom-means` | `79303ce9-2661-579c-95cd-31e6e4d5a231` | comparison | What the symptom means | Connection refused · The host is up, but nothing listens on that port (it sent RST) · ss -tlnp on the server: is the service running and on which address? · Connection timed out · Something dropped th... | none |
| 6 | `firewalls-change-what-you-see` | `c801a252-8420-523c-86e6-cca03efd5f32` | callout | Firewalls change what you see | Many hosts and networks drop ping and traceroute probes, so silence is not proof that something is down. A timeout points at a filtering device; practise reading rules in the lab "Lock down a web serv... | none |
| 7 | `check-your-understanding` | `8b686bc4-ba75-58d6-bf95-ff4808161c3a` | knowledge-check | Check your understanding | curl to a server reports "Connection refused" immediately. What do you know? · The server is reachable, but no program accepts connections on that port · A firewall silently dropped the packets · DNS... | none |

- **Block types used:** prose, flow, code, sequence, comparison, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, tree, topology, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (pipeline): Where exactly does a broken connection break?
  - `link` Is the interface up? → section `where-does-it-break` (`5b2ec156-2ac6-5be5-b9ab-8daf565dfe72`)
  - `addr` Do I have an address? → section `where-does-it-break` (`5b2ec156-2ac6-5be5-b9ab-8daf565dfe72`) → lesson `dns-and-dhcp`
  - `gw` Can I reach the gateway? → section `the-routine-on-linux` (`8876c39d-d924-5e57-bbda-7e0a4916bf5c`) → lesson `routing-basics`
  - `path` Can I reach the remote address? → section `how-traceroute-finds-the-path` (`0dbf960a-d3c9-5bde-a8cd-aed128e7fa21`)
  - `name` Does the name resolve? → section `where-does-it-break` (`5b2ec156-2ac6-5be5-b9ab-8daf565dfe72`) → lesson `dns-and-dhcp`
  - `port` Does the port answer? → section `what-the-symptom-means` (`79303ce9-2661-579c-95cd-31e6e4d5a231`) → lesson `tcp-and-udp-in-depth`
  - `app` Look at the application → section `where-does-it-break` (`5b2ec156-2ac6-5be5-b9ab-8daf565dfe72`)

### Chapter 5: Services and seeing traffic

#### 1. DNS and DHCP (`dns-and-dhcp`)

- **State:** published · **Minutes:** 20 · **Fingerprint:** `36a574bcad650a7e` (send it back as `base.lesson_fingerprint`)
- **Summary:** The two services that make networks usable, and the ways attackers abuse them.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Trace a DNS lookup from a browser to an authoritative server → `resolving-wwwexamplecom`
  2. Explain the four steps of DHCP → **no dedicated section found**
  3. Describe common DNS and DHCP attacks → **no dedicated section found**
- **Takeaways:** DNS turns names into IP addresses through a chain of resolvers and authoritative servers. · DHCP hands out addresses with Discover, Offer, Request, Acknowledge. · Both protocols trust their answers by default, which attackers exploit.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `names-form-a-tree` | `e1c8bd8f-8e43-5707-bf73-0930c724015f` | tree | Names form a tree | . · Root · The top of the tree. 13 root server names (a to m) are run by 12 organisations, with hundreds of copies worldwide. They know where every top-level domain is. · top-level domain · com · A to... | none |
| 2 | `resolving-wwwexamplecom` | `3820e512-3dd9-5c45-ade1-886364532c01` | sequence | Resolving www.example.com | Laptop · Resolver · Root · .com TLD · Authoritative · Query · A? · The browser needs the address (an A record) for www.example.com. It asks the operating system, which checks its cache. On a miss it s... | none |
| 3 | `try-it-yourself` | `2cec6755-9c59-5441-ba0f-04546a5e3113` | code | Try it yourself | dig www.example.com +short dig www.example.com +trace | none |
| 4 | `dhcp-getting-an-address` | `e795ff86-0ca1-5b70-bbc1-cafabde2b365` | sequence | DHCP: getting an address | New laptop · DHCP server · Discover · broadcast · INIT · The laptop has no address yet, so it broadcasts to the whole local network from 0.0.0.0 to 255.255.255.255 (UDP port 68 to 67): is there a DHCP... | none |
| 5 | `how-they-get-abused` | `52653ea5-d1aa-5ee1-9c6e-7abd51f3f784` | callout | How they get abused | DNS spoofing returns false answers that send users to an attacker's server. A rogue DHCP server can hand out itself as the gateway and see all traffic. DNS is also used to smuggle data out (DNS tunnel... | none |
| 6 | `check-your-understanding` | `1880c771-f442-5e2a-b29d-f7da64060802` | knowledge-check | Check your understanding | An attacker plugs a small device into a meeting room socket and answers DHCP requests faster than the real server, naming itself as the default gateway. What does the attacker gain? · Victims send the... | none |

- **Block types used:** tree, sequence, code, callout, knowledge-check. **Not used:** prose, steps, diagram, media, layer-stack, flow, topology, comparison, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (tree): How do names become addresses, and how does a new device get an address at all?
  - `root` Root (.) → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `com` com → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `org` org → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `vn` vn → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `example` example.com → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `www` www.example.com → section `resolving-wwwexamplecom` (`3820e512-3dd9-5c45-ade1-886364532c01`)
  - `mail` mail.example.com → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `edu` edu.vn → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)
  - `uit` uit.edu.vn → section `names-form-a-tree` (`e1c8bd8f-8e43-5707-bf73-0930c724015f`)

#### 2. Application protocols at a glance (`application-protocols`)

- **State:** published · **Minutes:** 12 · **Fingerprint:** `a70de2381331c1b5` (send it back as `base.lesson_fingerprint`)
- **Summary:** Which services you will meet, on which ports and transports, and which ones send secrets in clear text.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Recognise common services from their port and transport → `services-to-recognise`
  2. Tell clear-text protocols from their encrypted replacements → **no dedicated section found**
  3. Explain why a port number alone does not prove what is running → `ports-can-lie`
- **Takeaways:** Well-known ports are conventions: 22 SSH, 53 DNS, 80 HTTP, 443 HTTPS, 3389 RDP. · Telnet, FTP and plain HTTP send passwords readable to anyone on the path; use their encrypted replacements. · Any service can run on any port, so confirm what is listening from its traffic, not from the number.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `a-port-is-a-convention` | `592d9b9a-05cc-5522-b8dd-4b443de6cd9c` | prose | A port is a convention | Servers listen on well-known ports so that clients know where to knock: a browser goes to port 443 unless told otherwise. The table below is the short list worth knowing by heart. Each protocol has it... | none |
| 2 | `services-to-recognise` | `3cc52657-cadb-5e1b-8ad1-fb5b38e50844` | comparison | Services to recognise | HTTP · 80 · TCP · Web traffic in clear text · HTTPS · 443 · TCP, and UDP for HTTP/3 · HTTP inside TLS; HTTP/3 runs over QUIC on UDP · DNS · 53 · UDP, and TCP for large answers · Encrypted variants: DN... | none |
| 3 | `clear-text-and-its-replacement` | `40c7981c-bcc0-56b3-b865-29d1777b0140` | comparison | Clear text and its replacement | Remote shell · Telnet (23) · SSH (22) · File transfer · FTP (21) · SFTP over SSH, or HTTPS · Web · HTTP (80) · HTTPS (443) · Monitoring · SNMP v1 and v2c · SNMP v3 with authentication and encryption ·... | none |
| 4 | `look-up-and-list-services` | `e1fbaa2a-1d25-5fed-a170-3a422a10cc3b` | code | Look up and list services | getent services ssh # ssh 22/tcp, from /etc/services sudo ss -tlnp # what is actually listening, and which program | none |
| 5 | `ports-can-lie` | `246cf2be-a285-5b32-b533-3472215df99e` | callout | Ports can lie | Nothing stops SSH from listening on 443 or a backdoor from using port 53 to slip past a firewall. A port number is a hint. To know what really runs, look at the traffic itself, which is what the packe... | none |
| 6 | `check-your-understanding` | `5af791e4-12a8-5231-a381-7bd566a665ac` | knowledge-check | Check your understanding | A scan finds TCP port 3389 open on a server reachable from the internet. What is probably running, and why does it matter? · Windows Remote Desktop, a common target for password guessing · DNS, which... | none |

- **Block types used:** prose, comparison, code, callout, knowledge-check. **Not used:** steps, diagram, media, layer-stack, sequence, flow, tree, topology, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (no shape): Which service is this, and does it send secrets in clear text?

#### 3. Wi-Fi essentials (`wifi-essentials`)

- **State:** published · **Minutes:** 15 · **Fingerprint:** `41e679dd0862cf78` (send it back as `base.lesson_fingerprint`)
- **Summary:** How Wi-Fi joins devices to a network, the rings of protection around your data, and what WPA3 changed.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Describe how a device joins a Wi-Fi network → `guest-networks`
  2. Compare WPA2 and WPA3 → `wpa2-and-wpa3-personal`
  3. Recognise evil-twin and deauthentication attacks → **no dedicated section found**
- **Takeaways:** Anyone in radio range can hear a Wi-Fi network, so its protection comes from authentication and encryption. · WPA3-Personal stops offline password guessing and requires protected management frames. · An evil twin copies a network's name; enterprise Wi-Fi with certificate checking stops clients from trusting it.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `ethernet-over-radio` | `6321cef6-5667-5fb3-be11-da94493417c6` | prose | Ethernet over radio | Wi-Fi (the IEEE 802.11 standards) carries frames with the same MAC addressing as Ethernet, but over radio in the 2.4, 5 and 6 GHz bands. A network announces its name, the SSID, and each access point r... | none |
| 2 | `rings-around-your-data` | `83d395aa-4bb8-5b04-b140-473056d8c6cc` | layer-stack | Rings around your data | 1 · Radio range · Assume an attacker can capture everything sent over the air. · The outermost ring: anyone close enough can receive every frame on the network, encrypted or not. Walls slow radio down... | none |
| 3 | `wpa2-and-wpa3-personal` | `e548a964-3251-5ad8-9d12-5b2f82aadd8c` | comparison | WPA2 and WPA3 (Personal) | Password check · Pre-shared key and the four-way handshake · SAE ("Dragonfly"), a password-authenticated key exchange · Captured handshake · Can be cracked offline, guessing passwords at full speed ·... | none |
| 4 | `inspect-wi-fi-on-linux` | `3451f900-b582-5df5-b234-c505c1f081f7` | code | Inspect Wi-Fi on Linux | nmcli dev wifi list # nearby networks: SSID, BSSID, channel, security iw dev wlan0 link # the access point you are connected to and the signal | none |
| 5 | `your-interface-may-have-another-name` | `68691bd6-88e7-5022-b2da-2b8cea34610e` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so your wireless card is probably not wlan0 but something like wlp2s0 (a wired one looks like enp0s3). Run ip -br link to list your in... | none |
| 6 | `evil-twins-and-deauthentication` | `fc81a02d-5f73-5789-aa8a-817f6d473c90` | callout | Evil twins and deauthentication | An attacker can set up an access point with the same SSID as a real one, then send forged "deauthenticate" frames so clients drop off and reconnect, some of them to the impostor. WPA3 makes protected... | none |
| 7 | `guest-networks` | `5a7d702d-03c4-5f36-b5dc-e547fd9d4c33` | prose | Guest networks | Visitors' devices belong on their own network: a separate SSID mapped to its own VLAN and subnet, with firewall rules that allow the internet and nothing internal. Turning on client isolation also sto... | none |
| 8 | `check-your-understanding` | `564cfe0e-690b-5062-bc00-ce0c0a254761` | knowledge-check | Check your understanding | An attacker recorded a WPA2-Personal handshake in your car park. What can they do with it at home? · Try to crack the passphrase offline, as fast as their hardware allows · Nothing: handshakes contain... | none |

- **Block types used:** prose, layer-stack, comparison, code, callout, knowledge-check. **Not used:** steps, diagram, media, sequence, flow, tree, topology, timeline, bitfield, calculator, step-through, scene-3d, script-3d.
- **Architecture on the course map** (onion): How do you connect without wires and keep the neighbours out?
  - `data` Your data → section `rings-around-your-data` (`83d395aa-4bb8-5b04-b140-473056d8c6cc`)
  - `enc` Encryption → section `rings-around-your-data` (`83d395aa-4bb8-5b04-b140-473056d8c6cc`)
  - `auth` Authentication → section `wpa2-and-wpa3-personal` (`e548a964-3251-5ad8-9d12-5b2f82aadd8c`)
  - `assoc` Association → section `rings-around-your-data` (`83d395aa-4bb8-5b04-b140-473056d8c6cc`)
  - `radio` Radio range → section `evil-twins-and-deauthentication` (`fc81a02d-5f73-5789-aa8a-817f6d473c90`)

#### 4. Packet capture basics (`packet-capture-basics`)

- **State:** published · **Minutes:** 20 · **Fingerprint:** `f06a6a6d1c137316` (send it back as `base.lesson_fingerprint`)
- **Summary:** Capturing traffic with tcpdump and reading it layer by layer, safely and legally.
- **Objectives**, with the teaching sections whose heading or opening matches (a hint only; check it):
  1. Capture traffic with tcpdump using capture filters → **no dedicated section found**
  2. Read a captured packet from frame to application data → **no dedicated section found**
  3. Explain what encrypted traffic still reveals → **no dedicated section found**
- **Takeaways:** A capture filter decides what is saved; a display filter decides what you look at afterwards. · Reading a packet means unwrapping it the way the receiver does: frame, packet, segment, data. · With TLS you still see addresses, ports, the server name, sizes and timing, but not the content.
- **Sections:**

| # | key | uuid | type | heading | gist | sources |
|---|---|---|---|---|---|---|
| 1 | `seeing-what-is-really-on-the-wire` | `e45c65e5-78ff-51b1-a49e-ad74cfdf46aa` | prose | Seeing what is really on the wire | Logs tell you what a program decided to write down. A packet capture shows what actually crossed the network, which is why it settles arguments about firewalls, DNS and "the server never got my reques... | none |
| 2 | `capture-safely` | `ebdaabc9-9e13-5324-8e8d-e37477ef9fc1` | steps | Capture safely | Capture only on networks and systems you are authorised to monitor. Capturing other people's traffic without permission is illegal in most countries. · Capture as little as you need: one interface, on... | none |
| 3 | `tcpdump-in-practice` | `3db702a9-ab3a-5b55-b82b-ee3c123b0da4` | code | tcpdump in practice | sudo tcpdump -i eth0 -n port 53 # DNS queries and answers, no name lookups sudo tcpdump -i eth0 -n -c 20 host 203.0.113.10 # 20 packets to or from one host sudo tcpdump -i eth0 -w web.pcap 'tcp port 4... | none |
| 4 | `your-interface-may-have-another-name` | `26d60616-cf70-5447-a599-54a6d56eb1a6` | callout | Your interface may have another name | Most distributions name interfaces after where the hardware sits, so yours is probably not eth0: a wired card is often enp0s3 or eno1, a wireless one wlp2s0. Run ip -br link to list your interfaces an... | none |
| 5 | `capture-filters-and-display-filters` | `299e0591-86bf-5531-9c11-8aba02ba3810` | comparison | Capture filters and display filters | Applied · While capturing: packets that do not match are never saved · Afterwards: it hides packets but keeps the file intact · Syntax · BPF: tcp port 443 and host 203.0.113.10 · Wireshark: tcp.port =... | none |
| 6 | `reading-one-http-request` | `6a822f7d-74e6-57e3-999b-074b5c78208f` | step-through | Reading one HTTP request | The outermost layer: source and destination MAC addresses and the EtherType (IPv4). It tells you which two devices on this link exchanged it, usually a host and its gateway. · Ethernet · IP · TCP · HT... | none |
| 7 | `what-encryption-still-shows` | `b082fb86-46dc-5326-a71f-145f90774627` | callout | What encryption still shows | With TLS you cannot read the content, but you still see who talked to whom, on which ports, when, and how much. The server name is usually visible in the TLS handshake (SNI) too. That is often enough... | none |
| 8 | `check-your-understanding` | `9d0bab68-7f19-549b-b482-ffcc8ebf4b3b` | knowledge-check | Check your understanding | You only care about DNS traffic and want small capture files. Which kind of filter do you use? · A capture filter such as port 53, so nothing else is saved · A display filter, applied after capturing... | none |

- **Block types used:** prose, steps, code, callout, comparison, step-through, knowledge-check. **Not used:** diagram, media, layer-stack, sequence, flow, tree, topology, timeline, bitfield, calculator, scene-3d, script-3d.
- **Architecture on the course map** (pipeline): How do you see what is really on the wire, safely and legally?
  - `permit` Get permission → section `capture-safely` (`ebdaabc9-9e13-5324-8e8d-e37477ef9fc1`)
  - `filter` Capture filter → section `capture-filters-and-display-filters` (`299e0591-86bf-5531-9c11-8aba02ba3810`)
  - `write` Save a pcap → section `tcpdump-in-practice` (`3db702a9-ab3a-5b55-b82b-ee3c123b0da4`)
  - `display` Display filter → section `capture-filters-and-display-filters` (`299e0591-86bf-5531-9c11-8aba02ba3810`)
  - `read` Read the layers → section `reading-one-http-request` (`6a822f7d-74e6-57e3-999b-074b5c78208f`) → lesson `encapsulation-and-pdus`

## Terms already explained

Terms the lessons introduce in bold, and where. Refer to these instead of explaining them again.

- **protocols**: `intro-to-computer-networks#what-a-network-is`
- **Hosts**: `intro-to-computer-networks#the-devices-in-the-path`, `network-devices-and-media#boxes-on-the-path`
- **Switches**: `intro-to-computer-networks#the-devices-in-the-path`
- **Routers**: `intro-to-computer-networks#the-devices-in-the-path`
- **Firewalls**: `intro-to-computer-networks#the-devices-in-the-path`
- **LAN**: `intro-to-computer-networks#lans-wans-and-the-internet`
- **WAN**: `intro-to-computer-networks#lans-wans-and-the-internet`
- **network interface**: `network-devices-and-media#boxes-on-the-path`
- **hub**: `network-devices-and-media#hubs-and-why-you-rarely-see-them`
- **OSI model**: `osi-model#why-a-layered-model`
- **segment**: `osi-model#encapsulation`, `encapsulation-and-pdus#every-layer-adds-its-own-header`
- **ports**: `osi-model#encapsulation`, `tcp-and-udp-in-depth#ports-and-sockets`
- **packet**: `osi-model#encapsulation`, `encapsulation-and-pdus#every-layer-adds-its-own-header`
- **IP addresses**: `osi-model#encapsulation`
- **frame**: `osi-model#encapsulation`, `encapsulation-and-pdus#every-layer-adds-its-own-header`, `ethernet-and-mac-addresses#a-frame-on-the-wire`
- **MAC addresses**: `osi-model#encapsulation`
- **protocol data unit**: `encapsulation-and-pdus#every-layer-adds-its-own-header`
- **data**: `encapsulation-and-pdus#every-layer-adds-its-own-header`
- **datagram**: `encapsulation-and-pdus#every-layer-adds-its-own-header`
- **maximum transmission unit**: `encapsulation-and-pdus#size-limits-the-mtu`
- **1500 bytes**: `encapsulation-and-pdus#size-limits-the-mtu`
- **fragments**: `encapsulation-and-pdus#size-limits-the-mtu`
- **destination MAC address**: `ethernet-and-mac-addresses#a-frame-on-the-wire`
- **source MAC address**: `ethernet-and-mac-addresses#a-frame-on-the-wire`
- **EtherType**: `ethernet-and-mac-addresses#a-frame-on-the-wire`
- **frame check sequence**: `ethernet-and-mac-addresses#a-frame-on-the-wire`
- **OUI**: `ethernet-and-mac-addresses#two-bits-that-matter`
- **I/G**: `ethernet-and-mac-addresses#two-bits-that-matter`
- **U/L**: `ethernet-and-mac-addresses#two-bits-that-matter`
- **source**: `switching-and-the-mac-table#a-switch-learns-as-it-goes`
- **MAC address table**: `switching-and-the-mac-table#a-switch-learns-as-it-goes`
- **virtual LAN**: `vlans#one-switch-several-networks`
- **broadcast domain**: `vlans#one-switch-several-networks`
- **octets**: `ip-addressing#anatomy-of-an-ipv4-address`
- **IPv6**: `ip-addressing#ipv6-in-one-paragraph`
- **next hop**: `arp#two-addresses-for-one-trip`, `routing-basics#hop-by-hop`
- **default gateway**: `arp#two-addresses-for-one-trip`
- **Address Resolution Protocol**: `arp#two-addresses-for-one-trip`
- **REACHABLE**: `arp#a-cycle-not-a-one-off`
- **STALE**: `arp#a-cycle-not-a-one-off`
- **FAILED**: `arp#a-cycle-not-a-one-off`
- **Neighbor Discovery**: `arp#ipv6-does-it-differently`
- **subnet mask**: `subnetting#network-part-and-host-part`
- **CIDR notation**: `subnetting#network-part-and-host-part`
- **NAT**: `ipv6-essentials#why-ipv6-exists`
- **routing table**: `routing-basics#hop-by-hop`
- **time to live**: `routing-basics#hop-by-hop`
- **default route**: `routing-basics#hop-by-hop`
- **network address translation**: `nat-and-private-addresses#many-devices-one-address`
- **well-known**: `tcp-and-udp-in-depth#ports-and-sockets`
- **registered**: `tcp-and-udp-in-depth#ports-and-sockets`
- **dynamic**: `tcp-and-udp-in-depth#ports-and-sockets`
- **socket**: `tcp-and-udp-in-depth#ports-and-sockets`
- **4-tuple**: `tcp-and-udp-in-depth#ports-and-sockets`
- **Internet Control Message Protocol**: `icmp-and-troubleshooting#the-network-talks-back`
- **echo request and reply**: `icmp-and-troubleshooting#the-network-talks-back`
- **destination unreachable**: `icmp-and-troubleshooting#the-network-talks-back`
- **time exceeded**: `icmp-and-troubleshooting#the-network-talks-back`
- **well-known ports**: `application-protocols#a-port-is-a-convention`
- **SSID**: `wifi-essentials#ethernet-over-radio`
- **BSSID**: `wifi-essentials#ethernet-over-radio`
- **authenticates**: `wifi-essentials#ethernet-over-radio`
- **associates**: `wifi-essentials#ethernet-over-radio`
- **encryption keys**: `wifi-essentials#ethernet-over-radio`
- **client isolation**: `wifi-essentials#guest-networks`
- **packet capture**: `packet-capture-basics#seeing-what-is-really-on-the-wire`

## Block types available

prose, callout, steps, code, diagram, media, layer-stack, sequence, flow, tree, topology, comparison, timeline, bitfield, calculator, step-through, knowledge-check, scene-3d, script-3d. Their contracts and examples are in the AI authoring guide.
