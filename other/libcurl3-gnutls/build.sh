#!/bin/bash
#
# Build Recipe for special version of libcurl3-gnutls to be side loaded with MPD
#
# The MPD curl plugin isn't stable with version 7.64.0-4+deb10u2.
# Upgrading the system to a new version breaks other tools.
# That is why it repacks libcurl3-gnutls under a different name and location.
# Also the MPD is configurated to set the LD_LIBRARY_PATH to prefer this version
#
# (C) bitkeeper 2021 http://moodeaudio.org
# License: GPLv3
#
################################################################

# pacakge upstream deb:
# CURL_VER=7.74.0
# CURL_PKG_VER=$CURL_VER-1.2~bpo10+1
# CURL_DEST=libcurl3-gnutls_7.74.0
# CURL_BASE_PKG=http://ftp.nl.debian.org/debian/pool/main/c/curl/libcurl3-gnutls_${CURL_PKG_VER}_armhf.deb

# package downstream deb:
# build the downstream package (extract for the cloudsmith repo below):
# dget -u http://security.debian.org/pool/updates/main/c/curl/curl_7.52.1-5+deb9u14.dsc
# cd curl-7.52.1
# in the debian/control fix the libssl and libssl-dev dep to the following line:  libssl1.1-dev | libssl-dev (>= 1.1),
# dpkg-buildpackage -us -uc

CURL_VER=7.52.1
CURL_PKG_VER=$CURL_VER-5+deb9u14
CURL_DEST=libcurl3-gnutls_7.52.1
CURL_BASE_PKG=https://dl.cloudsmith.io/public/moodeaudio/m7x_test/deb/raspbian/pool/buster/main/l/li/libcurl3-gnutls_7.52.1-5+deb9u14_armhf.deb

mkdir -p build/etc/systemd/system/mpd.service.d
mkdir -p build/opt
wget $CURL_BASE_PKG
dpkg-deb -x libcurl3-gnutls_${CURL_PKG_VER}_armhf.deb build/opt/${CURL_DEST}
cp -p override.conf build/etc/systemd/system/mpd.service.d/override.conf

fpm -s dir -t deb -n mpd_libcurl3-gnutls -v $CURL_VER \
--license GPLv3 \
--category libs \
-S moode \
--iteration 1moode1 \
--deb-priority optional \
--deb-no-default-config-files \
--url http://curl.haxx.se \
-m ghedo@debian.org \
--description 'Easy-to-use client-side URL transfer library (GnuTLS flavour). This special package to sideload the version that works with MPD curl plugin' \
--depends libbrotli1 \
--after-install post_install.sh \
--after-remove after_remove.sh \
build/.=/

rm -rf build
rm -f libcurl3-gnutls_${CURL_PKG_VER}_armhf.deb
