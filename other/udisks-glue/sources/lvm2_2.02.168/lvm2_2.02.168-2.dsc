-----BEGIN PGP SIGNED MESSAGE-----
Hash: SHA256

Format: 3.0 (quilt)
Source: lvm2
Binary: lvm2, lvm2-udeb, clvm, libdevmapper-dev, libdevmapper1.02.1, libdevmapper1.02.1-udeb, dmsetup, dmsetup-udeb, libdevmapper-event1.02.1, dmeventd, liblvm2app2.2, liblvm2cmd2.02, liblvm2-dev, python-lvm2, python3-lvm2
Architecture: linux-any
Version: 2.02.168-2
Maintainer: Debian LVM Team <pkg-lvm-maintainers@lists.alioth.debian.org>
Uploaders: Bastian Blank <waldi@debian.org>
Homepage: http://sources.redhat.com/lvm2/
Standards-Version: 3.9.6
Vcs-Browser: https://anonscm.debian.org/cgit/pkg-lvm/lvm2.git/
Vcs-Git: git://anonscm.debian.org/pkg-lvm/lvm2.git
Build-Depends: debhelper (>> 9), dh-python, dh-systemd, autoconf-archive, automake, libblkid-dev, libcmap-dev, libcorosync-common-dev, libcpg-dev, libdlm-dev (>> 2), libreadline-gplv2-dev, libselinux1-dev, libquorum-dev, libudev-dev, python-all-dev, python3-all-dev, pkg-config, systemd
Package-List:
 clvm deb admin extra arch=linux-any
 dmeventd deb admin optional arch=linux-any
 dmsetup deb admin optional arch=linux-any
 dmsetup-udeb udeb debian-installer optional arch=linux-any
 libdevmapper-dev deb libdevel optional arch=linux-any
 libdevmapper-event1.02.1 deb libs optional arch=linux-any
 libdevmapper1.02.1 deb libs optional arch=linux-any
 libdevmapper1.02.1-udeb udeb debian-installer optional arch=linux-any
 liblvm2-dev deb libdevel optional arch=linux-any
 liblvm2app2.2 deb libs optional arch=linux-any
 liblvm2cmd2.02 deb libs optional arch=linux-any
 lvm2 deb admin optional arch=linux-any
 lvm2-udeb udeb debian-installer optional arch=linux-any
 python-lvm2 deb python optional arch=linux-any
 python3-lvm2 deb python optional arch=linux-any
Checksums-Sha1:
 8f3feb1c7db077a5dcdbdb71b2471319f023dacd 1562080 lvm2_2.02.168.orig.tar.xz
 a310b0e8f8112f06c6effe7cd7c7e0ec0f9b4f3e 32516 lvm2_2.02.168-2.debian.tar.xz
Checksums-Sha256:
 ca257318fecfc66fb36b5ea02d90e075afb340557fcf5a343ba6071f84aeed8c 1562080 lvm2_2.02.168.orig.tar.xz
 1a6673093d4ef5eef4555c109b934cf2f89c1288f926dc4a4b949f1705feed8f 32516 lvm2_2.02.168-2.debian.tar.xz
Files:
 d55f345a41d59ef8eb79b08a546dd3d9 1562080 lvm2_2.02.168.orig.tar.xz
 b365f9a941a7d0ccd6e49cc9891a366f 32516 lvm2_2.02.168-2.debian.tar.xz

-----BEGIN PGP SIGNATURE-----

iQEzBAEBCAAdFiEER3HMN63jdS1rqjxLbZOIhYpp/lEFAljMDyMACgkQbZOIhYpp
/lGX4gf/VXjM7DsoTmtkaC+i/ZKb6LQ3tcMpShDv2glvHzsJyd/cJgxbw+cEuKjj
wunzrfFqe8YTJBKe2zQS+wZnZHi5UUk3iUxgCuknPGlGRO23RyPHNBUwKLuv8aj1
eOYmeXkxcM7BBlzhxzewyTaHSWIsVD63B4d5Kw9c6gK5XkbYkRTzq06TK1YySxK+
2kMmyQuNfUA4lTneGqhqjsq/pVU/f0l9xwE4hPOgC1+jor2nm5h2wFbW3SkqRrdU
H8lRx/+0ApZNioT52McbP54eeoRophwZeO+cnL8zkdkBfL3p+afOzzNdPLVehUci
Zgi7GVkwjtJdcKEG7e1HPMbTLNN8Qw==
=aNzM
-----END PGP SIGNATURE-----
