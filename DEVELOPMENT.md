# moode-nopi — development & testing

**moode-nopi** is a *distribution fork* of [moOde audio player](https://github.com/moode-player/moode)
that runs the full moOde stack on **Debian 13 (Trixie) x86_64** and, by extension,
**Armbian Trixie arm64** — while keeping behaviour **byte-identical to moOde on a
Raspberry Pi**.

It is **not** an architectural fork: there is **one codebase**. Platform
differences are handled at runtime with `isPi()` (presence of a `Revision` line in
`/proc/cpuinfo`) and at install time by a single additive installer,
[`install.sh`](install.sh). Source deviation from upstream moOde is kept
minimal and `isPi()`-guarded, so the fork stays **rebaseable on moOde upstream**
(we follow their updates instead of diverging).

---

## Build & install (quick reference)

The installer deploys the **pre-built** web app from `build/dist/` (gitignored).
Build it first on a Node 18 host, then run the installer on the target Debian box.

```bash
# 1. Build the web app  (Node 18.20.8)
npm install
npx gulp deploy --test --all --force      # -> build/dist/

# 2. On the target (running Debian 13 x86_64 / Armbian arm64):
sudo ./install.sh            # add --reset-db to recreate the config DB
#   first run on a *minimal* Debian (no sudo yet): su -c './install.sh'
```

The installer builds the moOde-tagged audio binaries **on-device** for parity
(mpd selective-resample, caps eqfa12p, CamillaDSP, pleezer, peppyalsa), runs the
worker as `www-data`, deploys moOde's own `etc/` configs, and is re-runnable.
Log: `/var/log/install-x86.log`.

---

## Development & testing environments

moOde is deeply coupled to the **kernel, init and devices** (ALSA, kernel
filesystem modules, network-interface naming, systemd, USB DAC, HDMI). No single
environment tests everything — use the right tier for the job.

### Tier comparison

| Capability | **Docker** (`test-docker.sh`) | **KVM VM** (the rig) | **Real hardware** |
|---|:---:|:---:|:---:|
| Speed / iteration | ⚡ seconds | minutes | slowest |
| `install.sh` runs clean / idempotent | ✅ | ✅ | ✅ |
| Package resolution + on-device **builds** | ✅ | ✅ | ✅ |
| systemd as PID 1 (service enable/start) | ⚠️ privileged | ✅ native | ✅ |
| WebUI comes up (`wrkready`, nginx/php/mpd) | ✅ | ✅ | ✅ |
| Own **kernel** (FS-driver detection, modules) | ❌ host kernel | ✅ | ✅ |
| Real NICs (`net.ifnames=0` eth0, NetworkManager) | ❌ | ✅ | ✅ |
| **Audio / USB DAC** (ALSA, DSP functional) | ❌ | ⚠️ USB passthrough | ✅ |
| **HDMI / X / Chromium kiosk / Peppy** | ❌ | ⚠️ limited (no real GPU) | ✅ |
| Touchscreen / USB volume knob | ❌ | ⚠️ via USB passthrough | ✅ |
| Bluetooth | ❌ | ❌ (no adapter) | ✅ |

### When to use which

1. **Docker — fast smoke test.** Validates that the installer runs to completion,
   the dependency set resolves, all moOde binaries **compile**, systemd brings the
   services up and the WebUI responds — in one command, no hardware. Use it as the
   first gate before committing installer changes and before a slower VM/hardware
   run. **It cannot** test anything kernel- or device-bound (audio, display, touch,
   real networking, FS detection) because it shares the host kernel.

   ```bash
   npm install && npx gulp deploy --test --all   # build/dist must exist
   ./test-docker.sh run      # build image, run installer under systemd, check WebUI
   ./test-docker.sh shell    # poke inside       ./test-docker.sh logs   # worker log
   ./test-docker.sh clean    # remove image + container
   ```
   Open the WebUI at <http://localhost:8080/>.

2. **KVM VM — primary dev/integration environment.** A real kernel + real systemd +
   real network interfaces make it the most representative environment short of
   hardware, and USB passthrough gives basic DAC/audio testing. This is where most
   day-to-day work should happen: it catches the kernel/init/device issues Docker
   silently can't (FS-driver detection, `eth0` renaming + NetworkManager keyfiles,
   the `www-data`+systemd model, ALSA).

   Setup notes (one-time):
   - Boot a **Debian 13 genericcloud** image under KVM; forward host `:2222`→ssh and
     `:8080`→WebUI:80; share the repo read-only over **9p**.
   - The genericcloud kernel lacks 9p/USB/`mac80211_hwsim`: inside the VM run
     `sudo apt install linux-image-amd64`, **purge `linux-image-cloud-amd64`**
     (GRUB boots the cloud kernel otherwise), reboot.
   - Mount the repo: `sudo mount -t 9p -o trans=virtio,version=9p2000.L,ro
     <tag> /opt/moode` (re-mount after each reboot; not in fstab).
   - **USB DAC passthrough**: plug the DAC into the **host before** starting the VM,
     pass it through (e.g. `262a:9227`), else no card enumerates.
   - Install: `sudo /opt/moode/install.sh --reset-db`.

3. **Real hardware — final validation.** The only place to validate audio quality
   and the DAC, DSP, HDMI display / kiosk / Peppy, the touchscreen, Bluetooth, and
   real networking (Wi-Fi, `eth0`/`wlan0`). Validated reference: an Intel **N4000**
   mini-PC (Debian 13, HDMI touch panel, USB DAC).

### Recommendation

Use **Docker** as a quick installer/build smoke test, the **KVM VM as the main dev
environment** (it mirrors a real box at the kernel/init/device level), and **real
hardware for final functional validation**. Docker alone gives false confidence for
this project — too much depends on the kernel and devices.

### Health checks (any environment)

```bash
ps -o user= -C worker.php                 # must be www-data (x86 model)
moodeutl -q "SELECT value FROM cfg_system WHERE param='wrkready'"   # must be 1
systemctl is-active moode-worker nginx php8.4-fpm mpd
mpc outputs
journalctl -u moode-worker -f             # worker log
```
