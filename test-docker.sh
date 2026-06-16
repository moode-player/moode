#!/bin/bash
#
# SPDX-License-Identifier: GPL-3.0-or-later
#
# Build a Debian 13 + systemd container and run install-x86.sh inside it to
# smoke-test the moOde x86 port. Disposable; tests everything except real audio.
#
# Usage:
#   sudo ./test-docker.sh          build image, (re)create container, run installer
#   sudo ./test-docker.sh shell    open a shell in the running container
#   sudo ./test-docker.sh logs     follow the moode-worker journal
#   sudo ./test-docker.sh clean    remove the container and image
#
# Prereq: build the frontend first on a Node 18 host:
#   npm install && npx gulp deploy --test --all      (produces build/dist/)
#
# The WebUI is exposed on http://localhost:8080/ once the install succeeds.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGE="moode-x86-test"
NAME="moode-x86-test"
HOST_PORT=8080

log()  { printf '\033[1;32m==>\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

# Needs access to the docker daemon: either via the `docker` group (preferred)
# or by running this script with sudo.
docker info >/dev/null 2>&1 || die "Cannot reach the docker daemon.
If you just added yourself to the 'docker' group, open a NEW terminal first
(group membership only applies to new sessions), or run: sudo $0 $*"

cmd="${1:-run}"

case "$cmd" in
	shell) exec docker exec -it "$NAME" bash ;;
	logs)  exec docker exec -it "$NAME" journalctl -u moode-worker -f ;;
	clean)
		docker rm -f "$NAME" 2>/dev/null || true
		docker rmi "$IMAGE" 2>/dev/null || true
		log "Cleaned."
		exit 0
		;;
	run) ;;
	*) die "Unknown command: $cmd (use: run | shell | logs | clean)" ;;
esac

[ -d "$REPO_DIR/build/dist/var/www" ] || die "Missing build/dist — run first:
  npm install && npx gulp deploy --test --all"

log "Building image $IMAGE"
docker build -f "$REPO_DIR/Dockerfile.test" -t "$IMAGE" "$REPO_DIR"

log "(Re)creating container $NAME"
docker rm -f "$NAME" 2>/dev/null || true

# Pass the host ALSA devices into the container if present, so mpd.service can
# open an output and actually start. Without /dev/snd a container has no ALSA
# subsystem and mpd fails to start (the only thing the smoke test otherwise can't
# cover). This does NOT validate real audio - it just lets mpd come up; load a
# dummy card on the host first (`sudo modprobe snd-dummy`) if you have no real one.
SND_OPT=""
if [ -d /dev/snd ]; then
	SND_OPT="--device /dev/snd"
	log "Passing host /dev/snd into the container (mpd can start)"
else
	log "No /dev/snd on host - mpd will fail to start (run: sudo modprobe snd-dummy)"
fi

# systemd in a container needs the host cgroup and a writable /run. --privileged
# + --cgroupns=host is the reliable combo on cgroup v2 hosts.
docker run -d --name "$NAME" \
	--privileged --cgroupns=host \
	-v /sys/fs/cgroup:/sys/fs/cgroup:rw \
	--tmpfs /run --tmpfs /run/lock \
	$SND_OPT \
	-v "$REPO_DIR":/opt/moode:ro \
	-p "$HOST_PORT":80 \
	"$IMAGE" >/dev/null

log "Waiting for systemd to come up"
for i in $(seq 1 30); do
	state="$(docker exec "$NAME" systemctl is-system-running 2>/dev/null || true)"
	[ "$state" = running ] || [ "$state" = degraded ] && break
	sleep 1
done
log "systemd state: ${state:-unknown}"

log "Running install-x86.sh inside the container"
# The repo is mounted read-only; install-x86.sh only reads from it and writes to
# the container filesystem, so that is fine.
set +e
docker exec "$NAME" bash -c 'cd /opt/moode && ./install-x86.sh'
rc=$?
set -e

echo
if [ "$rc" -ne 0 ]; then
	die "Installer exited with code $rc — inspect with: sudo $0 shell"
fi

log "Checking the WebUI responds"
docker exec "$NAME" curl -sS -o /dev/null -w 'WebUI HTTP status: %{http_code}\n' http://localhost/ || true

echo
log "Service status:"
docker exec "$NAME" systemctl --no-pager --type=service --state=running list-units \
	'nginx*' 'php*' 'mpd*' 'avahi*' 'moode-worker*' 2>/dev/null || true

echo
log "Open http://localhost:$HOST_PORT/ in your browser."
log "Shell:  sudo $0 shell     Worker log:  sudo $0 logs     Remove:  sudo $0 clean"
