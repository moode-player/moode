<?php
/**
 * Wrapper for functionality related to the use of CamillaDSP with moOde
 */

require_once dirname(__FILE__) . '/playerlib.php';

// camilladsp config
// camilladsp mode off|cdsp|???  cdsp use camilla cmd line options for audio format,
// config  custom |...configs...  custom make no change to the assigned config file, else assign the selected config file
// assign playback device yes|no  patch camilla config to use the playback device selected with moOde.

class CamillaDsp {

    private $ALSA_CDSP_CONFIG = '/etc/alsa/conf.d/camilladsp.test.conf';
    private $CAMILA_CONFIG_DIR = '/usr/share/camilladsp';
    private $device = NULL;
    // private $mode = 'cdsp';
    private $configfile = NULL;

    function __construct ($configfile, $device = NULL) {
        $this->configfile =$configfile;
        $this->device = $device;
    }

    /**
     * Set in camilladsp config file the playback device to use
     */
    function setPlaybackDevice($device) {
        if( $this->configfile != NULL && $this->configfile != 'Off' && $this->configfile != 'custom') {
            $this->device = $device;
            sysCmd("sudo sed -i -s '/device/s/hw:[0-9]/hw:" . $device . "/g' " . $this->getCurrentConfigFileName() );
        }
    }

    /**
     * Set in the alsa_cdsp config the camilladsp config file to use
     */
    function selectConfig($configname) {
        if($configName != 'custom' && $configName != 'Off') {
            $configfilename = $this->CAMILA_CONFIG_DIR . '/configs/' . str_replace ('/', '\/', $configname);
            syscmd("sudo sed -i -s '/[ ]config_out/s/\\\".*[.]yaml\\\"/\\\"" . $configfilename . "\\\"/g' " . $this->ALSA_CDSP_CONFIG );
        }
    }

    function getConfigsLocationsFileName() {
        return  $this->CAMILA_CONFIG_DIR . '/configs/';
    }

    function getCoeffsLocation() {
        return  $this->CAMILA_CONFIG_DIR . '/coeffs/';
    }

    /**
     * Get the filename of the camilladsp config file to use
     */
    function getCurrentConfigFileName() {
        return $this->CAMILA_CONFIG_DIR . '/configs/' . $this->configfile;
    }

    /**
     * Check the provided configfile
     * return NULL when config is correct else an array with error messages.
     */
    function checkConfigFile($configname) {
        $result = syscmd("camilladsp -c " . $this->CAMILA_CONFIG_DIR . '/configs/' . $configname);

        if(count($result) ==0 ) {
            $result = NULL;
        }
        return $result;
    }

    /**
     * Returns the basename (filename without path) of the available configs
     */
    function getAvailableConfigsRaw() {
        return $this->getAvailableConfigs(False);
    }

    function getAvailableConfigs($extended = True) {
        $configs = [];
        // If extended moode is used, return also Off and custom as selectors
        if( $extended == True ) {
            $configs['Off'] = 'Off'; // don't use camilla
            $configs['custom'] = 'Custom'; // custom configuration setup used
        }
        foreach (glob($this->CAMILA_CONFIG_DIR . '/configs/*.yaml') as $filename) {
            $fileParts = pathinfo($filename);
            $configs[$fileParts['basename']] = $fileParts['filename'];
        }
        return $configs;
    }

    function getAvailableCoeffs() {
        $configs = [];
        foreach (glob($this->CAMILA_CONFIG_DIR . '/coeffs/*.*') as $filename) {
            $fileParts = pathinfo($filename);
            $configs[$fileParts['basename']] = $fileParts['filename'];
        }
        return $configs;
    }

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

    function backup() {
    }

    function restore($values) {
    }

}

function test() {
    $cdsp = New CamillaDsp('config.good.yaml', "5");

    // print($cdsp->getCurrentConfigFileName() . "\n");
    // $cdsp->setPlaybackDevice(4);
    // $cdsp->setConfig("/foo/bar.yaml");
    print("\n");
    print($cdsp->checkConfigFile("config.good.yaml"));
    print("\n");
    print_r($cdsp->checkConfigFile("config.bad.yaml"));

    // print_r($cdsp->availableConfigs() );
    // print(count($cdsp->checkConfigFile("config.good.yaml")));

    // if( count($cdsp->checkConfigFile("config.bad.yaml")) > 0) {
    //     print("config bad \n");
    // }
    // else {
    //     print("config ok \n");
    // }
    print($cdsp->version());
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test();
}

?>