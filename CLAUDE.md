# moode-nopi (moOde non-Pi port) — project guide

This repo is the **moOde audio player** source (PHP/nginx/MPD/ALSA/SQLite). This
fork (**moode-nopi**) ports it to **Debian 13 (Trixie) x86_64** — and by
extension **Armbian Trixie arm64/armhf** — while keeping the single codebase
working unchanged on a real Raspberry Pi.

## source code and packages

- **Source code** moOde player project for Pi `https:// github.com/moode-player/moode`
- **Doc** for moOde Developer documentation `https:// github.com/moode-player/pkgbuild`
- **Package builder** for building dependencies `https:// github.com/moode-player/pkgbuild`
- **Package source** where to find built dependencies `https:// github.com/moode-player/pkgsource`

## Goal & guiding rules

- **Port via runtime detection, not a fork.** One codebase. Platform differences
  are handled at runtime with `isPi()` (`www/inc/common.php`, = presence of a
  `Revision` line in `/proc/cpuinfo`), and at install time by **one additive
  installer**, `install.sh`, that sets up the moOde stack on a running Debian.
- **Maximum iso with the Pi.** Behaviour, versions, and especially **all audio
  processing** must match moOde on the Pi. When moOde ships a patched binary
  (mpd, caps…), build that exact patched version for x86 — don't settle for a
  different stock one.
- **Minimize source deviation.** Fix x86 issues in `install.sh` / system
  config, **not** by editing upstream moOde source. The only acceptable source
  edits are tiny **`isPi()`-guarded** changes where upstream has a baked-in Pi
  assumption that cannot be fixed from the installer (e.g. worker home-dir writes).
  Such edits must leave Pi behaviour byte-identical.
- **No committed binaries that rot.** Build moode-tagged `.deb`s on-device or
  fetch pinned release binaries from the installer; never commit blobs to the tree.
- **Granular commits** prefixed `x86 …`, kept small until a final squash/rebase
  onto `develop` (deferred until after real-hardware validation).
- **Build before deploy.** After editing `www/` code you MUST `gulp deploy`
  (below). `build/dist/` is the deploy artifact, not `www/`.
- **GPLv3 compliance (keep the modification notice current).** moOde is GPL v3
  and this fork redistributes it, so the GPL §5(a)/(b) "this is a modified
  version + relevant date" notice is **mandatory** and lives in two files: the
  fork banner atop `README.md` and the `NOTICE` file. **Never remove** `LICENSE`,
  `NOTICE`, the README banner, or upstream copyright headers; never relicense or
  add usage restrictions; keep tagged binaries' source available (our on-device
  build / pinned-source rule already does this). **When you cut a new
  `*-nopi.N` tag, update the date + version** in BOTH the README banner and
  `NOTICE` (the "latest: …" line) as part of the release commit — that's the
  one upkeep step. The fork **start** date (2026-06-16) is fixed; don't change it.

## Project structure

- `install.sh` — **the** central additive installer. Organised in phases:
  Phase 1 packages, 1b helper bins, 1c CamillaDSP, 1d pleezer+cargo-deb, 1e caps
  (Parametric EQ), 1f mpd (selective resample), 2 deploy web app, 3/3b configs,
  5 perms, 5b music storage, 5c on-demand renderers, 6 worker/devmon units,
  7 enable/disable services. Flags at top: `INSTALL_BLUETOOTH/AIRPLAY/UPNP/...`.
  Re-runnable; `--reset-db` recreates the DB. Reads from `build/dist/`.
- `www/` — the PHP web app + daemons. Key files:
  - `www/daemon/worker.php` — startup + job-processing daemon. Generates
    `/etc/mpd.conf` from the DB, runs queued jobs (DSP toggles, renderer installs).
  - `www/inc/common.php` — `isPi()`, `sysCmd()` (= `exec('sudo LC_ALL=C …')`,
    ALWAYS root), `submitJob()`.
  - `www/inc/mpd.php` — generates `mpd.conf`. `www/inc/cdsp.php` — CamillaDSP.
    `www/inc/renderer.php` — start/stop BT/Squeezelite/etc. services.
- `etc/alsa/conf.d/` — the ALSA plugin chain (`_audioout`, camilladsp, alsaequal,
  eqfa12p, crossfeed, bluealsa…). `*.overwrite.*` = files deployed with the
  `.overwrite` stripped to replace a stock package's config/unit.
- `build/dist/` — gulp output (gitignored); what `install.sh` deploys.
- `var/local/www/db/moode-sqlite3.db.sql` — the config DB schema.

## Build methods

- **Web app:** Node 18.20.8 — `export PATH="$HOME/.nvm/versions/node/v18.20.8/bin:$PATH"`
  then **`npx gulp build --all --force && npx gulp deploy --test --all --force`** →
  `build/dist/`. `gulp build` is what minifies+bundles CSS/JS (styles.min.css,
  main.min.css, *.min.js) into app.dest; `gulp deploy` only COPIES from there. On a
  fresh clone, `gulp deploy` ALONE ships un-bundled assets → an unstyled "pure HTML"
  WebUI (it silently "worked" before only when app.dest held bundles from a prior
  build). install.sh Phase 0b runs both.
- **moode-tagged `.deb`s** — two patterns, prefer (B):
  - (A) Debian source + moOde patch: `dget -qd -u <debian .dsc>` (use **`-qd`** —
    plain `dget` auto-extracts and then a later `dpkg-source -x` fails "target
    exists"), `dpkg-source -x`, `patch -p1 < <pkgbuild patch>`,
    `dch -b -v <ver>moode1`, `dpkg-buildpackage -b -us -uc`. Used for **caps**.
  - (B) **moOde's exact source via cloudsmith deb-src** (most iso, reusable for
    any moOde audio pkg): add **deb-src only** (their binaries are arm)
    `https://dl.cloudsmith.io/public/moodeaudio/m8y/deb/raspbian trixie main`
    (key `…/m8y/gpg.key`), `apt-get update`, `apt-get source <pkg>=<ver>moode1`,
    `mk-build-deps --install` (needs `devscripts`+`equivs`), `dpkg-buildpackage -b`,
    `dpkg -i --force-confold <deb>` (plain `apt install` PROMPTS on conffiles like
    mpd.conf and half-configures), `apt-mark hold <pkg>`. Used for **mpd**.
- **Authoritative moOde references** (always cross-check, don't guess):
  - `moode-player/imgbuild` → `moode-cfg/stage*-moode-install_01-packages` = the
    pinned package list the Pi image installs (+ `stage3_02-…-post_…run-chroot.sh`).
  - `moode-player/pkgbuild` → per-package build recipes & patches.
  - `moode-player/plugins` → on-demand renderer (AirPlay/Spotify) zips.
  - `moode-player/docs` → architecture/build/packaging docs (read before porting).

## The root / $USER privilege model (critical — read before touching perms)

- On x86 the **worker runs as `www-data`** (systemd unit `moode-worker`), the SAME
  user as nginx/php-fpm, so the PHP `files` session is shared natively. On the Pi
  the worker is root (rc.local) — that split silently breaks the session on Debian.
- **Every privileged op goes through `sysCmd()` = `sudo` → root.** So the worker
  doesn't need to be root; helpers it launches (thumb-gen, plugin-updater…) run
  as root via sudo.
- `fs.protected_regular=2` (Trixie default): a process can't open-for-write a file
  it doesn't own in a sticky world-writable dir unless the file is owned by the
  dir owner → **`/var/local/php` must be `chown www-data`** (Phase 5) or every
  root-run helper sees an empty `$_SESSION`.
- The player home is `0700 <user>:<user>`; `www-data` can't write it. Worker
  **direct** (non-sudo) writes into the home must be `isPi()`-guarded to a
  www-data-writable dir off the Pi (done for `install_*.log`). **NEVER** `chmod
  g+w` the home — sshd `StrictModes` then rejects the player's pubkey auth (locks
  you out); ACLs flip the same st_mode bit, so no perms-only fix works.

## Errors to avoid (hard-won)

- `chmod g+w /home/<user>` → SSH lockout (StrictModes). Use `isPi()` guard instead.
- Running moOde's `pkgbuild` `rebuilder.lib.sh`/`build.sh` on x86 — it adds a
  **raspbian** cloudsmith **binary** repo. Replicate the recipe manually, or use
  **deb-src only**.
- `cargo-deb` 3.7.0 fails to compile on Debian's Rust 1.85 → pin **2.12.1**.
- `dget <dsc>` auto-extracts → use `dget -qd` when you also call `dpkg-source -x`.
- Installing a moOde `.deb` with `apt install` prompts on conffiles (mpd.conf) and
  half-configures → `dpkg -i --force-confold`, then re-`apt-mark hold`.
- Features needing moOde's **patched** binaries: Parametric EQ (`EqFA12p` in
  caps), MPD "Selective resampling" (`selective_resample_mode`). Stock binaries
  reject these and the component fails (MPD won't start → "Socket open failed").
- After `--reset-db`, audio config resets to **Pi defaults** (HDMI/vc4hdmi) — pick
  the real output in Configure > Audio. A DAC with no hardware volume control
  needs MPD volume = Software (else the volknob reads 0).
- CamillaDSP `custom` mode does NOT auto-generate `working_config.yml`; pick a
  named config or supply one, else camilladsp starts with no device.

## Debug methods (the x86 VM)

Rig lives at `/home/$USER/moode-nopi-vm/` (NOT a tracked moOde file).

- `./run-vm.sh start|ssh|stop|reset` — boots a Debian 13 cloud image (KVM).
  Forwards host `:2222`→ssh, `:8080`→WebUI:80. Repo shared read-only over 9p.
- **Rig prerequisite:** the genericcloud kernel lacks 9p/USB/`mac80211_hwsim`.
  `sudo apt install linux-image-amd64` then **purge `linux-image-cloud-amd64`**
  (GRUB boots cloud kernel otherwise) and reboot.
- Mount the repo in the VM: `sudo mount -t 9p -o trans=virtio,version=9p2000.L,ro
  moderepo /opt/moode` (not in fstab — re-mount after reboot).
- SSH: `ssh -i /home/$USER/moode-nopi-vm/id_vm -p 2222 -o StrictHostKeyChecking=no
  -o UserKnownHostsFile=/dev/null moode@127.0.0.1`. Console login: the cloud-init
  creds set in `run-vm.sh`'s `user-data`.
- **USB DAC passthrough** (run-vm.sh `usb-host` 262a:9227): plug the DAC into the
  HOST **before** `./run-vm.sh start`, else no card enumerates.
- Install in the VM: `sudo INSTALL_LOG=/var/log/install-nopi.log /opt/moode/install.sh
  --reset-db` (the `/opt/moode` 9p mount is read-only, so the default in-repo log
  `install-nopi.log` can't be written there — override the path).
- Health checks: `ps -o user= -C worker.php` (must be `www-data`),
  `cfg_system.wrkready` must be `1` (UI blank until then), `mpc outputs`,
  `systemctl is-active moode-worker nginx php8.4-fpm mpd`,
  `journalctl -u moode-worker|mpd`, `/var/log/moode.log`.
- BT can't be tested in the VM (no BT adapter) → defer to real hardware.

## Status & detailed log

Detailed, evolving findings (every package, fix, and gotcha) live in the agent
memory files `x86-port-effort.md` (high-level status) and `x86-deps-and-network.md`
(per-feature log). **Validated on a real N4000 mini-PC** (Debian 13, HDMI touch
panel, USB DAC): audio/DSP (mpd `0.24.12-1moode1`, caps `0.9.26-1moode1`,
CamillaDSP 4.1.3, EQ, crossfeed, resampling), all renderers, networking
(eth0/wlan0 via NetworkManager — `net.ifnames=0` + NM keyfiles written via root
staging), local display (X11 + Chromium kiosk, native res + touch), and Peppy
**Meter + Spectrum** (full-screen 1920×1280, custom ×4-scaled skins). Spectrum's
former black-screen was the upstream `spectrum.py` running the draw loop in a
background thread (never presents on x86/X11/SDL2); fixed by overlaying moOde's
own `spectrum.py` (PeppySpectrum issue #1 fix — draws on the main loop) on the
upstream clone, exactly like moOde's `pkgbuild` `build.sh`. The local-display
suite is fully working: touchmon WebUI↔Peppy auto-switch, on-screen keyboard
(needs the CrOS user-agent spoof), and the USB volume knob (`usb_volknob` —
triggerhappy run as www-data, off by default). The per-config audit (General
settings / HDMI displays / Volume controllers) is **done**. **Open:** squash/rebase
onto `develop`.
