#!/usr/bin/php
<?php
/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2026 The moOde audio player project / Tim Curtis
 * Copyright 2026 OliPi Project
 *
 * CLI helper for moOde EQ control
 * AI-assisted coding
 */

if (posix_getuid() != 0) {
    fwrite(STDERR, "This command requires sudo\n");
    exit(1);
}

require_once '/var/www/inc/common.php';
require_once '/var/www/inc/alsa.php';
require_once '/var/www/inc/audio.php';
require_once '/var/www/inc/eqp.php';
require_once '/var/www/inc/peripheral.php';
require_once '/var/www/inc/session.php';
require_once '/var/www/inc/sql.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (posix_getuid() != 0) {
    fwrite(STDERR, "This command requires sudo\n");
    return;
}

// --- Session ---
session_id(phpSession('get_sessionid'));
phpSession('open');

$dbh = sqlConnect();

function out($msg = '') {
    echo $msg . PHP_EOL;
}

function usage() {
    out('Usage:');
    out('  sudo eqctl.php status');
    out('  sudo eqctl.php graphic list|set <id|"name"|off>');
    out('  sudo eqctl.php parametric list|set <id|"name"|off>');
    exit(1);
}

function normalize_arg($s) {
    return trim((string)$s);
}

function dsp_protections($type) {
    // --- Multiroom ---
    if (($_SESSION['multiroom_tx'] ?? 'Off') !== 'Off' ||
        ($_SESSION['multiroom_rx'] ?? 'Off') === 'On') {
        return "DSP not allowed when multiroom is active";
    }
    // --- ALSA chain ---
    if (allowDspInAlsaChain() == false) {
        return "DSP not allowed in current ALSA chain";
    }
    // --- Peppy display ---
    if (($_SESSION['peppy_display'] ?? '0') === '1' &&
        ($_SESSION['alsa_output_mode'] ?? '') === 'plughw') {
        return 'When Peppy is on, ALSA output mode cannot be "Default"';
    }
    // --- Other DSP active ---
    if (($_SESSION['invert_polarity'] ?? '0') != '0') {
        return "Disable invert polarity first";
    }
    if (($_SESSION['crossfeed'] ?? 'Off') != 'Off') {
        return "Disable crossfeed first";
    }
    if (($_SESSION['camilladsp'] ?? 'off') != 'off') {
        return "Disable CamillaDSP first";
    }
    // --- EQ conflict ---
    if ($type === 'eqp12' && ($_SESSION['alsaequal'] ?? 'Off') !== 'Off') {
        return "Graphic EQ must be off first";
    }
    if ($type === 'alsaequal' && ($_SESSION['eqfa12p'] ?? 'Off') !== 'Off') {
        return "Parametric EQ must be off first";
    }
    return null;
}

// --- Parametric EQ ---
function resolve_eqp12_target($dbh, $arg) {
    $eqp12 = Eqp12($dbh);
    $presets = $eqp12->getPresets();

    $arg = normalize_arg($arg);
    if ($arg === '' || strtolower($arg) === 'off' || $arg === '0') return 0;
    if (ctype_digit($arg)) {
        $id = intval($arg);
        return array_key_exists($id, $presets) ? $id : null;
    }
    foreach ($presets as $id => $name) {
        if (strcasecmp($name, $arg) === 0) return intval($id);
    }
    return null;
}

function set_eqp12($dbh, $target) {
    $eqp12 = Eqp12($dbh);
    $presets = $eqp12->getPresets();
    $old = $eqp12->getActivePresetIndex();
    $new = $target;
    $name = $new == 0 ? 'Off' : ($presets[$new] ?? 'Unknown');

    if ($new === null) {
        out("ERR: unknown Parametric EQ preset");
        exit(2);
    }

    $reason = dsp_protections('eqp12');
    if ($new != 0 && $reason !== null) {
        out("ERR: " . $reason);
        exit(3);
    }

    if ($new == $old) {
        out("Graphic EQ already set to '$name'");
        return;
    }

    $eqp12->setActivePresetIndex($new);
    phpSession('write', 'eqfa12p', $new == 0 ? 'Off' : 'On');
    submitJob('eqfa12p', $old . ',' . $new);
    out("parametric|$new|$name");
}

function list_eqp12($dbh) {
    $eqp12 = Eqp12($dbh);
    $presets = $eqp12->getPresets();
    $active = $eqp12->getActivePresetIndex();
    out("0|Off|" . ($active == 0 ? "1" : "0"));
    foreach ($presets as $id => $name) {
        out($id . "|" . $name . "|" . ($active == $id ? "1" : "0"));
    }
}

// --- Graphic EQ ---
function resolve_alsaequal_target($dbh, $arg) {
    $arg = normalize_arg($arg);
    if ($arg === '' || strtolower($arg) === 'off' || $arg === '0') {
        return 'Off';
    }
    $rows = sqlQuery("SELECT id, curve_name FROM cfg_eqalsa ORDER BY id", $dbh);
    // --- ID lookup ---
    if (ctype_digit($arg)) {
        $id = intval($arg);
        foreach ($rows as $row) {
            if (intval($row['id']) === $id) {
                return $row['curve_name'];
            }
        }
        return null;
    }
    // --- Name lookup ---
    foreach ($rows as $row) {
        if (strcasecmp($row['curve_name'], $arg) === 0) {
            return $row['curve_name'];
        }
    }
    return null;
}

function set_alsaequal($dbh, $target) {
    $rows = sqlQuery("SELECT id, curve_name FROM cfg_eqalsa ORDER BY id", $dbh);
    $old = $_SESSION['alsaequal'] ?? 'Off';
    $new = $target;

    if ($new === null) {
        out("ERR: unknown alsaequal curve");
        exit(2);
    }

    $reason = dsp_protections('alsaequal');
    if ($new !== 'Off' && $reason !== null) {
        out("ERR: " . $reason);
        exit(3);
    }

    if ($new === $old) {
        out("Graphic EQ already set to '$old'");
        return;
    }

    phpSession('write', 'alsaequal', $new);
    submitJob('alsaequal', $old . ',' . $new);
    // --- Resolve ID ---
    $id = 0;
    if ($new !== 'Off') {
        foreach ($rows as $row) {
            if ($row['curve_name'] === $new) {
                $id = $row['id'];
                break;
            }
        }
    }
    out("graphic|$id|$new");
}

function list_alsaequal($dbh) {
    $rows = sqlQuery("SELECT id, curve_name FROM cfg_eqalsa ORDER BY id", $dbh);
    $active = $_SESSION['alsaequal'] ?? 'Off';
    out("0|Off|" . ($active === 'Off' ? "1" : "0"));
    foreach ($rows as $row) {
        out($row['id'] . "|" . $row['curve_name'] . "|" . ($active === $row['curve_name'] ? "1" : "0"));
    }
}

function status_all($dbh) {
    // Parametric EQ
    $eqp12 = Eqp12($dbh);
    $active_eqp = $eqp12->getActivePresetIndex();
    if ($active_eqp != 0) {
        $presets = $eqp12->getPresets();
        $name = $presets[$active_eqp] ?? 'Unknown';
        out("parametric|$active_eqp|$name");
        return;
    }

    // Graphic EQ
    $active_alsa = $_SESSION['alsaequal'] ?? 'Off';
    if ($active_alsa !== 'Off') {
        $rows = sqlQuery("SELECT id, curve_name FROM cfg_eqalsa ORDER BY id", $dbh);
        foreach ($rows as $row) {
            if ($row['curve_name'] === $active_alsa) {
                out("graphic|" . $row['id'] . "|" . $active_alsa);
                return;
            }
        }
    }

    // nothing actif
    out("none|0|Off");
}

// --- CLI dispatch ---
$mode = $argv[1] ?? '';
$cmd  = $argv[2] ?? '';
$arg  = $argv[3] ?? '';

if ($mode === 'status' && $cmd === '') {
    status_all($dbh);
    phpSession('close');
    exit(0);
}
if ($mode === '' || $cmd === '') usage();
if ($mode === 'parametric') {
    if ($cmd === 'list') list_eqp12($dbh);
    elseif ($cmd === 'set') set_eqp12($dbh, resolve_eqp12_target($dbh, $arg));
    else usage();
} elseif ($mode === 'graphic') {
    if ($cmd === 'list') list_alsaequal($dbh);
    elseif ($cmd === 'set') set_alsaequal($dbh, resolve_alsaequal_target($dbh, $arg));
    else usage();
} else usage();

phpSession('close');
