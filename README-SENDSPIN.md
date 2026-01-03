# Experiment: moOde + Sendspin

Experiment: extending moOde with a renderer for Sendspin.

Start a Sendspin server; e.g.

```bash
sudo docker run -v /home/your-login-name/music-assistant-data:/data --network host --cap-add=DAC_READ_SEARCH --cap-add=SYS_ADMIN --security-opt apparmor:unconfined ghcr.io/music-assistant/server:beta
```

Install [sendspin-cli](https://github.com/Sendspin/sendspin-cli) in moOde; e.g.

```bash
sudo pip install uv --break-system-packages
sudo uv tool install sendspin
```

Test via SSH if the Sendspin server can be reached; and desired audio device is available:

```bash
sudo /root/.local/share/uv/tools/sendspin/bin/sendspin --list-servers
sudo /root/.local/share/uv/tools/sendspin/bin/sendspin --list-audio-devices
```

Change the audio device by modifying `/etc/systemd/system/sendspin.service` :

```service
[Unit]
Description=Sendspin CLI
After=network-online.target
Requires=network-online.target

[Service]
Type=simple
ExecStart=/root/.local/share/uv/tools/sendspin/bin/sendspin --audio-device snd_rpi_hifiberry_dacplus --headless
Restart=on-failure
User=root

[Install]
WantedBy=multi-user.target
```
