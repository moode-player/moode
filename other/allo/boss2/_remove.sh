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
echo "Remove Etc modules i2c_dev"
sudo sed -i '/# Allo Boss2 OLED display/d' /etc/modules
sudo sed -i '/i2c-dev/d' /etc/modules
