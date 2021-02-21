#!/bin/bash
#
# Install CamillaDSP GUI and related
#
# bitlab 2021
#

CAMILLAGUI_VER=0.6.0_cea187d85599ad9f0b8d2f69e496bf281a58f34a
PYCAMILLADSP_VER=0.5.0
PYCAMILLADSP_PLOT_VER=0.4.5

sudo pip3 install aiohttp
sudo pip3 install camilladsp-$PYCAMILLADSP_VER-py3-none-any.whl

# Installing can take quite some time to install nump and matplotlib
sudo pip3 install numpy matplotlib
sudo pip3 install camilladsp_plot-$PYCAMILLADSP_PLOT_VER-py3-none-any.whl
sudo mkdir -p /opt
sudo unzip -o camillagui-v$CAMILLAGUI_VER.zip -d /opt/camillagui

sudo cp camillagui.yml /opt/camillagui/config
sudo cp gui-config.yml /opt/camillagui/config
sudo cp camillagui.service /etc/systemd/system

# Don't start it, start it when needed from the moode config pages
#sudo systemctl enable camillagui
#sudo systemctl start camillagui


