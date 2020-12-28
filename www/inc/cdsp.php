<?php
/**
 * Wrapper for functionality related to the use of CamillaDSP with moOde
 */

require_once dirname(__FILE__) . '/playerlib.php';

const CDSP_CHECK_VALID = 1;
const CDSP_CHECK_INVALID = 0;
const CDSP_CHECK_NOTFOUND = -1;
class CamillaDsp {

    private $ALSA_CDSP_CONFIG = '/etc/alsa/conf.d/camilladsp.conf';
    private $CAMILA_CONFIG_DIR = '/usr/share/camilladsp';
    private $CAMILA_EXE = '/usr/local/bin/camilladsp';
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
        if( $this->configfile != NULL && $this->configfile != 'off' && $this->configfile != 'custom') {
            $this->device = $device;
            sysCmd("sudo sed -i -s '/device/s/hw:[0-9]/hw:" . $device . "/g' " . $this->getCurrentConfigFileName() );
        }
    }

    /**
     * Set in the alsa_cdsp config the camilladsp config file to use
     */
    function selectConfig($configname) {
        if($configName != 'custom' && $configName != 'off') {
            $configfilename = $this->CAMILA_CONFIG_DIR . '/configs/' . str_replace ('/', '\/', $configname);
            $configfilename = str_replace ('/', '\/', $configfilename);
            syscmd("sudo sed -i -s '/[ ]config_out/s/\\\".*\\\"/\\\"" . $configfilename . "\\\"/g' " . $this->ALSA_CDSP_CONFIG );
        }
        $this->configfile = $configname;
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
        $configFullPath = $this->CAMILA_CONFIG_DIR . '/configs/' . $configname;

        $output = array();
        $exitcode = -1;
        if( file_exists($configFullPath)) {
            $cmd = $this->CAMILA_EXE . " -c " . $configFullPath;
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

    function getAvailableConfigs($extended = True) {
        $configs = [];
        // If extended moode is used, return also Off and custom as selectors
        if( $extended == True ) {
            $configs['off'] = 'Off'; // don't use camilla
            $configs['custom'] = 'Custom'; // custom configuration setup used
        }
        foreach (glob($this->CAMILA_CONFIG_DIR . '/configs/*.yml') as $filename) {
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

function test_cdsp() {
    $cdsp = New CamillaDsp('config.good.yml', "5");

    // print($cdsp->getCurrentConfigFileName() . "\n");
    $cdsp->setPlaybackDevice(4);
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
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test_cdsp();
}

?>