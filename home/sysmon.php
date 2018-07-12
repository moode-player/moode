#!/usr/bin/php
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
 * 2018-07-11 TC moOde 4.2
 *
 */

while(true) {
	// cpu frequency
	$cpufreq = file_get_contents('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq');	
	if ($cpufreq < 1000000) {
        $cpufreq = number_format($cpufreq / 1000, 0, '.', '');
        $cpufreq .= ' MHz';
	}
	else {
        $cpufreq = number_format($cpufreq / 1000000, 1, '.', '');
        $cpufreq .= ' GHz';
	}

	// cpu temp
	$cputemp = substr(file_get_contents('/sys/class/thermal/thermal_zone0/temp'), 0, 2);

	// cpu utilization
	$cpuload = exec("top -bn 2 -d 0.75 | grep 'Cpu(s)' | tail -n 1 | awk '{print $2 + $4 + $6}'");
	$cpuload = number_format($cpuload,0,'.','');

	// memory utilization
	$memtotal = exec("grep MemTotal /proc/meminfo | awk '{print $2}'");
	$memavail = exec("grep MemAvailable /proc/meminfo | awk '{print $2}'");
	$memutil = number_format(100 * (1 - ($memavail / $memtotal)), 0, '.', '');

	// number of cores	
	$cores = trim(exec('grep -c ^processor /proc/cpuinfo'));
	
	// cpu architecture
	$sysarch = trim(shell_exec('uname -m'));
	
	echo 'CPU: ' . $cpufreq . ' | LOAD: ' . $cpuload . '%' . ' | TEMP: ' . $cputemp . "\xB0" . 'C | RAM_USED: ' . $memutil . '%' . ' | CORES: ' . $cores . ' | ARCH: ' . $sysarch . "\r";
}
