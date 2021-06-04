#!/bin/bash
rm -rf /etc/systemd/system/mpd.service.d
systemctl daemon-reload
systemctl restart mpd
