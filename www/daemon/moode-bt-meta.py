#!/usr/bin/env python3
import json, logging, os, subprocess, tempfile, threading
from collections import OrderedDict
import dbus, dbus.mainloop.glib
from gi.repository import GLib

BLUEZ_SERVICE, BLUEZ_ROOT = "org.bluez", "/"
PLAYER_INTERFACE, DEVICE_INTERFACE = "org.bluez.MediaPlayer1", "org.bluez.Device1"
PROPERTIES_INTERFACE, OBJECT_MANAGER_INTERFACE = "org.freedesktop.DBus.Properties", "org.freedesktop.DBus.ObjectManager"

META_PATH = "/var/www/images/bt_meta.json"
DEFAULT_COVER = "/images/default-radio-cover.jpg"
ACTIVE_STATUSES = {"playing", "paused", "forward-seek", "reverse-seek"}

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")

bus = None
players, device_alias_cache, artwork_cache = {}, {}, OrderedDict()
state_lock = threading.RLock()
artwork_slots = threading.BoundedSemaphore(2)

current_track_id = 0
last_render_signature = None
last_device_name = "Bluetooth Device"
active_artwork_key = None
fetching_keys = set()
render_timer = None
nudged_paths = set()

def clean_text(value):
    if value is None: return ""
    try: value = str(value).strip()
    except Exception: return ""
    if value.lower() in {"not provided", "unknown", "none", "null"}: return ""
    return value

def atomic_write_json(payload):
    directory = os.path.dirname(META_PATH)
    os.makedirs(directory, exist_ok=True)
    fd, temporary_path = tempfile.mkstemp(prefix=".bt_meta.", suffix=".tmp", dir=directory)
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as output:
            json.dump(payload, output, ensure_ascii=False, separators=(",", ":"))
            output.write("\n")
            output.flush()
            os.fsync(output.fileno())
        os.chmod(temporary_path, 0o644)
        os.replace(temporary_path, META_PATH)
    except Exception:
        try: os.unlink(temporary_path)
        except OSError: pass

def cache_artwork(key, url):
    with state_lock:
        artwork_cache[key] = url
        artwork_cache.move_to_end(key)
        while len(artwork_cache) > 128: artwork_cache.popitem(last=False)

def get_cached_artwork(key):
    with state_lock:
        url = artwork_cache.get(key)
        if url: artwork_cache.move_to_end(key)
        return url

def get_all_player_properties(path):
    try:
        obj = bus.get_object(BLUEZ_SERVICE, path)
        props = dbus.Interface(obj, PROPERTIES_INTERFACE)
        return dict(props.GetAll(PLAYER_INTERFACE))
    except Exception: return {}

def get_device_name(player_path, player_properties=None):
    global last_device_name
    properties = player_properties or {}
    device_path = properties.get("Device")
    if device_path: device_path = str(device_path)
    elif "/player" in player_path: device_path = player_path.rsplit("/", 1)[0]
    else: device_path = ""
    
    if not device_path: return last_device_name
    cached_name = device_alias_cache.get(device_path)
    if cached_name: return cached_name
    
    try:
        obj = bus.get_object(BLUEZ_SERVICE, device_path)
        props = dbus.Interface(obj, PROPERTIES_INTERFACE)
        alias = clean_text(props.Get(DEVICE_INTERFACE, "Alias"))
        if not alias: alias = clean_text(props.Get(DEVICE_INTERFACE, "Name"))
        if alias:
            device_alias_cache[device_path] = alias
            last_device_name = alias
            return alias
    except Exception: pass
    return last_device_name

def fetch_moode_artwork(artist, title):
    if not title and not artist: return DEFAULT_COVER
    search_title = f"{artist} - {title}" if artist and title else title or artist
    search_title_sql = search_title.replace("'", "''")
    query = f"SELECT cover_url FROM cfg_rcucache WHERE title = '{search_title_sql}'"
    
    try:
        result = subprocess.run(["moodeutl", "-q", query], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, timeout=5)
        cover_url = result.stdout.strip()
        if cover_url:
            if not (cover_url.startswith("http://") or cover_url.startswith("https://") or cover_url.startswith("/")): cover_url = "/" + cover_url
            return cover_url
    except Exception: pass

    radiocover_script = "/var/www/util/radiocover_plus.py"
    if os.path.isfile(radiocover_script):
        try:
            result = subprocess.run(["/usr/bin/python3", radiocover_script, "--title", search_title, "--station", ""], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, timeout=12)
            cover_url = result.stdout.strip()
            if cover_url:
                if not (cover_url.startswith("http://") or cover_url.startswith("https://") or cover_url.startswith("/")): cover_url = "/" + cover_url
                return cover_url
        except Exception: pass
    return DEFAULT_COVER

def background_art_fetch(artist, title, album, device, artwork_key):
    acquired = artwork_slots.acquire(timeout=20)
    try:
        if acquired:
            cover_url = fetch_moode_artwork(artist, title)
            cache_artwork(artwork_key, cover_url)
            with state_lock:
                if active_artwork_key == artwork_key:
                    payload = {"title": title, "artist": artist, "album": album, "cover_url": cover_url, "device": device}
                    atomic_write_json(payload)
    finally:
        if acquired:
            artwork_slots.release()
        with state_lock:
            fetching_keys.discard(artwork_key)

def update_meta(artist, title, album, status="playing", device=""):
    global current_track_id, last_device_name, last_render_signature, active_artwork_key
    artist, title, album, status, device = clean_text(artist), clean_text(title), clean_text(album), clean_text(status).lower() or "stopped", clean_text(device)
    
    if device: last_device_name = device
    else: device = last_device_name

    if status == "stopped" or (not title and not artist):
        signature = ("stopped" if status == "stopped" else "empty", device)
        payload = {"title": "Playback Stopped" if status == "stopped" else "No Track Playing", "artist": "", "album": "", "cover_url": DEFAULT_COVER, "device": device}
        with state_lock:
            active_artwork_key = None
            if signature == last_render_signature: return
            last_render_signature = signature
            atomic_write_json(payload)
        return

    signature = (status, artist, title, album, device)
    artwork_key = (artist.casefold(), title.casefold())
    
    with state_lock:
        active_artwork_key = artwork_key
        if signature == last_render_signature: return
        last_render_signature = signature
        cached_cover = get_cached_artwork(artwork_key)
        payload = {"title": title, "artist": artist, "album": album, "cover_url": cached_cover or DEFAULT_COVER, "device": device}
        atomic_write_json(payload)

    if cached_cover: return
    
    with state_lock:
        if artwork_key in fetching_keys: return
        fetching_keys.add(artwork_key)
        
    threading.Thread(target=background_art_fetch, args=(artist, title, album, device, artwork_key), daemon=True).start()

def player_status(properties): return clean_text(properties.get("Status")).lower()
def player_track(properties): return dict(properties.get("Track", {}))

def render_best_player():
    active = [(0 if player_status(p) == "playing" else 1, path, p) for path, p in players.items() if player_status(p) in ACTIVE_STATUSES]
    if not active:
        update_meta("", "", "", "stopped", last_device_name)
        return
        
    active.sort(key=lambda item: item[0])
    _, path, properties = active[0]
    track = player_track(properties)
    
    artist = clean_text(track.get("Artist", ""))
    title = clean_text(track.get("Title", ""))
    status = player_status(properties)
    
    if title:
        nudged_paths.discard(path)
    elif status == "playing":
        if path not in nudged_paths:
            nudged_paths.add(path)
            try:
                player_iface = dbus.Interface(bus.get_object(BLUEZ_SERVICE, path), PLAYER_INTERFACE)
                # The AVRCP Pulse: Rapidly Pause and Play to force Android to broadcast the missing metadata
                player_iface.Pause()
                def trigger_play():
                    try: player_iface.Play()
                    except Exception: pass
                    return False
                GLib.timeout_add(150, trigger_play)
            except Exception:
                pass
                
    update_meta(artist, title, clean_text(track.get("Album", "")), status, get_device_name(path, properties))

def execute_render():
    global render_timer
    render_timer = None
    render_best_player()
    return False

def queue_render():
    global render_timer
    if render_timer: GLib.source_remove(render_timer)
    render_timer = GLib.timeout_add(800, execute_render)

def on_property_changed(interface, changed, invalidated, path=None):
    if interface != PLAYER_INTERFACE or not path or not str(path).startswith("/org/bluez/"): return
    try:
        existing = players.setdefault(str(path), {})
        existing.update(dict(changed))
        for key in invalidated: existing.pop(str(key), None)
        if "Status" in changed and player_status(existing) in ACTIVE_STATUSES and "Track" not in existing:
            refreshed = get_all_player_properties(str(path))
            if refreshed: existing.update(refreshed)
        queue_render()
    except Exception: pass

def on_interfaces_added(path, interfaces):
    try:
        interfaces = dict(interfaces)
        if PLAYER_INTERFACE in interfaces:
            players[str(path)] = dict(interfaces[PLAYER_INTERFACE])
            queue_render()
    except Exception: pass

def on_interfaces_removed(path, interfaces):
    try:
        if PLAYER_INTERFACE in [str(item) for item in interfaces]:
            players.pop(str(path), None)
            nudged_paths.discard(str(path))
            queue_render()
    except Exception: pass

def scan_existing_players():
    try:
        manager = dbus.Interface(bus.get_object(BLUEZ_SERVICE, BLUEZ_ROOT), OBJECT_MANAGER_INTERFACE)
        discovered = {str(path): dict(dict(ifaces)[PLAYER_INTERFACE]) for path, ifaces in manager.GetManagedObjects().items() if PLAYER_INTERFACE in dict(ifaces)}
        players.clear()
        players.update(discovered)
        queue_render()
    except Exception:
        players.clear()
        update_meta("", "", "", "stopped", last_device_name)

def on_name_owner_changed(name, old_owner, new_owner):
    if str(name) != BLUEZ_SERVICE: return
    if new_owner: GLib.timeout_add(500, lambda: scan_existing_players() or False)
    else:
        players.clear()
        device_alias_cache.clear()
        nudged_paths.clear()
        update_meta("", "", "", "stopped", last_device_name)

def main():
    global bus
    dbus.mainloop.glib.DBusGMainLoop(set_as_default=True)
    bus = dbus.SystemBus()
    bus.add_signal_receiver(on_property_changed, signal_name="PropertiesChanged", dbus_interface=PROPERTIES_INTERFACE, path_keyword="path")
    bus.add_signal_receiver(on_interfaces_added, signal_name="InterfacesAdded", dbus_interface=OBJECT_MANAGER_INTERFACE)
    bus.add_signal_receiver(on_interfaces_removed, signal_name="InterfacesRemoved", dbus_interface=OBJECT_MANAGER_INTERFACE)
    bus.add_signal_receiver(on_name_owner_changed, signal_name="NameOwnerChanged", dbus_interface="org.freedesktop.DBus", bus_name="org.freedesktop.DBus", path="/org/freedesktop/DBus", arg0=BLUEZ_SERVICE)
    update_meta("", "", "", "stopped", last_device_name)
    scan_existing_players()
    GLib.timeout_add_seconds(5, lambda: scan_existing_players() or True)
    try: GLib.MainLoop().run()
    except KeyboardInterrupt: pass

if __name__ == "__main__": main()
