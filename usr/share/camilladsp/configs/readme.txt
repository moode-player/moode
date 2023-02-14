Default CamillaDSP configurations

ASH-IR R02 Control Room.yml - Binaural Room Impulse Response R02 Control Room from the ASH-IR dataset (without HpCF). (https://github.com/ShanonPearce/ASH-IR-Dataset)
Crossfeed Bs2b.yml          - Bs2b from Wang-Yue which contains 5 setting sets. Refer to link for more information. (https://github.com/Wang-Yue/camilladsp-crossfeed)
Crossfeed Mikhail.yml       - Mikhail Naganov, customize with own IR correction. (https://melp242.blogspot.com/2021/03/headphone-virtualization-for-music.html)
Crossfeed MPM.yml           - Mikhail Phonitor Mini (MPM). Mikhail Naganov reverse engineered SPL Phonitor Mini Crossfeed with DSP by Ebert-Hanke (https://github.com/Ebert-Hanke/autoeq2camilladsp)
Crossfeed Natural.yml       - Ebert-Hanke implementation of Natural roughly modeled after some publications by Jan Meier. (https://github.com/Ebert-Hanke/autoeq2camilladsp)
Crossfeed Chu Moy.yml       - Ebert-Hanke implementation of Pow Chu Moy Crossfeed based on Linkwitz. (https://github.com/Ebert-Hanke/autoeq2camilladsp)
Flat.yml                    - Empty config (no DSP)
Loudness.yml                - Loudness volume curve with 3dB high boost, 6dB low boost and -3dB gain.
MS-Matrix Narrow.yml        - M-S (Mid-Side) matrix with side signal reduced
MS-Matrix Wide.yml          - M-S (Mid-Side) matrix with side signal increased
PEQ 10-Band.yml             - Generic example of a 10 band parametric equalizer
Trifield 3-channel.yml      - Michael Gerzon derived Trifield decoder. Requires at least 3 channels available for output. (https://www.meridian-audio.info/public/trifield%5B2563%5D.pdf)
Volume Control.yml          - CamillaDSP volume control
