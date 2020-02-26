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
 * 2019-12-24 TC moOde 6.3.0
 * Contrib: Lee Jordan
 */


require_once dirname(__FILE__) . '/inc/playerlib.php';

playerSession('open', '' ,'');
$db         = new SQLite3('/var/local/www/db/moode-sqlite3.db');
$host       = gethostname();


// BUILD MOODE STATION LINEUP
$result     = $db->query('SELECT * FROM cfg_radio WHERE type = "s"');
$_lups      = '<ul class="ui-lineup">';

while ($lup = $result->fetchArray()) {
    if($lup['id'] < 499){
        $_lups .= '<li>';
        $_lups .= '<a href="http://'.$host.'/radio?ch='.$lup['id'].'" target="_blank"><span class="lup-id">' . $lup['id'] . '</span>' . '&nbsp;<span class="lup-name">' . $lup['name'] . '<span>&nbsp;<span class="lup-hostname">on '.$host.'</span></a>';
        $_lups .= '</li>';
    }
}

$_lups .= '</ul>';




$i = 0;

// LOOK UP USER ADDED STATIONS IN DB TO BUILD LIST OF LOGOS
$results    = $db->query('SELECT * FROM cfg_radio WHERE type = "u"');


// PUSH READABLE NETWORK NAMES FROM USER INPUT TO CREATE CURRENT NETWORK LOGOS 
$_networklogos = '<ul class="network-logos">';
$i = 0;
$logopath = "/images/radio-logos/thumbs/";
$_lupu      = '<ul class="ui-lineup">';
while ($row = $results->fetchArray()) {
    $logosrc    = $logopath . $row['name'] .".jpg";
    $lastid     = $row['id'];
    
    // Lay down the logo browser ...
    $_networklogos .= '<li><p style="text-align:center;"><small>'.$row['name'].'</small><img id="fauxbtn'.$i.'" class="network-logo" src="'.$logosrc.'" onclick="document.getElementById(\'logoupload'.$i.'\').click();" style="cursor:pointer;"><input type="file" name="logoupload'.$i.'" id="logoupload'.$i.'"><input type="hidden" name="logoname'.$i.'" id="logoname'.$i.'" value="'.$row['name'].'"></p></li>';
    
    // Lay down the user station lineup ...
    if($row['id'] > 499){
        $_lupu .= '<li>';
        $_lupu .= '<a href="http://'.$host.'/radio?ch='.$row['id'].'" target="_blank"><span class="lup-id">' . $row['id'] . '</span>' . '&nbsp;<span class="lup-name">' . $row['name'] . '<span>&nbsp;<span class="lup-hostname">on '.$host.'</span></a>';
        $_lupu .= '</li>';
    }
    $i++;
   
}
$_networklogos .= '</ul><input type="hidden" name="logonum" id="logonum" value="'.$i.'"><p style="margin-left:50px; margin-top:20px;"><button class="btn btn-medium btn-primary btn-submit" type="submit" name="savelogos" value="1" >Save Logos</button></p>';
$_lupu .= '</ul>';



// SAVE BUTTON - SET USER BULK STATIONS
if (isset($_POST['save']) && $_POST['save'] == '1') {
    
    $_out = "";
    $c = 0;
    foreach ($_POST['config'] as $key => $value) {
		$_SESSION[$key] = $value;
        $c++;
    }
    
    
    $filedest       = "/var/lib/mpd/music/RADIO";
    for ($x = 0; $x <= $c; $x++) {
        $s_name     = $_SESSION["userstation_".$x."_name"];
        $s_url      = $_SESSION["userstation_".$x."_url"];
        $s_content  = "[playlist]\nFile1=" . $s_url . "\nTitle1=" . $s_name . "\nLength1=-1\nNumberOfEntries=1\nVersion=2";

        
        // CHECK FOR DUPES
        $result     = $db->query('SELECT * FROM cfg_radio WHERE name="'.$s_name.'"');
        $s_match    = 0;
        while ($ir  = $result->fetchArray()) {
            if($s_name == $ir['name']){
                $s_match = 1;
            }
        }
        
        if($s_url != "" && $s_match == 0){
            $results    = $db->query('INSERT INTO cfg_radio (station,name,type,logo) VALUES ("'.$s_url.'", "'.$s_name.'", "u", "local")');
        } else {
            $results    = $db->query('UPDATE cfg_radio SET station="'.$s_url.'" WHERE name="'.$s_name.'" AND type="u"');
        }
        unset ($_SESSION["userstation_".$x."_name"]);
        unset ($_SESSION["userstation_".$x."_url"]);
        
        if($s_match < 2) {
            $s_file     = fopen($filedest . "/" . $s_name . ".pls", "w") or die("Unable to open file!");
            fwrite($s_file, $s_content);
            fclose($s_file);
        }
    }
    
    if($s_file) {
        $_usrmsg = "<strong>Success: Playlists regenerated in RADIO/_Stations</strong>";
        $_SESSION['notify']['title'] = 'Changes Saved';
    }
        
    shell_exec("mpc update");
    
}





// SECOND SAVE BUTTON - CHANGING RADIO NETWORK LOGOS
if (isset($_POST['savelogos']) && $_POST['savelogos'] == '1') {

    $n              = $_POST['logonum'];
    $webroot        = "/var/www/";
    $logopath       = "images/radio-logos/";
    $targetsmall    = $webroot . $logopath . "thumbs/";
    $targetlarge    = $webroot . $logopath;
    $targetwidth    = 200;
    $targetheight   = 200;
 
    
    
    
    
    // LOOP USERS LOGO CHOICES
    for ($i = 0; $i <= $n; $i++) {
        if($_FILES['logoupload'.$i]['size'] > 0){
            
            // sort out some naming
            $permname       = $_POST['logoname'.$i] . ".jpg";
            $tempfile       = "/tmp/" . $permname ;
            
            // keep the tempfile active
            copy($_FILES['logoupload'.$i]['tmp_name'], $tempfile);
            
            // copy the temp file as original size
            $runcmdlarge = "sudo cp -f /tmp/'" . $permname . "' " . $targetlarge . "'" . $permname . "'";
            shell_exec($runcmdlarge);
            
            
            
            // resize tempfile to thmbnail size
            $source_properties = getimagesize($tempfile);
            $image_resource_id = imagecreatefromjpeg($tempfile);  
            
            $target_layer=imagecreatetruecolor($targetwidth,$targetheight);
            imagecopyresampled($target_layer,$image_resource_id,0,0,0,0,$targetwidth,$targetheight, $source_properties[0],$source_properties[1]);
            imagejpeg($target_layer,"/tmp/" . $permname);
            
            // move the thumbnail
            $runcmdsmall = "sudo mv -f /tmp/'" . $permname . "' " . $targetsmall . "'" . $permname . "'";
            shell_exec($runcmdsmall);
        }
    }
    
        
    
    $_usrmsg = "<strong>Success: Network logos regenerated in RADIO</strong>";
	$_SESSION['notify']['title'] = 'Logos Saved';
    
    shell_exec("mpc update");
}



// IMPORT STATIONS
if (isset($_POST['import_stations'])) {
    shell_exec("sudo python /var/www/radio/sources/moode/scripts/user-import.py");
    $_SESSION['notify']['title'] = 'User Stations Imported';
}

// IMPORT STATIONS
if (isset($_POST['export_stations'])) {
    shell_exec("sudo python /var/www/radio/sources/moode/scripts/user-export.py");
    
    $_SESSION['notify']['title'] = 'User Stations Exported';
}

$exportname = '/var/www/radio/sources/moode/stations.zip';

if (file_exists($exportname)) {
    $_usrstationhtml = '<p><a href="/radio/sources/moode/stations.zip"><i class="fas fa-file-alt"></i>&nbsp; Download Your Export</a></p>';
} else {
    $_usrstationhtml = '<p><a href="/radio/sources/stations.zip"><i class="fas fa-file-alt"></i>&nbsp; Download Example Export</a></p>';
}




// TOGGLE MOODE DEFAULT STATIONS
if (isset($_POST['update_station_hide'])) {
	if (isset($_POST['station_hide'])) {
		$_SESSION['notify']['title'] = $_POST['station_split'] == '1' ? 'Moode stations actioned' : 'Moode stations actioned';
		$_SESSION['notify']['duration'] = 3;
        $_station_hide   = $_POST['station_hide'];
        $_SESSION['station_hide'] = $_station_hide;
        
        $_select['toggle_station_hide1'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide0\" value=\"1\" " . (($_POST['station_hide'] == '1') ? "checked=\"checked\"" : "") . ">\n";           
        $_select['toggle_station_hide0'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide1\" value=\"0\" " . (($_POST['station_hide'] == '0') ? "checked=\"checked\"" : "") . ">\n";
        
        
        
        // LOOK UP MOODE STATIONS IN DB ONLY ACTION THESE, LEAVE USER STATIONS ALONE
        $results = $db->query('SELECT * FROM cfg_radio WHERE type = "s"');
        
        
        if ($_POST['station_hide'] == '1') {
            while ($row = $results->fetchArray()) {
                
                if($row['logo'] == "local"){
                    
                    // with quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/'".$row['name'].".pls' /var/www/radio/sources/moode");

                    // with double quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/\"".$row['name'].".pls\" /var/www/radio/sources/moode");

                    // without
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/".$row['name'].".pls /var/www/radio/sources/moode");
                } else {
                    // BBC 320kb STATIONS ...
                    $name           = $row['logo'];
                    $name           = str_replace("images/radio-logos/", "", $name);
                    $name           = str_replace(".jpg", "", $name);
                    
                    // with quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/'".$name.".pls' /var/www/radio/sources/moode");

                    // with double quotes
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/\"".$name.".pls\" /var/www/radio/sources/moode");

                    // without
                    shell_exec("sudo sudo mv /var/lib/mpd/music/RADIO/".$name.".pls /var/www/radio/sources/moode");    
                }
                
                
                
            }
        } else {
            while ($row = $results->fetchArray()) {
                // Restoring much faster ...
                shell_exec("sudo sudo mv /var/www/radio/sources/moode/*.pls /var/lib/mpd/music/RADIO");
                
                
            }
        }
        shell_exec("mpc update");
        
	}
} else {
    $_select['toggle_station_hide1'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide0\" value=\"1\" " . (($_SESSION['station_hide'] == '1') ? "checked=\"checked\"" : "") . ">\n";          
    $_select['toggle_station_hide0'] = "<input type=\"radio\" name=\"station_hide\" id=\"toggle_station_hide1\" value=\"0\" " . (($_SESSION['station_hide'] == '0') ? "checked=\"checked\"" : "") . ">\n";
}



session_write_close();



waitWorker(1, 'rdo-config');

$tpl = "rdo-config.html";
$section = basename(__FILE__, '.php');
storeBackLink($section, $tpl);

include('/var/local/www/header.php');
eval("echoTemplate(\"" . getTemplate("templates/$tpl") . "\");");
include('footer.php');
