<?php 
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * keyboard.php (C) 2016 Richard Parslow 
 * 2016-08-28 RP initial version 
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
 * 2019-04-12 TC moOde 5.0
 *
 */ 
  
function buildKeyboardSelect($selected) { 
    $keyboard_list = array( 
"us", 
"gb", 
"af", 
"ara", 
"al", 
"am", 
"at", 
"az", 
"ba", 
"bd", 
"be", 
"bg", 
"br", 
"brai", 
"bt", 
"bw", 
"by", 
"cm", 
"ca", 
"cd", 
"ch", 
"cn", 
"cz", 
"dk", 
"ee", 
"epo", 
"hr", 
"in", 
"ir", 
"iq", 
"fo", 
"fi", 
"fr", 
"gh", 
"gn", 
"ge", 
"de", 
"es", 
"et", 
"gr", 
"hu", 
"ie", 
"is", 
"il", 
"it", 
"jp", 
"ke", 
"kg", 
"kh", 
"kr", 
"kz", 
"la", 
"latam", 
"lk", 
"lt", 
"lv", 
"ma", 
"mao", 
"md", 
"me", 
"mk", 
"ml", 
"mm", 
"mn", 
"mt", 
"mv", 
"ng", 
"no", 
"nl", 
"np", 
"pk", 
"ph", 
"pl", 
"pt", 
"ro", 
"ru", 
"rs", 
"si", 
"sk", 
"se", 
"sn", 
"sy", 
"tj", 
"th", 
"tm", 
"tr", 
"tw", 
"tz", 
"ua", 
"uz", 
"vn", 
"za" 
    ); 

    $res = ''; 
    foreach ($keyboard_list as $kb) { 
        $sel = ($selected == $kb) ? ' selected' : ''; 
        $res .= sprintf("<option value='%s'%s>%s</option>\n", $kb, $sel, $kb); 
    } 

    return $res; 
}

function buildKvariantSelect($selected) { 
    $kvariant_list = array( 
"Afghani", 
"Amharic", 
"Arabic", 
"Arabic (Morocco)", 
"Arabic (Syria)", 
"Albanian", 
"Armenian", 
"Azerbaijani", 
"Bambara", 
"Belarusian", 
"Belgian", 
"Bangla", 
"Bosnian", 
"Braille", 
"Bulgarian", 
"Burmese", 
"Chinese", 
"Croatian", 
"Czech", 
"Danish", 
"Dhivehi", 
"Dutch", 
"Dzongkha", 
"English (Cameroon)", 
"English (Ghana)", 
"French (Guinea)", 
"English (Nigeria)", 
"English (South Africa)", 
"English (UK)", 
"English (US)", 
"Esperanto", 
"Estonian", 
"Faroese", 
"Finnish", 
"Filipino", 
"French", 
"French (Canada)", 
"French (Democratic Republic of the Congo)", 
"Georgian", 
"German", 
"German (Austria)", 
"German (Switzerland)", 
"Greek", 
"Hebrew", 
"Hungarian", 
"Icelandic", 
"Indian", 
"Iraqi", 
"Irish", 
"Italian", 
"Japanese", 
"Kazakh", 
"Khmer (Cambodia)", 
"Korean", 
"Kyrgyz", 
"Lao", 
"Lithuanian", 
"Latvian", 
"Maori", 
"Montenegrin", 
"Macedonian", 
"Maltese", 
"Moldavian", 
"Mongolian", 
"Nepali", 
"Norwegian", 
"Persian", 
"Polish", 
"Portuguese", 
"Portuguese (Brazil)", 
"Romanian", 
"Russian", 
"Serbian", 
"Slovenian", 
"Slovak", 
"Spanish", 
"Spanish (Latin American)", 
"Swahili (Tanzania)", 
"Swahili (Kenya)", 
"Swedish", 
"Sinhala (phonetic)", 
"Tajik", 
"Thai", 
"Turkish", 
"Taiwanese", 
"Tswana", 
"Turkmen", 
"Ukrainian", 
"Uzbek", 
"Urdu (Pakistan)", 
"Vietnamese", 
"Wolof" 
    ); 

    $res = ''; 
    foreach ($kvariant_list as $kv) { 
        $sel = ($selected == $kv) ? ' selected' : ''; 
        $res .= sprintf("<option value='%s'%s>%s</option>\n", $kv, $sel, $kv); 
    } 

    return $res; 
}
