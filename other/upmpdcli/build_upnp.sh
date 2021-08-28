#!/bin/bash
#=========================================================================
# Purpose script: Build installable deb package of moode upnp substem
#                 to replace manually build + installed ones.
#
# (C) 2020 @bitlab (@bitkeeper Git)
#
# Used resources for building the package
# https://www.lesbonscomptes.com/upmpdcli/ - home of the upmpdcli and related packages
# https://framagit.org/medoc92 - the source repo of the upmpdcli and related packages
# https://framagit.org/medoc92/libupnpp/-/blob/master/doc/libupnpp-ctl.txt
# https://www.debian.org/doc/manuals/maint-guide/build.en.html
#
# This Program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3, or (at your option)
# any later version.
#
# This Program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#==========================================================================

VER_LIBNPUPNP=4.1.1
VER_LIBUPNPP=0.21.0
VER_LIBUPNPP_BIND=0.20.1
VER_UPMPDCLI=1.5.11


FILE=./build_upnp.sh
if [[ ! -f "$FILE" ]]; then
  echo "Script is stated from the wrong directory (cd first to the location of this script)!"
  exit 1
fi

mkdir -p upmpdcli.dev
cd upmpdcli.dev

# ---------------------------------------------------------------
# 0. install general pre build requirements:
# ---------------------------------------------------------------
sudo apt-get update

# install pre build requirements:
array=( libtool-bin build-essential fakeroot devscripts swig )
for i in "${array[@]}"
do
	dpkg -l $i > /dev/null 2>&1
    if [[ $? -gt 0 ]]
    then
        echo "package $i : missing"
        sudo apt-get -y install $i
    else
        echo "package $i : present"
    fi
done


# clean non packages versions of the upnp subsystem, if needed
../clean_upnp_subsystem.sh

# ---------------------------------------------------------------
# 1. libnpupnp
# ---------------------------------------------------------------
# TODO: in package present also check version (also by all other further package step below)
#dpkg -l libnpupnp1-dev | grep $VER_LIBNPUPNP > /dev/null 2>&1
ls ../libnpupnp1_$VER_LIBNPUPNP-1~ppaPPAVERS~SERIES1_armhf.deb \
../libupnpp6_$VER_LIBUPNPP-1~ppaPPAVERS~SERIES1_armhf.deb > /dev/null 2>&1
if [[ $? -gt 0 ]]
then
    rm -rf libnpupnp_build
    mkdir -p libnpupnp_build
    cd libnpupnp_build

    # method 1 - from source package.
    # dget -u https://www.lesbonscomptes.com/upmpdcli/downloads/debian/pool/main/libn/libnpupnp1/libnpupnp1_4.0.14-1~ppa1~unstable.dsc
    # cd libnpupnp1-4.0.14/
    # debuild -us -uc

    # method 2 - from git repo
    # It is easier to use the package source, but the package repo doesn't contain all packages version. So we use method 2.
    git clone https://framagit.org/medoc92/npupnp.git libnpupnp-$VER_LIBNPUPNP
    cd libnpupnp-$VER_LIBNPUPNP
    git checkout -b libnpupnp-v$VER_LIBNPUPNP libnpupnp-v$VER_LIBNPUPNP
    dpkg-checkbuilddeps
    git archive  --format=tar.gz --output ../libnpupnp1_$VER_LIBNPUPNP.orig.tar.gz libnpupnp-v$VER_LIBNPUPNP
    dpkg-buildpackage -us -uc

    cd ..

    # install the deb + dev package (required for build of other packages)
    cp ./libnpupnp1_$VER_LIBNPUPNP-1~ppaPPAVERS~SERIES1_armhf.deb ../..
    sudo apt-get install ./libnpupnp1-dev_$VER_LIBNPUPNP-1~ppaPPAVERS~SERIES1_armhf.deb ./libnpupnp1_$VER_LIBNPUPNP-1~ppaPPAVERS~SERIES1_armhf.deb
    libtool --finish /usr/lib/arm-linux-gnueabihf

    cd ..
else
    echo "libnpupnp1-$VER_LIBNPUPNP already present ... skip build"
fi


# ---------------------------------------------------------------
# 2. libupnpp
# ---------------------------------------------------------------
# dpkg -l libupnpp6-dev | grep $VER_LIBUPNPP > /dev/null 2>&1
ls ../libupnpp6_$VER_LIBUPNPP-1~ppaPPAVERS~SERIES1_armhf.deb \
../python3-libupnpp_$VER_LIBUPNPP_BIND-1~ppaPPAVERS~SERIES1_armhf.deb \
> ../upmpdcli_$VER_UPMPDCLI-1~ppaPPAVERS~SERIES1_armhf.deb /dev/null 2>&1
if [[ $? -gt 0 ]]
then
    rm -rf libupnpp_build
    mkdir -p libupnpp_build
    cd libupnpp_build

    git clone https://framagit.org/medoc92/libupnpp.git libupnpp-$VER_LIBUPNPP
    cd libupnpp-$VER_LIBUPNPP
    git checkout -b libupnpp-v$VER_LIBUPNPP libupnpp-v$VER_LIBUPNPP
    git archive  --format=tar.gz --output ../libupnpp6_$VER_LIBUPNPP.orig.tar.gz libupnpp-v$VER_LIBUPNPP
    dpkg-checkbuilddeps
    dpkg-buildpackage -us -uc

    cd ..

    # install the deb + dev package (required for build of other packages)
    cp ./libupnpp6_$VER_LIBUPNPP-1~ppaPPAVERS~SERIES1_armhf.deb ../..
    sudo apt-get install ./libupnpp6-dev_$VER_LIBUPNPP-1~ppaPPAVERS~SERIES1_armhf.deb ./libupnpp6_$VER_LIBUPNPP-1~ppaPPAVERS~SERIES1_armhf.deb

    libtool --finish /usr/lib/arm-linux-gnueabihf

    cd ..
else
    echo "libupnpp6-$VER_LIBUPNPP already present ... skip build"
fi


# ---------------------------------------------------------------
# 3. libupnpp-bindings
# ---------------------------------------------------------------
# dpkg -l python3-libupnpp | grep $VER_LIBUPNPP_BIND > /dev/null 2>&1
ls ../python3-libupnpp_$VER_LIBUPNPP_BIND-1~ppaPPAVERS~SERIES1_armhf.deb > /dev/null 2>&1
if [[ $? -gt 0 ]]
then
    rm -rf libupnpp-bindings_build
    mkdir -p libupnpp-bindings_build
    cd libupnpp-bindings_build

    git clone https://framagit.org/medoc92/libupnpp-bindings.git libupnpp-bindings-$VER_LIBUPNPP_BIND
    cd libupnpp-bindings-$VER_LIBUPNPP_BIND
    git checkout -b libupnpp-bindings-v$VER_LIBUPNPP_BIND libupnpp-bindings-v$VER_LIBUPNPP_BIND

    ./autogen.sh
    chmod +x ./configure
    # the two commands shouldn't be needed, but without wrong makefiles are generate wich cause a swig error about an unsupported option -Wdate-time
    ./configure --prefix=/usr PYTHON_VERSION=3.7
    make

    # -b only binary pkg no source possible to
    # -d option required because the dep names aren't correct for python3
    dpkg-buildpackage -b -uc -us -d
    cd ..

    # install the deb (required by get artwork script, not for further build)
    cp ./python3-libupnpp_$VER_LIBUPNPP_BIND-1~ppaPPAVERS~SERIES1_armhf.deb ../..
    sudo apt-get install ./python3-libupnpp_$VER_LIBUPNPP_BIND-1~ppaPPAVERS~SERIES1_armhf.deb
    libtool --finish /usr/lib/arm-linux-gnueabihf

    cd ..
else
    echo "python3-libupnpp-$VER_LIBUPNPP_BIND already present ... skip build"
fi

# ---------------------------------------------------------------
# 4. upmpdcli
# ---------------------------------------------------------------
# dpkg -l upmpdcli | grep $VER_UPMPDCLI > /dev/null 2>&1
ls ../upmpdcli_$VER_UPMPDCLI-1~ppaPPAVERS~SERIES1_armhf.deb > /dev/null 2>&1
if [[ $? -gt 0 ]]
then
    rm -rf upmpdcli_build
    mkdir -p upmpdcli_build
    cd upmpdcli_build

    git clone https://framagit.org/medoc92/upmpdcli.git upmpdcli-$VER_UPMPDCLI
    cd upmpdcli-$VER_UPMPDCLI
    git checkout -b upmpdcli-v$VER_UPMPDCLI upmpdcli-v$VER_UPMPDCLI
    # # Only required during build NOT for runtime image
    sudo apt-get -y install dh-systemd qt5-default qt5-qmake qtbase5-dev

    dpkg-checkbuilddeps

    git archive  --format=tar.gz --output ../upmpdcli_$VER_UPMPDCLI.orig.tar.gz upmpdcli-v$VER_UPMPDCLI
    dpkg-buildpackage -us -uc
    cd ..

    cp ./upmpdcli_$VER_UPMPDCLI-1~ppaPPAVERS~SERIES1_armhf.deb ../..
    sudo apt-get install ./upmpdcli_$VER_UPMPDCLI-1~ppaPPAVERS~SERIES1_armhf.deb

    cd ..
else
    echo "upmpdcli-$VER_UPMPDCLI already present ... skip build"
fi


# ---------------------------------------------------------------

cd ..

# remove build environment
# rm -rf ./upmpdcli.dev
