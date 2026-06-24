# Support

**moode-nopi** is a community *distribution fork* of
[moOde audio player](https://github.com/moode-player/moode) that ports the moOde
stack to **non-Pi hardware** (Debian x86_64, Armbian arm64/armhf). It is a
hobby project, maintained on a **best-effort** basis, with **no warranty and no
guarantee of support** (it is GPL-v3 software, provided "AS IS" — see
[`LICENSE`](./LICENSE) and [`NOTICE`](./NOTICE)). It is **not affiliated with or
endorsed by** the moOde project.

## Supported base

moode-nopi runs **only** on the **Debian 13 "Trixie" family**: Debian, Armbian
Trixie, or Raspberry Pi OS (Raspbian) Trixie. **Ubuntu and other distributions
are not supported** — their newer toolchains break the pinned source builds
(e.g. CMake 4 vs ashuffle, gcc-15) and the upstream package repos publish Debian
suites only. Your diagnostics report shows `Base OS = SUPPORTED` / `UNSUPPORTED`
at the top; bug reports on an unsupported base will be closed.

Please pick the right channel — it keeps the bug tracker usable and gets you a
better answer.

## ⛔ Not here

- **You run a real Raspberry Pi.** moode-nopi only concerns *non-Pi* hardware.
  Anything on a Pi is upstream moOde's domain → use the
  [official moOde Forum](https://moodeaudio.org/).
- **A general moOde question** (how a feature works, audio config, a bug that
  also happens on a Pi) — that's moOde itself, not the port → moOde Forum.

A quick test: run `sudo ./report.sh` (see below). If it prints
`isPi() = TRUE`, this is **not** a moode-nopi issue.

## 💬 Questions, ideas, help installing

Use **[Discussions](https://github.com/Gjuju/moode-nopi/discussions)**, not the
issue tracker. Setup help, "is this expected?", feature ideas — all go there.

## 🐞 Reporting a port-specific bug

Open a **Bug report** issue *only* for a reproducible problem that is specific to
running moOde on non-Pi hardware. The form **requires a diagnostics report**:

```bash
cd <your moode-nopi clone>
git pull
sudo ./report.sh --upload      # paste the printed URL into the issue
```

`report.sh` collects a **redacted** diagnostics bundle (platform, services,
worker/MPD state, DSP, network, package versions, logs). Secrets (Wi-Fi keys,
NAS credentials, public IP/MAC) are masked automatically — but please skim the
file before sharing. No network? Run `sudo ./report.sh` and attach the local
`/tmp/nopi-report-*.txt` file instead.

**Issues without a diagnostics report, or that turn out to be a real-Pi / general
moOde question, will be closed** and redirected here. Thanks for understanding —
it's what keeps a one-person fork sustainable.
