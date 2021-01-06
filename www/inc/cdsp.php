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
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Wrapper for functionality related to the use of CamillaDSP with moOde
 */

require_once dirname(__FILE__) . '/playerlib.php';

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
    private $device = NULL;
    private $configfile = NULL;

    function __construct ($configfile, $device = NULL) {
        $this->configfile =$configfile;
        $this->device = $device;
    }

    /**
     * Set in camilladsp config file the playback device to use
     */
    function setPlaybackDevice($device) {
        if( $this->configfile != NULL && $this->configfile != 'off' && $this->configfile != 'custom') {
            $this->device = $device;
            $supportedFormats = $this->detectSupportedSoundFormats();
            $useFormat = count($supportedFormats) >= 1 ?  $supportedFormats[0] : 'S32LE';

            sysCmd("sudo sed -i -s '/device/s/hw:[0-9]/hw:" . $device . "/g' " . $this->getCurrentConfigFileName() );

            sysCmd("sudo sed -i -s '/format/s/[:][ ].*/: " . $useFormat . "/g' " . $this->getCurrentConfigFileName() );
        }
    }

    /**
     * Set in the alsa_cdsp config the camilladsp config file to use
     */
    function selectConfig($configname) {
        if($configName != 'custom' && $configName != 'off') {
            $configfilename = $this->CAMILLA_CONFIG_DIR . '/configs/' . str_replace ('/', '\/', $configname);
            $configfilename = str_replace ('/', '\/', $configfilename);
            syscmd("sudo sed -i -s '/[ ]config_out/s/\\\".*\\\"/\\\"" . $configfilename . "\\\"/g' " . $this->ALSA_CDSP_CONFIG );
        }
        $this->configfile = $configname;
    }

    function detectSupportedSoundFormats() {
        $available_alsa_sample_formats_from_sound_card_as_string = sysCmd('moodeutl -f')[0]; //Sound card sample formats from ALSA
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
            $cmd = $this->CAMILLA_EXE . " -c " . $configFullPath;
            exec($cmd, $output, $exitcode);
            $exitcode = $exitcode == 0 ? 1 : 0;

        }else {
            $output[] = 'Config file "' . $configFullPath. '" NOT found';
        }
        $result = [];
        $result['valid'] = $exitcode;
        $result['msg'] =  $output;

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
        $configs = [];
        // If extended moode is used, return also Off and custom as selectors
        if( $extended == True ) {
            $configs['off'] = 'Off'; // don't use camilla
            $configs['custom'] = 'Custom'; // custom configuration setup used
        }
        foreach (glob($this->CAMILLA_CONFIG_DIR . '/configs/*.yml') as $filename) {
            $fileParts = pathinfo($filename);
            $configs[$fileParts['basename']] = $fileParts['filename'];
        }
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
    function coeffInfo($coefffile) {
        $fileName = $this->CAMILLA_CONFIG_DIR . '/coeffs/'. $coefffile;
        $jsonString = syscmd("mediainfo --Output=JSON " . $fileName);
        $mediaDataObj = json_decode(implode($jsonString));

        $ext = $mediaDataObj->{'media'}->{'track'}[0]->{'FileExtension'};
        $siz = $mediaDataObj->{'media'}->{'track'}[0]->{'FileSize'};
        $rate =$mediaDataObj->{'media'}->{'track'}[1]->{'SamplingRate'};
        $bits =$mediaDataObj->{'media'}->{'track'}[1]->{'BitDepth'};
        $ch = $mediaDataObj->{'media'}->{'track'}[1]->{'Channels'};
        $format =$mediaDataObj->{'media'}->{'track'}[1]->{'Format'};

        $mediaInfo = Array();
        if($ext)
            $mediaInfo['extension'] = $ext;
        if($format)
            $mediaInfo['format'] = $format;
        if($rate)
            $mediaInfo['samplerate'] = $rate/1000.0 . ' kHz';
        if($bits)
            $mediaInfo['bitdepth'] = $bits . ' bits';
        if($ch)
            $mediaInfo['channels'] = $ch;
        if($siz != NULL)
            $mediaInfo['size'] = sprintf('%.1f kB', $siz/1024.0) ;

        return $mediaInfo;
    }

    /**
     * Returns the version of the used CamillaDSP
     */
    function version() {
        $version  = NULL;
        $result = syscmd("camilladsp --version ");

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
            syscmd("sudo systemctl start camillagui");
        }else {
            syscmd("sudo systemctl stop camillagui");
        }
    }

    // placeholders for autoconfig support, empty for now
    function backup() {
    }

    function restore($values) {
    }

}

function test_cdsp() {
    $cdsp = New CamillaDsp('config_foobar.yaml', "5");

    // print($cdsp->getCurrentConfigFileName() . "\n");
    //$cdsp->setPlaybackDevice(4);
    // $cdsp->selectConfig("config_foobar.yml");
    print("\n");
    print_r($cdsp->checkConfigFile("config.good.yml"));
    print("\n");
    print_r($cdsp->checkConfigFile("config.bad.yml"));
    print("\n");
    print_r($cdsp->checkConfigFile("config.doesnt_exist.yml"));

    // print_r($cdsp->availableConfigs() );
    // print(count($cdsp->checkConfigFile("config.good.yml")));

    print_r($cdsp->checkConfigFile("config.good.yml"));
    print(gettype($cdsp->checkConfigFile("config.good.yml")['valid']));
    if( $cdsp->checkConfigFile("config.good.yml")['valid'] == 1) {
        print("config ok \n");
    }
    else {
        print("config bad \n");
    }
    print($cdsp->version());

    print_r($cdsp->detectSupportedSoundFormats());


    // $cdsp->setPlaybackDevice(7);

    // print_r($cdsp->coeffinfo('Sennheiser_HD800S.wav'));
    $res = $cdsp->coeffInfo('Sennheiser_HD800S.wav');
    print_r($res);
//    print_r($res->{'media'}->{'track'}[0]->{'Format'});

    // print($res['media']['track'][1]['BitDepth']);

    $res = $cdsp->coeffInfo('test1.txt');
    print_r($res);
        $cdsp->changeCamillaStatus(0);
        print_r( $cdsp->getCamillaGuiStatus() );
        print("\n");
        $cdsp->changeCamillaStatus(1);
        print_r( $cdsp->getCamillaGuiStatus() );
        print("\n");

}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test_cdsp();
}

?>
