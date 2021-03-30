#!/bin/bash
#
# Remove Boss2 OLED driver and support files
#
echo "Remove Python libs"
sudo apt-get -y purge python-smbus python-pil
sudo apt-get clean
echo "Remove Display driver"
sudo rm -rf /opt/boss2_oled
echo "Remove Systemd unit"
sudo rm /lib/systemd/system/boss2oled.service
sudo systemctl daemon-reload
