#!/bin/bash
#
# Install Boss2 OLED driver and support files
#
sudo ./dev.sh
echo "Install Python libs"
sudo apt-get -y install python-smbus python-pil
sudo apt-get clean
echo "Install Display driver"
sudo cp /mnt/moode-player/GitHub/moode/other/allo/boss2/boss2_oled.tar.gz /opt/
cd /opt/
sudo tar -xzf ./boss2_oled.tar.gz
sudo rm ./boss2_oled.tar.gz
cd ~
echo "Install Systemd unit"
sudo cp /mnt/moode-player/GitHub/moode/lib/systemd/system/boss2oled.service /lib/systemd/system/
sudo systemctl daemon-reload
sudo systemctl disable boss2oled.service
