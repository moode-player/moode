<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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
 * 2020-07-22 TC moOde 7.0.0
 *
 * This includes the @chris-rudmin 2019-08-08 rewrite of the GenLibrary() function
 * to support the new Library renderer /var/www/js/scripts-library.js
 * Refer to https://github.com/moode-player/moode/pull/16 for more info.
 *
 */
require_once dirname(__FILE__) . '/playerlib.php';


// factory for a Eqp wrapper for the 12 bands Eqfa12p.conf
function Eqp12($dbh)  {
    return New Eqp($dbh, 'cfg_eqp12', 12, 'eqfa12p.conf');
}

// It could also be reused to manage the settings of the 4 bands variant
// function Eqp4($dbh)  {
//     return New Eqp($dbh, 'cfg_eqfa4p', 12, 'eqfa4p.conf');
// }

/**
 *
 * Wrapper class to manage the settings of X bands CAPS parametric Eq.
 * It manage the databases and also the content of the Alsa configuratuin file
 *
 */
class Eqp {

    private $dbh = NULL;
    private $band_count = EQP_BANDS12;
    private $table = "";
    private $alsa_file ="";

    function __construct($db, $table, $bands, $configfile) {
        $this->dbh = &$db;
        $this->band_count = $bands;
        $this->table = $table;
        $this->alsa_file = $configfile;
    }

    function applyConfig($config) {
        $configstr = $this->config2string($config);
        sysCmd('sed -i "/controls/c\ \t\t\tcontrols [ ' . $configstr . ' ]" ' . ALSA_PLUGIN_PATH .'/'. $this->alsa_file);
        // to really enable it two additional steps are required:
        // - in mpd config eqfa12p should be use instead as of eqfa4p for eqp
        // - sysCmd('mpc enable only 3');
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
            $config['bands'][$key]['bandwidth'] = $value[2];
            $config['bands'][$key]['gain'] = $value[3];
        } else {
            $config['master_gain'] = $value[0];
        }

        }
        return $config;
    }

    function config2string($config) {
        $text ='';
        foreach($config['bands'] as $key=>$bandconfig) {
            $bandconfigtext = "";
            foreach($bandconfig as $param=>$value) {
                $bandconfigtext = $bandconfigtext . $value . " ";
            }
            $text = $text . $bandconfigtext . " ";
        }

        $text = $text . $config['master_gain'];
        return $text;
    }

    function getpreset($index) {
        $querystr = 'SELECT settings from '.$this->table.' where id = '.$index.';';

        $result = sdbquery($querystr, $this->dbh);
        $config = $this->string2config($result[0]['settings']);
        return $config;
    }

    function setpreset($index, $name, $config) {
        if (count($config['bands']) == $this->band_count ) {
            $settingsStr = $this->config2string($config);
            $querystr ="";
            if($index) {
                $querystr = "UPDATE ".$this->table." SET settings = '".$settingsStr."' WHERE id = ".$index.";";
            }
            else {
                $querystr ="INSERT INTO ".$this->table." (curve_name, settings, active) VALUES ('".$name."', '".$settingsStr."', 0);";
            }
            $result = sdbquery($querystr, $this->dbh);

            $querystr = 'SELECT id from '.$this->table.' where curve_name = "'.$name.'" limit 1;';
            $result = sdbquery($querystr, $this->dbh);
            return count($result)==1 ? $result[0]['id']: NULL;
        }
    }

    function unsetpreset($index) {
        if($index) {
            $querystr = "DELETE FROM ".$this->table." WHERE id = ".$index.";";
            $result = sdbquery($querystr, $this->dbh);
        }
    }

    function getPresets() {
        $querystr = 'SELECT id, curve_name from '.$this->table.';';
        $result = sdbquery($querystr, $this->dbh);
        $presets = [];
        foreach($result as $preset_row) {
            $presets[$preset_row['id']] = $preset_row['curve_name'];
        }
        return $presets;
    }

    function getActivePresetIndex() {
        $querystr = 'SELECT id from '.$this->table.' WHERE active=1;';
        $result = sdbquery($querystr, $this->dbh);
        return count($result)==1 ? $result[0]['id']: 0;
    }

    function setActivePresetIndex($index) {
        $querystr = "UPDATE ".$this->table." SET active = 0 WHERE active = 1;";
        $result = sdbquery($querystr, $this->dbh);

        // $currentActiveIndex =$this->getActivePresetIndex($db);
        // if( $currentActiveIndex) {
        //     $querystr = "UPDATE ".$this->table." SET active = 0 WHERE id = ".$currentActiveIndex.";";
        //     $querystr = "UPDATE ".$this->table." SET active = 0 WHERE id = ".$currentActiveIndex.";";
        //     $result = sdbquery($querystr, $this->dbh);
        // }
        if($index >= 1 ) {
            $querystr = "UPDATE ".$this->table." SET active = 1 WHERE id = ".$index.";";
            $result = sdbquery($querystr, $this->dbh);
        }
    }

}


// if php isn't used as include run buildin tests for development diagnostics
function test() {
    $dbh = &cfgdb_connect();
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
    $config['bands'][11]['gain'] =-3.1;
    $eqp12->applyConfig($config);

    unset($eqp12);
}


if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    test();
}
?>