<html>
<head>
    <title>Simple Radio Player</title>
    <style>
        body {
            margin              : 0px;
            padding             : 0px;
            font-size           : 75%;
            font-family         : sans-serif;
            color               : #fff;
            background-color    : rgb(32,32,32);
        }
        
        .cmd-msg {
            opacity             : 0.4;
        }
    </style>
</head>
<body>
<?php


/** Radio-Play API
  *
  * Lee Jordan @duracell80
  * 12/26/2019

    

*/


$cmd        = $_GET["cmd"];
$src        = $_GET["src"];
$ch         = $_GET["ch"];
$play       = $_GET["play"];
$type       = $_GET["type"];





$apiPath    = "/var/www/radio";
$playPath   = "/var/lib/mpd/playlists";
$radioPath  = "/var/lib/mpd/music/RADIO";
$radioFile  = "Radio_Play";
$radioList  = "/var/lib/mpd/playlists/Radio_Play.m3u";






?> <div class="cmd-msg"><?php
    
        
        
    
    
    switch ($type) {
    case "cast":
        
        // EXAMPLE http://192.168.2.4/radio?type=cast&src=http://ice55.securenetsystems.net/DASH7
        $m3u_content  = "#EXTM3U\n";
        $m3u_content .= "#EXTINF:-1,Cast To Moode Audio\n";
        $m3u_content .= $src;
        
        shell_exec("sudo touch /var/lib/mpd/playlists/Radio_Play.m3u");
        shell_exec("sudo chmod 777 /var/lib/mpd/playlists/Radio_Play.m3u");
        file_put_contents($radioList, $m3u_content); 

        $runcmd = "mpc clear; mpc load Radio_Play"; shell_exec($runcmd);
        $runcmd = "mpc play";
        echo(shell_exec($runcmd));
        
        sleep(2);
        header("Location: /");
        
        
        break;
    
    case "tag":
        $runcmd = "sudo python " . $apiPath . "/sources/rb/rb-tags-preview.py " . $play;
        echo("Previewing radio tag: " . $play);
        shell_exec($runcmd);
        //header("Location: /"); 
        break;
    case "station":
        
        break;
    case "country":
        
        break;
    
    case "moode":
        
        
    case "mpc":
        if(isset($cmd) && !empty($cmd)){
            switch ($cmd) {

                // UPDATE DATABASE
                case "update":
                    $runcmd = "mpc update";
                    echo(shell_exec($runcmd));
                    break;

                // STATUS
                case "status":
                    $runcmd = "mpc status";
                    echo(shell_exec($runcmd));
                    break;

                // LIST Playlists
                case "list":
                    $runcmd = "mpc lsplaylists";
                    $list   = shell_exec($runcmd); 
                    $playlists = explode("\n", $list);

                    header('Content-type: text/javascript');
                    echo(pretty_json(json_encode($playlists)."\n"));

                    break;

                // STOP
                case "stop":
                    $runcmd = "mpc stop";
                    echo(shell_exec($runcmd));
                    break;

                // PLAY
                case "play":
                    $runcmd = "mpc play";
                    echo(shell_exec($runcmd));
                    break;

                // PAUSE
                case "pause":
                    $runcmd = "mpc pause-if-playing";
                    echo(shell_exec($runcmd));
                    break;

                // PREV
                case "prev":
                    $runcmd = "mpc prev";
                    echo(shell_exec($runcmd));
                    break;

                // NEXT
                case "next":
                    $runcmd = "mpc next";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP FORWARD 15s
                case "fwd15":
                    $runcmd = "mpc seek +15";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP FORWARD 30s
                case "fwd30":
                    $runcmd = "mpc seek +30";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP FORWARD 60s
                case "fwd60":
                    $runcmd = "mpc seek +60";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP FORWARD 5m
                case "fwd5m":
                    $runcmd = "mpc seek +300";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP BACK 15s
                case "bck15":
                    $runcmd = "mpc seek -15";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP BACK 30s
                case "bck30":
                    $runcmd = "mpc seek -30";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP BACK 60s
                case "bck60":
                    $runcmd = "mpc seek -60";
                    echo(shell_exec($runcmd));
                    break;

                // SKIP BACK 5m
                case "bck5m":
                    $runcmd = "mpc seek -300";
                    echo(shell_exec($runcmd));
                    break;




                default:
                    break;
            }
        }
        break;
        
    default:
        // LOOK UP MOODE STATION IN DB BY ID
        if ($ch) {
            $m3u_content    = "#EXTM3U\n";
            $stationfound   = 0;
            $db             = new SQLite3('/var/local/www/db/moode-sqlite3.db');
            $results        = $db->query('SELECT station,name FROM cfg_radio WHERE id =' . $ch);

            while ($row = $results->fetchArray()) {

                // Build the file
                $m3u_content .= "#EXTINF:-1," . $row['name'] . "\n";
                $m3u_content .= $row['station'];
                $stationfound = 1;
            }

            // PLAY IT AGAIN SAM ...
            shell_exec("sudo touch /var/lib/mpd/playlists/Radio_Play.m3u");
            shell_exec("sudo chmod 777 /var/lib/mpd/playlists/Radio_Play.m3u");
            file_put_contents($radioList, $m3u_content); 

            $runcmd = "mpc clear; mpc load Radio_Play"; shell_exec($runcmd);
            $runcmd = "mpc play";


            // SEND BROWSER TO PLAYER OR NOT IF IoT THEN ECHO COMMAND
            if($stationfound == 1){
                echo(shell_exec($runcmd));
                header("Location: /");    
            } else {
                echo("Error: Station ID Not Found");
            }

        } else {
            
    
            ?>
            <style>
                .mbox {
                    width : 50%; margin : 0 auto; border: 1px solid #fff;
                }
                
                .cmd-msg {
                    opacity             : 1 !important;
                }
                
                .ui-lineup {
                    
                    list-style-type : none;
                    height          : 175px;
                    padding         : 15px;
                    width           : 95%;
                    overflow-x      : hidden;
                    overflow-y      : auto;
                    
                }
                

                .ui-lineup li {
                    display         : inline-block;
                    margin          : 15px;
                }

                .ui-lineup li a {
                    display         : inline-block;
                    color           : #fff;
                    border          : 1px solid #fff;
                    padding         : 8px;
                    text-decoration : none;
                }
                .ui-lineup li a:hover {
                    text-decoration : none;
                }

                .ui-lineup .lup-id {
                    color           : #fff;
                    display         : inline-block;
                    margin-right    : 15px;
                    font-weight     : bold;

                }
                .ui-lineup .lup-hostname {
                    display         : none;
                }
                
                
                
                @media only screen and (max-width: 800px) {
                    .mbox {
                        width   : 100%; 
                        margin  : 0 auto;
                    }
                }
                
            </style>
            <div class="mbox">
                <div style="padding: 30px;">
                <h3>Cast to Moode</h3>
                <p>Send a Radio URL directly to moode from this page.</p>
                <form action="./" method="get">
                    <fieldset style="border-width:0px;">
                        <label style="display:inline-block; width : 10%; float:left;">URL: </label>
                        <input type="text" name="src" style="display:inline-block; width : 70%; float:left;">
                        <input type="submit" name="radioplay" id="radioplay" value="Play" style="display:inline-block; float:right; width:15%;">
                        <br style="clear:both;">
                        <input type="hidden" name="type" value="cast">
                    </fieldset>
                </form>
                </div>
            </div>
            <p>&nbsp;</p>
            
            <div class="mbox">
            <div style="padding: 15px;">
            <h3>Station Line Up</h3>
            <p>Drag and drop these to your desktop or browser bookmarks bar. Click or tap any one station to play that station on Moode now.</p>
            <?php
            
            
            // PLAY URL BOX and Station Lineups
    
            $db        = new SQLite3('/var/local/www/db/moode-sqlite3.db');
            $host      = gethostname();


            // BUILD MOODE STATION LINEUP
            $result    = $db->query('SELECT * FROM cfg_radio WHERE type = "s"');
            $lups      = '<ul class="ui-lineup">';

            while ($lup = $result->fetchArray()) {
                if($lup['id'] < 499){
                    $lups .= '<li>';
                    $lups .= '<a href="http://'.$host.'/radio?ch='.$lup['id'].'" target="_blank"><span class="lup-id">' . $lup['id'] . '</span>' . '&nbsp;<span class="lup-name">' . $lup['name'] . '<span>&nbsp;<span class="lup-hostname">on '.$host.'</span></a>';
                    $lups .= '</li>';
                }
            }

            $lups .= '</ul>';
            
            echo $lups;
            ?></div></div>
            <p>&nbsp;</p>
            
            <div class="mbox">
            <div style="padding: 15px;">
            <h3>User Station Line Up</h3>
            <p>Drag and drop these to your desktop or browser bookmarks bar. Click or tap any one station to play that station on Moode now.</p>
            <?php
            
            
            // PLAY URL BOX and Station Lineups
            // BUILD USER STATION LINEUP
            $result    = $db->query('SELECT * FROM cfg_radio WHERE type = "u"');
            $lups      = '<ul class="ui-lineup">';

            while ($lup = $result->fetchArray()) {
                if($lup['id'] > 499){
                    $lups .= '<li>';
                    $lups .= '<a href="http://'.$host.'/radio?ch='.$lup['id'].'" target="_blank"><span class="lup-id">' . $lup['id'] . '</span>' . '&nbsp;<span class="lup-name">' . $lup['name'] . '<span>&nbsp;<span class="lup-hostname">on '.$host.'</span></a>';
                    $lups .= '</li>';
                }
            }

            $lups .= '</ul>';
            
            echo $lups;
            ?></div></div>
            <p>&nbsp;</p>
            <?php
    
            
            
        }
       break;
}
?></div><?php


















?>
</body>
</html>
