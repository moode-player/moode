<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * Refer to the link below for a copy of the GNU General Public License.
 * http://www.gnu.org/licenses/
 *
 */

require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/mpd.php';
require_once __DIR__ . '/inc/session.php';
require_once __DIR__ . '/inc/sql.php';

$sock = getMpdSock();
$dbh = sqlConnect();
phpSession('open');

// Apply setting changes
if (isset($_POST['save']) && $_POST['save'] == '1') {
	$result = sqlRead('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);

	// Burr Brown PCM and TAS family
	if (strpos($result[0]['dacchip'], 'PCM5') !== false || strpos($result[0]['dacchip'], 'TAS') !== false) {
		$chipoptions = $_POST['config']['analoggain'] . ',' . $_POST['config']['analogboost'] . ',' . $_POST['config']['digfilter'];
		$chiptype = 'burr_brown_pcm5';
		// ALSA amixer cmds
		cfgChipOptions($chipoptions, $chiptype);
		// See if filter change submitted
		if (explode(',', $result[0]['chipoptions'])[2] != $_POST['config']['digfilter']) {
			$status = getMpdStatus($sock);
			// Restart playback to make filter change effective
			if ($status['state'] === 'play') {
				chainMpdCmds($sock, array('pause', 'play'));
			}
		}
		// Update chip options
		$result = sqlUpdate('cfg_audiodev', $dbh, $_SESSION['i2sdevice'], $chipoptions);

		// Allo Piano 2.1 Hi-Fi DAC device settings
		if ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
			if ($_POST['config']['outputmode'] == 'Dual-Mono' || $_POST['config']['outputmode'] == 'Dual-Stereo') {
				sysCmd('/var/www/util/sysutil.sh set-piano-dualmode ' . '"' . $_POST['config']['outputmode'] . '"');
			}
			else {
				sysCmd('/var/www/util/sysutil.sh set-piano-submode ' . '"' . $_POST['config']['outputmode'] . '"');
				sysCmd('/var/www/util/sysutil.sh set-piano-lowpass ' . '"' . $_POST['config']['lowpass'] . '"');
				sysCmd('/var/www/util/sysutil.sh set-piano-subvol ' . '"' . $_POST['config']['subwvol'] . '"');
			}
			$_SESSION['notify']['title'] = NOTIFY_TITLE_INFO;
			$_SESSION['notify']['msg'] = NOTIFY_MSG_SYSTEM_RESTART_REQD;
			$_SESSION['notify']['duration'] = NOTIFY_DURATION_MEDIUM;
		}
	}

	// Allo Katana ES9038 Q2M chip
	if ($_SESSION['i2sdevice'] == 'Allo Katana DAC') {
		$chipoptions = $_POST['config']['katana_osf'] . ',' . $_POST['config']['katana_deemphasis'] . ',' . $_POST['config']['katana_dop'];
		$chiptype = 'ess_sabre_katana';

		cfgChipOptions($chipoptions, $chiptype);

		if (explode(',', $result[0]['chipoptions'])[0] != $_POST['config']['katana_osf']) {
			$status = getMpdStatus($sock);

			if ($status['state'] === 'play') {
				chainMpdCmds($sock, array('pause', 'play'));
			}
		}

		$result = sqlUpdate('cfg_audiodev', $dbh, $_SESSION['i2sdevice'], $chipoptions);
	}

	// Allo Boss 2 Cirrus Logic CS43198 chip
	if ($_SESSION['i2sdevice'] == 'Allo Boss 2 DAC') {
		$chipoptions = $_POST['config']['boss2_deemphasis_filter'] . ',' . $_POST['config']['boss2_filter_speed'] . ',' . $_POST['config']['boss2_highpass_filter'] . ',' .
			$_POST['config']['boss2_nonosf_emulate'] . ',' . $_POST['config']['boss2_phase_compensation'] . ',' . $_POST['config']['boss2_hv_enable'];
		$chiptype = 'cirrus_logic_cS43198_boss2';

		cfgChipOptions($chipoptions, $chiptype);

		$cfgoptions = explode(',', $result[0]['chipoptions']);
		if ($cfgoptions[0] != $_POST['config']['boss2_deemphasis_filter'] || $cfgoptions[1] != $_POST['config']['boss2_filter_speed'] ||
			$cfgoptions[2] != $_POST['config']['boss2_highpass_filter'] || $cfgoptions[3] != $_POST['config']['boss2_nonosf_emulate'] ||
			$cfgoptions[4] != $_POST['config']['boss2_phase_compensation'] || $cfgoptions[5] != $_POST['config']['boss2_hv_enable']) {
			$status = getMpdStatus($sock);

			if ($status['state'] === 'play') {
				chainMpdCmds($sock, array('pause', 'play'));
			}
		}

		// NOTE: This volume is automatically sync'd with master volume in worker.php event loop
		// Update DoP volume
		//sysCmd('amixer -c 0 sset Digital ' . $_POST['config']['boss2_dop_volume'] . '%');

		$result = sqlUpdate('cfg_audiodev', $dbh, $_SESSION['i2sdevice'], $chipoptions);
	}

	// Audiophonics ES9028/38 Q2M chip
	if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
		$chipoptions = $_POST['config']['audiophonics_q2m_osf'] . ',' . $_POST['config']['audiophonics_q2m_input'];
		$chiptype = 'ess_sabre_audiophonics_q2m';

		cfgChipOptions($chipoptions, $chiptype);

		if (explode(',', $result[0]['chipoptions'])[0] != $_POST['config']['audiophonics_q2m_osf']) {
			$status = getMpdStatus($sock);

			if ($status['state'] === 'play') {
				chainMpdCmds($sock, array('pause', 'play'));
			}
		}

		$result = sqlUpdate('cfg_audiodev', $dbh, $_SESSION['i2sdevice'], $chipoptions);
	}

	// MERUS Amp HAT ZW chip
	if ($_SESSION['i2sdevice'] == 'MERUS(tm) Amp piHAT ZW') {
		$chipoptions = $_POST['config']['merus_ma12070p_pmp'];
		$chiptype = 'merus_ma12070p';

		cfgChipOptions($chipoptions, $chiptype);

		$result = sqlUpdate('cfg_audiodev', $dbh, $_SESSION['i2sdevice'], $chipoptions);
	}
}

phpSession('close');

$result = sqlRead('cfg_audiodev', $dbh, $_SESSION['i2sdevice']);
$array = explode(',', $result[0]['chipoptions']);

// Burr Brown PCM/5 and TAS chips
if (strpos($result[0]['dacchip'], 'PCM5') !== false || strpos($result[0]['dacchip'], 'TAS') !== false) {
	$_burrbrown_hide = '';
	// Analog volume, analog volume boost, digital interpolation filter
	$analoggain = $array[0];
	$analogboost = $array[1];
	$digfilter = $array[2];
	// Analog volume
	$_select['analoggain'] .= "<option value=\"100\" " . (($analoggain == '100') ? "selected" : "") . ">0 dB (2-Vrms)</option>\n";
	$_select['analoggain'] .= "<option value=\"0\" " . (($analoggain == '0') ? "selected" : "") . ">-6 dB (1-Vrms)</option>\n";
	// Analog volume boost
	$_select['analogboost'] .= "<option value=\"0\" " . (($analogboost == '0') ? "selected" : "") . ">0 dB (normal amplitude)</option>\n";
	$_select['analogboost'] .= "<option value=\"100\" " . (($analogboost == '100') ? "selected" : "") . ">.8 dB (10% boosted amplitude)</option>\n";
	// Digital interpolation filter
	$_select['digfilter'] .= "<option value=\"FIR interpolation with de-emphasis\" " . (($digfilter == 'FIR interpolation with de-emphasis') ? "selected" : "") . ">FIR interpolation with de-emphasis</option>\n";
	$_select['digfilter'] .= "<option value=\"High attenuation with de-emphasis\" " . (($digfilter == 'High attenuation with de-emphasis') ? "selected" : "") . ">High attenuation with de-emphasis</option>\n";
	$_select['digfilter'] .= "<option value=\"Low latency IIR with de-emphasis\" " . (($digfilter == 'Low latency IIR with de-emphasis') ? "selected" : "") . ">Low latency IIR with de-emphasis</option>\n";
	$_select['digfilter'] .= "<option value=\"Ringing-less low latency FIR\" " . (($digfilter == 'Ringing-less low latency FIR') ? "selected" : "") . ">Ringing-less low latency FIR</option>\n";
} else {
	$_burrbrown_hide = 'hide';
}

// Allo Piano 2.1 Hi-Fi DAC
if ($_SESSION['i2sdevice'] == 'Allo Piano 2.1 Hi-Fi DAC') {
	$_allo_piano_hide = '';
	// Get current settings
	$dualmode = sysCmd('/var/www/util/sysutil.sh get-piano-dualmode');
	$submode = sysCmd('/var/www/util/sysutil.sh get-piano-submode');
	$subvol = sysCmd('/var/www/util/sysutil.sh get-piano-subvol');
	$lowpass = sysCmd('/var/www/util/sysutil.sh get-piano-lowpass');
	// Determine output mode
	if ($dualmode[0] != 'None') {
		$outputmode = $dualmode[0];
		$_allo_piano_submode_disabled = '';
	} else if ($submode[0] == '2.0') {
		$outputmode = $submode[0];
		$_allo_piano_submode_disabled = '';
	} else {
		$outputmode = $submode[0];
		$_allo_piano_submode_disabled = '';
	}
	// Output mode
	$_select['outputmode'] .= "<option value=\"Dual-Mono\" " . (($outputmode == 'Dual-Mono') ? "selected" : "") . ">Dual-Mono</option>\n";
	$_select['outputmode'] .= "<option value=\"Dual-Stereo\" " . (($outputmode == 'Dual-Stereo') ? "selected" : "") . ">Dual-Stereo</option>\n";
	$_select['outputmode'] .= "<option value=\"2.0\" " . (($outputmode == '2.0') ? "selected" : "") . ">Stereo</option>\n";
	$_select['outputmode'] .= "<option value=\"2.1\" " . (($outputmode == '2.1') ? "selected" : "") . ">Subwoofer 2.1</option>\n";
	$_select['outputmode'] .= "<option value=\"2.2\" " . (($outputmode == '2.2') ? "selected" : "") . ">Subwoofer 2.2</option>\n";
	// Subwoofer low pass frequency
	$_select['lowpass'] .= "<option value=\"60\" " . (($lowpass[0] == '60') ? "selected" : "") . ">60</option>\n";
	$_select['lowpass'] .= "<option value=\"70\" " . (($lowpass[0] == '70') ? "selected" : "") . ">70</option>\n";
	$_select['lowpass'] .= "<option value=\"80\" " . (($lowpass[0] == '80') ? "selected" : "") . ">80</option>\n";
	$_select['lowpass'] .= "<option value=\"90\" " . (($lowpass[0] == '90') ? "selected" : "") . ">90</option>\n";
	$_select['lowpass'] .= "<option value=\"100\" " . (($lowpass[0] == '100') ? "selected" : "") . ">100</option>\n";
	$_select['lowpass'] .= "<option value=\"110\" " . (($lowpass[0] == '110') ? "selected" : "") . ">110</option>\n";
	$_select['lowpass'] .= "<option value=\"120\" " . (($lowpass[0] == '120') ? "selected" : "") . ">120</option>\n";
	$_select['lowpass'] .= "<option value=\"130\" " . (($lowpass[0] == '130') ? "selected" : "") . ">130</option>\n";
	$_select['lowpass'] .= "<option value=\"140\" " . (($lowpass[0] == '140') ? "selected" : "") . ">140</option>\n";
	$_select['lowpass'] .= "<option value=\"150\" " . (($lowpass[0] == '150') ? "selected" : "") . ">150</option>\n";
	$_select['lowpass'] .= "<option value=\"160\" " . (($lowpass[0] == '160') ? "selected" : "") . ">160</option>\n";
	$_select['lowpass'] .= "<option value=\"170\" " . (($lowpass[0] == '170') ? "selected" : "") . ">170</option>\n";
	$_select['lowpass'] .= "<option value=\"180\" " . (($lowpass[0] == '180') ? "selected" : "") . ">180</option>\n";
	$_select['lowpass'] .= "<option value=\"190\" " . (($lowpass[0] == '190') ? "selected" : "") . ">190</option>\n";
	$_select['lowpass'] .= "<option value=\"200\" " . (($lowpass[0] == '200') ? "selected" : "") . ">200</option>\n";
	// Subwoofer volume
	$subwvol = str_replace('%', '', $subvol[0]);
} else {
	$_allo_piano_hide = 'hide';
}

// Allo Katana DAC
if ($_SESSION['i2sdevice'] == 'Allo Katana DAC') {
	$_allo_katana_hide = '';
	// Oversampling filter, de-emphasis, DoP
	$katana_osf = $array[0];
	$katana_deemphasis = $array[1];
	$katana_dop = $array[2];
	// Oversampling filter
	$_select['katana_osf'] .= "<option value=\"Apodizing Fast Roll-off Filter\" " . (($katana_osf == 'Apodizing Fast Roll-off Filter') ? "selected" : "") . ">Apodizing Fast Roll-off Filter</option>\n";
	$_select['katana_osf'] .= "<option value=\"Brick Wall Filter\" " . (($katana_osf == 'Brick Wall Filter') ? "selected" : "") . ">Brick Wall Filter</option>\n";
	$_select['katana_osf'] .= "<option value=\"Corrected Minimum Phase Fast Roll-off Filter\" " . (($katana_osf == 'Corrected Minimum Phase Fast Roll-off Filter') ? "selected" : "") . ">Corrected Minimum Phase Fast Roll-off Filter</option>\n";
	$_select['katana_osf'] .= "<option value=\"Linear Phase Fast Roll-off Filter\" " . (($katana_osf == 'Linear Phase Fast Roll-off Filter') ? "selected" : "") . ">Linear Phase Fast Roll-off Filter</option>\n";
	$_select['katana_osf'] .= "<option value=\"Linear Phase Slow Roll-off Filter\" " . (($katana_osf == 'Linear Phase Slow Roll-off Filter') ? "selected" : "") . ">Linear Phase Slow Roll-off Filter</option>\n";
	$_select['katana_osf'] .= "<option value=\"Minimum Phase Fast Roll-off Filter\" " . (($katana_osf == 'Minimum Phase Fast Roll-off Filter') ? "selected" : "") . ">Minimum Phase Fast Roll-off Filter</option>\n";
	$_select['katana_osf'] .= "<option value=\"Minimum Phase Slow Roll-off Filter\" " . (($katana_osf == 'Minimum Phase Slow Roll-off Filter') ? "selected" : "") . ">Minimum Phase Slow Roll-off Filter</option>\n";
	// De-emphasis
	$_select['katana_deemphasis'] .= "<option value=\"Bypass\" " . (($katana_deemphasis == 'Bypass') ? "selected" : "") . ">Bypass</option>\n";
	$_select['katana_deemphasis'] .= "<option value=\"32kHz\" " . (($katana_deemphasis == '32kHz') ? "selected" : "") . ">32 kHz</option>\n";
	$_select['katana_deemphasis'] .= "<option value=\"44.1kHz\" " . (($katana_deemphasis == '44.1kHz') ? "selected" : "") . ">44.1 kHz</option>\n";
	$_select['katana_deemphasis'] .= "<option value=\"48kHz\" " . (($katana_deemphasis == '48kHz') ? "selected" : "") . ">48 kHz</option>\n";
	// DoP
	$_select['katana_dop'] .= "<option value=\"on\" " . (($katana_dop == 'on') ? "selected" : "") . ">On</option>\n";
	$_select['katana_dop'] .= "<option value=\"off\" " . (($katana_dop == 'off') ? "selected" : "") . ">Off</option>\n";
} else {
	$_allo_katana_hide = 'hide';
}

// Allo Boss 2 DAC
if ($_SESSION['i2sdevice'] == 'Allo Boss 2 DAC') {
	$_allo_boss2_hide = '';
	// Chip options
	$boss2_deemphasis_filter = $array[0];
	$boss2_filter_speed = $array[1];
	$boss2_highpass_filter = $array[2];
	$boss2_nonosf_emulate = $array[3];
	$boss2_phase_compensation = $array[4];
	$boss2_hv_enable = $array[5];
	// NOTE: this volume is automatically sync'd with master volume in worker.php event loop
	//$boss2_dop_volume = rtrim(sysCmd('/var/www/util/sysutil.sh get-alsavol Digital')[0], '%');
	// De-emphasis filter
	$_select['boss2_deemphasis_filter'] .= "<option value=\"on\" " . (($boss2_deemphasis_filter == 'on') ? "selected" : "") . ">On</option>\n";
	$_select['boss2_deemphasis_filter'] .= "<option value=\"off\" " . (($boss2_deemphasis_filter == 'off') ? "selected" : "") . ">Off</option>\n";
	// Filter speed
	$_select['boss2_filter_speed'] .= "<option value=\"Slow\" " . (($boss2_filter_speed == 'Slow') ? "selected" : "") . ">Slow</option>\n";
	$_select['boss2_filter_speed'] .= "<option value=\"Fast\" " . (($boss2_filter_speed == 'Fast') ? "selected" : "") . ">Fast</option>\n";
	// High pass filter
	$_select['boss2_highpass_filter'] .= "<option value=\"on\" " . (($boss2_highpass_filter == 'on') ? "selected" : "") . ">On</option>\n";
	$_select['boss2_highpass_filter'] .= "<option value=\"off\" " . (($boss2_highpass_filter == 'off') ? "selected" : "") . ">Off</option>\n";
	// Non-oversampling emulation
	$_select['boss2_nonosf_emulate'] .= "<option value=\"on\" " . (($boss2_nonosf_emulate == 'on') ? "selected" : "") . ">On</option>\n";
	$_select['boss2_nonosf_emulate'] .= "<option value=\"off\" " . (($boss2_nonosf_emulate == 'off') ? "selected" : "") . ">Off</option>\n";
	// Phase compensation
	$_select['boss2_phase_compensation'] .= "<option value=\"on\" " . (($boss2_phase_compensation == 'on') ? "selected" : "") . ">On</option>\n";
	$_select['boss2_phase_compensation'] .= "<option value=\"off\" " . (($boss2_phase_compensation == 'off') ? "selected" : "") . ">Off</option>\n";
	// Analog output voltage
	$_select['boss2_hv_enable'] .= "<option value=\"off\" " . (($boss2_hv_enable == 'off') ? "selected" : "") . ">1.7 vrms</option>\n";
	$_select['boss2_hv_enable'] .= "<option value=\"on\" " . (($boss2_hv_enable == 'on') ? "selected" : "") . ">2.0 vrms</option>\n";
} else {
	$_allo_boss2_hide = 'hide';
}

// Audiophonics ES9028/38 Q2M DAC
if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
	$_audiophonics_q2m_hide = '';
	// Oversampling filter, input select
	$audiophonics_q2m_osf = $array[0];
	$audiophonics_q2m_input = $array[1];
	// Oversampling filter
	if ($_SESSION['i2sdevice'] == 'Audiophonics ES9028/9038 DAC') {
		$_select['audiophonics_q2m_osf'] .= "<option value=\"apodizing fast\" " . (($audiophonics_q2m_osf == 'apodizing fast') ? "selected" : "") . ">Apodizing Fast Roll-off Filter</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"brick wall\" " . (($audiophonics_q2m_osf == 'brick wall') ? "selected" : "") . ">Brick Wall Filter</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"corrected minimum phase fast\" " . (($audiophonics_q2m_osf == 'corrected minimum phase fast') ? "selected" : "") . ">Corrected Minimum Phase Fast Roll-off Filter</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"linear phase fast\" " . (($audiophonics_q2m_osf == 'linear phase fast') ? "selected" : "") . ">Linear Phase Fast Roll-off Filter</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"linear phase slow\" " . (($audiophonics_q2m_osf == 'linear phase slow') ? "selected" : "") . ">Linear Phase Slow Roll-off Filter</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"minimum phase fast\" " . (($audiophonics_q2m_osf == 'minimum phase fast') ? "selected" : "") . ">Minimum Phase Fast Roll-off Filter</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"minimum phase slow\" " . (($audiophonics_q2m_osf == 'minimum phase slow') ? "selected" : "") . ">Minimum Phase slow Roll-off Filter</option>\n";
	} else {
		// Pre 2019 implementation
		$_select['audiophonics_q2m_osf'] .= "<option value=\"brick wall\" " . (($audiophonics_q2m_osf == 'brick wall') ? "selected" : "") . ">PCM Filter sharp</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"corrected minimum phase fast\" " . (($audiophonics_q2m_osf == 'corrected minimum phase fast') ? "selected" : "") . ">PCM Filter fast</option>\n";
		$_select['audiophonics_q2m_osf'] .= "<option value=\"minimum phase slow\" " . (($audiophonics_q2m_osf == 'minimum phase slow') ? "selected" : "") . ">PCM Filter slow</option>\n";
	}
	// NOTE: This option is handled in the Source Select screen
	$_select['audiophonics_q2m_input'] .= "<option value=\"I2S\" " . (($audiophonics_q2m_input == 'I2S') ? "selected" : "") . ">I2S</option>\n";
	$_select['audiophonics_q2m_input'] .= "<option value=\"SPDIF\" " . (($audiophonics_q2m_input == 'SPDIF') ? "selected" : "") . ">S/PDIF</option>\n";
} else {
	$_audiophonics_q2m_hide = 'hide';
}

// MERUS Amp HAT ZW
if ($_SESSION['i2sdevice'] == 'MERUS(tm) Amp piHAT ZW') {
	$_merus_ma12070p = '';
	$merus_ma12070p_pmp = $array[0];
	// Power mode profiles
	$_select['merus_ma12070p_pmp'] .= "<option value=\"PMF0\" " . (($merus_ma12070p_pmp == 'PMF0') ? "selected" : "") . ">PMF0 - No filter, optimized efficiency, default applications</option>\n";
	$_select['merus_ma12070p_pmp'] .= "<option value=\"PMF1\" " . (($merus_ma12070p_pmp == 'PMF1') ? "selected" : "") . ">PMF1 - No filter, optimized audio performance, active speaker applications</option>\n";
	$_select['merus_ma12070p_pmp'] .= "<option value=\"PMF2\" " . (($merus_ma12070p_pmp == 'PMF2') ? "selected" : "") . ">PMF2 - No filter, optimized audio performance, default applications</option>\n";
	$_select['merus_ma12070p_pmp'] .= "<option value=\"PMF3\" " . (($merus_ma12070p_pmp == 'PMF3') ? "selected" : "") . ">PMF3 - LC filter, high efficiency, high audio performance, good EMI, low ripple loss</option>\n";
	$_select['merus_ma12070p_pmp'] .= "<option value=\"PMF4\" " . (($merus_ma12070p_pmp == 'PMF4') ? "selected" : "") . ">PMF4 - No filter, optimized efficiency, active speaker applications</option>\n";
} else {
	$_merus_ma12070p = 'hide';
}

waitWorker('chp-config');

$tpl = "chp-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');

// Configure chip options
function cfgChipOptions($options, $type) {
	$array = explode(',', $options);

	if ($type == 'burr_brown_pcm5') {
		// Burr Brows PCM5: Analog volume, analog volume boost, digital interpolation filter
		sysCmd('amixer -c 0 sset "Analogue" ' . $array[0]);
		sysCmd('amixer -c 0 sset "Analogue Playback Boost" ' . $array[1]);
		sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[2] . '"');
	} else if ($type == 'ess_sabre_katana') {
		// Allo Katana: Oversampling filter, de-emphasis, DoP
		sysCmd('amixer -c 0 sset "DSP Program" ' . '"' . $array[0] . '"');
		sysCmd('amixer -c 0 sset "Deemphasis" ' . $array[1]);
		sysCmd('amixer -c 0 sset "DoP" ' . $array[2]);
	} else if ($type == 'cirrus_logic_cS43198_boss2') {
		// Allo Boss 2
		sysCmd('amixer -c 0 sset "PCM De-emphasis Filter" ' . $array[0]);
		sysCmd('amixer -c 0 sset "PCM Filter Speed" ' . $array[1]);
		sysCmd('amixer -c 0 sset "PCM High-pass Filter" ' . $array[2]);
		sysCmd('amixer -c 0 sset "PCM Nonoversample Emulate" ' . $array[3]);
		sysCmd('amixer -c 0 sset "PCM Phase Compensation" ' . $array[4]);
		sysCmd('amixer -c 0 sset "HV_Enable" ' . $array[5]);
	} else if ($type == 'ess_sabre_audiophonics_q2m') {
		// Audiophonics ES9028/9038 Q2M: Oversampling filter, input select
		sysCmd('amixer -c 0 sset "FIR Filter Type" ' . '"' . $array[0] . '"');
		sysCmd('amixer -c 0 sset "I2S/SPDIF Select" ' . '"' . $array[1] . '"');
	} else if ($type == 'merus_ma12070p') {
		// MERUS Amp HAT ZW Power mode profile
		sysCmd('amixer -c 0 sset "Q.PM Prof" ' . '"' . $array[0] . '"');
	}
}
