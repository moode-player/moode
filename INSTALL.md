# Installing moode-nopi

moode-nopi is the moOde audio player ported to **Debian 13 (Trixie) x86_64** (and,
by extension, Armbian Trixie arm64). It is **not** a ready-made disk image: you
install a minimal Debian first, then run the additive `install.sh`, which
builds the moOde stack on top.

This guide takes you from a blank PC to a working player.

---

## 1. What you need

- A 64-bit PC (mini-PC, NUC, old laptop/desktop…) with:
  - a disk of ~16 GB or more (the OS + moOde; your music can live on USB/NAS),
  - wired Ethernet for the install (Wi-Fi is configured later in the moOde UI),
  - a USB DAC, HDMI audio, or onboard audio for output.
- A USB stick (~1 GB) for the Debian installer.
- The Debian 13 **netinst** image for amd64:
  <https://www.debian.org/download> → *netinst* → `debian-13.x.x-amd64-netinst.iso`.

Write the ISO to the USB stick (e.g. with Balena Etcher, Rufus, or
`dd if=debian-13...iso of=/dev/sdX bs=4M status=progress` — pick the right device).

---

## 2. Install Debian 13 (the important choices)

Boot the PC from the USB stick and start the **Graphical install** (or Install).
Most screens are the defaults; the ones that matter for moode-nopi are below.

| Step | What to choose | Why |
|------|----------------|-----|
| Hostname | `moode` (or anything) | Becomes the player name; you can change it later in the UI. |
| Domain name | leave blank | — |
| **Root password** | **leave it EMPTY** | When root has no password, the Debian installer locks the root account and gives your first user `sudo`. That   lets you run the installer with `sudo` straight away. |
| **Full name / username** | username **`moode`** | The installer derives the player user from **UID 1000** = the first user you create. It must be `moode`. |
| **User password** | **`moodeaudio`** | Same default as the Pi image. Change it afterwards if you like. |
| Time zone / locale / keyboard | your choice | — |
| **Partitioning** | *Guided – use entire disk* → **All files in one partition** | moOde expects a single root partition (no separate `/home`). |
| Write changes to disk | Yes | — |
| Package manager / mirror | pick a nearby mirror; **no** to popularity contest | — |
| **Software selection (tasksel)** | **UNCHECK everything except**: ☑ **SSH server** and ☑ **standard system utilities**. **No desktop environment.** | moOde runs headless; its own kiosk display (X11 + Chromium) is installed by `install.sh`, not a Debian desktop. |
| **GRUB boot loader** | **Yes** – install GRUB to the disk you just partitioned (e.g. `/dev/sda`) | Required. The installer disables predictable NIC naming via GRUB's kernel cmdline (see *Network interface names* below). Debian installs GRUB by default in both UEFI (`grub-efi`) and legacy-BIOS (`grub-pc`) modes — just accept it. |

Finish, remove the USB stick, and reboot.

> If you accidentally **set** a root password, your first user won't have `sudo`.
> Either add it once as root — `su -` then `usermod -aG sudo moode` (or just run
> the first install via `su -c`), or reinstall leaving the root password empty.
> `install.sh` also installs `sudo` and adds the player user to the `sudo`
> group on its first run, so this self-heals after the first root-run.

---

## 3. First boot

Log in as `moode` / `moodeaudio` — on the console, or over SSH from another
machine:

```bash
ssh moode@<ip-address>      # find the IP from your router, or run `ip a` on the box
```

Make sure the box can reach the internet (the installer downloads packages and
builds a few moOde-tagged binaries):

```bash
ping -c2 deb.debian.org
```

---

## 4. Get moode-nopi

**First, make sure your user can use `sudo`.** Everything below — even
installing `git` — needs it, and on a fresh Debian `root` can't log in over SSH,
so you can't side-step it. Check with:

```bash
sudo -v        # asks for YOUR password; silent success = you're in the sudo group
```

If that prints `sudo: command not found` (or `-bash: sudo: commande
introuvable`), a minimal Debian install ships **without `sudo` at all**. Install
it first, from a root shell on the console — note `apt`, not `sudo apt`, since
that's the very thing you're missing:

```bash
su -                            # root password you set during install
apt update && apt install -y sudo
usermod -aG sudo moode          # use your actual username if not `moode`
exit                            # then log out and back in for the group to apply
```

If instead it prints `<user> is not in the sudoers file` (or `not allowed to run
sudo`), `sudo` is installed but you most likely **set a root password** during
the Debian install (see step 2). Add yourself to the `sudo` group once, from a
root shell on the console:

```bash
su -                            # root password you set during install
usermod -aG sudo moode          # use your actual username if not `moode`
exit                            # then log out and back in for the group to apply
```

(If you left the root password empty as recommended, you already have `sudo` and
can skip this.)

Now clone the repository onto the box:

```bash
sudo apt-get update && sudo apt-get install -y git
git clone https://github.com/Gjuju/moode-nopi.git
cd moode-nopi
```

Cloning drops you on the **`main`** branch, which **is** the latest stable release
(`main` only ever moves forward to a newer release tag). So there is **nothing to
check out** — you're already on the version you want.

> *Advanced, optional:* to pin an older release use `git checkout <tag>` (see the
> repo's **Releases**); to track the in-progress version use `git checkout develop`.
> Most people should just stay on `main`.

That's all you fetch — you do **not** build anything by hand. The installer
builds the web app itself on the first run (see below).

---

## 5. Run the installer

```bash
sudo ./install.sh
```

- On the **first run** the installer builds the web app for you (it installs
  Node 18 via nvm and runs the gulp build) before deploying — no manual build
  step needed.
- **No flag needed for a first install:** the installer **creates the config
  database automatically when none exists**. `--reset-db` is only for **wiping an
  existing** config back to factory defaults (it backs the current DB up first), so
  on a fresh box it would be redundant.
- It then installs packages and builds moOde's patched binaries (mpd, caps,
  squeezelite, peppyalsa…) from source, so the whole run takes several minutes.
- Full log: `install.log`, written next to the script in your clone directory (the
  whole run is mirrored there as well as to the terminal; override with
  `INSTALL_LOG=/path sudo ./install.sh …`). Note `/var/log/moode.log` is a
  different file — moOde's **runtime** log, written by the worker once it starts.
- It is **re-runnable** and **keeps your settings by default**; pass `--reset-db`
  only when you deliberately want to start the database over from scratch.

When it finishes, reboot once:

```bash
sudo reboot
```

---

## 6. First use

Open the WebUI in a browser:

```text
http://moode.local/        (or http://<ip-address>/)
```

Then in **Configure → Audio**, pick your real output device (out of the box the
fresh database defaults to the Pi's HDMI, which doesn't exist on a PC). A USB DAC with no
hardware volume control needs **MPD volume = Software**.

Wi-Fi, library/NAS, renderers, the local HDMI display, etc. are all configured
from the WebUI exactly as on a Raspberry Pi.

---

## Updating to a newer release

When a newer `*-nopi.*` release comes out, update in place from the same clone:

```bash
cd moode-nopi
git pull                           # or: git fetch && git checkout <newer-tag>
sudo ./install.sh --update
```

`--update` rebuilds the web app from the pulled source, re-deploys it, re-applies
configs, and restarts services — **keeping your existing settings** (it does not
touch the database; never pass `--reset-db` for an update).

---

## Notes

- **One codebase, Pi-identical behaviour.** Platform differences are resolved at
  runtime via `isPi()`; Pi-only features (config.txt overlays, GPIO/I2S HATs,
  LED/fan, external antenna…) are hidden or skipped automatically on a PC.
- **Log to RAM (log2ram).** Configure > System's "Log to RAM" control spares a
  flash-based root filesystem from `/var/log` write wear. The installer enables it
  automatically when the root filesystem sits on an SD card / eMMC (`mmcblk*`,
  typical on Armbian SBCs) and skips it on x86 SSD/NVMe where it's pointless; the
  UI control then appears only when the `log2ram` package is actually installed.
  Force or skip with `INSTALL_LOG2RAM=1|0` at the top of `install.sh`.
- **Network interface names.** moOde's UI (SSID scan, Network config) expects
  `eth0` / `wlan0`. On a fresh Debian your NICs will instead have *predictable*
  names that vary by firmware — `enp1s0`, `eno1`, `ens33` (typical under UEFI) or
  even `em1` / `p1p1` (the biosdevname scheme on some legacy-BIOS machines). **You
  don't need to know or care which** — the installer adds `net.ifnames=0
  biosdevname=0` to GRUB's kernel cmdline and runs `update-grub`, which neutralises
  *all* of those schemes, so after the post-install reboot the NICs come up as
  `eth0` / `wlan0` on UEFI and legacy-BIOS alike. NetworkManager then owns them.
  This is why **GRUB is required**: it's the bootloader the installer knows how to
  edit. If you boot some other loader (e.g. systemd-boot, or Armbian's), the
  installer prints a warning and you must add `net.ifnames=0 biosdevname=0` to the
  kernel cmdline yourself.
- **arm64 / Armbian**: the same `install.sh` works on Armbian Trixie (arm64);
  start from a minimal Armbian server image instead of the Debian netinst, then
  follow from step 3. Armbian uses u-boot, not GRUB, so the installer writes the
  `net.ifnames=0` cmdline to `/boot/armbianEnv.txt` and re-points netplan at
  NetworkManager (see the network note above). Validated on an Orange Pi 3 LTS
  (Allwinner H6) as a proof-of-concept.
- **USB touchscreens on SBC kernels (`hid-multitouch`).** Some SBC/Armbian kernels
  are built **without** `CONFIG_HID_MULTITOUCH`, so a USB Windows-8-multitouch
  panel falls back to `hid-generic` and behaves like a mouse (tap & hold becomes a
  drag-select instead of touch). Debian's x86 kernel and Raspberry Pi OS both ship
  the module, so this only affects such SBC kernels. The installer does **not**
  handle this (it's kernel-specific and out of scope); if you hit it, build the
  module out-of-tree against your kernel and install it via DKMS:

  ```bash
  # Kernel headers + DKMS. The headers PACKAGE NAME differs by platform, so pick it
  # automatically (do NOT just run `linux-headers-$(uname -r)` everywhere - on Armbian
  # `uname -r` is e.g. 6.18.33-current-sunxi64 and no such package exists; the real
  # one is linux-headers-current-sunxi64, named by ${BRANCH}-${LINUXFAMILY}):
  if [ -f /etc/armbian-release ]; then
      . /etc/armbian-release; HDR="linux-headers-${BRANCH}-${LINUXFAMILY}"  # e.g. linux-headers-current-sunxi64
  else
      HDR="linux-headers-$(uname -r)"                                       # Debian x86
  fi
  sudo apt install dkms "$HDR"
  V=$(awk '/^VERSION/{a=$3}/^PATCHLEVEL/{b=$3}/^SUBLEVEL/{c=$3}END{print a"."b"."c}' \
        /lib/modules/$(uname -r)/build/Makefile)     # e.g. 6.18.33
  S=/usr/src/hid-multitouch-backport-$V; sudo mkdir -p "$S"; cd "$S"
  B=https://git.kernel.org/pub/scm/linux/kernel/git/stable/linux.git/plain/drivers/hid
  for f in hid-multitouch.c hid-ids.h hid-haptic.h; do sudo curl -fsSLo "$f" "$B/$f?h=v$V"; done
  printf 'obj-m += hid-multitouch.o\n' | sudo tee Makefile >/dev/null
  printf 'PACKAGE_NAME="hid-multitouch-backport"\nPACKAGE_VERSION="%s"\nBUILT_MODULE_NAME[0]="hid-multitouch"\nDEST_MODULE_LOCATION[0]="/updates"\nAUTOINSTALL="yes"\n' "$V" | sudo tee dkms.conf >/dev/null
  sudo dkms add -m hid-multitouch-backport -v "$V"
  sudo dkms build -m hid-multitouch-backport -v "$V"
  sudo dkms install -m hid-multitouch-backport -v "$V"
  ```

  The panel's multitouch interface rebinds to `hid-multitouch` immediately (no
  reboot), `AUTOINSTALL=yes` rebuilds it on kernel updates, and udev autoloads it
  by modalias on boot. (`hid-haptic.h` is fetched only for its inline stubs when
  `CONFIG_HID_HAPTIC` is off, which is the usual case.)
- **Internet is required during install** (and updates): the installer fetches
  apt packages, Node, and builds several moOde binaries from source.
- **Validated platforms.** The port is hardware-validated (audio/DSP, renderers,
  networking incl. WiFi, local display) on these three, all with a Debian 13.5
  (Trixie) userland:

  | Board                          | Arch            | OS                       | Kernel                    |
  | ------------------------------ | --------------- | ------------------------ | ------------------------- |
  | Intel N4000 mini-PC            | amd64 (x86_64)  | Debian 13.5 (Trixie)     | `6.12.90+deb13.1-amd64`   |
  | Orange Pi 3 LTS (Allwinner H6) | arm64 (aarch64) | Armbian 26.5.1 (Trixie)  | `6.18.33-current-sunxi64` |
  | Orange Pi+ 2E (Allwinner H3)   | armhf (armv7l)  | Armbian 26.08.0 (Trixie) | `6.18.35-current-sunxi`   |

  Other Debian-13 PCs and Armbian-Trixie SBCs should work the same way; these are
  just the reference machines the installer is regularly exercised on.
