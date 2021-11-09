#!/bin/bash
################################################################
#
# Build Recipe CamillaDSP and subprojects 1.1
#
# This build recipe will generate python and deb packages output.
#
# (C) bitkeeper 2021 http://moodeaudio.org
# License: GPLv3
#
################################################################

#export MOODE_WORK=~/moode.dev/moode
#export CDSP_WORK=~/moode.dev/camilladsp.dev

export MOODE_WORK=`pwd`/../..
export CDSP_WORK=./camilladsp.dev

CAMILLADSP_VER=0.6.3
PYCAMILLADSP_VER=0.6.0
PYCAMILLADSP_PLOT_VER=0.6.2
CAMILLAGUI_VER=0.6.0
CAMILLAGUI_BACKEND_VER=0.8.0

mkdir -p $CDSP_WORK
cd $CDSP_WORK


# ---------------------------------------------------------------
# 0. install build deps (if needed)
# ---------------------------------------------------------------
sudo apt-get update

# general deb build stuff
sudo apt-get install build-essential fakeroot devscripts

# deps for creating deb with fpm
# https://fpm.readthedocs.io/en/latest/index.html
RUBY_VER=`ruby --version`
if [[ $? -gt 0 ]]
then
   sudo apt install ruby-full
fi

FPM_VER=`fpm --version`
if [[ $? -gt 0 ]]
then
	sudo gem install --no-document fpm
fi

# deps building the react app
NPM_VER=`npm --version`
if [[ $? -gt 0 ]]
then
	sudo apt-get install npm
fi

# Install cargo + rust tools
# Required for camilladsp
CARGO_VER=`cargo --version`
if [[ $? -gt 0 ]]
then
  echo "cargo: not installed"
	export RUSTUP_UNPACK_RAM=94371840; export RUSTUP_IO_THREADS=1
	echo "Choose option 1 when asked !"
	# Requires log of and log in after install
	curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
	#echo "logout and in again and restart script!"
	#exit 1
	source $HOME/.cargo/env
 else
  echo "cargo: already installed"
fi

CARGO_DEB_VER=`cargo-deb --version`
if [[ $? -gt 0 ]]
then
	echo "cargo-deb: not installed, install it:"
	cargo install cargo-deb
else
	echo "cargo-deb: already installed"
fi

# ---------------------------------------------------------------
# 1. pycamilladsp
# ---------------------------------------------------------------

# if not already cloned:
git clone https://github.com/HEnquist/pycamilladsp.git
cd pycamilladsp
# if already cloned:
# git fetch --all
git checkout -b v$PYCAMILLADSP_VER v$PYCAMILLADSP_VER
python3 ./setup.py bdist_wheel
cp dist/camilladsp-$PYCAMILLADSP_VER-py3-none-any.whl $MOODE_WORK/other/camilladsp/gui
cd ..

# ---------------------------------------------------------------
# 2. pycamilladsp-plot
# ---------------------------------------------------------------

# if not already cloned:
git clone https://github.com/HEnquist/pycamilladsp-plot.git
cd pycamilladsp-plot
# if already cloned:
# git fetch --all
git checkout -b v$PYCAMILLADSP_PLOT_VER v$PYCAMILLADSP_PLOT_VER
# old jsonschema paackage isn't good enough:
patch -p1 setup.py < $MOODE_WORK/other/camilladsp/gui/camilladsp_plot_jsonschema_dep.patch
python3 ./setup.py bdist_wheel
cp dist/camilladsp_plot-$PYCAMILLADSP_PLOT_VER-py3-none-any.whl $MOODE_WORK/other/camilladsp/gui
cd ..

# ---------------------------------------------------------------
# 3. camillagui
# ---------------------------------------------------------------
git clone https://github.com/HEnquist/camillagui.git
cd camillagui
# if already cloned:
# git fetch --all
git checkout -b v$CAMILLAGUI_VER v$CAMILLAGUI_VER
# add option to hide files tab on expert mode:
patch -p1 < $MOODE_WORK/other/camilladsp/gui/camillagui_hide_files.patch
# installing npm deps with npm ci failed, so use npm install instead
# npm ci
npm install
npm install react-scripts
npm run-script build
cd ..

# ---------------------------------------------------------------
# 4. camillagui-backend
# ---------------------------------------------------------------
git clone https://github.com/HEnquist/camillagui-backend.git
cd camillagui-backend
# if already cloned:
# git fetch --all
git checkout -b v$CAMILLAGUI_BACKEND_VER v$CAMILLAGUI_BACKEND_VER
patch -p1 < $MOODE_WORK/other/camilladsp/gui/camillagui_backend_hide_files.patch

# setup a directory structure for the files which should end up in the deb file:
rm -rf package
mkdir -p package/opt/camillagui
mkdir -p package/etc/systemd/system

# copy the required files into the directory structure
cp -r ../camillagui/build backend config LICENSE.txt main.py README.md package/opt/camillagui
cp $MOODE_WORK/other/camilladsp/gui/css-variables.css package/opt/camillagui/build
cp $MOODE_WORK/other/camilladsp/gui/camillagui.yml package/opt/camillagui/config
cp $MOODE_WORK/other/camilladsp/gui/gui-config.yml package/opt/camillagui/config
cp $MOODE_WORK/other/camilladsp/gui/camillagui.service package/etc/systemd/system

# build a deb files based on the directory structure
fpm -s dir -t deb -n camillagui -v $CAMILLAGUI_BACKEND_VER \
--license GPLv3 \
--category sound \
-S moode \
--iteration 1moode1 \
--deb-priority optional \
--url https://github.com/HEnquist/camilladsp \
-m moodeaudio.org \
--description 'CamillaGUI is a web-based GUI for CamillaDSP.' \
--pre-install $MOODE_WORK/other/camilladsp/gui/deb_preinstall.sh \
--before-remove $MOODE_WORK/other/camilladsp/gui/deb_beforeremove.sh \
package/opt/camillagui/.=/opt/camillagui \
package/etc/=/etc/.

cp camillagui_$CAMILLAGUI_BACKEND_VER*.deb $MOODE_WORK/other/camilladsp/gui
cd ..

# ---------------------------------------------------------------
# 5. camilladsp
# ---------------------------------------------------------------
git clone  https://github.com/HEnquist/camilladsp.git
cd camilladsp
# if already cloned:
# git fetch --all
git checkout -b v$CAMILLADSP_VER v$CAMILLADSP_VER
# add meta data for generating the deb
patch -p1 < $MOODE_WORK/other/camilladsp/camilladsp_cargo-deb.patch
RUSTFLAGS='-C target-feature=+neon -C target-cpu=native' cargo-deb -- --no-default-features --features alsa-backend --features websocket

cp target/debian/camilladsp_$CAMILLADSP_VER-moode1_armhf.deb $MOODE_WORK/other/camilladsp/
cd ..

# ---------------------------------------------------------------
# done
# ---------------------------------------------------------------
# rm -rf $CDSP_WORK
echo "done building camilladsp and subprojects"


