<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2021 @bitlab (@bitkeeper Git)
*/

/*
 * Wrapper for functionality related to the use of CamillaDSP with moOde
*/

require_once __DIR__ . '/common.php';

const CDSP_CHECK_VALID = 1;
const CDSP_CHECK_INVALID = 0;
const CDSP_CHECK_NOTFOUND = -1;

const CGUI_CHECK_ACTIVE = 0;
const CGUI_CHECK_INACTIVE = 3;
const CGUI_CHECK_ERROR = -2;
const CGUI_CHECK_NOTFOUND = -1;

class CamillaDsp {

    private $ALSA_CDSP_CONFIG = '/etc/alsa/conf.d/camilladsp.conf';
    private $CAMILLA_CONFIG_DIR = '/usr/share/camilladsp';
    private $CAMILLA_EXE = '/usr/local/bin/camilladsp';
    private $CAMILLAGUI_WORKING_CONGIG = '/usr/share/camilladsp/working_config.yml';
    private $DEFAULT_OPTIONS = array(
    	'off' => 'Signal processing is off',
    	'custom' => 'Manually create a CamillaDSP setup for example when using multiple output devices',
        'quick convolution filter' => 'Use the selection in "Quick convolution filter" below to provide basic convolution with gain.',
    	'__quick_convolution__.yml' => 'Use the selection in "Quick convolution filter" below to provide basic convolution with gain.'
    );
    private $cardNum = NULL;
    private $configfile = NULL;
    private $quickConvolutionConfig = ",,,";

    function __construct ($configfile, $cardNum = NULL, $quickconvfg) {
        $this->configfile =$configfile;
        $this->device = $cardNum;
        $this->quickConvolutionConfig = $this->stringToQuickConvolutionConfig($quickconvfg);

        // Little bit dirty trick:
        // nginx, camillagui and cdsp not all run as the same user but required
        // write rights to the same files.
        // print('chmod -R 666 '. $this->CAMILLA_CONFIG_DIR.'/configs; chmod 666 '. $this->$CAMILLA_CONFIG_DIR.'/coefss/*');
        $this->fixFileRights();
    }

    /**
     * Set in camilladsp config file the playback device to use
     */
    function setPlaybackDevice($cardNum, $outputMode = "plughw") {
        if( $this->configfile != NULL && $this->configfile != 'off' && $this->configfile != 'custom') {
            $this->device = $cardNum;
            $alsaDevice = $outputMode == 'iec958' ? getAlsaIEC958Device() : $outputMode . ':' . $cardNum . ',0';
            $supportedFormats = $this->detectSupportedSoundFormats();
            $useFormat = count($supportedFormats) >= 1 ?  $supportedFormats[0] : 'S32LE';

            $yml_cfg = yaml_parse_file( $this->getCurrentConfigFileName() );
            $yml_cfg['devices']['capture'] = Array( 'type' => 'File',
                                                    'channels' => 2,
                                                    'filename' => '/dev/stdin',
                                                    'format' => $useFormat);
            $yml_cfg['devices']['playback'] = Array( 'type' => 'Alsa',
                                                'channels' => 2,
                                                'device' => $alsaDevice,
                                                'format' => $useFormat);

            // patch issue where yaml parser to an empty [], which would break the cdsp config
            if(key_exists('filters', $yml_cfg) && count(array_keys ($yml_cfg['filters'] ) )==0 ) {
                unset($yml_cfg['filters']);
            }
            if(key_exists('mixers', $yml_cfg) && count(array_keys ($yml_cfg['mixers'] ) )==0 ) {
                unset($yml_cfg['mixers']);
            }
            if(key_exists('pipeline', $yml_cfg) && count($yml_cfg['pipeline']  )==0 ) {
                unset($yml_cfg['mixers']);
            }

            // patches required for migrating config to camilladsp 2.0
            $majorVer = substr($this->version(), 11, 1); // Ex: version() -> CamillaDSP 2.0
            if ($majorVer >= 2) {
                if( key_exists('volume_ramp_time', $yml_cfg['devices']) && $yml_cfg['devices']['volume_ramp_time'] != 150) {
                    $yml_cfg['devices']['volume_ramp_time'] = 150;
                }
                if( !key_exists('volume_ramp_time', $yml_cfg['devices']) ) {
                    $yml_cfg['devices']['volume_ramp_time'] = 150;
                }
                if( key_exists('enable_resampling', $yml_cfg['devices']) ) {
                    unset($yml_cfg['devices']['enable_resampling']);
                }
                if( key_exists('resampler_type', $yml_cfg['devices']) ) {
                    unset($yml_cfg['devices']['resampler_type']);
                }
                if( key_exists('capture_samplerate', $yml_cfg['devices']) && $yml_cfg['devices']['capture_samplerate'] == 0) {
                    unset($yml_cfg['devices']['capture_samplerate']);
                }
                yaml_emit_file($this->getCurrentConfigFileName(), $yml_cfg);
            }
        }
    }

    /**
     * Set in the alsa_cdsp config the camilladsp config file to use
     */
    function selectConfig($configname) {
        if($configname != 'custom' && $configname != 'off' && $configname != '') {
            if( $configname == '__quick_convolution__.yml' ) {
                $this->writeQuickConvolutionConfig();
            }

            $configfilename = $this->CAMILLA_CONFIG_DIR . '/configs/' . $configname;
            $configfilename_escaped = str_replace ('/', '\/', $configfilename);
            $this->patchRelConvPath($configname);
            if(is_file($configfilename)) {
                sysCmd("sudo ln -s -f \"" . $configfilename . "\" " . $this->CAMILLAGUI_WORKING_CONGIG);
            }
        }

        $this->configfile = $configname;
    }

    function reloadConfig() {
        if( $this->configfile != 'off') {
            sysCmd('sudo killall -s SIGHUP camilladsp');
        }
    }

    function getConfig() {
        return $this->configfile;
    }

    /**
     *
     */
    function stringToQuickConvolutionConfig($quickConvConfig) {
        $config= ",,,";
        if($quickConvConfig) {
            $parts = explode(',', $quickConvConfig);
            if( count($parts) == 4 ) {
                $config = array( "gain" => $parts[0],
                                "irl" => $parts[1],
                                "irr" => $parts[2],
                                "irtype" => $parts[3]);
            }
        }
        return $config;
    }

    function setQuickConvolutionConfig($quickConvConfig) {
         $this->quickConvolutionConfig = $quickConvConfig;
    }

    function getQuickConvolutionConfig() {
        return $this->configfile = $this->quickConvolutionConfig;
    }

    function isQuickConvolutionActive() {
        return $this->configfile == '__quick_convolution__.yml';
    }


    function writeQuickConvolutionConfig() {
        $templateFile = $this->CAMILLA_CONFIG_DIR . '/__quick_convolution__.yml';
        $configFile = $this->CAMILLA_CONFIG_DIR . '/configs/__quick_convolution__.yml';
        $lines = file_get_contents($templateFile);

        $search = array('__IR_GAIN__',
                        '__IR_TYPE__',
                        '__IR_LEFT__',
                        '__IR_RIGHT__',
                        '__IR_PARAMS_L__',
                        '__IR_PARAMS_R__');

        $parameters_left = '';
        $parameters_right = '';
        $ir_type = 'Raw';
        if($this->quickConvolutionConfig['irtype'] == 'WAVE') {
            $info =$this->coeffInfo($this->quickConvolutionConfig['irr'], true);
            $ir_type = 'Wav';
            $parameters_left = 'channel: 0';
            $parameters_right = $parameters_left;
            // if stereo file and lfeft uses the same as right assume to use the second channel to right
            if( $info['channels'] == 2 && $this->quickConvolutionConfig['irl'] == $this->quickConvolutionConfig['irr']) {
                $parameters_right = 'channel: 1';
            }
        }
        else {
            $parameters_left = 'format: ' . $this->quickConvolutionConfig['irtype'];
            $parameters_right = $parameters_left;
        }

        $replaceWith = array(   $this->quickConvolutionConfig['gain'],
                                $ir_type,
                                '../coeffs/' . $this->quickConvolutionConfig['irl'],
                                '../coeffs/' . $this->quickConvolutionConfig['irr'],
                                $parameters_left,
                                $parameters_right);


        $newLines = str_replace( $search, $replaceWith, $lines );

        file_put_contents ( $configFile .'.tmp', $newLines) ;
        sysCmd('sudo mv "' . $configFile . '.tmp" "' . $configFile . '"' );
        sysCmd('sudo chmod a+rw "' . $configFile . '"' );
    }

    function copyConfig($source, $destination) {
        copy($this->CAMILLA_CONFIG_DIR . '/configs/' . $source , $this->CAMILLA_CONFIG_DIR . '/configs/' . $destination);
    }

    function newConfig($configname) {
        copy($this->CAMILLA_CONFIG_DIR . '/__config_template__.yml' , $this->CAMILLA_CONFIG_DIR . '/configs/' . $configname);
    }

    function detectSupportedSoundFormats() {
        $available_alsa_sample_formats_from_sound_card_as_string = $_SESSION['audio_formats']; //Sound card sample formats from ALSA
        $available_alsa_sample_formats_from_sound_card = explode (', ', $available_alsa_sample_formats_from_sound_card_as_string);
        $sound_device_supported_sample_formats = array();
        foreach ($this->alsaToCamillaSampleFormatLut() as $alsa_format => $cdsp_format) {
           if (in_array($alsa_format, $available_alsa_sample_formats_from_sound_card)) {
                $sound_device_supported_sample_formats[] = $cdsp_format;
           }
        }

        return $sound_device_supported_sample_formats;
    }

    function getConfigsLocationsFileName() {
        return  $this->CAMILLA_CONFIG_DIR . '/configs/';
    }

    function getCoeffsLocation() {
        return  $this->CAMILLA_CONFIG_DIR . '/coeffs/';
    }

    /**
     * Get the filename of the camilladsp config file to use
     */
    function getCurrentConfigFileName() {
        return $this->CAMILLA_CONFIG_DIR . '/configs/' . $this->configfile;
    }

    /**
     * Check the provided configfile
     * return NULL when config is correct else an array with error messages.
     */
    function checkConfigFile($configname) {
        $configFullPath = $this->CAMILLA_CONFIG_DIR . '/configs/' . $configname;

        $output = array();
        $exitcode = -1;
        if( file_exists($configFullPath)) {
            $cmd = $this->CAMILLA_EXE . " -c \"" . $configFullPath . "\"";
            exec($cmd, $output, $exitcode);
            $exitcode = $exitcode == 0 ? 1 : 0;

        } else {
            $output[] = 'Configuration file "' . $configFullPath. '" not found';
        }
        $result = [];
        $result['valid'] = $exitcode;
        $result['msg'] = str_replace('Config is', 'Configuration is', $output);

        return $result;
    }

    /**
     * Returns the basename (filename without path) of the available configs
     */
    function getAvailableConfigsRaw() {
        return $this->getAvailableConfigs(False);
    }

    /**
     * Returns list  available options for the camilladsp setting, including the Off and Custom
     */
    function getAvailableConfigs($extended = True) {
        $configsFirst = [];
        $configsRest = [];
        // If extended moode is used, return also Off and custom as selectors
        if( $extended == True ) {
            $configsFirst['off'] = 'Off'; // don't use camilla
            $configsFirst['custom'] = 'Custom'; // custom configuration setup used
            $configsFirst['__quick_convolution__.yml'] = 'Quick convolution filter'; // custom configuration setup used
        }
        foreach (glob($this->CAMILLA_CONFIG_DIR . '/configs/*.yml') as $fileName) {
            $fileParts = pathinfo($fileName);
            if($fileParts['basename'] != "__quick_convolution__.yml") {
                $configsRest[$fileParts['basename']] = $fileParts['filename'];
            }
        }
        ksort($configsRest, SORT_NATURAL | SORT_FLAG_CASE);
        $configs = array_merge($configsFirst, $configsRest);
        return $configs;
    }

    /**
     * Get list available coeffs for convolution
     */
    function getAvailableCoeffs() {
        $configs = [];
        foreach (glob($this->CAMILLA_CONFIG_DIR . '/coeffs/*.*') as $filename) {
            $fileParts = pathinfo($filename);
            $configs[$fileParts['basename']] = $fileParts['filename'];
        }
        return $configs;
    }

    /**
     * Provide coeff info
     */
    function coeffInfo($coefffile, $raw = False) {
        $fileName = $this->CAMILLA_CONFIG_DIR . '/coeffs/'. $coefffile;
        $jsonString = sysCmd("mediainfo --Output=JSON \"" . $fileName . "\"");
        $mediaDataObj = json_decode(implode($jsonString));

        $ext = $mediaDataObj->{'media'}->{'track'}[0]->{'FileExtension'};
        $siz = $mediaDataObj->{'media'}->{'track'}[0]->{'FileSize'};
        $rate =$mediaDataObj->{'media'}->{'track'}[1]->{'SamplingRate'};
        $bits =$mediaDataObj->{'media'}->{'track'}[1]->{'BitDepth'};
        $ch = $mediaDataObj->{'media'}->{'track'}[1]->{'Channels'};
        $format =$mediaDataObj->{'media'}->{'track'}[1]->{'Format'};
        $encodingP =$mediaDataObj->{'media'}->{'track'}[1]->{'Format_Profile'};
        $encodingS =$mediaDataObj->{'media'}->{'track'}[1]->{'Format_Settings_Sign'};

        $mediaInfo = Array();
        if($ext)
            $mediaInfo['extension'] = $ext;
        if($format)
            $mediaInfo['format'] = $format;
        if($encodingP)
            $mediaInfo['encoding'] = $encodingP;
        elseif($encodingS)
            $mediaInfo['encoding'] = $encodingS;
        if($rate)
            $mediaInfo['samplerate'] = $raw ? intval($rate) : $rate/1000.0 . ' kHz';
        if($bits)
            $mediaInfo['bitdepth'] = $raw ? intval($bits) : $bits . ' bits';
        if($ch)
            $mediaInfo['channels'] = intval($ch);
        if($siz != NULL)
            $mediaInfo['size'] = $raw ? intval($siz) : sprintf('%.1f kB', $siz/1024.0) ;

        return $mediaInfo;
    }

    function getConfigLabel($configName) {
        $selectedConfigLabel = ($configName != '__quick_convolution__.yml') ? $configName : 'Quick convolution filter';
        return $selectedConfigLabel;
    }

    /**
     * Returns the version of the used CamillaDSP
     */
    function version() {
        $version  = NULL;
        $result = sysCmd("camilladsp --version ");

        if(  count($result) == 1 ) {
            $version =  $result[0];
        } else {
            $version = "Error: Unable to detect version of Camilla DSP.";
        }
        return $version;
    }

    /**
     * ALSA sample formats with corresponding CamillaDSP sample formats
     */
    function alsaToCamillaSampleFormatLut() {
        return array(
            'FLOAT64_LE' => 'FLOAT64LE',
            'FLOAT_LE' => 'FLOAT32LE',
            'S32_LE' => 'S32LE',
            'S24_3LE' => 'S24LE3',
            'S24_LE' => 'S24LE',
            'S16_LE' => 'S16LE');
    }

    function impulseResponseType() {
        return array(
           'WAVE',
           'TEXT',
           'FLOAT64LE',
           'FLOAT32LE',
           'S32LE',
           'S24LE3',
           'S24LE',
           'S16LE'
        );
    }

    function getCamillaGuiStatus() {
        $output = array();
        $exitcode = CGUI_CHECK_NOTFOUND;
        if( file_exists('/etc/systemd/system/camillagui.service')) {
            $cmd = 'systemctl status camillagui';
            exec($cmd, $output, $exitcode);
        }

        return $exitcode;
    }

    function changeCamillaStatus($enable) {
        if($enable) {
            sysCmd("sudo systemctl enable camillagui");
            sysCmd("sudo systemctl start camillagui");
        }else {
            sysCmd("sudo systemctl stop camillagui");
            sysCmd("sudo systemctl disable camillagui");
        }
    }

    function getGuiExpertMode() {
        return file_exists('/opt/camillagui/config/gui-config.yml') == false;
    }

    function setGuiExpertMode($mode) {
        if( $mode == true
           && file_exists('/opt/camillagui/config/gui-config.yml')
           && file_exists('/opt/camillagui/config/gui-config.yml.disabled') == false ) {
            sysCmd("sudo mv /opt/camillagui/config/gui-config.yml /opt/camillagui/config/gui-config.yml.disabled");
        }
        else if( $mode == false
                 && file_exists('/opt/camillagui/config/gui-config.yml.disabled')
                 && file_exists('/opt/camillagui/config/gui-config.yml') == false ) {
            sysCmd("sudo mv /opt/camillagui/config/gui-config.yml.disabled /opt/camillagui/config/gui-config.yml");
        }
    }

    function _waveConvertOptions($bitdeph, $encoding) {
        // standard supported raw formats
        $conversion_table = [ 'f' =>
                              [ 64 => [64, 'floating-point'],
                                32 => [32, 'floating-point'] ],
                              'i' =>
                              [ 32 => [32, 'signed-integer'],
                                24 => [24, 'signed-integer'] ,
                                16 => [16, 'signed-integer'] ]
                            ];

        // chekc if the src wav is a support dest raw format
        if( array_key_exists($encoding, $conversion_table) && array_key_exists($bitdeph, $conversion_table[$encoding])) {
            return $conversion_table[$encoding][$bitdeph];
        }
        // else just convert it to 32b signed format
        return [32, 'signed-integer'];
    }

    function convertWaveFile($coefffile) {
        $info = $this->coeffInfo($coefffile, TRUE);

        if( isset($info['extension']) && isset($info['channels']) && strtolower($info['extension']) == 'wav' ) {
            $sox_path = '/usr/bin/sox';
            if(file_exists($sox_path)) {
                $bitdepth = intval(explode(" ", $info['bitdepth'])[0]);
                $coding = strtolower($info['encoding'][0]) =='f' ? 'f': 'i';
                $sox_options = $this->_waveConvertOptions($bitdepth, $coding);
                $sox_options_str =  sprintf(' -b %d -e %s ', $sox_options[0], $sox_options[1] );

                $path_parts = pathinfo($coefffile);
                $fileName = $this->CAMILLA_CONFIG_DIR . '/coeffs/'. $coefffile;

                $fileNameRawBase = sprintf('%s/coeffs/%s_%%s%dHz_%db%s.raw', $this->CAMILLA_CONFIG_DIR , $path_parts['filename'], $info['samplerate'], $bitdepth, $coding == 'f'? 'f': '') ;
                $cmds = [];
                if( $info['channels'] == 1 ) {
                    $fileNameRaw = sprintf($fileNameRawBase, '');
                    unlink($fileNameRaw);
                    $cmd = $sox_path .' "' . $fileName . '"' . $sox_options_str . '"' . $fileNameRaw. '"';

                    print($cmd);
                    exec($cmd . " 2>&1", $output);
                    if( file_exists($fileNameRaw) ) {
                        unlink($fileName);
                        $this->fixFileRights();
                        return NULL;
                    }
                    else {
                        $output[] = 'Could not find generated files';
                        return $output;
                    }
                }else{
                    $fileNameRawL = sprintf($fileNameRawBase, 'L_');
                    $fileNameRawR = sprintf($fileNameRawBase, 'R_');

                    print($fileNameRawL);

                    unlink($fileNameRawL);
                    unlink($fileNameRawR);
                    $cmd = $sox_path .' "' . $fileName . '"' . $sox_options_str . '"' . $fileNameRawL. '" remix 1 ; '. $sox_path .' "' . $fileName . '"' . $sox_options_str . '"' . $fileNameRawR. '" remix 2';
                    print($cmd);
                    exec($cmd . " 2>&1", $output);
                    if( file_exists($fileNameRawL) && file_exists($fileNameRawR)) {
                        unlink($fileName);
                        $this->fixFileRights();
                        return NULL;
                    }
                    else {
                        $output[] = 'Could not find generated files';
                        return $output;
                    }
                }

            }
            else {
                return ['SoX not found, please install SoX'];
            }
        }
        else {
            return ['File is not a Stereo wave file'];
        }

    }


    /**
     * CamillaGUI requires absolute path names, convert rel coeff files to absolute
     */
    function patchRelConvPath($config) {
        if( $config != NULL && $config != 'off' && $config != 'custom') {
            $configfile =  $this->getConfigsLocationsFileName() . $config;
            if( file_exists($configfile)) {
                $coeffsdir  = str_replace ('/', '\/', $this->CAMILLA_CONFIG_DIR . '/coeffs' );
                $cmd = "sed -i -s 's/[.][.]\/coeffs/" . $coeffsdir. "/g' " . $configfile;
                return $this->userCmd($cmd);
            }
            else {
                return 99;
            }
        }
        return 0;
    }

    function fixFileRights() {
        sysCmd('chmod 666 '.$this->CAMILLA_CONFIG_DIR.'/configs/*; sudo chmod 666 '.  $this->CAMILLA_CONFIG_DIR.'/coeffs/*');
    }
    function userCmd($cmd) {
        exec($cmd, $output, $exitcode);
        return $exitcode;
    }

    function getLogLevel() {
        $res=sysCmd("cat " . $this->ALSA_CDSP_CONFIG . "| grep -e '#[ ]*-v'");
        $level =  (count($res)==0 ) ? 'verbose' : 'default';
        return $level;
    }

    function setLogLevel($level) {
        if( $level == 'verbose') {
            sysCmd('sed -i "s/#[ ]*-v/         -v/g" ' . $this->ALSA_CDSP_CONFIG);
        }
        else {
            sysCmd('sed -i "s/^[ ]*-v/#        -v/g" ' . $this->ALSA_CDSP_CONFIG);
        }
    }

    // placeholders for autoconfig support, empty for now
    function backup() {
    }

    function restore($values) {
    }

    // CamillaDSP 2 config description
    function getConfigDescription($config) {
        $defOptions = $this->DEFAULT_OPTIONS;
        if (key_exists(lcfirst($config), $defOptions)) {
        	$description = $defOptions[lcfirst($config)];
        } else {
            $ext = substr($config, -4) != '.yml' ? '.yml' : '';
        	$parsedConfig = yaml_parse_file($this->CAMILLA_CONFIG_DIR . '/configs/' . $config . $ext);
        	$description = key_exists('description', $parsedConfig) ?
        		$parsedConfig['description'] :
        		'No description available';
        }
        return $description;
    }

    function updCDSPConfig($newMode, $currentMode, $cdsp) {
        if ($newMode != $currentMode && ($newMode == 'off' || $currentMode == 'off')) {
            // Switching to/from Off
            if ($newMode == 'off') {
                $mixerType = $_SESSION['alsavolume'] != 'none' ? 'hardware' : 'software';
                $notifyMsg = ucfirst($mixerType) . ' volume.';
                $queueArg1 = ',change_mixer_to_default';
            } else {
                $mixerType = 'null';
                $notifyMsg = 'CamillaDSP volume.';
                $queueArg1 = ',change_mixer_to_camilladsp';
            }

            // Update cfg_mpd here so "Volume type" gets refreshed when the page returns
            // after the config is changed in the Equalizers section of Audio Config
            sqlUpdate('cfg_mpd', sqlConnect(), 'mixer_type', $mixerType);

            sendFECmd('cdsp_update_config' . ',' . $notifyMsg);
            // So notification stays up for a bit.
            sleep(2);
            submitJob('camilladsp', $newMode . $queueArg1);
        } else {
            // Switching between configs
            $cdsp->reloadConfig();
            sendFECmd('cdsp_config_updated' . ',' . $newMode);
        }
    }

    static function isMPD2CamillaDSPVolSyncEnabled() {
    	return ($_SESSION['mpdmixer'] == 'null' && $_SESSION['camilladsp'] !='off' && $_SESSION['camilladsp_volume_sync'] != 'off');
    }

    static function getCDSPVol() {
        if (file_exists('/var/lib/cdsp/statefile.yml')) {
            $result = sysCmd("cat /var/lib/cdsp/statefile.yml | grep 'volume' -A1 | grep -e '- ' | awk '/- /{print $2}'")[0];
        } else {
            $result = '0.00';
        }
        return (intval($result * 100) / 100);
    }

    static function setCDSPVolTo0dB ($vol = '0.0') {
        sysCmd("sed -i '0,/- -.*/s//- " . $vol . "/' /var/lib/cdsp/statefile.yml");
    }

    static function calcMappedDbVol($volume, $dynamic_range) {
        $x = $volume/100.0;
        $y = pow(10, $dynamic_range/20);
        $a = 1/$y;
        $b = log($y);
        $y= $a*exp($b*($x));
        if ($x < .1) {
            $y = $x*10*$a*exp(0.1*$b);
        }
        if( $y == 0) {
            $y = 0.000001; // NOTE: Must be same value in /usr/local/bin/mpd2cdspvolume function lin_vol_curve()
        }
        return 20* log10($y);
    }
}

function test_cdsp() {
    // $cdsp = New CamillaDsp('config_foobar.yaml', "5", "-9;test2.txt;test3.txt;S24_3LE");
    $cdsp = New CamillaDsp('flat.yml', "2", "-9;test2.txt;test3.txt;S24_3LE");

    //$cdsp = New CamillaDsp('flat.yml', "2", "-9;Cor1S44.wav;Cor1S44.wav;WAVE");



    // print($cdsp->getCurrentConfigFileName() . "\n");
//    $cdsp->setPlaybackDevice(4);
    // $cdsp->selectConfig("config_foobar.yml");
    // print("\n");
    // print_r($cdsp->checkConfigFile("config.good.yml"));
    // print("\n");
    // print_r($cdsp->checkConfigFile("config.bad.yml"));
    // print("\n");
    // print_r($cdsp->checkConfigFile("config.doesnt_exist.yml"));

    // print_r($cdsp->availableConfigs() );
    // print(count($cdsp->checkConfigFile("config.good.yml")));

    // print_r($cdsp->checkConfigFile("config.good.yml"));
    // print(gettype($cdsp->checkConfigFile("config.good.yml")['valid']));
    // if( $cdsp->checkConfigFile("config.good.yml")['valid'] == 1) {
    //     print("config ok \n");
    // }
    // else {
    //     print("config bad \n");
    // }
    // print($cdsp->version());

   print_r($cdsp->detectSupportedSoundFormats());


    // $cdsp->setPlaybackDevice(7);

    // print_r($cdsp->coeffinfo('Sennheiser_HD800S.wav'));
    //$res = $cdsp->coeffInfo('Sennheiser_HD800S.wav');
    //print_r($res);
//    print_r($res->{'media'}->{'track'}[0]->{'Format'});

    // print($res['media']['track'][1]['BitDepth']);

    // $res = $cdsp->coeffInfo('test1.txt');
    // print_r($res);
    //     $cdsp->changeCamillaStatus(0);
    //     print_r( $cdsp->getCamillaGuiStatus() );
    //     print("\n");
    //     $cdsp->changeCamillaStatus(1);
    //     print_r( $cdsp->getCamillaGuiStatus() );
    //     print("\n");

    //$cdsp->copyConfig('config_hp.yml', 'fliepflap.yml');

    //$cdsp->setGuiExpertMode(true);
    //$cdsp->setGuiExpertMode(false);
    // $cdsp->convertWave2Raw('BRIR_R02_P1_E0_A30_L.wav');



    // print_r($cdsp->convertWaveFile('test_samplerate_44100Hz.wav'));
    // print_r($cdsp->convertWaveFile('Sennheiser_HD800S_L.wav'));
    // print_r($cdsp->convertWaveFile('BRIR_R02_P1_E0_A30C_44100Hz_24b.raw'));
//    $cdsp->setPlaybackDevice(2);

// $fileIn = "/tmp/flat.in.yml";
// $fileOut = "/tmp/flat.out.yml";
// $yml_cfg = yaml_parse_file( $fileIn  );

// if(key_exists('filters', $yml_cfg) && count(array_keys ($yml_cfg['filters'] ) )==0 ) {
//     unset($yml_cfg['filters']);
// }
// if(key_exists('mixers', $yml_cfg) && count(array_keys ($yml_cfg['mixers'] ) )==0 ) {
//     unset($yml_cfg['mixers']);
// }
// if(key_exists('pipeline', $yml_cfg) && count(array_keys ($yml_cfg['pipeline'] ) )==0 ) {
//     unset($yml_cfg['mixers']);
// }

// yaml_emit_file($fileOut, $yml_cfg);
    // print($cdsp->getLogLevel() );
    // // $cdsp->setLogLevel('verbose');
    // $cdsp->setLogLevel('default');

    // print($cdsp->getLogLevel() );

    //$cdsp->writeQuickConvolutionConfig();

}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test_cdsp();
}

?>
