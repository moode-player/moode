<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * (C) 2021 Stephanowicz
 * https://github.com/Stephanowicz/moode/
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
 * 2021-MM-DD TC moOde 7.x.x
 *
 */

require_once dirname(__FILE__) . '/inc/getid3/getid3.php';
$file=shell_exec('mpc -f %file%|head -n 1');
if($file){
  $file=trim ($file);
  $path=shell_exec('sudo egrep \'^music_directory\' /etc/mpd.conf | grep -Po \'(?<=")[^"]*\'');
  $path=trim ($path);
  $path.='/'.$file;
  $img = getImage($path);
  if($img){
    echo json_encode($img);
  }
  else {
    echo "";
  }
  die;
}

function getImage($filename)
 {
     if (!file_exists($filename)) {
         return null;
     }
     $getID3 = new getID3();
     // Analyze file and store returned data in $file_info
     $file_info = $getID3->analyze($filename);
     // extract the album artwork
     $artwork = null;
     //flac ogg
     if (isset($file_info['comments']['picture'][0]['data'])) {
       foreach($file_info['comments']['picture'] as $value){
         if(isset($value['data'])){
           $artwork[] = 'data:'.$value['image_mime'].';charset=utf-8;base64,'.base64_encode($value['data']);
         }
       }
     }
     else {
       //mp3
       if (isset($file_info['id3v2']['APIC'][0]['data'])) {
         foreach($file_info['id3v2']['APIC'] as $value){
           if(isset($value['data'])){
             $artwork[] = 'data:'.$value['image_mime'].';charset=utf-8;base64,'.base64_encode($value['data']);
           }
         }
       }
     }
     return $artwork;
 }
 ?>
