////////////////////////////////////////////////////////////

# trx: Realtime audio over IP

////////////////////////////////////////////////////////////

(C) Copyright 2020 Mark Hills <mark@xwax.org>

See the COPYING file for licensing terms.

This software is distributed from the following URL:

  http://www.pogo.org.uk/~mark/trx/

trx is a simple toolset for broadcasting live audio. It is based on
the Opus codec <http://www.opus-codec.org/> and sends and receives
encoded audio over IP networks.

It can be used for point-to-point audio links or multicast,
eg. private transmitter links or audio distribution. In contrast to
traditional streaming, high quality wideband audio (such as music) can
be sent with low-latency and fast recovery from dropouts.

With quality audio hardware and wired ethernet, a total latency of no
more than a few milliseconds is possible.

DETAILS

Features include:

- Very simple to set up
- Low latency with fast recovery from dropouts
- Full control over latency and buffers
- Supports IPv4 and IPv6, including multicast

Unlike TCP streaming such as Icecast, trx uses RTP/UDP with handling of dropped packets and network congestion that is more appropriate to live or realtime audio. Much of this comes courtesy of the brilliant Opus audio codec. The result is an incredibly graceful handling of network loss or dropouts.

It is intended for use over closed or private IP networks. It can be used over the public internet, but you are warned that it has no built-in security or authentication of the received audio. If you do not already have a private network you may wish to use an appropriate tunnel or IPsec.

trx is based on the following software/libraries:

- ALSA (Linux)
- Opus codec
- lib oRTP: packaged with most Linux distributions

////////////////////////////////////////////////////////////

# Compile TRX

////////////////////////////////////////////////////////////

sudo apt-get -y install libopus-dev

# moOde modified sources
sudo ./dev.sh
sudo cp -r /mnt/moode-player/GitHub/moode/other/trx/trx-0.6.0/ ./
cd trx-0.6.0
sudo make

# Copy tx and rx binaries to /usr/local/bin/
sudo make install

# Cleanup
cd ..
sudo rm -rf trx*

////////////////////////////////////////////////////////////

# Testing

# NOTE: Receiver volume is controlled via Hardware volume thus
the receiving device must support Hardware volume controller
otherwise volume will be fixed at 100% (0dB).

////////////////////////////////////////////////////////////

#
# TRX Sender
#
# Speex converter types:
#
# - speexrate_best      Use quality 10 (equivalent to SRC_SINC_BEST_QUALITY)
# - speexrate_medium    Use quality 5 (equivalent to SRC_SINC_MEDIUM_QUALITY)
# - speexrate           Use quality 3 (equivalent to SRC_SINC_FASTEST)
#

pcm.trx_send {
type plug
slave {
pcm "plughw:Loopback,1,0"
rate 48000
format S16_LE
channels 2
}
rate_converter "speexrate"
}

# Transmit
sudo tx -d trx_send -h 239.0.0.1 -p 1350 -m 128 -f 1920 -R 45
# Receive
sudo rx -d plughw:0,0 -h 239.0.0.1 -p 1350 -m 64 -j 32 -f 1920 -R 45

# Verbose
-v 2

# Background
-D /tmp/txpid
-D /tmp/rxpid

# Process status
ps H -q `pidof -s tx` -o 'pid,tid,cls,rtprio,comm,cmd'
ps H -q `pidof -s rx` -o 'pid,tid,cls,rtprio,comm,cmd'

////////////////////////////////////////////////////////////

# Devices

////////////////////////////////////////////////////////////

# HW_PARAMS
cat /proc/asound/Loopback/pcm0p/sub0/hw_params
cat /proc/asound/Loopback/pcm1c/sub0/hw_params
CARD=0
cat /proc/asound/card$CARD/pcm0p/sub0/hw_params

# Dummy pcm device
sudo modprobe snd-dummy
aplay -l | awk

////////////////////////////////////////////////////////////

# Appendix

////////////////////////////////////////////////////////////

# Original from moOde repo
wget https://github.com/moode-player/moode/tree/develop/other/trx/_original/trx-0.5.tar.gz
