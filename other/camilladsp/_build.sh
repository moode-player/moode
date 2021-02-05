CAMILLADSP_SRC=camilladsp-0.5.0-beta4

CURRENT_PATH= `pwd`

#only once is required:
#echo "Choose option 1 when asked !"
#curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
#logout and in

mkdir -p ~/camilladsp.dev
cd ~/camilladsp.dev
tar -xzf ~/moode/other/camilladsp/$CAMILLADSP_SRC.tar.gz

RUSTFLAGS='-C target-feature=+neon -C target-cpu=native' cargo build --release --no-default-features --features alsa-backend --features websocket

cp target/release/camilladsp $CURRENT_PATH
