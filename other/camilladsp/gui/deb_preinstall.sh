#!/bin/bash
# incase this the first time a camillagui deb package is installed 3nd it concerns 
# an inplace update some cleanup is required.
service stop camillagui
rm /opt/camillagui/build
rm /opt/camillagio/*.py

