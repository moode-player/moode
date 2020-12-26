# =================================================================
# BUILDPLAN camilladsp
# https://github.com/HEnquist/camilladsp
#
# The camilladsp chain exists out of multiple projects.
#
# Two for the basics:
# - camilladsp          - camilla executable
# - alsa_cdsp			- alsa driver for sending audio to camilla (it also starts/stops camilla)
# The four only when you want to run also the camillagui:
# - pycamilladsp        - python package
# - pycamilladsp-plot   - python package
# - camillagui-backend  - python app
# - camillagui          - react web app
# The camillagui build are described in an other buildplan/
#


# ------------------------------------------------------
#
# Build camilladsp scratch
# https://github.com/HEnquist/camilladsp
#

# Step 1: Get build tooling
#
# Rustc version in apt is to old (at writing 0.41, camilladsp requires >= 0.43)
# so build/get is from https://rustup.rs/:
# If rustc/cargo is already installed remove it first.
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
# Choose option 1 when asked
# After build logout and login again to have the path updated

# Step 2: Checkout camilladsp
#
git clone https://github.com/HEnquist/camilladsp.git
cd camilladsp

# if don't want to work on head, checkout the wanted release like:
# git checkout -b v0.4.0 tags/v0.4.0
# However, currently only the head has all the features required for moOde.
# The next stable release will probably be v0.5.0

# Step 3: Build it
#
RUSTFLAGS='-C target-feature=+neon -C target-cpu=native' cargo build --release --no-default-features --features alsa-backend --features websocket

# Step 4: Install it
# after the build the executable can be copied:
sudo cp target/release/camilladsp /usr/local/bin/

# ------------------------------------------------------
#

# Build alsa_cdsp scratch
# https://github.com/scripple/alsa_cdsp
#

# Step 1: Checkout alsa+_cdsp
git clone https://github.com/scripple/alsa_cdsp.git
cd alsa_cdsp

# Step 2: Build it
make

# Step 2: Install it
sudo make install

# If installing a prebuild lib use:
# sudo install -m 644 libasound_module_pcm_cdsp.so `pkg-config --variable=libdir alsa`/alsa-lib/

# ------------------------------------------------------

# Prepare use of camilladsp
# sudo cp ~/moode/etc/alsa/conf.d/camilladsp.conf /etc/alsa/conf.d

# Set CamillaDSP to "on" in Menu > Configure > Audio > Equalizers > CamillaDSP
# and follow the instructions in the info box.

# =================================================================

# This is is only necessary, if you plan to use camillagui-backend:
# sudo mkdir /usr/share/camilladsp
# sudo mkdir /usr/share/camilladsp/configs
# sudo mkdir /usr/share/camilladsp/coeffs
# sudo chmod -R 777 /usr/share/camilladsp


