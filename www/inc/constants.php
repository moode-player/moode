<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
*/

// PHP
const PHP_VER = '8.2';

// Log files
const MOODE_LOG = '/var/log/moode.log';
const AUTOCFG_LOG = '/var/log/moode_autocfg.log';
const AUTORESTORE_LOG = '/var/log/moode_autorestore.log';
const UPDATER_LOG = '/var/log/moode_update.log';
const PLAY_HISTORY_LOG = '/var/log/moode_playhistory.log';
const MOUNTMON_LOG = '/var/log/moode_mountmon.log';
const SHAIRPORT_SYNC_LOG = '/var/log/moode_shairport-sync.log';
const LIBRESPOT_LOG = '/var/log/moode_librespot.log';
const PLEEZER_LOG = '/var/log/moode_pleezer.log';
const SPOTEVENT_LOG = '/var/log/moode_spotevent.log';
const DEEZEVENT_LOG = '/var/log/moode_deezevent.log';
const SPSEVENT_LOG = '/var/log/moode_spsevent.log';
const SLPOWER_LOG = '/var/log/moode_slpower.log';
// MPD
const MPD_RESPONSE_ERR = 'ACK';
const MPD_RESPONSE_OK = 'OK';
const MPD_CONF = '/etc/mpd.conf';
const MPD_MUSICROOT = '/var/lib/mpd/music/';
const MPD_PLAYLIST_ROOT = '/var/lib/mpd/playlists/';
const MPD_LOG = '/var/log/mpd/log';
// Spotify Connect and Deezer Connect
const SPOTMETA_FILE = '/var/local/www/spotmeta.txt';
const DEEZMETA_FILE = '/var/local/www/deezmeta.txt';
const DEEZ_CREDENTIALS_FILE = '/etc/deezer/deezer.toml';
// SQLite
const SQLDB = 'sqlite:/var/local/www/db/moode-sqlite3.db';
const SQLDB_PATH = '/var/local/www/db/moode-sqlite3.db';
// Players >>
const PLAYERS_CACHE_FILE = '/var/local/www/players.txt';
// Library/Playback
const LIBCACHE_BASE = '/var/local/www/libcache';
const ROOT_DIRECTORIES = array('NAS', 'NVME', 'SDCARD', 'USB');
const DEFAULT_RADIO_TITLE = 'Radio station';
const DEFAULT_RADIO_COVER = 'images/default-album-cover.png';
const DEFAULT_ALBUM_COVER = 'images/default-album-cover.png';
const DEFAULT_UPNP_COVER = 'images/default-upnp-cover.jpg';
const DEFAULT_RX_COVER = 'images/default-rx-cover.jpg';
const DEFAULT_PLAYLIST_COVER = '/var/www/images/default-playlist-cover.jpg';
const DEFAULT_NOTFOUND_COVER = '/var/www/images/default-notfound-cover.jpg';
const PLAYLIST_COVERS_ROOT = '/var/local/www/imagesw/playlist-covers/';
const RADIO_LOGOS_ROOT = '/var/local/www/imagesw/radio-logos/';
const LOGO_ROOT_DIR = 'imagesw/radio-logos/';
const TMP_IMAGE_PREFIX = '__tmp__';
const ALSA_PLUGIN_PATH = '/etc/alsa/conf.d';
const STATION_EXPORT_DIR = '/var/local/www/imagesw';
const LIB_MOUNT_OK = '<i class="fa-solid fa-sharp fa-check sx" style="color:#27ae60;"></i>';
const LIB_MOUNT_FAILED = '<i class="fa-solid fa-sharp fa-times sx" style="color:#e74c3c;"></i>';
const LIB_MOUNT_TYPE_SMB = 'cifs';
const LIB_MOUNT_TYPE_NFS = 'nfs';
const LIB_MOUNT_TYPE_NVME = 'nvme';
const LIB_MOUNT_TYPE_SATA = 'sata';
const LIB_DRIVE_UNFORMATTED = 'Unformatted';
const LIB_DRIVE_NOT_EXT4 = 'Not ext4';
const LIB_DRIVE_NO_LABEL = 'No label';
// Thumbnail generator
const THMCACHE_DIR = '/var/local/www/imagesw/thmcache/';
const THM_SM_W = 80; // Small thumbs
const THM_SM_H = 80;
const THM_SM_Q = 75;
// Radio and Playlist thumbs
const THM_DEFAULT_W = 600;
const THM_DEFAULT_Q = 75;
// System
const PORT_FILE = '/tmp/moode_portfile'; // Command engine
const SESSION_SAVE_PATH = '/var/local/php';
const DEV_ROOTFS_SIZE = 3670016000; // Bytes (3.5GB)
const LOW_DISKSPACE_LIMIT = 524288; // Bytes (512MB)
const BOOT_DIR = '/boot/firmware';
const BOOT_CONFIG_TXT = BOOT_DIR . '/config.txt';
const BOOT_CMDLINE_TXT = BOOT_DIR . '/cmdline.txt';
const BOOT_MOODEBACKUP_ZIP = '/boot/moodebackup.zip';
const BOOT_MOODECFG_INI = '/boot/moodecfg.ini';
const BT_PINCODE_CONF = '/etc/bluetooth/pin.conf';
const ETC_MACHINE_INFO = '/etc/machine-info';
const CHROMIUM_DOWNGRADE_VER = '126.0.6478.164-rpt1';
const NO_USERID_DEFINED = 'userid does not exist';
// File sharing
const FS_SMB_CONF = '/etc/samba/smb.conf';
// Notifications
const NOTIFY_TITLE_INFO = '<i class="fa fa-solid fa-sharp fa-circle-check" style="color:#27ae60;"></i> Info';
const NOTIFY_TITLE_ALERT = '<i class="fa fa-solid fa-sharp fa-circle-xmark" style="color:#e74c3c;"></i> Alert';
const NOTIFY_TITLE_ERROR = '<i class="fa fa-solid fa-sharp fa-do-not-enter" style="color:#e74c3c;"></i> Error';
const NOTIFY_DURATION_SHORT = 2; // Seconds
const NOTIFY_DURATION_DEFAULT = 5;
const NOTIFY_DURATION_MEDIUM = 10;
const NOTIFY_DURATION_LONG = 30;
const NOTIFY_DURATION_INFINITE = 8640000; // 100 days
const NOTIFY_MSG_SYSTEM_RESTART_REQD = 'Restart the system for the changes to take effect.';
const NOTIFY_MSG_SVC_RESTARTED = ' has been restarted to make the changes effective.';
const NOTIFY_MSG_SVC_MANUAL_RESTART = ' has been restarted.';
const NOTIFY_MSG_LOCALDISPLAY_STARTING = 'Local display is starting...';
const NOTIFY_MSG_LOOPBACK_ACTIVE = 'Loopback cannot be turned off while playback is active.';
// Component names (for notification messages)
const NAME_AIRPLAY = 'AirPlay';
const NAME_BLUETOOTH = 'Bluetooth Controller';
const NAME_BLUETOOTH_PAIRING_AGENT = 'Pairing Agent';
const NAME_SPOTIFY = 'Spotify Connect';
const NAME_DEEZER = 'Deezer Connect';
const NAME_SQUEEZELITE = 'Squeezelite';
const NAME_UPNP = 'UPnP';
const NAME_DLNA = 'DLNA';
const NAME_PLEXAMP = 'Plexamp';
const NAME_ROONBRIDGE = 'RoonBridge';
const NAME_GPIO = 'GPIO Controller';
const NAME_LOCALDISPLAY = 'Local Display';

// Local display X11 CalibrationMatrix touch angles
const X11_TOUCH_ANGLE = array(
    '0' => '1 0 0 0 1 0 0 0 1',
    '90' => '0 1 0 -1 0 1 0 0 1',
    '180' => '-1 0 1 0 -1 1 0 0 1',
    '270' => '0 -1 1 1 0 0 0 0 1'
);

// SMB protocol versions
const SMB_VERSIONS = array(
    "2.02" => "2.0",
    "2.10" => "2.1",
    "3.00" => "3.0",
    "3.02" => "3.0.2",
    "3.11" => "3.1.1",
    "202" => "2.0",
    "210" => "2.1",
    "300" => "3.0",
    "302" => "3.0.2",
    "311" => "3.1.1"
);

// Boot config.txt managed lines
const CFG_HEADERS_REQUIRED = 5;
const CFG_MAIN_FILE_HEADER = '# This file is managed by moOde';
const CFG_DEVICE_FILTERS_HEADER = '# Device filters';
const CFG_GENERAL_SETTINGS_HEADER = '# General settings';
const CFG_DO_NOT_ALTER_HEADER = '# Do not alter this section';
const CFG_AUDIO_OVERLAYS_HEADER = '# Audio overlays';
const CFG_FORCE_EEPROM_READ = 'force_eeprom_read=0';
const CFG_HDMI_ENABLE_4KP60 = 'hdmi_enable_4kp60';
const CFG_PCI_EXPRESS = 'pciex1';
const CFG_PCI_EXPRESS_GEN3 = 'pciex1_gen=3';
const CFG_PI_AUDIO_DRIVER = 'vc4-kms-v3d';
const CFG_DISABLE_BT = 'disable-bt';
const CFG_DISABLE_WIFI = 'disable-wifi';
const CFG_DISPLAY_AUTODETECT = 'display_auto_detect=1';
const CFG_PITOUCH_INVERTXY = 'vc4-kms-dsi-7inch,invx,invy';
// Boot cmdline.txt managed params
const CFG_PITOUCH_ROTATE_180 = 'video=DSI-1:800x480@60,rotate=180';

// Features availability bitmask
// NOTE: Updates must also be made to matching code blocks in playerlib.js, sysinfo.sh, moodeutl, and footer.php
// moodeutl -q "SELECT value FROM cfg_system WHERE param='feat_bitmask'"
// moodeutl -q "UPDATE cfg_system SET value='97271' WHERE param='feat_bitmask'"
const FEAT_HTTPS        = 1;		// y HTTPS mode
const FEAT_AIRPLAY      = 2;		// y AirPlay renderer
const FEAT_MINIDLNA     = 4;		// y DLNA server
const FEAT_RECORDER     = 8; 		//   Stream recorder
const FEAT_SQUEEZELITE  = 16;		// y Squeezelite renderer
const FEAT_UPMPDCLI     = 32;		// y UPnP client for MPD
const FEAT_DEEZER       = 64;   	// y Deezer Connect renderer
const FEAT_ROONBRIDGE   = 128;		// y RoonBridge renderer
const FEAT_LOCALDISPLAY = 256;		// y Local display
const FEAT_INPSOURCE    = 512;		// y Input source select
const FEAT_UPNPSYNC     = 1024;		//   UPnP volume sync
const FEAT_SPOTIFY      = 2048;		// y Spotify Connect renderer
const FEAT_GPIO         = 4096;		// y GPIO button handler
const FEAT_PLEXAMP      = 8192;		// y Plexamp renderer
const FEAT_BLUETOOTH    = 16384;	// y Bluetooth renderer
const FEAT_DEVTWEAKS    = 32768;	//   Developer tweaks
const FEAT_MULTIROOM    = 65536;	// y Multiroom audio
//						-------
//						  97271

// Selective resampling bitmask
const SOX_UPSAMPLE_ALL			= 3; // Upsample if source < target rate
const SOX_UPSAMPLE_ONLY_41K		= 1; // Upsample only 44.1K source rate
const SOX_UPSAMPLE_ONLY_4148K	= 2; // Upsample only 44.1K and 48K source rates
const SOX_ADHERE_BASE_FREQ		= 8; // Resample (adhere to base freq)

// Album and Radio HD badge parameters
// NOTE: Mirrored in playerlib.js
const ALBUM_HD_BADGE_TEXT 			= 'HD';
const ALBUM_BIT_DEPTH_THRESHOLD 	= 16;
const ALBUM_SAMPLE_RATE_THRESHOLD 	= 44100;
const RADIO_HD_BADGE_TEXT 			= 'HiRes';
const RADIO_BITRATE_THRESHOLD 		= 128;

// MPD output names
const ALSA_DEFAULT			= 'ALSA Default';
const ALSA_BLUETOOTH		= 'ALSA Bluetooth';
const HTTP_SERVER			= 'HTTP Server';
const STREAM_RECORDER		= 'Stream Recorder';

// Audio output interfaces
const AO_HDMI = 'hdmi';
const AO_HEADPHONE = 'headphone';
const AO_I2S = 'i2s';
const AO_USB = 'usb';
const AO_TRXSEND = 'trxsend';

// Audio device names (Pi integrated audio)
const PI_HDMI1 = 'Pi HDMI 1';
const PI_HDMI2 = 'Pi HDMI 2';
const PI_HEADPHONE = 'Pi Headphone jack';

// Audio drivers (Pi integrated audio)
const PI_VC4_KMS_V3D = 'vc4-kms-v3d';
const PI_SND_BCM2835 = 'snd-bcm2835';

// ALSA max number of cards (4 USB, 3 Integrated, 1 I2S)
const ALSA_MAX_CARDS = 8;
// ALSA special device names
const ALSA_LOOPBACK_DEVICE = 'Loopback';
const ALSA_DUMMY_DEVICE = 'Dummy';
const ALSA_VC4HDMI_SINGLE_DEVICE = 'vc4hdmi';
const ALSA_EMPTY_CARD = 'empty';
// ALSA names that can't be set directly in Audio Config
const ALSA_RESERVED_NAMES = array(ALSA_LOOPBACK_DEVICE, ALSA_DUMMY_DEVICE);
// ALSA default mixer names
const ALSA_DEFAULT_MIXER_NAME_I2S = 'Digital';
const ALSA_DEFAULT_MIXER_NAME_INTEGRATED = 'PCM';
// ALSA output mode names
const ALSA_OUTPUT_MODE_NAME = array('plughw' => 'Default', 'hw' => 'Direct', 'iec958' => 'IEC958');
const ALSA_OUTPUT_MODE_BT_NAME = array('_audioout' => 'Standard', 'plughw' => 'Compatibility');
// ALSA HDMI IEC958
const ALSA_IEC958_DEVICE = 'default:vc4hdmi';
const ALSA_IEC958_FORMAT = 'IEC958_SUBFRAME_LE';

// Friendly name to display in Output device field in Audio Config
const TRX_SENDER_NAME = 'Multiroom sender';

// Input select devices
const INP_SELECT_DEVICES = array(
    'HiFiBerry DAC+ ADC',
    'Audiophonics ES9028/9038 DAC'
);

// Library Saved Searches
const LIBSEARCH_BASE = '/var/local/www/libsearch_';
const LIB_FULL_LIBRARY = 'Full Library (Default)';

// Month names
const MONTH_NAME = array(
	'01' => 'January',
	'02' => 'February',
	'03' => 'March',
	'04' => 'April',
	'05' => 'May',
	'06' => 'June',
	'07' => 'July',
	'08' => 'August',
	'09' => 'September',
	'10' => 'October',
	'11' => 'Novenber',
	'12' => 'December'
);

// Netmask to CIDR table
const CIDR_TABLE = array(
    "255.255.255.255" => '32',
    "255.255.255.254" => '31',
    "255.255.255.252" => '30',
    "255.255.255.248" => '29',
    "255.255.255.240" => '28',
    "255.255.255.224" => '27',
    "255.255.255.192" => '26',
    "255.255.255.128" => '25',
    "255.255.255.0" => '24',
    "255.255.254.0" => '23',
    "255.255.252.0" => '22',
    "255.255.248.0" => '21',
    "255.255.240.0" => '20',
    "255.255.224.0" => '19',
    "255.255.192.0" => '18',
    "255.255.128.0" => '17',
    "255.255.0.0" => '16',
    "255.254.0.0" => '15',
    "255.252.0.0" => '14',
    "255.248.0.0" => '13',
    "255.240.0.0" => '12',
    "255.224.0.0" => '11',
    "255.192.0.0" => '10',
    "255.128.0.0" => '9',
    "255.0.0.0" => '8',
    "254.0.0.0" => '7',
    "252.0.0.0" => '6',
    "248.0.0.0" => '5',
    "240.0.0.0" => '4',
    "224.0.0.0" => '3',
    "192.0.0.0" => '2',
    "128.0.0.0" => '1',
    "0.0.0.0" => '0'
);

// Used by security chk functions
const SHL_CMDS = array('base64');
const SQL_CMDS = array('delete ', 'select ', 'union ', 'update ');
const XSS_CMDS = array('script', 'href', 'img src'); // May use in future

// Recorder plugin (currently n/a)
const RECORDER_RECORDINGS_DIR 	 = '/Recordings';
const RECORDER_DEFAULT_COVER	 = 'Recorded Radio.jpg';
const RECORDER_DEFAULT_ALBUM_TAG = 'Recorded YYYY-MM-DD';
