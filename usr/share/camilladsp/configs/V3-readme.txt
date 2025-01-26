Sample Configurations
=====================

ASH-IR R02 Control Room		- Binaural Room Impulse Response R02 Control Room from the ASH-IR dataset (without HpCF)
				  https://github.com/ShanonPearce/ASH-IR-Dataset

Crossfeed Bs2b			- Bs2b from Wang-Yue which contains 5 setting sets. Refer to link for more information
				  https://github.com/Wang-Yue/camilladsp-crossfeed

Crossfeed Chu Moy		- Ebert-Hanke implementation of Pow Chu Moy Crossfeed based on Linkwitz
				  https://github.com/Ebert-Hanke/autoeq2camilladsp

Crossfeed Mikhail		- Mikhail Naganov, customize with own IR correction

Crossfeed MPM			- Mikhail Phonitor Mini (MPM). Mikhail Naganov reverse engineered SPL Phonitor Mini Crossfeed with DSP by Ebert-Hanke

Crossfeed Natural		- Ebert-Hanke implementation of Natural roughly modeled after some publications by Jan Meier

Flat				- No signal processing

Loudness			- Loudness volume curve with 3dB high boost, 6dB low boost and -3dB gain

MS-Matrix Narrow		- M-S (Mid-Side) matrix with side signal reduced

MS-Matrix Wide			- M-S (Mid-Side) matrix with side signal increased

PEQ 10-Band			- Generic example of a 10 band parametric equalizer

Polarity Inversion		- Invert +/- polarity on both channels

ProtoDAC			- ProtoDAC TDA1387 X8 Non-oversampling DAC. Invert +/- signal polarity on both channels and apply Flat dither to
  16 bit samples.

Trifield 3-channel		- Michael Gerzon derived Trifield decoder. Requires at least 3 channels available for output
				  https://www.meridian-audio.info/public/trifield%5B2563%5D.pdf
