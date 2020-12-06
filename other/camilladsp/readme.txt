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
# - pycamilladsp        - python packge
# - pycamilladsp-plot   - python packge
# - camillagui-backend  - pyhon app
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
#git checkout -b v0.4.0 tags/v0.4.0

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
# sudo install -m 644 libasound_module_pcm_cdsp.so `pkg-config --variable=libdir alsa`

# =================================================================
# Prepare use of camilladsp

# sudo cp ~/moode/etc/alsa/conf.d/camilladsp.conf /etc/alsa/conf.default
# sudo mkdir /usr/share/camilladsp
# sudo mkdir /usr/share/camilladsp/configs
# sudo mkdir /usr/share/camilladsp/coeffs
# sudo cp ~moode/usr/share/camilladsp/config.yaml /usr/share/camilladsp
# sudo cp ~moode/usr/share/camilladsp/config.out.yaml /usr/share/camilladsp
# sudo chmod -R 777 /usr/share/camilladsp


# As long as not integrated in moOde, we will reuse the Graphical EQ as output o MPD:

# 1 use custom mpd conf mergge
# place a file /etc/mpd.custom.conf with the following content
# audio_output {
# type "alsa"
# name "ALSA graphic eq"
# device "camilladsp"
# }
# enable dev tweaks for custom mpd conf merge
# moodeutl -A add 32768

# 2 update the camilladsp config to use the correct device (if you want it the same as moode is using):
# update the /usr/share/camilladsp/*.yaml files to use the same playback device as
# in /etc/alsa/conf.d/alsaequal.conf.

# enable graphic eq in moode config to activate camilladsp



