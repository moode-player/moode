<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2020 @bitlab (@bitkeeper Git)
*/

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

// Factory for a Eqp wrapper for the 12 bands Eqfa12p.conf
function Eqp12($dbh)  {
    return New Eqp($dbh, 'cfg_eqp12', 12, 'eqfa12p.conf');
}

/**
 *
 * Wrapper class to manage the settings of X bands CAPS parametric Eq.
 * It manage the databases and also the content of the Alsa configuratuin file
 *
 */
class Eqp {

    private $dbh = NULL;
    private $band_count = 12;
    private $table = "";
    private $alsa_file = "";

    function __construct($db, $table, $bands, $configfile) {
        $this->dbh = &$db;
        $this->band_count = $bands;
        $this->table = $table;
        $this->alsa_file = $configfile;
    }

    function applyConfig($config) {
        $configstr = $this->config2string($config, True); // force to use bw instead of q
        sysCmd('sudo sed -i "/controls/c\controls [ ' . $configstr . ' ]" ' . ALSA_PLUGIN_PATH .'/'. $this->alsa_file);
    }

    function string2config($string) {
        $config = [];
        $config['bands']=[];
        $parts = explode('  ', $string);
        foreach($parts as $key=>$value) {
            $value = explode(' ', $value);
            if( count($value) >1 ) {
                $config['bands'][$key]=[];
                $config['bands'][$key]['enabled'] = $value[0];
                $config['bands'][$key]['frequency'] = $value[1];
                $config['bands'][$key]['q'] = $value[2];
                $config['bands'][$key]['gain'] = $value[3];
            }
            else {
                $config['master_gain'] = $value[0];
            }

        }
        return $config;
    }

    function config2string($config, $tobw=False) {
        $text ='';
        foreach($config['bands'] as $key=>$bandconfig) {
            $bandconfigtext = "";
            $bw = 0;

            // use bw instead of q in output string
            if($tobw == True) {
                $bw = sprintf("%0.3f", $this->q2bw($bandconfig['frequency'],  $bandconfig['q']));
            }
            foreach($bandconfig as $param=>$value) {
                if($tobw == True && $param == 'q') {
                    $bandconfigtext = $bandconfigtext . $bw . " ";
                }
                else {
                    $bandconfigtext = $bandconfigtext . $value . " ";
                }
            }
            $text = $text . $bandconfigtext . " ";
        }

        $text = $text . $config['master_gain'];
        return $text;
    }

    function getpreset($index) {
        $querystr = 'SELECT settings from ' . $this->table . ' where id = '. $index . ';';

        $result = sqlQuery($querystr, $this->dbh);
        $config = $this->string2config($result[0]['settings']);
        return $config;
    }

    function setpreset($index, $name, $config) {
        if (count($config['bands']) == $this->band_count ) {
            $settingsStr = $this->config2string($config);
            $querystr = "";
            if($index) {
                $querystr = "UPDATE " . $this->table . " SET settings = '" . $settingsStr . "' WHERE id = " . $index . ";";
            }
            else {
                $querystr ="INSERT INTO " . $this->table . " (curve_name, settings, active) VALUES ('" . $name . "', '" . $settingsStr . "', 0);";
            }
            $result = sqlQuery($querystr, $this->dbh);

            $querystr = 'SELECT id from ' . $this->table . ' where curve_name = "' . $name . '" limit 1;';
            $result = sqlQuery($querystr, $this->dbh);
            return (is_array($result) and count($result)==1) ? $result[0]['id']: NULL;
        }
    }

    function unsetpreset($index) {
        if($index) {
            $querystr = "DELETE FROM " . $this->table . " WHERE id = " . $index . ";";
            $result = sqlQuery($querystr, $this->dbh);
        }
    }

    function getPresets() {
        $querystr = 'SELECT id, curve_name from ' . $this->table . ';';
        $result = sqlQuery($querystr, $this->dbh);
        $presets = [];
        foreach($result as $preset_row) {
            $presets[$preset_row['id']] = $preset_row['curve_name'];
        }
        return $presets;
    }

    function getActivePresetIndex() {
        $querystr = 'SELECT id from ' . $this->table . ' WHERE active=1;';
        $result = sqlQuery($querystr, $this->dbh);
        return (is_array($result) and count($result)==1) ? $result[0]['id']: 0;
    }

    function setActivePresetIndex($index) {
        $querystr = "UPDATE " . $this->table . " SET active = 0 WHERE active = 1;";
        $result = sqlQuery($querystr, $this->dbh);

        if($index >= 1 ) {
            $querystr = "UPDATE " . $this->table . " SET active = 1 WHERE id = " . $index . ";";
            $result = sqlQuery($querystr, $this->dbh);
        }
    }

    /**
     * Calculated the bw for EqFa based on f center and q factor.
     *
     * If for analyzing also the f of the -3dB points are required the following is needed:
     *
     *  $a = (2.0 * $q**2.0 + 1)/ (2.0* $q**2.0);
     *  $b = sqrt( ( ((2.0* $q**2.0+1)/$q**2)**2) /4 -1 );
     *  $y1 = $a + $b;
     *  $y2 = $a - $b;
     *
     *  $fl = sqrt(($frequency**2)/$y1);
     *  $fh = $y1* $fl;
     *
     *  $fd = $fh- $fl;
     *  $bw = $fd /$frequency;
     *
     */
    function q2bw($frequency, $q) {

        // From Q the bandwidth can easly be calculated,
        // only if you need more like the f of the -3db points
        // you need the math below:
        $bw = 0.5/$q;
        return $bw;
    }

    /**
     * Provide import functionality for autocfg
     */
    function import($values) {
        $curve_count = count($values['eqp12_curve_name']);
        $keys = array_keys($values);

        $querystr = 'DELETE FROM '. $this->table . ';';
        $result = sqlQuery($querystr, $this->dbh);

        for ($index = 0; $index < $curve_count; $index++) {
            $curve_name = $values['eqp12_curve_name'][$index];
            $curve_settings = $values['eqp12_settings'][$index];
            $curve_active = $values['eqp12_active'][$index];

            $querystr = 'SELECT id from ' . $this->table . ' WHERE curve_name = "' . $curve_name . '" LIMIT 1;';
            $result = sqlQuery($querystr, $this->dbh);
            // Check if curve is present, in that case an update will be done
            $curve_curr_id = is_array($result) && count($result) == 1 ? $result[0]['id'] : NULL;

            $config = $this->string2config($curve_settings);
            $curve_id = $this->setpreset($curve_curr_id , $curve_name, $config);
            if (in_array(strtolower($curve_active), ["1", "yes", "true", "on"])) {
                $this->applyConfig($config);
                $this->setActivePresetIndex($curve_id);
            }
        }
    }

    /**
     * Provide export functionality for autocfg
     */
    function export() {
        $querystr = 'SELECT id, curve_name, settings, active from ' . $this->table . ';';
        $result = sqlQuery($querystr, $this->dbh);

        $eqp_export ='';
        $stringformat = "eqp12_%s[%d] = \"%s\"\n";
        foreach($result as $index=>$preset_row) {
            $eqp_export =  $eqp_export . sprintf($stringformat, 'curve_name', $index, $preset_row['curve_name']);
            $eqp_export =  $eqp_export . sprintf($stringformat, 'settings', $index, $preset_row['settings']);
            $eqp_export =  $eqp_export . sprintf($stringformat, 'active', $index, $preset_row['active'] == 1 ? 'Yes': 'No');
        }

        return $eqp_export;
    }
}


// if php isn't used as include run buildin tests for development diagnostics
function test() {
    $dbh = &sqlConnect();
    $eqp12 = Eqp12($dbh);

    print("get config for preset 1:\n");
    $config = $eqp12->getpreset(1);
    print_r($config);
    print("\nconfig to string:\n");
    $string = $eqp12->config2string($config);
    print($string);
    print("\n");

    print("\nget active preset:\n");
    print( $eqp12->getActivePresetIndex() );

    print("\nlist available presets:\n");
    print_r($eqp12->getPresets());

    print("\ncreate preset:\n");
    $new_preset_id = $eqp12->setpreset(NULL, "test", $config);
    print($new_preset_id);
    print("\n");
    print_r($eqp12->getPresets());

    print("\nset active preset:\n");
    $eqp12->setActivePresetIndex($new_preset_id);
    print( $eqp12->getActivePresetIndex() );

    print("\nupdate preset:\n");
    print($eqp12->setpreset($new_preset_id, NULL, $config));

    $eqp12->setActivePresetIndex(1);
    print("\nremove preset:\n");
    $eqp12->unsetpreset($new_preset_id);
    print_r($eqp12->getPresets());

    print("\nupdate config file:\n");
    $config['bands'][11]['gain'] = -3.1;
    $eqp12->applyConfig($config);

    print( $eqp12->q2bw(1000, 4.0) . "\n");
    print( $eqp12->q2bw(1000, 8.0) . "\n");
    print( $eqp12->q2bw(1000, 1.0) . "\n");

    $string = "1 1000 1 6  0 20 1 3  0 4000 1 8  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  -6";

    $config = $eqp12->string2config($string);
    print_r($config);

    $string = $eqp12->config2string($config);
    print($string."\n");
    $string = $eqp12->config2string($config, True);
    print($string."\n");

    $eqp12->setActivePresetIndex(0);
    print($eqp12->getActivePresetIndex());
    print("\n");
    $eqp12->setActivePresetIndex(1);
    print($eqp12->getActivePresetIndex());
    print("\n");
    $eqp12->setActivePresetIndex(0);

    $config = $eqp12->getpreset(1);
    $new_preset_id = $eqp12->setpreset(NULL, "test", $config);
    print($new_preset_id );
    print("\n");

    unset($eqp12);
}


if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test();
}
?>
