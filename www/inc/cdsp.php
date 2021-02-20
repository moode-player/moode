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
    private $CAMILLAGUI_WORKING_CONGIG = '/usr/share/camilladsp/working_config.yml';
    private $device = NULL;
    private $configfile = NULL;
    private $quickConvolutionConfig = ";;;";

    function __construct ($configfile, $device = NULL, $quickconvfg) {
        $this->configfile =$configfile;
        $this->device = $device;
        $this->quickConvolutionConfig = $this->stringToQuickConvolutionConfig($quickconvfg);
    }

    /**
     * Set in camilladsp config file the playback device to use
     */
    function setPlaybackDevice($device) {
        if( $this->configfile != NULL && $this->configfile != 'off' && $this->configfile != 'custom') {
            $this->device = $device;
            $supportedFormats = $this->detectSupportedSoundFormats();
            $useFormat = count($supportedFormats) >= 1 ?  $supportedFormats[0] : 'S32LE';

            // sysCmd("sudo sed -i -s '/device/s/hw:[0-9]/hw:" . $device . "/g' " . $this->getCurrentConfigFileName() );

            $camillaConfigDict = file_get_contents( $this->getCurrentConfigFileName() );

            $formatCount = 0;
            $hwCount = 0;
            $fhandle = fopen($this->getCurrentConfigFileName(), "r");
            $lines = array();
            if($fhandle) {
                while (!feof($fhandle ) ) {
                    $line = fgets($fhandle);
                    if ($formatCount<2 && strpos($line, 'format: ') !== false) {
                        $lines[] = explode(":", $line)[0] . ": ".$useFormat ."\n";
                        $formatCount++;
                    }else if ($hwCount<1 && strpos($line, 'device: ') !== false) {
                        $lines[] = explode(":", $line)[0] . ": \"hw:" . $device . ",0\"\n";
                        $hwCount++;
                    }else {
                        $lines[] = $line;
                    }
                }
                fclose($fhandle);
                file_put_contents($this->getCurrentConfigFileName(), $lines);
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
            if(is_file($configfilename)) {
                syscmd("sudo ln -s -f \"" . $configfilename . "\" " . $this->CAMILLAGUI_WORKING_CONGIG);
            }
            $configfilename_escaped = str_replace ('/', '\/', $this->CAMILLAGUI_WORKING_CONGIG);
            syscmd("sudo sed -i -s '/[ ]config_out/s/\\\".*\\\"/\\\"" . $configfilename_escaped . "\\\"/g' " . $this->ALSA_CDSP_CONFIG );

        }

        $this->configfile = $configname;
    }

    function reloadConfig() {
        if( $this->configfile!= 'off') {
            syscmd('sudo killall -s SIGHUP camilladsp');
        }
    }

    function getConfig() {
        return $this->configfile;
    }

    /**
     *
     */
    function stringToQuickConvolutionConfig($quickConvConfig) {
        $config= ";;;";
        if($quickConvConfig) {
            $parts = explode(';', $quickConvConfig);
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
                        '__IR_LEFT__',
                        '__IR_RIGHT__',
                        '__IR_FORMAT__');

        $replaceWith = array(   $this->quickConvolutionConfig['gain'],
                                '../coeffs/' . $this->quickConvolutionConfig['irl'],
                                '../coeffs/' . $this->quickConvolutionConfig['irr'],
                                $this->quickConvolutionConfig['irtype'] );

        $newLines = str_replace( $search, $replaceWith, $lines );

        file_put_contents ( $configFile .'.tmp', $newLines) ;
        sysCmd('sudo mv "' . $configFile . '.tmp" "' . $configFile . '"' );
        sysCmd('sudo chmod a+rw "' . $configFile . '"' );
    }

    function copyConfig($source, $destination) {
        copy($this->CAMILLA_CONFIG_DIR . '/configs/' . $source , $this->CAMILLA_CONFIG_DIR . '/configs/' . $destination);
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
            $cmd = $this->CAMILLA_EXE . " -c \"" . $configFullPath . "\"";
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
            $configs['__quick_convolution__.yml'] = 'Quick convolution filter'; // custom configuration setup used
        }
        foreach (glob($this->CAMILLA_CONFIG_DIR . '/configs/*.yml') as $filename) {
                $fileParts = pathinfo($filename);
                if($fileParts['basename'] != "__quick_convolution__.yml") {
                    $configs[$fileParts['basename']] = $fileParts['filename'];
                }
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
        $jsonString = syscmd("mediainfo --Output=JSON \"" . $fileName . "\"");
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

    function getConfigLabel($configname) {
        $selectedConfigLabel = ($configname != '__quick_convolution__.yml') ? $configname : 'Quick convolution filter';
        return $selectedConfigLabel;
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

    function impulseResponseType() {
        return array(
           'TEXT',
           'FLOAT64LE',
           'FLOATLE',
           'S32LE',
           'S243LE',
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
            syscmd("sudo systemctl start camillagui");
        }else {
            syscmd("sudo systemctl stop camillagui");
        }
    }

    function getGuiExpertMode() {
        return file_exists('/opt/camillagui/config/gui-config.yml') == false;
    }

    function setGuiExpertMode($mode) {
        if( $mode == true
           && file_exists('/opt/camillagui/config/gui-config.yml')
           && file_exists('/opt/camillagui/config/gui-config.yml.disabled') == false ) {
            syscmd("sudo mv /opt/camillagui/config/gui-config.yml /opt/camillagui/config/gui-config.yml.disabled");
        }
        else if( $mode == false
                 && file_exists('/opt/camillagui/config/gui-config.yml.disabled')
                 && file_exists('/opt/camillagui/config/gui-config.yml') == false ) {
            syscmd("sudo mv /opt/camillagui/config/gui-config.yml.disabled /opt/camillagui/config/gui-config.yml");
        }
    }

    // placeholders for autoconfig support, empty for now
    function backup() {
    }

    function restore($values) {
    }

}

function test_cdsp() {
    // $cdsp = New CamillaDsp('config_foobar.yaml', "5", "-9;test2.txt;test3.txt;S24_3LE");
    $cdsp = New CamillaDsp('config_conv.yml', "5", "-9;test2.txt;test3.txt;S24_3LE");



    // print($cdsp->getCurrentConfigFileName() . "\n");
    //$cdsp->setPlaybackDevice(4);
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

    //$cdsp->copyConfig('config_hp.yml', 'fliepflap.yml');

    //$cdsp->setGuiExpertMode(true);
    //$cdsp->setGuiExpertMode(false);
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test_cdsp();
}

?>
