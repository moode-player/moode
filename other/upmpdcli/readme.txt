The upnp render exits out the following parts:
- libnpupnp
- libupnpp
- python3-libupnpp
- upmpdcli

For each part a deb package is available.
With the script install_upnp.sh those package can be installed on moOde image.
For rebuilding the packages the script build_upnp.sh is available.

Both script have the used version numbers of top.

When you want to rebuild existing package, you first need to delete the existing *.deb files in this directory.
After a build the build system has also the package installed, but contains far more then required for a running image of moOde due development requirements.