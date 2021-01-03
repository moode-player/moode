#!/bin/bash
#
# Collect and build CamillaDSP GUI and related
#
# bitlab 2021
#

CAMILLAGUI_VER=v0.6.0
PYCAMILLADSP_VER=v0.5.0
PYCAMILLADSP_PLOT_VER=v0.4.3

mkdir camilladsp.dev
cd camilladsp.dev
git clone --single-branch --branch $PYCAMILLADSP_VER https://github.com/HEnquist/pycamilladsp.git
cd pycamilladsp
python3 ./setup.py bdist_wheel

cd ..

git clone --single-branch --branch $PYCAMILLADSP_PLOT_VER https://github.com/HEnquist/pycamilladsp-plot.git
cd pycamilladsp-plot
python3 ./setup.py bdist_wheel

cd ../..
cp camilladsp.dev/pycamilladsp/dist/camilladsp-*-py3-none-any.whl .
cp camilladsp.dev/pycamilladsp-plot/dist/camilladsp_plot-*-none-any.whl .

rm -rf camilladsp.dev

# Backend release also contains a build of the frontend
wget -O camillagui-$CAMILLAGUI_VER.zip https://github.com/HEnquist/camillagui-backend/releases/download/$CAMILLAGUI_VER/camillagui.zip

echo "Don't forget to remove the old wheels and zip with git"