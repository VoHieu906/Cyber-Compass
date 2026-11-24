<?php

// Blog post (F2 Part B). Blocks are [type, heading, payload]; prose, callout, steps, code and
// diagram also take their compact forms (CONTENT_AUTHORING_GUIDE §4c). Every command and file
// in this post was run against Docker Engine 29 (cgroup v2, containerd image store) on 2026-11-22.

$media = 'posts/media/docker-containers-101';

return [
    'slug' => 'docker-containers-101',
    'title' => 'Docker & Containers 101',
    'summary' => 'What a container really is on Linux, how Docker builds, ships and runs one, and how to write, network, store and secure containers well. With a working Flask and Postgres example.',
    'tags' => ['docker', 'containers', 'linux', 'devops'],
    'published_at' => '2026-11-22',
    'cover' => ['file' => "{$media}/cover.png", 'alt' => 'Four dashed boxes, each a stack of coloured layers with an orange layer on top, standing on one teal slab labelled Linux kernel.'],
    'sections' => [

        // 1. Why containers exist
        ['prose', 'Why containers exist', <<<'MD'
Every developer has said it: **"it works on my machine."** The code is the same, yet the program fails on a colleague's laptop or on the server. The reason is almost never the code. It is everything around it: a different Python version, a missing system library, a configuration file in another place, an environment variable nobody wrote down.

Containers fix this by packaging a program **together with everything it needs to run**: its libraries, its runtime, its default configuration. The package is an **image**. Running an image gives you a **container**, and it behaves the same on a laptop, a test server and a cloud machine, because it carries its own environment with it.

Containers also solve a second, quieter problem: **dependency conflicts**. One application needs OpenSSL 3, another still needs an older library; one wants Python 3.11, another 3.13. On one machine these fight. In containers, each application has its own copy, and they never see each other's files.

The third reason is **density**. A virtual machine carries a whole operating system, so a server runs a handful of them. Containers share the host's kernel, so the same server can run dozens or hundreds, each starting in well under a second.
MD],
        ['callout', 'A container is a process', 'Keep this sentence in mind through the whole post: a container is not a small virtual machine. It is one or more ordinary Linux processes that the kernel shows a restricted view of the system and holds to limits. Everything else (images, layers, registries, Compose) is tooling around that idea.', 'info'],

        // 2. History
        ['prose', 'A short history', <<<'MD'
Docker did not invent containers. It packaged forty years of operating-system features into a tool that was pleasant to use. The timeline shows the pieces arriving one by one: first a way to change a process's root directory, then whole isolated "jails" and "zones", then the Linux kernel features Docker is built on, and finally the open standards that let many tools share the same images.
MD],
        ['timeline', 'From chroot to the OCI', [
            'events' => [
                ['when' => '1979', 'title' => 'chroot in Version 7 Unix', 'detail' => 'A process can be given a different root directory. It sees only part of the filesystem, but nothing else is isolated: it still shares processes, users and the network with everything else.'],
                ['when' => '2000', 'title' => 'FreeBSD jails', 'detail' => 'FreeBSD 4.0 adds jails: a chroot plus its own processes, users and IP address. The first widely used "container" on a mainstream system.'],
                ['when' => '2002', 'title' => 'Linux mount namespaces', 'detail' => 'Linux 2.4.19 lets a process have its own list of mounted filesystems. It is the first of the namespaces that containers use today.'],
                ['when' => '2005', 'title' => 'Solaris Zones', 'detail' => 'Solaris 10 ships zones: isolated environments with their own processes, network stack and resource controls on one kernel.'],
                ['when' => '2008', 'title' => 'cgroups and LXC', 'detail' => 'Control groups, started at Google as "process containers", are merged in Linux 2.6.24 to limit CPU and memory per group of processes. LXC combines cgroups and namespaces into Linux containers.'],
                ['when' => '2013', 'title' => 'User namespaces, then Docker', 'detail' => 'Linux 3.8 completes user namespaces. In March, dotCloud releases Docker: a simple command line, images built from a Dockerfile and a registry to share them.'],
                ['when' => '2014', 'title' => 'libcontainer replaces LXC', 'detail' => 'Docker 0.9 talks to the kernel through its own Go library instead of LXC, so it no longer depends on LXC being installed.'],
                ['when' => '2015', 'title' => 'The Open Container Initiative', 'detail' => 'Docker and others found the OCI to standardise the image format and the runtime. Docker donates runc, the low-level runtime, as its reference implementation.'],
                ['when' => '2016', 'title' => 'containerd and runc split out', 'detail' => 'Docker 1.11 stops running containers itself: dockerd hands them to containerd, which starts each one with runc. Linux 4.5 declares cgroup v2 stable the same year.'],
                ['when' => '2017', 'title' => 'containerd joins the CNCF', 'detail' => 'containerd becomes a vendor-neutral project. Kubernetes and many other platforms later use it directly, without Docker.'],
                ['when' => '2020', 'title' => 'cgroup v2 in Docker', 'detail' => 'Docker 20.10 supports cgroup v2, the unified hierarchy that modern distributions now use by default.'],
                ['when' => '2023', 'title' => 'BuildKit and Compose v2 by default', 'detail' => 'Docker Engine 23.0 makes BuildKit the default builder. Compose v1 (docker-compose) stops receiving updates; the docker compose plugin replaces it.'],
                ['when' => '2025', 'title' => 'containerd image store by default', 'detail' => 'Docker Engine 29 stores images through containerd on new installations, the same storage Kubernetes nodes use.'],
            ],
        ], 'sources' => [
            ['label' => 'Open Container Initiative: About', 'url' => 'https://opencontainers.org/about/overview/', 'kind' => 'docs', 'note' => 'Backs the 2015 entry only; the other dates are not checked by this source.'],
        ]],

        // 3. Containers vs virtual machines
        ['prose', 'Containers and virtual machines', <<<'MD'
A **virtual machine** (VM) pretends to be a whole computer. A **hypervisor** gives each VM virtual hardware, and each VM boots its own operating system with its own kernel. Two VMs on one server share nothing but the hypervisor and the physical hardware.

A **container** pretends much less. There is no virtual hardware and no second kernel. The container's processes run directly on the host's Linux kernel, like any other process; the kernel simply shows them a smaller world: their own files, their own process list, their own network interface. That is why a container starts in milliseconds (there is nothing to boot) and why an image can be a few megabytes (there is no operating system kernel inside it).

The price is the **isolation boundary**. Between two VMs stands a hypervisor with a small, heavily scrutinised interface. Between two containers stands the Linux kernel itself, with hundreds of system calls. A kernel bug that lets a process escape its namespaces affects every container on the host. This is why sharing a host between mutually untrusting tenants usually means VMs, or containers inside VMs.
MD],
        ['layer-stack', 'What a container stands on', [
            'tags_label' => 'Examples',
            'layers' => [
                ['tag' => '4', 'name' => 'Your containers', 'meta' => 'processes', 'description' => 'Each container is your application and its libraries, running as ordinary processes with a restricted view of the system. There is no operating system kernel inside the image.', 'tags' => ['nginx', 'postgres', 'your app'], 'note' => ['tone' => 'info', 'label' => 'In a VM', 'text' => 'This layer would be a complete guest operating system: its own kernel, init system and services, booted for every VM.']],
                ['tag' => '3', 'name' => 'Container engine and runtime', 'meta' => 'dockerd, containerd, runc', 'description' => 'Programs that turn an image into running processes: they unpack the layers, ask the kernel for namespaces and cgroups, and start your process. They run on the host, not inside containers.', 'tags' => ['dockerd', 'containerd', 'runc'], 'note' => ['tone' => 'info', 'label' => 'In a VM', 'text' => 'The equivalent is the hypervisor (KVM, Hyper-V, ESXi), which creates virtual hardware instead of a restricted view.']],
                ['tag' => '2', 'name' => 'Host Linux kernel', 'meta' => 'shared', 'description' => 'One kernel serves every container on the machine. It provides the isolation (namespaces), the limits (cgroups) and the filters (capabilities, seccomp). A container therefore cannot run a different kernel, and on macOS or Windows Docker runs a small Linux VM to have one.', 'facts' => [['label' => 'Start-up', 'value' => 'milliseconds: nothing boots'], ['label' => 'Isolation boundary', 'value' => 'the kernel system call interface']], 'note' => ['tone' => 'warning', 'label' => 'Shared means shared risk', 'text' => 'A kernel vulnerability that lets one container escape affects every container on the host. Patch the host kernel like any other critical system.']],
                ['tag' => '1', 'name' => 'Hardware', 'meta' => 'CPU, memory, disk, NIC', 'description' => 'The physical (or cloud) machine. Containers use it through the host kernel exactly as normal programs do, with no virtualisation overhead.'],
            ],
        ]],
        ['comparison', 'VM or container?', [
            'columns' => ['Virtual machine', 'Container'],
            'rows' => [
                ['label' => 'What is isolated', 'values' => ['A whole computer, with virtual hardware', 'Processes, with a restricted view of one kernel']],
                ['label' => 'Kernel', 'values' => ['Its own guest kernel; any OS works', 'The host kernel; Linux containers need a Linux kernel']],
                ['label' => 'Starts in', 'values' => ['Tens of seconds (an OS boots)', 'Well under a second (a process starts)']],
                ['label' => 'Typical size', 'values' => ['Gigabytes', 'Megabytes to a few hundred megabytes']],
                ['label' => 'Per server', 'values' => ['A handful to dozens', 'Dozens to hundreds']],
                ['label' => 'Isolation strength', 'values' => ['Strong: a small hypervisor interface', 'Weaker: the whole system call interface is shared']],
                ['label' => 'Good for', 'values' => ['Untrusted tenants, other operating systems', 'Packaging and running your own services']],
            ],
        ]],
        ['knowledge-check', 'Check: containers and VMs', [
            'questions' => [
                [
                    'prompt' => 'A team wants to run a Windows-only legacy program and a Linux web service on the same Linux server. What do they need for the Windows program?',
                    'options' => [
                        ['text' => 'A virtual machine running Windows', 'correct' => true],
                        ['text' => 'A Windows container on the Linux host', 'correct' => false],
                        ['text' => 'A Linux container with the Windows libraries copied in', 'correct' => false],
                    ],
                    'explanation' => 'Containers share the host kernel, and a Windows program needs the Windows kernel. A VM brings its own kernel, so it can run any operating system. Windows containers exist, but they need a Windows host kernel; copying libraries into a Linux container cannot replace the kernel underneath.',
                ],
                [
                    'prompt' => 'Why does a container usually start much faster than a virtual machine?',
                    'options' => [
                        ['text' => 'Nothing boots: the container is a new process on an already running kernel', 'correct' => true],
                        ['text' => 'Container images are compressed more efficiently', 'correct' => false],
                        ['text' => 'Docker keeps every container paused in memory until it is used', 'correct' => false],
                    ],
                    'explanation' => 'Starting a container means creating namespaces, cgroups and a filesystem view, then starting one process: the kernel is already running. Compression only affects downloads, and Docker does not keep idle containers in memory; a VM is slower because it must boot a whole operating system.',
                ],
            ],
        ]],

        // 4. Core concepts
        ['prose', 'The core concepts', <<<'MD'
Docker's vocabulary is small, and each word names one thing. These are the ones the rest of the post uses; the glossary at the end repeats them in short.

- An **image** is a read-only template for containers: a stack of filesystem **layers** plus a small **config** (which command to run, which user, environment variables, exposed ports). Images never change; a new version is a new image.
- A **layer** is one set of filesystem changes: files added, changed or deleted. Layers are identified by the **SHA-256 digest** of their content, so identical layers are stored and downloaded once and shared by every image that uses them.
- A **container** is a running (or stopped) instance of an image: the image's layers, plus one thin **writable layer** of its own, plus its runtime settings (name, ports, volumes, limits).
- A **Dockerfile** is the recipe that builds an image: a base image, then instructions such as `COPY` and `RUN`. Most instructions produce one layer.
- A **registry** is a server that stores images (Docker Hub, GitHub Container Registry, Amazon ECR). Inside it, a **repository** holds the versions of one image, such as `library/nginx`. A **tag** is a movable, human name for one version (`1.29-alpine`); a **digest** (`sha256:…`) names exact content and never moves. `nginx:1.29-alpine` can point to a newer build tomorrow; `nginx@sha256:…` cannot.
- A **volume** is storage managed by Docker that lives outside any container, so data survives when containers are replaced.
- A **network** is a virtual network that containers join; containers on the same user-defined network find each other by name.
- **Docker Engine** is the server side: the **`dockerd`** daemon, which exposes a **REST API** on a local socket (`/var/run/docker.sock`). The `docker` command is only a client for that API.
- **Docker Compose** describes a multi-container application in one file, `compose.yaml`, and runs it with `docker compose up`.
- The **OCI image and runtime specifications** are the open standards underneath: any OCI image runs on any OCI runtime, so images built by Docker run on Podman, containerd or Kubernetes unchanged.
MD],
        ['callout', 'Like a shipping container', 'A container image works like a shipping container: the crane, the ship and the truck never need to know what is inside, only that the box has the standard size and corners. Docker does the same for servers: any host with a container runtime can run any image, whatever language or libraries are packed inside.', 'fun-fact'],
        ['media', 'Three containers on one kernel', ['media' => ['file' => "{$media}/anatomy.png", 'alt' => 'Three containers, web, db and proxy, on one shared Linux kernel. Each has a writable layer over read-only image layers; db also has a volume. Namespaces, cgroups, capabilities, seccomp and overlayfs fence them.'], 'caption' => 'web runs gunicorn over a venv and python:3.13-slim; db keeps its data in the db-data volume; proxy runs nginx. Each writable layer belongs to one container; the image layers and the kernel are shared.']],

        // 5. What a container really is on Linux
        ['prose', 'What a container really is on Linux', <<<'MD'
Linux has no "container" object. A container is the combination of five kernel features applied to an ordinary process. Docker (through runc) switches them on when it starts your program:

1. **Namespaces** decide what the process can *see*.
2. **Control groups (cgroups)** decide how much it can *use*.
3. **Capabilities** decide which privileged actions its root user may *take*.
4. **Seccomp** filters which system calls it may *make* at all.
5. A **layered filesystem** (overlayfs) gives it its own *root directory*.

Take away all five and you are left with a normal process on the host. That is the honest picture to keep: the isolation is exactly as strong as these kernel features and their configuration.
MD],
        ['prose', 'Namespaces: what a container can see', <<<'MD'
A **namespace** wraps one kind of global resource so that the processes inside see their own instance of it. A container gets a new namespace of each kind below:

- **pid**: its own process numbers. The first process inside is **PID 1** and cannot see host processes.
- **net**: its own network interfaces, routing table, firewall rules and ports. Port 80 inside one container does not clash with port 80 in another.
- **mnt**: its own list of mounts, starting from the image's root filesystem.
- **uts**: its own hostname (by default, the short container ID).
- **ipc**: its own System V shared memory and message queues.
- **cgroup**: its own view of the cgroup tree, so it sees its own limits at `/sys/fs/cgroup`.
- **user**: maps user IDs, so root inside can be an unprivileged user outside. Docker does **not** do this by default; you turn it on with `userns-remap` or by running Docker in **rootless mode**.

You can see the namespaces of any process under `/proc/<pid>/ns`. Inside a container, `ps` sees only the container's own processes, and the first of them is PID 1.
MD, 'sources' => [
            ['label' => 'namespaces(7), Linux manual page', 'url' => 'https://man7.org/linux/man-pages/man7/namespaces.7.html', 'kind' => 'docs'],
        ]],
        ['code', 'See the namespaces yourself', <<<'SH'
# The namespaces of the shell inside a fresh container
docker run --rm alpine:3.20 ls /proc/self/ns
# cgroup  ipc  mnt  net  pid  pid_for_children  time  time_for_children  user  uts

# Inside, the process list starts again at 1
docker run --rm alpine:3.20 ps
# PID   USER     TIME  COMMAND
#     1 root      0:00 ps

# Its own hostname (the short container ID) and network interface
docker run --rm alpine:3.20 sh -c 'hostname; ip -4 addr show eth0 | grep inet'
SH, 'bash'],
        ['prose', 'cgroups v2: how much a container can use', <<<'MD'
**Control groups** put processes in a group and apply limits and accounting to the whole group: memory, CPU time, number of processes, block I/O. Every modern distribution uses **cgroup v2**, a single unified tree mounted at `/sys/fs/cgroup`, and Docker creates one group per container.

Without limits, a container may use all the host's memory and CPU, so one runaway process can starve every other container: a denial of service from the inside. The `docker run` flags translate directly into cgroup files: `--memory 256m` becomes `memory.max` (in bytes), `--cpus 0.5` becomes `cpu.max` (50 ms of CPU in every 100 ms period), `--pids-limit 100` becomes `pids.max`. When a container hits its memory limit, the kernel's out-of-memory killer ends a process inside it, not some innocent process elsewhere on the host.
MD, 'sources' => [
            ['label' => 'Control Group v2, Linux kernel documentation', 'url' => 'https://docs.kernel.org/admin-guide/cgroup-v2.html', 'kind' => 'docs'],
        ]],
        ['code', 'Watch a limit become a cgroup file', <<<'SH'
docker run --rm --memory 256m --cpus 0.5 --pids-limit 100 alpine:3.20 \
  cat /sys/fs/cgroup/memory.max /sys/fs/cgroup/cpu.max /sys/fs/cgroup/pids.max
# 268435456        (256 MiB in bytes)
# 50000 100000     (50 ms of CPU per 100 ms: half a CPU)
# 100

# Live usage of running containers, read from the same cgroups
docker stats --no-stream
SH, 'bash'],
        ['prose', 'Capabilities: what root may do', <<<'MD'
Traditionally, Linux had two kinds of process: root, which could do anything, and everyone else. **Capabilities** split root's power into about forty separate privileges: `CAP_NET_BIND_SERVICE` to listen on ports below 1024, `CAP_CHOWN` to change file owners, `CAP_SYS_ADMIN` for a huge list of administrative actions, and so on.

Root inside a container is **not** full root. Docker starts containers with a short allow-list of 14 capabilities and drops the rest, including `CAP_SYS_ADMIN`, `CAP_NET_ADMIN`, `CAP_SYS_MODULE` (loading kernel modules) and `CAP_SYS_PTRACE`. You can read the result from the kernel: the `CapEff` line of `/proc/self/status` is a hexadecimal bitmask of the **effective** capabilities, one bit per capability number.

In a default container on Docker Engine 29 it reads `00000000a80425fb`. The top 32 bits are all zero (capabilities 32 to 40, such as `CAP_BPF`, are all dropped). The block below decodes the low 32 bits, most significant byte first: each lit bit is a capability the container keeps.
MD, 'sources' => [
            ['label' => 'capabilities(7), Linux manual page', 'url' => 'https://man7.org/linux/man-pages/man7/capabilities.7.html', 'kind' => 'docs'],
        ]],
        ['bitfield', 'Decoding CapEff a80425fb', [
            'rows' => [
                ['label' => 'Byte 3 (a8): bits 31-24', 'fields' => [
                    ['label' => '31 SETFCAP', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '30 AUDIT_CONTROL', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '29 AUDIT_WRITE', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '28 LEASE', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '27 MKNOD', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '26 SYS_TTY_CONFIG', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '25 SYS_TIME', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '24 SYS_RESOURCE', 'bits' => '0', 'tone' => 'muted'],
                ]],
                ['label' => 'Byte 2 (04): bits 23-16', 'fields' => [
                    ['label' => '23 SYS_NICE', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '22 SYS_BOOT', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '21 SYS_ADMIN', 'bits' => '0', 'tone' => 'attn'],
                    ['label' => '20 SYS_PACCT', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '19 SYS_PTRACE', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '18 SYS_CHROOT', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '17 SYS_RAWIO', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '16 SYS_MODULE', 'bits' => '0', 'tone' => 'attn'],
                ]],
                ['label' => 'Byte 1 (25): bits 15-8', 'fields' => [
                    ['label' => '15 IPC_OWNER', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '14 IPC_LOCK', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '13 NET_RAW', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '12 NET_ADMIN', 'bits' => '0', 'tone' => 'attn'],
                    ['label' => '11 NET_BROADCAST', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '10 NET_BIND_SERVICE', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '9 LINUX_IMMUTABLE', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '8 SETPCAP', 'bits' => '1', 'tone' => 'accent'],
                ]],
                ['label' => 'Byte 0 (fb): bits 7-0', 'fields' => [
                    ['label' => '7 SETUID', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '6 SETGID', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '5 KILL', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '4 FSETID', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '3 FOWNER', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '2 DAC_READ_SEARCH', 'bits' => '0', 'tone' => 'muted'],
                    ['label' => '1 DAC_OVERRIDE', 'bits' => '1', 'tone' => 'accent'],
                    ['label' => '0 CHOWN', 'bits' => '1', 'tone' => 'accent'],
                ]],
            ],
        ]],
        ['prose', 'Reading the mask', <<<'MD'
Fourteen bits are set: `CHOWN`, `DAC_OVERRIDE`, `FOWNER`, `FSETID`, `KILL`, `SETGID`, `SETUID`, `SETPCAP`, `NET_BIND_SERVICE`, `NET_RAW`, `SYS_CHROOT`, `MKNOD`, `AUDIT_WRITE` and `SETFCAP`. They are what a typical service needs to start up (switch user, own its files, bind port 80). The ones highlighted as dangerous, such as `SYS_ADMIN`, `SYS_MODULE` and `NET_ADMIN`, are off, and they are exactly what most container escapes need.

Check it yourself with `docker run --rm alpine:3.20 grep Cap /proc/self/status`. Then add `--cap-drop ALL` and watch `CapEff` become all zeros: most web applications run perfectly well like that, as the hardened example later shows. `NET_RAW` is worth dropping in particular: it lets a process forge raw packets, which is useful for ping and for attackers spoofing traffic on the container network.
MD],
        ['prose', 'Seccomp: which system calls are allowed', <<<'MD'
Even with few capabilities, a process talks to the kernel through **system calls**, and every system call is attack surface. **Seccomp** (secure computing mode) attaches a filter to a process that the kernel checks on every system call. Docker applies a **default seccomp profile** that blocks about 44 of the more than 300 system calls, ones containers rarely need but attackers love: `kexec_load` (load a new kernel), `reboot`, `open_by_handle_at` (used in a well-known 2014 escape), `keyctl`, `mount`, and more.

You can confirm it is on: `docker info` lists `name=seccomp,profile=builtin` under Security Options, and inside a container `/proc/self/status` shows `Seccomp: 2` (filter mode). Running with `--security-opt seccomp=unconfined` removes the filter, and `--privileged` removes it *together with* every capability limit and device restriction. Treat both as "this is not a container any more".
MD, 'sources' => [
            ['label' => 'Docker docs: Seccomp security profiles for Docker', 'url' => 'https://docs.docker.com/engine/security/seccomp/', 'kind' => 'docs'],
        ]],
        ['prose', 'Overlayfs: a container\'s own root directory', <<<'MD'
An image is a stack of read-only layers, but a running program needs to write files. **Overlayfs**, a union filesystem in the Linux kernel, solves this without copying anything: it stacks directories and presents them as one.

The read-only image layers are the **lowerdirs**. On top sits the container's **upperdir**, its writable layer. The **merged** directory is the combined view that becomes the container's `/`. Reading a file finds the topmost layer that has it. Writing to a file from a lower layer first **copies it up** into the upperdir, then changes the copy; the image itself is never modified. Deleting a file from a lower layer creates a **whiteout** marker in the upperdir that hides it.

Docker Engine 29 keeps these layers through containerd's **overlayfs snapshotter** (older installations use Docker's own `overlay2` driver, which works the same way). Select a node in the tree to read what each part does.
MD, 'sources' => [
            ['label' => 'Overlay Filesystem, Linux kernel documentation', 'url' => 'https://docs.kernel.org/filesystems/overlayfs.html', 'kind' => 'docs'],
        ]],
        ['tree', 'The layers behind a container\'s /', [
            'nodes' => [
                ['id' => 'merged', 'label' => 'merged: the container sees /', 'note' => 'union view', 'detail' => 'Not stored anywhere: the kernel builds this view by looking through the layers from the top. It is mounted as the root of the container\'s mount namespace.'],
                ['id' => 'upper', 'label' => 'upperdir: writable layer', 'parent' => 'merged', 'note' => 'one per container', 'detail' => 'Every file the container creates or changes lands here, including copies of image files it modifies (copy-up) and whiteouts for files it deletes. It is deleted with the container by docker rm.'],
                ['id' => 'lower', 'label' => 'lowerdirs: image layers', 'parent' => 'merged', 'note' => 'read-only, shared', 'detail' => 'The image layers, top layer first. Many containers of the same image use the same lowerdirs at once, which is why a hundred containers of one image cost little more disk than one.'],
                ['id' => 'l3', 'label' => 'COPY app.py', 'parent' => 'lower', 'note' => 'a few KB', 'detail' => 'The last layer of the example image: the application code. Because it comes last, editing the code rebuilds only this layer.'],
                ['id' => 'l2', 'label' => 'COPY --from=build /venv', 'parent' => 'lower', 'note' => 'dependencies', 'detail' => 'The virtual environment with Flask, gunicorn and psycopg, copied from the build stage.'],
                ['id' => 'l1', 'label' => 'python:3.13-slim layers', 'parent' => 'lower', 'note' => 'base image', 'detail' => 'The Debian userland and Python interpreter. Shared by every image built on the same base, and downloaded only once per host.'],
                ['id' => 'work', 'label' => 'workdir: overlayfs scratch space', 'parent' => 'merged', 'note' => 'internal', 'detail' => 'An empty directory overlayfs needs on the same filesystem as the upperdir, to make copy-up and rename atomic. You never use it directly.'],
            ],
        ]],
        ['scene-3d', 'One kernel, three rooms', [
            'title' => 'Containers as rooms on a shared kernel floor',
            'description' => 'A dark slab labelled Host kernel forms the floor. Three translucent rooms stand on it, labelled Container A, B and C; inside each one an orange ball marked PID 1 floats. Dashed lines with moving dots run from each ball to the middle of the floor: system calls, the only way a process gets anything done, all handled by the same kernel. A label above says namespaces are the walls. The guided steps explain, in order: each room\'s walls are its namespaces, so a process sees only its own room; every room stands on the same kernel floor and talks to it through system calls; cgroups set how big each room may grow, so container B cannot take the others\' memory or CPU; capabilities and seccomp filter what may be asked of the floor; and a hole in the floor, a kernel vulnerability, would open every room at once, which is why the host kernel must be patched and why untrusted tenants get virtual machines.',
            'caption' => 'Select a room, a process or the floor to read what it stands for.',
            'aspect' => '16:9',
            'camera' => ['position' => [0, 4.2, 9.5], 'target' => [0, 0.8, 0], 'fov' => 50],
            'lights' => [
                ['type' => 'hemisphere', 'intensity' => 1.4],
                ['type' => 'directional', 'intensity' => 1.6, 'position' => [4, 8, 6]],
            ],
            'objects' => [
                ['id' => 'kernel', 'shape' => 'box', 'size' => [10, 0.35, 4.2], 'position' => [0, -0.18, 0], 'color' => '#334155', 'text' => 'Host kernel', 'info' => ['title' => 'The shared host kernel', 'text' => 'One Linux kernel serves every container. It enforces the walls (namespaces), the room sizes (cgroups) and the filters (capabilities, seccomp). Nothing inside a container replaces it.']],
                ['id' => 'room-a', 'shape' => 'box', 'size' => [2.6, 1.9, 2.6], 'position' => [-3.4, 0.95, 0], 'color' => '#38bdf8', 'opacity' => 0.22, 'text' => 'Container A', 'info' => ['title' => 'Container A', 'text' => 'Its own process numbers, network interface, hostname and mounts: the pid, net, uts and mnt namespaces. From inside, nothing else on the host is visible.']],
                ['id' => 'room-b', 'shape' => 'box', 'size' => [2.6, 1.9, 2.6], 'position' => [0, 0.95, 0], 'color' => '#38bdf8', 'opacity' => 0.22, 'text' => 'Container B', 'info' => ['title' => 'Container B', 'text' => 'The same kind of room, with its own walls. Its cgroup limits (memory.max, cpu.max, pids.max) cap how much of the host it can use.']],
                ['id' => 'room-c', 'shape' => 'box', 'size' => [2.6, 1.9, 2.6], 'position' => [3.4, 0.95, 0], 'color' => '#38bdf8', 'opacity' => 0.22, 'text' => 'Container C', 'info' => ['title' => 'Container C', 'text' => 'Containers on the same user-defined network can talk to each other through their network namespaces; otherwise the walls keep them apart.']],
                ['id' => 'pid-a', 'shape' => 'sphere', 'size' => [0.5, 0.5, 0.5], 'position' => [-3.4, 1.1, 0], 'color' => '#f59e0b', 'text' => 'PID 1', 'animation' => ['type' => 'bob', 'speed' => 1.2, 'amount' => 0.15], 'info' => ['title' => 'PID 1 in container A', 'text' => 'An ordinary host process. Inside its pid namespace it is number 1; on the host it has a normal, much larger number.']],
                ['id' => 'pid-b', 'shape' => 'sphere', 'size' => [0.5, 0.5, 0.5], 'position' => [0, 1.1, 0], 'color' => '#f59e0b', 'text' => 'PID 1', 'animation' => ['type' => 'bob', 'speed' => 1.0, 'amount' => 0.15], 'info' => ['title' => 'PID 1 in container B', 'text' => 'PID 1 has a special job: it receives the stop signal and must reap child processes. Run your program in exec form so it is PID 1 itself.']],
                ['id' => 'pid-c', 'shape' => 'sphere', 'size' => [0.5, 0.5, 0.5], 'position' => [3.4, 1.1, 0], 'color' => '#f59e0b', 'text' => 'PID 1', 'animation' => ['type' => 'bob', 'speed' => 1.4, 'amount' => 0.15], 'info' => ['title' => 'PID 1 in container C', 'text' => 'Every request it makes, from reading a file to opening a socket, is a system call into the shared kernel.']],
                ['id' => 'walls-label', 'shape' => 'label', 'text' => 'Walls = namespaces', 'position' => [0, 2.6, 0]],
            ],
            'links' => [
                ['from' => 'pid-a', 'to' => 'kernel', 'dashed' => true, 'label' => 'syscalls', 'flow' => ['count' => 2, 'speed' => 0.8]],
                ['from' => 'pid-b', 'to' => 'kernel', 'dashed' => true, 'flow' => ['count' => 2, 'speed' => 0.8]],
                ['from' => 'pid-c', 'to' => 'kernel', 'dashed' => true, 'flow' => ['count' => 2, 'speed' => 0.8]],
            ],
            'steps' => [
                ['title' => 'Rooms on one floor', 'text' => 'Three containers on one host. Select anything to read what it stands for, or step through.'],
                ['title' => 'The walls are namespaces', 'text' => 'Each room has its own process list, network interface, hostname and mounts. Container A cannot see B\'s processes or files, because the kernel shows each one only its own instances.', 'highlight' => ['room-a', 'room-b', 'room-c'], 'camera' => ['position' => [-2, 3, 6.5], 'target' => [-1.5, 0.9, 0]]],
                ['title' => 'One floor for everyone', 'text' => 'All three processes stand on the same kernel and reach it the same way: system calls. There is no second kernel, which is what makes containers small and fast.', 'highlight' => ['kernel', 'pid-a', 'pid-b', 'pid-c'], 'camera' => ['position' => [0, 2.2, 8], 'target' => [0, 0.4, 0]]],
                ['title' => 'cgroups set the room size', 'text' => 'Container B is limited to its share of memory, CPU and process numbers. If it runs away, the kernel stops it at its limit instead of letting it starve A and C.', 'highlight' => ['room-b', 'pid-b'], 'camera' => ['position' => [0, 3, 5.5], 'target' => [0, 0.9, 0]]],
                ['title' => 'Filters on the floor', 'text' => 'Capabilities decide which privileged requests root inside a room may make, and seccomp blocks dangerous system calls outright, such as loading a new kernel.', 'highlight' => ['kernel'], 'camera' => ['position' => [3, 3, 7], 'target' => [0, 0.2, 0]]],
                ['title' => 'A hole in the floor', 'text' => 'Because the floor is shared, a kernel vulnerability can open every room at once. Patch the host kernel, keep the filters on, and give untrusted tenants virtual machines.', 'highlight' => ['kernel', 'room-a', 'room-b', 'room-c'], 'camera' => ['position' => [0, 4.2, 9.5], 'target' => [0, 0.8, 0]]],
            ],
        ]],
        ['knowledge-check', 'Check: inside a container', [
            'questions' => [
                [
                    'prompt' => 'A container\'s /proc/self/status shows CapEff: 00000000a80425fb. Can a process in it load a kernel module?',
                    'options' => [
                        ['text' => 'No: bit 16 (CAP_SYS_MODULE) is not set', 'correct' => true],
                        ['text' => 'Yes, because the process runs as root', 'correct' => false],
                        ['text' => 'Yes, because NET_ADMIN is enough to load network drivers', 'correct' => false],
                    ],
                    'explanation' => 'Loading a module needs CAP_SYS_MODULE, bit 16, and byte 2 of the mask is 04: only bit 18 (SYS_CHROOT) is set. Being root inside the container does not help, because capabilities limit root. NET_ADMIN (bit 12) is also off, and it never allowed loading modules anyway.',
                ],
                [
                    'prompt' => 'A process in a container rewrites /etc/hosts, a file that came from the image. What happens on disk?',
                    'options' => [
                        ['text' => 'The file is copied up into the container\'s writable layer, and the copy is changed', 'correct' => true],
                        ['text' => 'The image layer is changed for every container that uses it', 'correct' => false],
                        ['text' => 'The write fails, because image layers are read-only', 'correct' => false],
                    ],
                    'explanation' => 'Overlayfs copies the file from the lower (image) layer into the upperdir on first write, then changes the copy. Image layers are never modified, so other containers are unaffected; and the write succeeds unless the container itself was started with a read-only root filesystem.',
                ],
                [
                    'prompt' => 'Which of these change what a containerised process can see? Choose all that apply.',
                    'options' => [
                        ['text' => 'Its pid namespace', 'correct' => true],
                        ['text' => 'Its mount namespace', 'correct' => true],
                        ['text' => 'Its memory limit in cgroups', 'correct' => false],
                        ['text' => 'Its seccomp profile', 'correct' => false],
                    ],
                    'explanation' => 'Namespaces change the view: the pid namespace hides other processes and the mount namespace gives the container its own filesystem tree. A cgroup limit changes how much the process may use, and seccomp changes which system calls it may make; neither changes what it sees.',
                ],
            ],
        ]],

        // 6. How the concepts connect: build, ship, run
        ['prose', 'Build, ship, run', <<<'MD'
With the kernel side clear, the Docker side is easier to follow. A **Dockerfile** and the files next to it (the **build context**) go to **BuildKit**, Docker's builder, which executes the instructions and produces an **image**. An image is three kinds of object, all named by their SHA-256 digest: the **layers** (compressed tar archives of filesystem changes), the **config** (command, user, environment, and the list of layer digests) and the **manifest** (the config's digest plus the layers' digests). A multi-platform image adds an **index** that points to one manifest per platform, such as `linux/amd64` and `linux/arm64`.

Because everything is content-addressed, pushing an image to a **registry** only uploads layers the registry does not have yet, and pulling only downloads layers the host does not have. A tag such as `myapp:1.4` is just a name the registry maps to a manifest digest. Finally, **running** the image creates a container: the image's layers as lowerdirs, a new writable layer, and the configuration from the image plus your `docker run` options.
MD],
        ['flow', 'From Dockerfile to running container', [
            'direction' => 'right',
            'nodes' => [
                ['id' => 'src', 'label' => 'Dockerfile + context', 'kind' => 'start', 'detail' => 'The recipe and the files it may COPY. .dockerignore decides which files are sent to the builder at all.'],
                ['id' => 'build', 'label' => 'docker build (BuildKit)', 'detail' => 'Runs each instruction, reusing cached results when an instruction and its inputs have not changed. Independent stages build in parallel.'],
                ['id' => 'image', 'label' => 'Image: layers + config + manifest', 'detail' => 'Every part is named by its SHA-256 digest, so the same content is stored once and verified on every download.'],
                ['id' => 'registry', 'label' => 'Registry', 'detail' => 'docker push uploads only missing layers. The tag (myapp:1.4) points to a manifest digest; the digest never changes.'],
                ['id' => 'pull', 'label' => 'docker pull on a server', 'detail' => 'Resolves the tag to a digest, picks the manifest for the host\'s platform and downloads only missing layers.'],
                ['id' => 'run', 'label' => 'docker run', 'detail' => 'Adds a writable layer, namespaces, cgroups and your options (ports, volumes, limits) and starts the image\'s command.'],
                ['id' => 'container', 'label' => 'Running container', 'kind' => 'end', 'detail' => 'Processes on the host kernel. Many containers can run from one image at the same time.'],
            ],
            'edges' => [
                ['from' => 'src', 'to' => 'build'],
                ['from' => 'build', 'to' => 'image'],
                ['from' => 'image', 'to' => 'registry', 'label' => 'push'],
                ['from' => 'registry', 'to' => 'pull'],
                ['from' => 'pull', 'to' => 'run'],
                ['from' => 'run', 'to' => 'container'],
            ],
        ]],
        ['prose', 'How the build cache works', <<<'MD'
BuildKit caches the result of every instruction. Before running an instruction it checks whether it has run **the same instruction on the same parent** before, and for `COPY` and `ADD` it also compares a checksum of the files being copied. If everything matches, it reuses the stored layer; the first instruction that differs, and **every instruction after it**, runs again.

That one rule explains most Dockerfile advice: put what changes rarely (the base image, system packages, dependency lists) first, and what changes often (your source code) last. The walkthrough below builds the post's example image instruction by instruction. Watch the "re-run after editing app.py?" value: the goal is for it to stay "no" until the very last step.
MD, 'sources' => [
            ['label' => 'Docker docs: Docker build cache', 'url' => 'https://docs.docker.com/build/cache/', 'kind' => 'docs'],
        ]],
        ['step-through', 'Building the example image, one instruction at a time', [
            'steps' => [
                ['title' => 'FROM python:3.13-slim AS build', 'text' => 'The build stage starts from the official slim Python image. Its layers are pulled once and reused by every later build on this host. Nothing is created yet.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted']], 'state' => [['label' => 'Stage', 'value' => 'build'], ['label' => 'This step adds', 'value' => 'nothing (base image)'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'RUN python -m venv /venv', 'text' => 'Creates an empty virtual environment. RUN executes a command in a temporary container and saves the changed files as a new layer.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => '/venv (empty)', 'tone' => 'plain']], 'state' => [['label' => 'Stage', 'value' => 'build'], ['label' => 'This step adds', 'value' => 'a layer'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'COPY requirements.txt /tmp/requirements.txt', 'text' => 'Only the dependency list is copied, not the whole project. Its checksum becomes part of the cache key, so this layer is reused until requirements.txt itself changes.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => '/venv (empty)', 'tone' => 'plain'], ['label' => 'requirements.txt', 'tone' => 'plain']], 'state' => [['label' => 'Stage', 'value' => 'build'], ['label' => 'This step adds', 'value' => 'a layer (keyed by the file\'s checksum)'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'RUN --mount=type=cache … pip install', 'text' => 'The slow step: downloading and installing Flask, gunicorn and psycopg. It depends only on the layers above, so it is reused on every code change. The cache mount keeps pip\'s download cache between builds without putting it in the image.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => '/venv (packages)', 'tone' => 'accent'], ['label' => 'requirements.txt', 'tone' => 'plain']], 'state' => [['label' => 'Stage', 'value' => 'build'], ['label' => 'This step adds', 'value' => 'a layer (the big one)'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'FROM python:3.13-slim (second stage)', 'text' => 'A fresh start. Nothing from the build stage comes along unless it is copied explicitly, so compilers, caches and build files never reach the final image.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted']], 'state' => [['label' => 'Stage', 'value' => 'runtime'], ['label' => 'This step adds', 'value' => 'nothing (base image)'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'RUN useradd --system --uid 10001 … app', 'text' => 'Creates an unprivileged user for the app, with a fixed UID so file ownership and policies can refer to it.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => 'user app', 'tone' => 'plain']], 'state' => [['label' => 'Stage', 'value' => 'runtime'], ['label' => 'This step adds', 'value' => 'a layer'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'COPY --from=build /venv /venv', 'text' => 'Copies only the finished virtual environment out of the build stage. This is what makes the build multi-stage: one image builds, another runs.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => 'user app', 'tone' => 'plain'], ['label' => '/venv', 'tone' => 'accent']], 'state' => [['label' => 'Stage', 'value' => 'runtime'], ['label' => 'This step adds', 'value' => 'a layer'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'ENV and WORKDIR', 'text' => 'ENV only changes the image config (PATH, unbuffered output). WORKDIR sets the working directory and creates /app if it is missing.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => 'user app', 'tone' => 'plain'], ['label' => '/venv', 'tone' => 'accent'], ['label' => '/app', 'tone' => 'plain']], 'state' => [['label' => 'Stage', 'value' => 'runtime'], ['label' => 'This step adds', 'value' => 'config (and the /app directory)'], ['label' => 'Re-run after editing app.py?', 'value' => 'no']]],
                ['title' => 'COPY app.py .', 'text' => 'The code goes in last. Editing app.py changes this checksum, so this is the only instruction that runs again: a rebuild takes seconds instead of minutes.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => 'user app', 'tone' => 'plain'], ['label' => '/venv', 'tone' => 'accent'], ['label' => '/app', 'tone' => 'plain'], ['label' => 'app.py', 'tone' => 'attn']], 'state' => [['label' => 'Stage', 'value' => 'runtime'], ['label' => 'This step adds', 'value' => 'a layer (a few KB)'], ['label' => 'Re-run after editing app.py?', 'value' => 'yes: the first step that changes']]],
                ['title' => 'USER, EXPOSE, HEALTHCHECK, CMD', 'text' => 'These only write the image config: run as app, document port 8000, how to check health, and the command in exec form. The final image is the runtime stage\'s layers plus this config; the build stage is left behind.', 'parts' => [['label' => 'python:3.13-slim', 'tone' => 'muted'], ['label' => 'user app', 'tone' => 'plain'], ['label' => '/venv', 'tone' => 'accent'], ['label' => '/app', 'tone' => 'plain'], ['label' => 'app.py', 'tone' => 'attn'], ['label' => 'config', 'tone' => 'muted']], 'state' => [['label' => 'Stage', 'value' => 'runtime (final image)'], ['label' => 'This step adds', 'value' => 'config only'], ['label' => 'Re-run after editing app.py?', 'value' => 'config is rewritten, instantly']]],
            ],
        ], 'sources' => [
            ['label' => 'Docker docs: Dockerfile reference', 'url' => 'https://docs.docker.com/reference/dockerfile/', 'kind' => 'docs'],
            ['label' => 'Docker docs: Multi-stage builds', 'url' => 'https://docs.docker.com/build/building/multi-stage/', 'kind' => 'docs'],
        ]],
        // "In other words" (F6 dogfood run): the recap after the densest block, pinned to the section the import created.
        ['callout', null, 'Picture the Dockerfile as a stack of plates: change one plate and every plate above it has to be washed again, while the plates below stay clean. So put what rarely changes (the base image, the dependencies) at the bottom and your own code at the top. The second FROM is a clean table: only what you carry over with COPY --from reaches the image you ship.', 'recap', 'uuid' => '99530de3-fd85-55f2-91b8-125d3c68b3c4'],

        // 7. How the concepts connect: runtime side
        ['prose', 'What happens on docker run', <<<'MD'
Now follow one command all the way down: `docker run -p 8080:80 nginx` on a host that has never pulled nginx. Several separate programs cooperate, and knowing who does what tells you where to look when something breaks:

- The **`docker` CLI** only builds HTTP requests to the Docker Engine API on `/var/run/docker.sock`.
- **`dockerd`** manages images, containers, networks and volumes, and keeps their state.
- **containerd** supervises the container's life on behalf of dockerd (and, on Engine 29, stores the images).
- **containerd-shim-runc-v2**, one small process per container, stays behind as the container's parent. That is why you can restart dockerd or containerd without stopping your containers.
- **runc** does the kernel work: it creates the namespaces and cgroup, mounts the root filesystem, applies capabilities and seccomp, then replaces itself with your program and exits.
MD],
        ['sequence', 'docker run -p 8080:80 nginx, step by step', [
            'actors' => [
                ['id' => 'cli', 'name' => 'docker CLI'],
                ['id' => 'dockerd', 'name' => 'dockerd'],
                ['id' => 'registry', 'name' => 'Registry'],
                ['id' => 'containerd', 'name' => 'containerd'],
                ['id' => 'runc', 'name' => 'shim + runc'],
            ],
            'messages' => [
                ['from' => 'cli', 'to' => 'dockerd', 'label' => 'create', 'detail' => 'POST /containers/create', 'explanation' => 'The CLI turns your command line into JSON and sends it to the Engine API over the local Unix socket. Anyone who can write to that socket controls the engine.'],
                ['from' => 'dockerd', 'to' => 'cli', 'label' => '404', 'detail' => 'no such image', 'explanation' => 'nginx:latest is not on this host, so creation fails and the CLI prints "Unable to find image locally".'],
                ['from' => 'cli', 'to' => 'dockerd', 'label' => 'pull', 'detail' => 'POST /images/create', 'explanation' => 'The CLI asks the engine to pull the image, then will retry the create. docker pull on its own sends the same request.'],
                ['from' => 'dockerd', 'to' => 'registry', 'label' => 'manifest', 'detail' => 'tag → digest', 'explanation' => 'The engine resolves the tag latest to a digest, reads the index and chooses the manifest for this host\'s platform, for example linux/amd64.'],
                ['from' => 'registry', 'to' => 'dockerd', 'label' => 'layers', 'detail' => 'blobs by digest', 'explanation' => 'Each missing layer is downloaded and its SHA-256 checked against the manifest, so a tampered or corrupted layer is refused. On Engine 29 containerd stores and unpacks them through its snapshotter.'],
                ['from' => 'cli', 'to' => 'dockerd', 'label' => 'create', 'detail' => 'retry: 201 + id', 'explanation' => 'Now the container is created: a writable layer, the merged configuration (image config plus your options) and a planned network endpoint. Nothing runs yet; docker ps -a shows it as Created.'],
                ['from' => 'cli', 'to' => 'dockerd', 'label' => 'start', 'detail' => 'POST /containers/{id}/start', 'explanation' => 'docker run is create plus start (plus attach when not detached). docker create and docker start do the same steps separately.'],
                ['from' => 'dockerd', 'to' => 'containerd', 'label' => 'create task', 'detail' => 'gRPC, OCI spec', 'explanation' => 'dockerd writes an OCI runtime spec (config.json: the process, user, mounts, namespaces, cgroup limits, capabilities, seccomp profile) and asks containerd to run it.'],
                ['from' => 'containerd', 'to' => 'runc', 'label' => 'start shim', 'detail' => 'containerd-shim-runc-v2', 'explanation' => 'containerd starts a shim for this container. The shim calls runc, keeps the container\'s input and output open and collects its exit status, so the container does not depend on containerd staying up.'],
                ['from' => 'runc', 'to' => 'containerd', 'label' => 'created', 'detail' => 'namespaces, cgroup, rootfs', 'explanation' => 'runc creates the namespaces, puts the process in a new cgroup with your limits, mounts the overlay root filesystem and switches to it, drops capabilities and loads the seccomp filter.'],
                ['from' => 'dockerd', 'to' => 'runc', 'label' => 'network', 'detail' => 'veth + docker0 + DNAT', 'explanation' => 'Docker\'s network code creates a veth pair, moves one end into the container\'s network namespace as eth0 with an address such as 172.17.0.2, plugs the other end into the docker0 bridge, and adds a firewall rule that forwards host port 8080 to 172.17.0.2:80.'],
                ['from' => 'containerd', 'to' => 'runc', 'label' => 'start', 'detail' => 'exec nginx as PID 1', 'explanation' => 'runc replaces itself with the image\'s command, so nginx becomes PID 1 in the container, and runc exits. From here the shim is nginx\'s parent.'],
                ['from' => 'dockerd', 'to' => 'cli', 'label' => '204', 'detail' => 'started', 'explanation' => 'The CLI prints the container ID (detached) or attaches to its output. curl http://localhost:8080 now reaches nginx through the port forwarding.'],
            ],
        ]],
        ['prose', 'Following one request into the container', <<<'MD'
The last two messages hide the most interesting plumbing. When a browser on another machine asks for `http://192.0.2.10:8080`, the packet arrives at the host's network card addressed to port 8080. Nothing on the host listens there in the usual sense. Instead:

1. A **DNAT** (destination NAT) rule that Docker added to the host's firewall (iptables by default; recent Docker Engine releases can use nftables instead, as an opt-in) rewrites the destination from `192.0.2.10:8080` to `172.17.0.2:80`.
2. The host routes the rewritten packet to the **docker0** bridge, a virtual switch that connects the containers on the default network.
3. The bridge forwards it, by MAC address, into the host end of the container's **veth pair**: two virtual interfaces joined like the two ends of a cable.
4. The packet comes out of the other end, which is **eth0** inside the container's network namespace, and nginx, listening on port 80, receives it.

The reply takes the same path backwards, and the firewall's connection tracking translates the addresses back. Connections from the host itself to `localhost:8080` are handled by a small helper, **docker-proxy**, because locally generated traffic does not pass through the same firewall chain.
MD],
        ['script-3d', 'A packet\'s path into a container', [
            'title' => 'From host port 8080 to nginx on port 80',
            'description' => 'Five blocks stand in a row, joined by a cable, from left to right: Host :8080, the DNAT rule, the docker0 bridge, the veth pair and eth0 :80 inside a translucent box marked container network namespace. An orange ball, a packet, travels along the cable from left to right and starts again. While it is left of the DNAT rule its label reads to 192.0.2.10:8080; once it passes the rule the label changes to to 172.17.0.2:80, showing the destination being rewritten. It then crosses the bridge and the veth pair and arrives at eth0, where nginx listens on port 80. With reduced motion the packet waits at its destination with the rewritten label. The same path is described step by step in the text above this block.',
            'caption' => 'Drag to look around. The label under the packet shows its destination address.',
            'aspect' => '16:9',
            'code' => <<<'JS'
const dark = theme === "dark";
scene.add(new THREE.HemisphereLight(0xffffff, dark ? 0x1e293b : 0xcbd5e1, 1.6));
const sun = new THREE.DirectionalLight(0xffffff, 1.4);
sun.position.set(3, 6, 4);
scene.add(sun);

// The stops along the path, left to right.
const stops = [
  { x: -5, name: "Host :8080", color: 0x64748b, h: 1.0 },
  { x: -2.5, name: "DNAT rule", color: 0xf97316, h: 0.8 },
  { x: 0, name: "docker0 bridge", color: 0x0ea5e9, h: 0.6 },
  { x: 2.5, name: "veth pair", color: 0xa855f7, h: 0.6 },
  { x: 5, name: "eth0 :80 (nginx)", color: 0x14b8a6, h: 1.2 },
];
stops.forEach((stop, i) => {
  const block = new THREE.Mesh(
    new THREE.BoxGeometry(1.2, stop.h, 1.2),
    new THREE.MeshStandardMaterial({ color: stop.color, roughness: 0.5 }),
  );
  block.position.set(stop.x, stop.h / 2, 0);
  scene.add(block);
  // Neighbouring labels alternate in height so they never overlap.
  const tag = label(stop.name, { scale: 1.3 });
  tag.position.set(stop.x, stop.h + 0.55 + (i % 2) * 0.7, 0);
  scene.add(tag);
});

// The cable, and the container's network namespace around eth0.
const cable = new THREE.Mesh(
  new THREE.CylinderGeometry(0.05, 0.05, 10, 12),
  new THREE.MeshStandardMaterial({ color: dark ? 0x94a3b8 : 0x64748b }),
);
cable.rotation.z = Math.PI / 2;
cable.position.set(0, 0.25, 0);
scene.add(cable);
const netns = new THREE.Mesh(
  new THREE.BoxGeometry(2.4, 2.4, 2.4),
  new THREE.MeshStandardMaterial({ color: 0x14b8a6, transparent: true, opacity: 0.14, depthWrite: false }),
);
netns.position.set(5, 1.2, 0);
scene.add(netns);
const nsTag = label("container network namespace", { scale: 1.1 });
nsTag.position.set(4.2, 3.4, 0);
scene.add(nsTag);

// The packet, with its destination address above it.
const packet = new THREE.Mesh(
  new THREE.SphereGeometry(0.22, 24, 16),
  new THREE.MeshStandardMaterial({ color: 0xf59e0b, emissive: 0xf59e0b, emissiveIntensity: 0.35 }),
);
scene.add(packet);
const before = label("to 192.0.2.10:8080", { scale: 1.2 });
const after = label("to 172.17.0.2:80", { scale: 1.2 });
scene.add(before, after);

// t runs from 0 (host port) to 1 (eth0); the cable is straight, so x is a straight blend.
function place(t) {
  const x = -5 + 10 * Math.min(1, Math.max(0, t));
  packet.position.set(x, 0.55, 0);
  const rewritten = x > -2.5;
  before.visible = !rewritten;
  after.visible = rewritten;
  (rewritten ? after : before).position.set(x, -0.45, 0.9);
}

camera.position.set(0, 3.6, 10);
controls.target.set(0, 0.6, 0);
controls.update();

if (reducedMotion) {
  place(1);
} else {
  onFrame((delta, elapsed) => place((elapsed / 6) % 1));
}
JS,
        ]],

        // 8. Lifecycle
        ['prose', 'The container lifecycle', <<<'MD'
A container moves through a small set of states. `docker create` makes it **created**; `docker start` makes it **running**; `docker pause` freezes its processes with the cgroup freezer (**paused**) until `docker unpause`. When its main process ends, by itself or because of `docker stop`, it is **exited**, and keeps its writable layer, logs and configuration until `docker rm` **removes** it. `docker run --rm` removes it automatically on exit.

A **restart policy** decides what happens when the main process exits: `no` (the default), `on-failure[:max-retries]` (only after a non-zero exit code), `always`, or `unless-stopped` (like always, but not after you stopped it by hand). The engine applies restart policies, so they also bring containers back after the daemon or the host restarts.
MD],
        ['flow', 'States of a container', [
            'nodes' => [
                ['id' => 'created', 'label' => 'Created', 'kind' => 'start', 'detail' => 'Configuration and writable layer exist; no process runs. docker create, or the first half of docker run.'],
                ['id' => 'running', 'label' => 'Running', 'detail' => 'The main process (PID 1 inside) is alive. docker exec can start extra processes in the same namespaces.'],
                ['id' => 'paused', 'label' => 'Paused', 'detail' => 'Every process is frozen by the cgroup freezer: it keeps its memory but gets no CPU time. docker unpause resumes it.'],
                ['id' => 'exited', 'label' => 'Exited', 'detail' => 'PID 1 ended. The exit code is kept (137 means it was killed by SIGKILL), and so are the writable layer and the logs.'],
                ['id' => 'policy', 'label' => 'Restart policy says restart?', 'kind' => 'decision', 'detail' => 'no: never. on-failure: only after a non-zero exit. always: every time. unless-stopped: every time, except after docker stop.'],
                ['id' => 'removed', 'label' => 'Removed', 'kind' => 'end', 'detail' => 'docker rm deletes the container and its writable layer. Named volumes are kept unless you also pass -v.'],
            ],
            'edges' => [
                ['from' => 'created', 'to' => 'running', 'label' => 'start'],
                ['from' => 'running', 'to' => 'paused', 'label' => 'pause'],
                ['from' => 'paused', 'to' => 'running', 'label' => 'unpause'],
                ['from' => 'running', 'to' => 'exited', 'label' => 'stop / exit'],
                ['from' => 'exited', 'to' => 'policy'],
                ['from' => 'policy', 'to' => 'running', 'label' => 'yes'],
                ['from' => 'policy', 'to' => 'removed', 'label' => 'no, then rm'],
            ],
        ]],
        ['prose', 'PID 1 and signals: why exec form matters', <<<'MD'
`docker stop` sends the container's PID 1 a **SIGTERM** ("please shut down"), waits for the stop timeout (10 seconds unless you set one), then sends **SIGKILL**, which cannot be caught. A well-behaved program uses those seconds to finish requests and close connections.

But the kernel treats PID 1 specially: a signal with no handler installed is ignored rather than killing it. And a Dockerfile's `CMD` has two forms that decide *which* process is PID 1:

- **Shell form**, `CMD gunicorn --bind 0.0.0.0:8000 app:app`, runs `/bin/sh -c "gunicorn …"`. The shell is PID 1, it does not pass SIGTERM on, and your server never hears it. After the timeout it is killed: exit code 137, no clean shutdown.
- **Exec form**, `CMD ["gunicorn", "--bind", "0.0.0.0:8000", "app:app"]`, starts gunicorn directly as PID 1. It receives SIGTERM, lets its workers finish and exits with code 0, usually in well under a second.

We measured both with this post's example images: the shell-form container took the full 10 seconds and was killed (exit 137); the exec-form one logged "Handling signal: term" and stopped cleanly in about 0.7 seconds. If your program must start through a script, end the script with `exec "$@"` so the program replaces the shell, or add `--init` to `docker run` to get a tiny init process that forwards signals and reaps child processes.
MD],

        // 9. Storage
        ['prose', 'Storage: where data lives', <<<'MD'
A container's writable layer is convenient and **temporary**: it is deleted with the container, it cannot easily be shared, and writing through overlayfs is slower than a plain directory. Anything you want to keep (a database, uploads, generated reports) belongs somewhere else. Docker offers three places, and choosing well is mostly about who owns the data:

- A **volume** is created and managed by Docker (`docker volume create`, or simply named in `-v name:/path`). Its contents survive the container and are the normal choice for databases.
- A **bind mount** maps a directory or file of the host into the container (`-v /host/path:/path` or `--mount type=bind,…`). Ideal for development, when the container should see your source code as you edit it.
- A **tmpfs mount** lives in memory only and disappears when the container stops: right for scratch files and secrets that must never touch the disk.
MD],
        ['comparison', 'Volume, bind mount or tmpfs', [
            'columns' => ['Volume', 'Bind mount', 'tmpfs'],
            'rows' => [
                ['label' => 'Managed by', 'values' => ['Docker (docker volume ls)', 'You: any host path', 'The kernel, in memory']],
                ['label' => 'Survives docker rm', 'values' => ['Yes, until docker volume rm or rm -v', 'Yes: it is the host\'s own directory', 'No: gone when the container stops']],
                ['label' => 'Typical use', 'values' => ['Database files, uploads', 'Source code during development, config files', 'Scratch space, /tmp on a read-only container']],
                ['label' => 'Portability', 'values' => ['High: no host path in the config', 'Low: depends on the host\'s layout', 'High']],
                ['label' => 'Watch out for', 'values' => ['Back it up like any data', 'The container can change host files', 'Uses memory; size it with tmpfs-size']],
            ],
        ]],
        ['callout', 'docker rm deletes the writable layer', 'Anything a container wrote outside a volume or bind mount is deleted by docker rm, and docker run --rm deletes it as soon as the container exits. docker compose down -v and docker volume prune delete volumes too. Before you remove anything that held real data, check docker inspect for its mounts, and keep backups of your volumes.', 'warning'],
        ['code', 'Volumes in practice', <<<'SH'
# A named volume survives the container that used it
docker volume create notes
docker run --rm -v notes:/data alpine:3.20 sh -c 'echo "kept" > /data/note.txt'
docker run --rm -v notes:/data alpine:3.20 cat /data/note.txt
# kept

# The writable layer does not
docker run --name scratch alpine:3.20 sh -c 'echo "lost" > /note.txt'
docker rm scratch            # /note.txt is gone with the container

docker volume rm notes       # delete the volume when you no longer need it
SH, 'bash'],

        // 10. Networking
        ['prose', 'Networking', <<<'MD'
Every container gets its own network namespace. What it is connected to depends on the **network driver**:

- **bridge** is the default. Containers on a bridge network get private addresses and reach the outside world through NAT on the host. Docker creates one default bridge, `docker0`, on `172.17.0.0/16`.
- A **user-defined bridge** (`docker network create mynet`) is what you should use for real applications. It adds **built-in DNS**: containers find each other by name through the embedded resolver at `127.0.0.11`, so `web` can connect to `db:5432` without knowing any address. Containers on different user-defined networks cannot reach each other at all, which makes networks a simple segmentation tool. The default `docker0` bridge has no such DNS.
- **host** removes network isolation: the container shares the host's network namespace and its ports. Faster, but nothing separates it from the host's network.
- **none** gives the container only a loopback interface, for jobs that must never talk to the network.

**Publishing** a port (`-p 8080:80`) is the only way in from outside the host: it adds the DNAT rule we followed earlier. `EXPOSE` in a Dockerfile publishes nothing; it only documents the port. And `-p 8080:80` listens on **every** host interface: use `-p 127.0.0.1:8080:80` when only the host itself should connect.
MD],
        ['topology', 'Two networks, three containers, one host', [
            'aspect' => '4:3',
            'zones' => [
                ['id' => 'frontend', 'label' => 'frontend network', 'tone' => 'dmz', 'x' => 46, 'y' => 4, 'w' => 50, 'h' => 32],
                ['id' => 'backend', 'label' => 'backend network (internal)', 'tone' => 'trusted', 'x' => 46, 'y' => 66, 'w' => 50, 'h' => 30],
            ],
            'devices' => [
                ['id' => 'browser', 'label' => 'Browser', 'kind' => 'laptop', 'x' => 8, 'y' => 18, 'address' => '198.51.100.7', 'detail' => 'A visitor on the internet. The only thing it can reach is the published port on the host.'],
                ['id' => 'host', 'label' => 'Host :8080', 'kind' => 'firewall', 'x' => 28, 'y' => 18, 'address' => '192.0.2.10', 'detail' => 'The host publishes port 8080: a DNAT rule forwards it to the proxy container. No other container has a published port.'],
                ['id' => 'proxy', 'label' => 'proxy (nginx)', 'kind' => 'server', 'x' => 60, 'y' => 20, 'detail' => 'Terminates incoming HTTP and forwards it to web by name: Docker\'s DNS resolves web on the frontend network.'],
                ['id' => 'web', 'label' => 'web (gunicorn)', 'kind' => 'server', 'x' => 84, 'y' => 48, 'detail' => 'Joined to both networks, so it is the only path between them. It reaches the database as db:5432.'],
                ['id' => 'db', 'label' => 'db (postgres)', 'kind' => 'database', 'x' => 66, 'y' => 82, 'detail' => 'On an internal network: no published port, no route to the internet, and invisible to the proxy and the browser.'],
            ],
            'links' => [
                ['from' => 'browser', 'to' => 'host', 'label' => ':8080'],
                ['from' => 'host', 'to' => 'proxy', 'label' => 'DNAT :80'],
                ['from' => 'proxy', 'to' => 'web', 'label' => 'web:8000'],
                ['from' => 'web', 'to' => 'db', 'label' => 'db:5432'],
            ],
        ]],
        ['layer-stack', 'Where Docker networking sits in the OSI model', [
            'tags_label' => 'Docker pieces',
            'layers' => [
                ['tag' => '7', 'name' => 'Application', 'meta' => 'data', 'description' => 'Your program speaks HTTP, PostgreSQL or anything else exactly as it would outside a container. Docker does not touch this layer; names like db are resolved for it by the embedded DNS server.', 'tags' => ['embedded DNS 127.0.0.11', 'service names']],
                ['tag' => '4', 'name' => 'Transport', 'meta' => 'segments', 'description' => 'Ports. Publishing -p 8080:80 maps a host TCP port to a container port; EXPOSE only documents one. Each container has its own port space, so two containers can both listen on 80.', 'tags' => ['-p 8080:80', 'docker-proxy', 'EXPOSE (documentation)'], 'note' => ['tone' => 'warning', 'label' => 'Every interface', 'text' => 'A published port listens on all host addresses unless you bind it: -p 127.0.0.1:8080:80.']],
                ['tag' => '3', 'name' => 'Network', 'meta' => 'packets', 'description' => 'Each container has an IP address on its network (172.17.0.2 on the default bridge), routes through the bridge\'s gateway (172.17.0.1), and leaves the host through masquerading NAT. Incoming published traffic is rewritten by DNAT.', 'tags' => ['172.17.0.0/16', 'DNAT', 'MASQUERADE', 'iptables']],
                ['tag' => '2', 'name' => 'Data link', 'meta' => 'frames', 'description' => 'The docker0 bridge (or one bridge per user-defined network) is a virtual switch. Each container is plugged in by a veth pair: one end is eth0 inside, the other is attached to the bridge.', 'tags' => ['docker0', 'veth pair', 'MAC addresses']],
                ['tag' => '1', 'name' => 'Physical', 'meta' => 'bits', 'description' => 'Nothing physical belongs to a container. Its traffic leaves through the host\'s real network card after routing and NAT.', 'tags' => ['host NIC']],
            ],
        ]],
        ['prose', 'Plan the address space', <<<'MD'
Docker picks subnets for new networks from a default pool, starting with `172.17.0.0/16` for `docker0`. That is a lot of addresses for one bridge (65 534 usable), and on some company networks the 172.16.0.0/12 range is already in use, which makes containers unable to reach real hosts with the same addresses. You can choose the subnet yourself: `docker network create --subnet 10.20.30.0/24 apps` gives a network with 254 usable addresses, enough for most single-host applications.

Try both in the calculator: start with `172.17.0.0/16`, then type `10.20.30.0/24`. Note the gateway Docker would use (the first usable address, `172.17.0.1` and `10.20.30.1`) and the first container's address right after it.
MD],
        ['calculator', 'Docker subnets in the CIDR calculator', ['tool' => 'cidr', 'initial' => '172.17.0.0/16']],
        ['knowledge-check', 'Check: container networking', [
            'questions' => [
                [
                    'prompt' => 'Two containers on the default docker0 bridge cannot reach each other by name, but they can by IP address. What is the usual fix?',
                    'options' => [
                        ['text' => 'Put both on a user-defined network, which has built-in DNS', 'correct' => true],
                        ['text' => 'Publish both containers\' ports with -p', 'correct' => false],
                        ['text' => 'Add EXPOSE to both Dockerfiles', 'correct' => false],
                    ],
                    'explanation' => 'Only user-defined networks get Docker\'s embedded DNS server, which resolves container and service names. Publishing ports opens them to the outside world, which is unnecessary for container-to-container traffic, and EXPOSE is documentation only: it changes nothing on the network.',
                ],
                [
                    'prompt' => 'A database container runs with -p 5432:5432 on a server with a public IP. Who can try to connect to it?',
                    'options' => [
                        ['text' => 'Anyone who can reach the server on port 5432', 'correct' => true],
                        ['text' => 'Only other containers on the same host', 'correct' => false],
                        ['text' => 'Only the host itself, because Docker binds to localhost by default', 'correct' => false],
                    ],
                    'explanation' => 'A published port listens on every host interface unless you write -p 127.0.0.1:5432:5432, and Docker\'s NAT rules are applied before many host firewall rules. Other containers do not need a published port at all; put the database on a private network instead.',
                ],
            ],
        ]],

        // 11. Hands-on
        ['prose', 'Hands-on: your first containers', <<<'MD'
Time to try it. You need Docker Engine on Linux, or Docker Desktop on macOS or Windows (which runs the engine in a small Linux VM). The steps below use only official images and leave nothing behind. On a Linux server, your user must be in the `docker` group or use `sudo`; remember that membership in that group is equivalent to root on the host.
MD],
        ['steps', 'Run, inspect and clean up a web server', [
            'Check the installation: `docker version` must show both a **Client** and a **Server** section. If the server part fails, the engine is not running or you lack permission to its socket.',
            'Start nginx in the background, publishing host port 8080 to the container\'s port 80: `docker run -d --name web -p 8080:80 nginx:1.29-alpine`. The image is pulled on first use.',
            'List running containers with `docker ps`, then open `http://localhost:8080` (or `curl http://localhost:8080`): you get nginx\'s welcome page.',
            'Read its output with `docker logs web`; add `-f` to follow new lines. Every request you make appears here, because the image sends nginx\'s logs to standard output.',
            'Run a command inside the running container: `docker exec web nginx -v`, or open a shell with `docker exec -it web sh` and look around (`ps`, `hostname`, `cat /etc/os-release`). Type `exit` to leave; the container keeps running.',
            'Inspect its configuration and state: `docker inspect web` prints JSON; `docker inspect web --format "{{.NetworkSettings.Networks.bridge.IPAddress}}"` picks one value.',
            'Stop it with `docker stop web`. `docker ps -a` still lists it as **Exited**: its writable layer and logs remain.',
            'Remove it with `docker rm web`. `docker images` still lists the nginx image; remove it with `docker rmi nginx:1.29-alpine` if you want the space back.',
        ]],
        ['code', 'The same session as commands', <<<'SH'
docker version
docker run -d --name web -p 8080:80 nginx:1.29-alpine
docker ps
curl http://localhost:8080
docker logs web
docker exec web nginx -v
docker inspect web --format '{{.NetworkSettings.Networks.bridge.IPAddress}} {{.State.Status}}'
docker stop web
docker ps -a --filter name=web
docker rm web
docker images nginx
SH, 'bash'],

        // 12. Dockerfile
        ['prose', 'Writing a good Dockerfile', <<<'MD'
The rest of the post uses one small application: a Flask app, served by gunicorn, that counts visits in PostgreSQL. Its directory holds four files: `app.py`, `requirements.txt`, a `Dockerfile` and a `.dockerignore`, plus the `compose.yaml` and a `db_password.txt` used in the next section.

First, the version many people write on day one. It works, and that is its only virtue:
MD],
        ['code', 'app.py', <<<'PY'
import os
from pathlib import Path

import psycopg
from flask import Flask

app = Flask(__name__)


def connect():
    password = Path(os.environ["DB_PASSWORD_FILE"]).read_text().strip()
    return psycopg.connect(host=os.environ.get("DB_HOST", "db"), dbname="app", user="app", password=password)


@app.get("/")
def index():
    with connect() as conn:
        conn.execute("CREATE TABLE IF NOT EXISTS visits (id serial PRIMARY KEY, at timestamptz DEFAULT now())")
        conn.execute("INSERT INTO visits DEFAULT VALUES")
        count = conn.execute("SELECT count(*) FROM visits").fetchone()[0]
    return f"Hello from a container. This page has been visited {count} times.\n"


@app.get("/healthz")
def healthz():
    return "ok\n"
PY, 'python'],
        ['code', 'requirements.txt', <<<'TXT'
flask==3.1.3
gunicorn==27.0.1
psycopg[binary]==3.3.9
TXT, 'text'],
        ['code', 'Dockerfile, before', <<<'DOCKERFILE'
FROM python:3.13
COPY . /app
WORKDIR /app
RUN pip install -r requirements.txt
CMD gunicorn --bind 0.0.0.0:8000 app:app
DOCKERFILE, 'dockerfile'],
        ['prose', 'What is wrong with it', <<<'MD'
- `python:3.13` is the full Debian image with compilers and hundreds of tools the app never uses: more to download, more to patch, more for an attacker to use.
- `COPY . /app` sends the **whole directory** into the image, including `.git`, local secrets such as `db_password.txt`, and caches. Anything copied is in a layer forever, even if a later step deletes it.
- Because everything is copied before `pip install`, **any** change to any file invalidates the cache and reinstalls every dependency.
- Nothing sets a user, so the app runs as **root** inside the container.
- The shell-form `CMD` makes `/bin/sh` PID 1, so `docker stop` waits the full timeout and kills the server.
- There is no health check, so Docker (and Compose) cannot tell a running process from a working service.

Here is the same application done properly. Every line was built and run for this post:
MD],
        ['code', 'Dockerfile, after', <<<'DOCKERFILE'
# syntax=docker/dockerfile:1

# Stage 1: install the dependencies into a virtual environment.
FROM python:3.13-slim AS build
RUN python -m venv /venv
ENV PATH="/venv/bin:$PATH"
COPY requirements.txt /tmp/requirements.txt
RUN --mount=type=cache,target=/root/.cache/pip pip install -r /tmp/requirements.txt

# Stage 2: the runtime image, with only what the app needs to run.
FROM python:3.13-slim
RUN useradd --system --uid 10001 --no-create-home app
COPY --from=build /venv /venv
ENV PATH="/venv/bin:$PATH" PYTHONUNBUFFERED=1
WORKDIR /app
COPY app.py .
USER app
EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=3s --retries=3 \
  CMD ["python", "-c", "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8000/healthz', timeout=2)"]
CMD ["gunicorn", "--bind", "0.0.0.0:8000", "--workers", "2", "--no-control-socket", "app:app"]
DOCKERFILE, 'dockerfile'],
        ['code', '.dockerignore', <<<'TXT'
.git
.env
*.txt
!requirements.txt
__pycache__/
*.pyc
compose.yaml
Dockerfile
TXT, 'text'],
        ['comparison', 'Before and after, measured', [
            'columns' => ['Before', 'After'],
            'rows' => [
                ['label' => 'Image size', 'values' => ['1.66 GB', '222 MB']],
                ['label' => 'Base image', 'values' => ['python:3.13 (full Debian, compilers)', 'python:3.13-slim, and no build tools in the final stage']],
                ['label' => 'What is copied in', 'values' => ['The whole directory, secrets and .git included', 'requirements.txt, the venv and app.py only (.dockerignore)']],
                ['label' => 'Rebuild after editing app.py', 'values' => ['Reinstalls every dependency', 'Re-runs one COPY: seconds']],
                ['label' => 'Runs as', 'values' => ['root', 'app (UID 10001)']],
                ['label' => 'PID 1 and docker stop', 'values' => ['/bin/sh; killed after 10 s (exit 137)', 'gunicorn; clean exit in under a second']],
                ['label' => 'Health', 'values' => ['Unknown: only "running"', 'HEALTHCHECK: healthy or unhealthy']],
            ],
        ]],
        ['prose', 'Why each change matters', <<<'MD'
- **Multi-stage build.** The first stage may contain anything needed to build; only what `COPY --from=build` takes reaches the final image. For compiled languages such as Go the difference is dramatic: the final stage can be almost empty.
- **Small base image.** `-slim` drops compilers and documentation. Alpine images are smaller still, but use musl instead of glibc, which some Python wheels do not support; distroless images go further and remove even the shell.
- **`.dockerignore`.** It decides what the build can see at all. Excluding `*.txt` here keeps `db_password.txt` out of the build context, so no instruction can copy it into a layer by accident.
- **Layer order.** Dependencies before code, so the expensive install is cached. The `--mount=type=cache` option (a BuildKit feature) also keeps pip's downloads between builds without storing them in the image.
- **Non-root `USER`.** If an attacker takes over the app, they are an unprivileged user inside the container, without root's file permissions or the capabilities root would keep. The app needs no write access to its own code: `COPY` leaves `app.py` owned by root, so `app` can read it but not change it.
- **`HEALTHCHECK`.** Docker runs the check inside the container and reports healthy or unhealthy in `docker ps`; Compose can wait for it, as the next section shows. The check uses Python itself, because the slim image has no `curl`.
- **Exec-form `CMD`.** gunicorn is PID 1, so it receives SIGTERM and shuts down cleanly. `--no-control-socket` turns off gunicorn's control socket, which this app does not use and which would otherwise try to write to the read-only filesystem we use later.
MD],
        ['knowledge-check', 'Check: the build cache', [
            'questions' => [
                [
                    'prompt' => 'In the "after" Dockerfile you add a package to requirements.txt and rebuild. Which instructions run again?',
                    'options' => [
                        ['text' => 'COPY requirements.txt, the pip install, and every instruction after them', 'correct' => true],
                        ['text' => 'Only the pip install', 'correct' => false],
                        ['text' => 'Everything, starting from FROM', 'correct' => false],
                    ],
                    'explanation' => 'The checksum of requirements.txt changed, so its COPY misses the cache, and once one instruction misses, every later instruction in that stage runs again; the runtime stage then re-copies the new venv and app.py. The FROM and venv steps before it are unchanged and stay cached.',
                ],
                [
                    'prompt' => 'A Dockerfile has RUN echo "$API_KEY" > /key, then RUN rm /key in the next instruction. Is the key in the final image?',
                    'options' => [
                        ['text' => 'Yes: it stays in the layer created by the first RUN', 'correct' => true],
                        ['text' => 'No: the second RUN deleted it', 'correct' => false],
                        ['text' => 'No: RUN instructions never create layers', 'correct' => false],
                    ],
                    'explanation' => 'Each RUN creates a layer. The second one only adds a whiteout that hides /key; the earlier layer still contains the file, and anyone who pulls the image can extract it with docker save. Use build secrets (RUN --mount=type=secret) for values the build needs, and runtime secrets for values the app needs.',
                ],
            ],
        ]],

        // 13. Compose
        ['prose', 'Multi-container apps with Compose', <<<'MD'
Real applications are several containers: here, the web app and its database. Starting them by hand means remembering networks, volumes, environment variables and the order things start in. **Docker Compose** puts all of that in one file, `compose.yaml`, and the `docker compose` plugin (Compose v2) runs it. The old `docker-compose` command and the `version:` key at the top of the file are both obsolete.

The file below describes the example application completely: two services, two networks, one volume and one secret.
MD],
        ['code', 'compose.yaml', <<<'YAML'
services:
  web:
    build: .
    ports:
      - "${WEB_PORT:-8080}:8000"
    environment:
      DB_HOST: db
      DB_PASSWORD_FILE: /run/secrets/db_password
    secrets:
      - db_password
    depends_on:
      db:
        condition: service_healthy
    networks:
      - frontend
      - backend
    read_only: true
    tmpfs:
      - /tmp
    cap_drop:
      - ALL
    security_opt:
      - no-new-privileges:true
    restart: unless-stopped

  db:
    image: postgres:17-alpine
    environment:
      POSTGRES_USER: app
      POSTGRES_DB: app
      POSTGRES_PASSWORD_FILE: /run/secrets/db_password
    secrets:
      - db_password
    volumes:
      - db-data:/var/lib/postgresql/data
    networks:
      - backend
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U app -d app"]
      interval: 5s
      timeout: 3s
      retries: 10
    restart: unless-stopped

networks:
  frontend:
  backend:
    internal: true

volumes:
  db-data:

secrets:
  db_password:
    file: ./db_password.txt
YAML, 'yaml'],
        ['prose', 'Reading the file', <<<'MD'
- **Services** are containers Compose manages. `web` is built from the Dockerfile in the same directory; `db` uses the official PostgreSQL image.
- **Ports.** Only `web` publishes a port. `${WEB_PORT:-8080}` is **variable interpolation**: Compose reads `WEB_PORT` from your shell or from a `.env` file next to `compose.yaml`, and uses 8080 when it is unset. Put per-machine settings there, never passwords you would commit.
- **`depends_on` with `condition: service_healthy`.** Compose starts `db`, waits until its health check (`pg_isready`) passes, and only then starts `web`. Plain `depends_on` would only wait for the container to *start*, not for PostgreSQL to accept connections.
- **Networks.** Compose creates user-defined networks, so `web` reaches the database simply as `db`. The `backend` network is `internal`: it has no route to the outside, so even a compromised database container cannot call home.
- **Volume.** `db-data` keeps the database between `docker compose down` and `up`. (PostgreSQL 18 images expect a different path; this example pins version 17.)
- **Secrets.** The password is mounted as a file at `/run/secrets/db_password` in each service that lists it, and both PostgreSQL (`POSTGRES_PASSWORD_FILE`) and the app read it from there. It is never an environment variable, so it does not appear in `docker inspect` or in process listings.
- **Hardening.** The `web` service runs with a read-only root filesystem, a tmpfs for `/tmp`, no capabilities at all and `no-new-privileges`. We checked: the app runs normally, and `CapEff` inside it is `0000000000000000`.
MD],
        ['steps', 'Run the application', [
            'Create the secret once: `openssl rand -hex 16 > db_password.txt`. The `.dockerignore` keeps it out of the image; keep it out of git too.',
            'Build and start everything in the background: `docker compose up --build -d`. Compose creates the networks and the volume, starts `db`, waits for it to be healthy, then starts `web`.',
            'Check the state with `docker compose ps`: both services should say **(healthy)** after a few seconds.',
            'Open `http://localhost:8080` and reload: the visit counter goes up. Follow the logs with `docker compose logs -f web`.',
            'Stop and remove the containers with `docker compose down`, then run `docker compose up -d` again: the counter continues, because the data lives in the `db-data` volume.',
            'When you are finished, `docker compose down -v` also deletes the volume, and with it the data.',
        ]],

        // 14. Security
        ['prose', 'Container security basics', <<<'MD'
Containers are a packaging and isolation tool, not a security boundary you can stop thinking about. The good news is that a few habits remove most of the real-world risk. One way to organise them is the CIA triad: what each property means for a container, how it breaks, and which controls protect it.
MD],
        ['diagram', 'Container risks through the CIA triad', 'cia-triad', 'Select each property to see what it means for containers, how it is typically broken, and the controls from this post that protect it.', [
            'properties' => [
                ['key' => 'confidentiality', 'name' => 'Confidentiality', 'definition' => 'Secrets and data inside a container stay readable only by the processes and people meant to read them.', 'example' => 'A database password set with ENV in a Dockerfile is visible to anyone who pulls the image, and a secret deleted in a later layer is still in the earlier one.', 'controls' => ['Runtime secrets as files (/run/secrets)', 'Build secrets (RUN --mount=type=secret)', '.dockerignore', 'Separate networks per tier']],
                ['key' => 'integrity', 'name' => 'Integrity', 'definition' => 'The code a container runs is the code you meant to run, and an attacker inside cannot change it or the host.', 'example' => 'A container started with the Docker socket mounted lets an attacker who takes it over start a privileged container and rewrite files on the host.', 'controls' => ['Pin images by digest', 'Scan and sign images', 'Non-root USER, read-only root filesystem', 'Never mount docker.sock, never --privileged']],
                ['key' => 'availability', 'name' => 'Availability', 'definition' => 'One container cannot exhaust the host and take its neighbours down, and services come back after failures.', 'example' => 'A memory leak in one unlimited container makes the host kill processes at random, or a fork bomb fills the process table.', 'controls' => ['--memory, --cpus, --pids-limit (cgroups)', 'Health checks', 'Restart policies', 'Volumes with backups']],
            ],
        ]],
        ['callout', 'The socket is the keys to the host', 'Never mount /var/run/docker.sock into a container and never run untrusted images with --privileged. Whoever can talk to the Docker socket can start a container that mounts the host\'s root filesystem and becomes root on the host, and --privileged gives a container every capability, every device and no seccomp filter. Both turn a compromised container into a compromised host. If a tool truly needs the engine (a CI runner, a monitoring agent), give it its own host or a socket proxy that allows only the calls it needs.', 'warning'],
        ['prose', 'Habits that matter most', <<<'MD'
- **Root in a container is still root on the kernel.** Without user namespaces, UID 0 inside is UID 0 outside; only capabilities and seccomp hold it back. Set a non-root `USER` in every image you build.
- **Use rootless mode where you can.** Rootless Docker runs the daemon and every container as an ordinary user, inside a user namespace, so even a full escape lands as that user. `userns-remap` gives a similar mapping with a root daemon.
- **Drop what you do not need.** Start from `--cap-drop ALL` and add back single capabilities with `--cap-add` only when the app fails without them. Add `--security-opt no-new-privileges` so setuid programs cannot regain privileges, and `--read-only` with a tmpfs for the few paths that need writing.
- **Limit resources.** `--memory`, `--cpus` and `--pids-limit` stop one container from starving the rest.
- **Know where images come from.** Prefer official or verified images, pin them by digest in production (`postgres@sha256:…`), and scan them for known vulnerabilities (`docker scout cves`, Trivy, Grype) in your pipeline. Rebuild regularly: a pinned image never receives security fixes on its own.
- **Keep secrets out of images and environment variables.** Use build secrets for the build and mounted secret files at run time, as the Compose example does.
- **Patch the host.** Every container shares its kernel.
MD],
        ['callout', 'A hardened docker run, all in one place', 'docker run -d --read-only --tmpfs /tmp --cap-drop ALL --security-opt no-new-privileges --memory 256m --cpus 1 --pids-limit 100 -p 127.0.0.1:8080:8000 myapp: read-only root, no capabilities, no privilege escalation, resource limits, and a port only the host can reach. Start here, and loosen one option at a time only when the application proves it needs it.', 'tip'],
        ['knowledge-check', 'Check: securing containers', [
            'questions' => [
                [
                    'prompt' => 'A monitoring container needs to list running containers, so a teammate mounts /var/run/docker.sock read-only into it. Is the host safe from that container?',
                    'options' => [
                        ['text' => 'No: the API can start a privileged container even through a read-only mount', 'correct' => true],
                        ['text' => 'Yes: read-only stops it from changing anything', 'correct' => false],
                        ['text' => 'Yes, as long as the container runs as a non-root user', 'correct' => false],
                    ],
                    'explanation' => 'The :ro flag only stops the socket file itself from being replaced; connecting to the socket and sending API requests still works, and one request can start a privileged container with the host\'s / mounted. A non-root user inside only helps if the socket\'s permissions refuse that user, which is not how such mounts are normally set up.',
                ],
                [
                    'prompt' => 'Which measures reduce what an attacker can do after taking over a web app container? Choose all that apply.',
                    'options' => [
                        ['text' => 'Running as a non-root USER', 'correct' => true],
                        ['text' => '--cap-drop ALL and --security-opt no-new-privileges', 'correct' => true],
                        ['text' => 'A read-only root filesystem', 'correct' => true],
                        ['text' => 'Publishing the app on a higher host port, such as 18080', 'correct' => false],
                    ],
                    'explanation' => 'A non-root user, no capabilities, no privilege escalation and a read-only filesystem all limit an attacker who already runs code inside. The host port number changes nothing about what that code can do; it only changes where clients connect.',
                ],
            ],
        ]],

        // 15. Debugging
        ['prose', 'Debugging and an everyday cheat sheet', <<<'MD'
When a container misbehaves, work from the outside in: is it running (`docker ps -a` and its exit code), what did it say (`docker logs`), what does it see (`docker exec`), and how is it configured (`docker inspect`). Exit code 137 means it was killed by SIGKILL, often by the out-of-memory killer (`docker inspect` shows `"OOMKilled": true`) or by `docker stop` after the timeout; 1 or other small numbers usually mean the program itself failed, and its logs will say why.
MD],
        ['code', 'Commands you will use every day', <<<'SH'
docker ps -a                          # all containers, with status and exit code
docker logs -f --tail 100 web         # follow the last 100 lines of output
docker exec -it web sh                # a shell inside a running container
docker inspect web --format '{{.State.ExitCode}} {{.State.OOMKilled}}'
docker stats --no-stream              # CPU, memory, network and PIDs per container
docker compose ps                     # the services of the current project
docker compose logs -f                # all services' output, interleaved
docker image ls                       # local images and their sizes
docker system df                      # disk used by images, containers, volumes, build cache
docker system prune                   # remove stopped containers, unused networks,
                                      # dangling images and build cache (asks first)
SH, 'bash'],
        ['callout', 'Read what prune will delete', 'docker system prune asks before deleting stopped containers, unused networks, dangling images and the build cache. Adding -a also deletes every image not used by a container, and --volumes deletes unused volumes, including database data that a stopped stack still needed. Read the list it prints before you answer yes.', 'warning'],

        // 16. Next steps
        ['prose', 'Where to go next', <<<'MD'
You now know what a container is underneath, how an image is built, shipped and run, and how to network, store and secure it. Some directions from here:

- **Orchestration.** One host and Compose go a long way. When an application needs many hosts, automatic rescheduling of failed containers and rolling updates, an **orchestrator** such as **Kubernetes** takes over. It runs the same OCI images through containerd, so everything in this post still applies, from image layers to capabilities.
- **Linux foundations.** Containers are Linux processes, so Linux skills pay off directly. On CyberCompass, the **Linux Command Line** course (`/courses/linux-command-line`) covers processes, users and file permissions, and its lesson on logs pairs with the lab **Investigate a suspicious login** (`/labs/investigate-a-suspicious-login`).
- **Networking.** NAT, subnets, bridges and ports are all networking ideas. The **Networking Fundamentals** course (`/courses/networking-fundamentals`) covers IP addressing, subnetting and the OSI model used above.
- **Hardening.** The **System Hardening** course (`/courses/system-hardening`) applies the same least-privilege thinking to whole servers, and the lab **Lock down a web server** (`/labs/lock-down-a-web-server`) practises firewall rules like the ones Docker writes for you.
- **Further reading.** The [Docker Engine security overview](https://docs.docker.com/engine/security/), [Dockerfile best practices](https://docs.docker.com/build/building/best-practices/), the [Compose file reference](https://docs.docker.com/reference/compose-file/), [rootless mode](https://docs.docker.com/engine/security/rootless/), the Linux manual page [namespaces(7)](https://man7.org/linux/man-pages/man7/namespaces.7.html), the kernel's [cgroup v2 documentation](https://docs.kernel.org/admin-guide/cgroup-v2.html) and the [OCI runtime specification](https://github.com/opencontainers/runtime-spec).
MD],

        // 17. Glossary
        ['prose', 'Glossary', <<<'MD'
- **BuildKit**: Docker's builder; runs Dockerfile instructions with caching, parallel stages, cache mounts and build secrets.
- **Bind mount**: a host file or directory made visible inside a container.
- **Capability**: one of about forty separate pieces of root's power, such as `CAP_NET_BIND_SERVICE`.
- **cgroup (control group)**: a kernel group of processes with limits and accounting for memory, CPU, PIDs and I/O.
- **Compose**: a tool and file format (`compose.yaml`) for multi-container applications; run with `docker compose`.
- **Container**: one or more processes started from an image, with their own namespaces, cgroup, writable layer and settings.
- **containerd**: the daemon that supervises containers and, on Engine 29, stores images; used by Docker and Kubernetes.
- **Digest**: the SHA-256 hash that names exact content (a layer, a config, a manifest); it never changes.
- **Dockerfile**: the instructions that build an image.
- **dockerd**: the Docker Engine daemon, which serves the Engine API.
- **Image**: read-only layers plus a config, described by a manifest.
- **Layer**: one set of filesystem changes in an image, named by its digest.
- **Manifest**: the document listing an image's config and layers by digest; an index lists one manifest per platform.
- **Namespace**: a kernel feature that gives processes their own instance of a global resource (processes, network, mounts, hostname, IPC, users, cgroups).
- **OCI**: the Open Container Initiative and its image, runtime and distribution specifications.
- **Overlayfs**: the union filesystem that stacks image layers under a container's writable layer.
- **Registry / repository / tag**: the server that stores images, one image's collection of versions inside it, and a movable name for one version.
- **Restart policy**: when the engine restarts an exited container: `no`, `on-failure`, `always` or `unless-stopped`.
- **runc**: the OCI runtime that creates the namespaces and cgroup and starts the container's first process.
- **Seccomp**: a kernel filter on the system calls a process may make.
- **Shim**: the small per-container process (`containerd-shim-runc-v2`) that stays as the container's parent.
- **veth pair**: two connected virtual network interfaces; one end is the container's `eth0`, the other is plugged into a bridge.
- **Volume**: Docker-managed storage that outlives containers.
MD],
    ],
];
