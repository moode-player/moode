#!/usr/bin/python3
#
# SPDX-License-Identifier: MIT
# Copyright 2026 The moOde-radio-plus (MR+) project
# - Ivo Scagliola: co-author, design, development and final testing
# - Marco Mosca:   co-author, design, development and testing
# - https://github.com/frantale70-lgtm/moOde-radio-plus
# Copyright 2026 The moOde audio player project / Tim Curtis
# - Modifications to support integration into moOde as a CLI utility
#
"""
Radio Cover+ - CLI utility

This is a modified version of the daemon script "moode_sse_server.py" from the GitHub
project named "moOde-radio-plus (MR+)". It functions as a foreground CLI utility that
can be called from within the existing MPD idle timeout process that Moode maintains.
This allows for seemless integration including support for the "Metadata file" option.

This version maintains the advanced parsing, weighted analysis and parallel search
from the daemon version.

1. Three-level Noise Gate
2. Two-stage Metadata Cleaning
3. Parallel Search across 7 Providers
4. Weighted Scoring by Release Type
5. Dual Deadline + Early Stop
6. Best Resolution Selection

Whats different:
- Not needed			SSE, MPD, Web, Event, related code/libs, segment covers, etc
- Input args			--title --station [--version]
- Settings				/etc/radiocover-plus/config.txt
- Log					/var/log/moode_radiocover_plus.log
- similarity()			Added test for squashed artist name ex: BistroBoy vs Bistro Boy
- search_itunes			Limit = 10, 1000x1000 res
- search_radio_paradise	Use moode station names (Main Mix, World)
- Formatting			Convert to hard tabs

"""
import os, sys, time, logging, argparse, signal
import requests, re, unicodedata, html, json
from PIL import ImageFile
from threading import Thread, Lock, Event
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor, wait, FIRST_COMPLETED
from logging.handlers import RotatingFileHandler

__version__ = "9.1.1 CLI utility"

# ================= CONFIG GLOBALS (fallback) =================
SPOTIFY_CLIENT_ID		= None
SPOTIFY_CLIENT_SECRET	= None
LASTFM_API_KEY			= None
DISCOGS_TOKEN			= None
THEAUDIODB_API_KEY		= "2"

#DEBOUNCE_MS		= 0.8
REQUEST_TIMEOUT	= 10.0
MB_TIMEOUT		= (0.5, 1.5)

MAX_SIZE_PX				= 800
COVER_QUALITY			= 85
MIN_SIMILARITY			= 0.75
MIN_SIMILARITY_ITUNES	= 0.90

FAST_DEADLINE_S  = 1.5
TOTAL_DEADLINE_S = 2.5
EARLY_STOP_SCORE = 5.0

CACHE_ENABLED			= False
PROVIDERS_LIST			= {}

# Noise (non-music) segments
SEGMENT_WEATHER		= "weather"
SEGMENT_TRAFFIC		= "traffic"
SEGMENT_NEWS		= "news"
SEGMENT_ADVERTISING	= "advertising"
SEGMENT_FUNDRAISING	= "fundraising"

# ================= PATHS =================
CONFIG_FILE		= "/etc/radiocover-plus/config.txt"
LOG_FILE		= "/var/log/moode_radiocover_plus.log"
MAX_LOG_SIZE	= 512 * 1024
BACKUP_COUNT	= 0

LOG_LEVEL_MAP = {
	"DEBUG":	logging.DEBUG,
	"INFO":		logging.INFO,
	"WARNING":	logging.WARNING,
	"ERROR":	logging.ERROR,
	"CRITICAL":	logging.CRITICAL,
}
LOG_LEVEL = "INFO"

# ================= GLOBALS =================
SPOTIFY_TOKEN			= None
SPOTIFY_TOKEN_EXPIRY	= 0
_shutdown_event			= Event()

# ================= LOGGING =================
handler = RotatingFileHandler(LOG_FILE, maxBytes=MAX_LOG_SIZE, backupCount=BACKUP_COUNT)
logging.basicConfig(
	level=logging.INFO,
	format='%(asctime)s [%(levelname)s] %(message)s',
	handlers=[handler]
)

# ================= CONFIG =================
ALL_LRU_CACHES = []

def read_global():
	global SPOTIFY_CLIENT_ID, SPOTIFY_CLIENT_SECRET, LASTFM_API_KEY, DISCOGS_TOKEN
	global THEAUDIODB_API_KEY, LOG_LEVEL
	#global CACHE_ENABLED
	#global DEBOUNCE_MS
	global REQUEST_TIMEOUT, MAX_SIZE_PX, COVER_QUALITY
	global MIN_SIMILARITY, MIN_SIMILARITY_ITUNES
	global FAST_DEADLINE_S, TOTAL_DEADLINE_S, EARLY_STOP_SCORE
	global PROVIDERS_LIST

	valid_levels = {"DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"}
	values = {}

	global CONFIG_FILE
	if not os.path.isfile(CONFIG_FILE):
		logging.error(f"[read_global] ❌ Config file missing: {CONFIG_FILE}")
		return

	try:
		with open(CONFIG_FILE, "r") as f:
			for lineno, line in enumerate(f, 1):
				line = line.strip()
				if not line or line.startswith("#"):
					continue
				if "=" not in line:
					logging.warning(f"[read_global] ❌ Invalid line {lineno}: {line}")
					continue
				key, value = line.split("=", 1)
				values[key.strip()] = value.strip() or None
	except Exception as e:
		logging.error(f"[read_global] ❌ Error reading config: {e}")
		return

	def load_token(name):
		val = values.get(name)
		if not val:
			logging.error(f"[read_global] ❌ Missing or empty token: {name}")
		return val

	def load_float(key, fallback):
		try:
			val = values.get(key)
			if val is not None:
				val = float(val)
				if val < 0: raise ValueError
				return val
		except ValueError:
			logging.error(f"[read_global] ❌ Invalid {key}, fallback={fallback}")
		return fallback

	def load_int(key, fallback):
		try:
			val = values.get(key)
			if val is not None:
				val = int(val)
				if val < 0: raise ValueError
				return val
		except ValueError:
			logging.error(f"[read_global] ❌ Invalid {key}, fallback={fallback}")
		return fallback

	SPOTIFY_CLIENT_ID		= load_token("SPOTIFY_CLIENT_ID")
	SPOTIFY_CLIENT_SECRET	= load_token("SPOTIFY_CLIENT_SECRET")
	LASTFM_API_KEY			= load_token("LASTFM_API_KEY")
	DISCOGS_TOKEN			= load_token("DISCOGS_TOKEN")
	THEAUDIODB_API_KEY		= values.get("THEAUDIODB_API_KEY", "2") or "2"

	level = values.get("LOG_LEVEL", "").upper()
	LOG_LEVEL = level if level in valid_levels else "INFO"
	logging.getLogger().setLevel(LOG_LEVEL_MAP[LOG_LEVEL])

	#CACHE_ENABLED			= values.get("CACHE_ENABLED", "").lower() in ("1","true","yes","on")

	#DEBOUNCE_MS				= load_float("DEBOUNCE_MS",				0.8)
	REQUEST_TIMEOUT			= load_float("REQUEST_TIMEOUT",			10.0)
	MIN_SIMILARITY			= load_float("MIN_SIMILARITY",			0.75)
	MIN_SIMILARITY_ITUNES	= load_float("MIN_SIMILARITY_ITUNES",	0.90)
	FAST_DEADLINE_S			= load_float("FAST_DEADLINE_S",			1.5)
	TOTAL_DEADLINE_S		= load_float("TOTAL_DEADLINE_S",		2.5)
	EARLY_STOP_SCORE		= load_float("EARLY_STOP_SCORE",		5.0)
	MAX_SIZE_PX				= load_int("MAX_SIZE_PX",				800)
	COVER_QUALITY			= load_int("COVER_QUALITY",				85)

	PROVIDER_NAMES = ["Spotify","iTunes","Deezer","LastFM","MusicBrainz","Discogs","TheAudioDB"]
	PROVIDERS_LIST = {
		n: values.get(n, "False").lower() in ("1","true","yes","on")
		for n in PROVIDER_NAMES
	}

def log_config_summary():
	def mask(v):
		if not v: return "None"
		return v[:3] + "***" + v[-3:] if len(v) > 6 else "***"
	logging.error("[config] ========== Configuration summary ==========")
	logging.error(f"[config] LOG_LEVEL              = {LOG_LEVEL}")
	logging.error(f"[config] SPOTIFY_CLIENT_ID      = {mask(SPOTIFY_CLIENT_ID)}")
	logging.error(f"[config] SPOTIFY_CLIENT_SECRET  = {mask(SPOTIFY_CLIENT_SECRET)}")
	logging.error(f"[config] LASTFM_API_KEY         = {mask(LASTFM_API_KEY)}")
	logging.error(f"[config] DISCOGS_TOKEN          = {mask(DISCOGS_TOKEN)}")
	logging.error(f"[config] THEAUDIODB_API_KEY     = {THEAUDIODB_API_KEY}")
	#logging.error(f"[config] DEBOUNCE_MS            = {DEBOUNCE_MS}")
	logging.error(f"[config] REQUEST_TIMEOUT        = {REQUEST_TIMEOUT}")
	logging.error(f"[config] FAST_DEADLINE_S        = {FAST_DEADLINE_S}")
	logging.error(f"[config] TOTAL_DEADLINE_S       = {TOTAL_DEADLINE_S}")
	logging.error(f"[config] EARLY_STOP_SCORE       = {EARLY_STOP_SCORE}")
	logging.error(f"[config] MIN_SIMILARITY         = {MIN_SIMILARITY}")
	logging.error(f"[config] MIN_SIMILARITY_ITUNES  = {MIN_SIMILARITY_ITUNES}")
	#logging.error(f"[config] CACHE_ENABLED          = {CACHE_ENABLED}")
	enabled = [n for n, v in PROVIDERS_LIST.items() if v]
	logging.error(f"[config] Providers enabled      = {enabled}")
	logging.error("[config] ===========================================")

def reload_config(signum=None, frame=None):
	logging.error("[reload_config] 🔄 Reloading configuration (SIGHUP)")
	read_global()
	for c in ALL_LRU_CACHES:
		info = c.cache_info()
		logging.warning(f"[reload_config] Clearing cache size={info.currsize} hits={info.hits}")
		c.cache_clear()
	logging.warning("[reload_config] 🧹 All LRU caches cleared")
	log_config_summary()

def graceful_exit(signum, frame):
	logging.info("[graceful_exit] 🛑 STOP received")
	_shutdown_event.set()
	sys.exit(0)

signal.signal(signal.SIGTERM, graceful_exit)
signal.signal(signal.SIGINT,  graceful_exit)
signal.signal(signal.SIGHUP,  reload_config)

# ================= QUERY UTILITY =================
MOJIBAKE_PATTERN = re.compile(
	r"""
		Ã[\x80-\xBF]
	  | Â[\x80-\xBF]
	  | â[\x80-\xBF]{2}
	  | ð[\x80-\xBF]{3}
	  | \ufffd
	""",
	re.VERBOSE,
)

def fix_mojibake_if_needed(text):
	if not MOJIBAKE_PATTERN.search(text):
		return text
	try:
		repaired = text.encode("latin1", "strict").decode("utf-8", "strict")
		before = len(MOJIBAKE_PATTERN.findall(text))
		after  = len(MOJIBAKE_PATTERN.findall(repaired))
		if after < before:
			return repaired
	except UnicodeError:
		pass
	return text

def normalize_id(text):
	"""Normalize for hashing/comparison: HTML unescape, NFKC, mojibake fix."""
	if not text: return ""
	text = html.unescape(text)
	text = unicodedata.normalize("NFKC", text)
	if MOJIBAKE_PATTERN.search(text):
		text = fix_mojibake_if_needed(text)
	text = unicodedata.normalize("NFKC", text)
	text = re.sub(r"\s+", " ", text).strip()
	return text

def split_artist_title(full_title):
	if not full_title: return "", ""
	m = re.match(r'^(.*?)\s-\s(.*)$', full_title)
	if m: return m.group(1).strip(), m.group(2).strip()
	if "~" in full_title:
		parts = [p.strip() for p in full_title.split("~") if p.strip()]
		if len(parts) >= 2:
			return parts[0], parts[1]
	return "", full_title.strip()

def sanitize(text):
	"""Light cleanup: & → and."""
	if not text: return ""
	text = unicodedata.normalize('NFKC', text)
	text = re.sub(r"\s&\s",  " and ", text)
	text = re.sub(r"\s\+\s", " and ", text)
	text = re.sub(r"[\/\:;\|\~\+]+", " ", text)
	text = re.sub(r"\s{2,}", " ", text)
	text = text.replace("Revisted", "Revisited")
	return text.strip()

def similarity(a, b):
	a_s, b_s = sanitize(a.lower()), sanitize(b.lower())
	if not a_s or not b_s: return 0.0
	if a_s.replace(" ", "") == b_s.replace(" ", ""): return 1.0
	a_set, b_set = set(a_s.split()), set(b_s.split())
	inter = len(a_set & b_set)
	union = len(a_set | b_set)
	similarity = inter / union if union else 0.0
	return similarity

def prepare_text_for_query(text):
	"""Aggressive cleanup: NFKD + ASCII transliteration."""
	if not text: return ""
	text = unicodedata.normalize('NFKD', text)
	text = text.encode('ascii', 'ignore').decode()
	text = re.sub(r'\s*&\s*', ' and ', text)
	text = re.sub(r'\s*[\(\[].*?[\)\]]', '', text)
	text = re.sub(r'[\/:;|~+]', ' ', text)
	text = re.sub(r'\b(deluxe|remaster|edition|version|single)\b', '', text, flags=re.I)
	text = re.sub(r'\s{2,}', ' ', text)
	text = text.replace('"', '').replace("'", "'").strip()
	return text.strip()

def prepare_query(artist, title, album=None):
	artist_q = prepare_text_for_query(artist)
	title_q  = prepare_text_for_query(title)
	query = f'artist:"{artist_q}" track:"{title_q}"'
	if album:
		album_q = prepare_text_for_query(album)
		if album_q:
			query += f' album:"{album_q}"'
	return query

def prepare_free_term(artist, title, album=None):
	parts = [prepare_text_for_query(x) for x in [artist, title, album] if x]
	return " ".join(parts)

def normalize_for_search(string):
	"""Light cleanup for attempt 1: removes feat/with."""
	t = string
	t = re.sub(r"\(\s*(feat\.?|ft\.?|featuring|w/|with|voc\.?|voice)\s+[^)]*\)", "", t, flags=re.IGNORECASE)
	t = re.sub(r"[\-,]?\s*(feat\.?|ft\.?|featuring|w/|voc\.?|voice)\s+.*$", "", t, flags=re.IGNORECASE)
	t = re.sub(r"\s{2,}", " ", t)
	return t.strip()

def clean_artist_name(artist):
	"""Aggressive artist cleanup for attempt 2."""
	if not artist: return ""
	raw = artist
	cleaned = re.sub(r'(?:www\.|[\w-]+\.(?:com|net|org|io|fm|it)|[\w\s]+:)\s*', '', artist, flags=re.IGNORECASE)
	if ':' in cleaned and len(cleaned.split(':')[0]) < 25:
		cleaned = cleaned.split(':')[-1]
	if ',' in cleaned:
		cleaned = cleaned.split(',')[0]
	cleaned = re.sub(r'\s*[\(\[].*?[\)\]]', '', cleaned).strip()
	if "~" in cleaned: cleaned = cleaned.split("~")[0]
	cleaned = cleaned.replace('*', ' ').strip()
	cleaned = re.sub(r'\b(remix|compilation|dj mix|original mix)\b', '', cleaned, flags=re.IGNORECASE)
	cleaned = re.sub(r'\s+(feat\.?|ft\.?|featuring|with|starring)\s+.*', '', cleaned, flags=re.IGNORECASE)
	res = cleaned.strip()
	if raw != res:
		logging.info(f"[clean_artist_name] 🧹 '{raw}' → '{res}'")
	return res

def normalize_title(title, artist=None):
	"""Aggressive title cleanup for attempt 2. Strips 'Artist - ' prefix if artist is provided."""
	if not title: return ""
	raw = title

	# Strip artist prefix if present
	if artist:
		prefix = f"{artist} - "
		if title.lower().startswith(prefix.lower()):
			title = title[len(prefix):]

	if "~" in title: title = title.split("~")[0]
	if "/" in title: title = title.split("/")[0]
	if "*" in title: title = title.replace("*", " ")
	title = re.sub(r'(?:www\.[a-z0-9\.]+|[a-z0-9\.]+\.ch)\s*', '', title, flags=re.IGNORECASE).strip()

	title = re.sub(r'\s*[\(\[].*?[\)\]]', '', title).strip()
	title = re.sub(r'[\s\-\.]+$', '', title).strip()
	res = title.strip()
	if raw != res:
		logging.info(f"[normalize_title] 🧹 '{raw}' → '{res}'")
	return res

# ================= NOISE / SEGMENT GATE =================
def is_noise(raw_title, station_name):
	"""
	Returns (True, segment_type) if noise with identified segment.
	Returns (True, None) if pure noise.
	Returns (False, None) if valid track.

	Segment keywords (weather/traffic/news/advertising) are checked
	only in the part of the title after the ' - ' separator,
	to avoid false positives when the station name contains
	words like 'sport' or 'news' (e.g. 'RADIO MANA SPORT').
	"""
	if not raw_title or len(raw_title.strip()) < 3:
		return True, None  # Vacuum gate

	ti = raw_title.lower().strip()
	st = station_name.lower().strip() if station_name else ""

	# Isolated AD block
	if ti == "ad" or ti.endswith(" ad"):
		return True, None

	# Station name equals title exactly
	if st == ti:
		return True, None

	# Segment keywords checked only in the part after ' - '
	if " - " in ti:
		segment_part = ti.split(" - ", 1)[1].strip()
	elif ti.startswith("- "):
		segment_part = ti[2:].strip()
	else:
		segment_part = ""

	if segment_part:
		weather_keywords = ["meteo", "weather", "wetter", "météo", "previsioni"]
		if any(w in segment_part for w in weather_keywords):
			return True, SEGMENT_WEATHER

		traffic_keywords = ["traffico", "traffic", "verkehr", "trafic", "viabilità", "viabilita"]
		if any(w in segment_part for w in traffic_keywords):
			return True, SEGMENT_TRAFFIC

		news_keywords = ["news", "notizie", "nachrichten", "actualités", "noticias", "nieuws"]
		if any(w in segment_part for w in news_keywords) or re.search(r'\bsport\b', segment_part):
			return True, SEGMENT_NEWS

		advertising_keywords = [
			"adbreak", "ad break", "advert", "advertising", "advertisement",
			"live365 - advertisement",
			"pubblicita", "pubblicità", "werbung", "publicité",
			"publicidad", "reklame", "reclame", "spot",
			"adbreak_end"
		]
		if any(w in segment_part for w in advertising_keywords):
			return True, SEGMENT_ADVERTISING

		fundraising_keywords = ["fund raising", "fund raiser"]
		if any(w in segment_part for w in fundraising_keywords):
			return True, SEGMENT_FUNDRAISING

	# Check weather/traffic/fundraising in full title (no separator)
	if any(w in ti for w in ["meteo", "weather", "wetter", "météo", "previsioni"]):
		return True, SEGMENT_WEATHER
	if any(w in ti for w in ["traffico", "traffic", "verkehr", "trafic", "viabilità", "viabilita"]):
		return True, SEGMENT_TRAFFIC
	if any(w in ti for w in ["adbreak", "ad break", "adbreak_end", "advert", "advertising"]):
		return True, SEGMENT_ADVERTISING
	if any(w in ti for w in ["fund raising", "fund raiser", "pledge drive"]):
		return True, SEGMENT_FUNDRAISING

	# Title too similar to station name
	st_parts = st.split()
	if st_parts:
		matches = sum(1 for p in st_parts if p in ti)
		if matches >= len(st_parts) * 0.6:
			return True, None

	# Pure noise without dedicated cover
	# Artist repeated in title with year → station metadata noise
	year_match = re.match(r'^(.+)\s-\s(\d{4})$', raw_title)
	if year_match and year_match.group(1).strip().lower() == ti.split(' - ')[0].strip().lower():
		logging.info(f"[DEBUG year_match] MATCHED: raw='{raw_title}' group1='{year_match.group(1).strip().lower()}' ti_part='{ti.split(' - ')[0].strip().lower()}'")
		return True, None
	noise_pure = ["jingle", "promo", "stationid", "oroscopo", "bollettino"]
	if "~~~" in ti:
		return True, None
	if any(ti.startswith(w) for w in noise_pure):
		return True, None

	return False, None

# ================= IMAGE RESOLUTION =================
def get_image_resolution(url, mode="regex"):
	if mode == "regex":
		try:
			m = re.search(r'(\d+)x(\d+)', url)
			if m: return int(m.group(1)), int(m.group(2))
		except Exception:
			pass
		return 0, 0

	if mode == "head":
		try:
			r = requests.head(url, timeout=min(REQUEST_TIMEOUT, 3))
			r.raise_for_status()
			size_bytes = int(r.headers.get("Content-Length", 0))
			if size_bytes > 0:
				est = max(1, int(size_bytes ** 0.5))
				return est, est
		except Exception:
			pass
		return 0, 0

	if mode == "pil":
		try:
			parser = ImageFile.Parser()
			r = requests.get(url, stream=True, timeout=min(REQUEST_TIMEOUT, 3))
			r.raise_for_status()
			for chunk in r.iter_content(1024):
				if not chunk or parser.image: break
				parser.feed(chunk)
			if parser.image: return parser.image.size
		except Exception:
			pass
		return 0, 0

	return 0, 0

# ================= PROVIDER SEARCH FUNCTIONS =================
def get_spotify_token():
	global SPOTIFY_TOKEN, SPOTIFY_TOKEN_EXPIRY
	if time.time() < SPOTIFY_TOKEN_EXPIRY and SPOTIFY_TOKEN:
		return SPOTIFY_TOKEN
	try:
		r = requests.post(
			"https://accounts.spotify.com/api/token",
			data={"grant_type": "client_credentials",
				  "client_id": SPOTIFY_CLIENT_ID,
				  "client_secret": SPOTIFY_CLIENT_SECRET},
			timeout=REQUEST_TIMEOUT
		)
		r.raise_for_status()
		data = r.json()
		SPOTIFY_TOKEN		= data["access_token"]
		SPOTIFY_TOKEN_EXPIRY = time.time() + data["expires_in"] - 300
		return SPOTIFY_TOKEN
	except Exception:
		return None

def search_spotify(artist, title, album=None):
	t = get_spotify_token()
	if not t: return None, None, None

	try:
		q = prepare_query(artist, title, album)
		r = requests.get(
			"https://api.spotify.com/v1/search",
			headers={"Authorization": f"Bearer {t}"},
			params={"q": q, "type": "track", "limit": 5},
			timeout=REQUEST_TIMEOUT
		)
		if r.ok:
			items = r.json().get("tracks", {}).get("items", [])
			good = [i for i in items
					if not artist or similarity(artist, i["artists"][0]["name"]) >= MIN_SIMILARITY]
			if good:
				type_order = {"album": 0, "single": 1, "compilation": 2, "appears_on": 3}
				good.sort(key=lambda x: type_order.get(x.get("album",{}).get("album_type","compilation"), 4))
				sel = good[0]
				cover_url  = sel["album"]["images"][0]["url"] if sel["album"].get("images") else None
				album_name = sel["album"].get("name")
				album_type = sel["album"].get("album_type")
				return cover_url, album_name, album_type
	except Exception as e:
		logging.error(f"[search_spotify] ❌ {e}")
	return None, None, None

def search_musicbrainz(artist, title, album=None):
	try:
		artist_q = prepare_text_for_query(artist)
		title_q  = prepare_text_for_query(title)
		q = f'artist:"{artist_q}" AND recording:"{title_q}"'
		if album:
			album_q = prepare_text_for_query(album)
			if album_q: q += f' AND release:"{album_q}"'
		r = requests.get(
			"https://musicbrainz.org/ws/2/recording",
			headers={"User-Agent": "MoodeRadio/9.1.0 ( moode@example.com )"},
			params={"query": q, "fmt": "json", "limit": 3},
			timeout=MB_TIMEOUT
		)
		if r.ok:
			for rec in r.json().get("recordings", []):
				for rel in rec.get("releases", []):
					mbid = rel.get("id")
					rel_title = rel.get("title")
					release_group = rel.get("release-group", {})
					album_type = release_group.get("primary-type") if release_group else None
					if not mbid: continue
					try:
						cr = requests.get(
							f"https://coverartarchive.org/release/{mbid}",
							headers={"User-Agent": "MoodeRadio/9.1.0 ( moode@example.com )"},
							timeout=MB_TIMEOUT
						)
						if cr.ok:
							for img in cr.json().get("images", []):
								if img.get("front"):
									img_url = (img.get("image")
											   or img.get("thumbnails", {}).get("large")
											   or img.get("thumbnails", {}).get("small"))
									if img_url:
										return img_url, rel_title, album_type
					except Exception as e:
						logging.debug(f"[search_musicbrainz] CAA error {mbid}: {e}")
	except Exception as e:
		logging.error(f"[search_musicbrainz] ❌ {e}")
	return None, None, None

def search_discogs(artist, title, album=None):
	if not DISCOGS_TOKEN: return None, None, None
	try:
		q = prepare_free_term(artist, title, album)
		r = requests.get(
			"https://api.discogs.com/database/search",
			params={"q": q, "type": "release", "token": DISCOGS_TOKEN},
			headers={"User-Agent": "MoodeRadio/9.1.0 ( moode@example.com )"},
			timeout=REQUEST_TIMEOUT
		)
		if r.ok:
			for item in r.json().get("results", []):
				formats   = item.get("format", [])
				item_type = item.get("type")
				if item_type == "release" and "Album" in formats and "Compilation" not in formats:
					title_str = item.get("title", "")
					if " - " in title_str:
						res_artist = title_str.split(" - ", 1)[0]
						if similarity(artist, res_artist) >= MIN_SIMILARITY:
							cover_img  = item.get("cover_image")
							album_name = title_str.split(" - ", 1)[1]
							return cover_img, album_name, "Album"
	except Exception as e:
		logging.error(f"[search_discogs] ❌ {e}")
	return None, None, None

def search_itunes(artist, title, album=None):
	try:
		term = prepare_free_term(artist, title, album)
		r = requests.get(
			"https://itunes.apple.com/search",
			params={"term": term, "media": "music", "entity": "musicTrack", "limit": 10},
			timeout=REQUEST_TIMEOUT
		)
		if r.ok:
			if r.json().get("resultCount") != '0':
				for i in r.json().get("results", []):
					if not artist or similarity(artist, i.get("artistName", "")) >= MIN_SIMILARITY_ITUNES:
						album_name = i.get("collectionName")
						album_type = i.get("collectionType")
						return i.get("artworkUrl100","").replace("100x100","1000x1000"), album_name, album_type

	except Exception:
		pass
	return None, None, None

def search_deezer(artist, title, album=None):
	try:
		q = prepare_query(artist, title, album)
		r = requests.get("https://api.deezer.com/search", params={"q": q, "limit": 5}, timeout=REQUEST_TIMEOUT)
		if r.ok:
			for i in r.json().get("data", []):
				if not artist or similarity(artist, i["artist"]["name"]) >= MIN_SIMILARITY:
					album_name = i["album"].get("title")
					return i["album"].get("cover_xl"), album_name, None
	except Exception:
		pass
	return None, None, None

def search_lastfm(artist, title, album=None):
	if not LASTFM_API_KEY: return None, None, None
	try:
		params = {"method": "track.getInfo", "api_key": LASTFM_API_KEY,
			"artist": artist, "track": title, "format": "json"}
		if album: params["album"] = prepare_text_for_query(album)
		r = requests.get("http://ws.audioscrobbler.com/2.0/", params=params, timeout=REQUEST_TIMEOUT)
		if r.ok:
			track = r.json().get("track", {})
			imgs  = track.get("album", {}).get("image", [])
			album_name = track.get("album", {}).get("title")
			if imgs:
				return imgs[-1]["#text"], album_name, None
	except Exception:
		pass
	return None, None, None

def search_theaudiodb(artist, title, album=None):
	try:
		r1 = requests.get(
			f"https://www.theaudiodb.com/api/v1/json/{THEAUDIODB_API_KEY}/searchtrack.php",
			params={"s": artist, "t": title},
			timeout=REQUEST_TIMEOUT
		)
		if not r1.ok: return None, None, None
		tracks = r1.json().get("track") or []
		if not tracks: return None, None, None
		id_album   = tracks[0].get("idAlbum")
		album_name = tracks[0].get("strAlbum")
		if not id_album: return None, None, None
		r2 = requests.get(
			f"https://www.theaudiodb.com/api/v1/json/{THEAUDIODB_API_KEY}/album.php",
			params={"m": id_album},
			timeout=REQUEST_TIMEOUT
		)
		if not r2.ok: return None, None, None
		albums = r2.json().get("album") or []
		if not albums: return None, None, None
		thumb = albums[0].get("strAlbumThumb")
		if thumb:
			return thumb, album_name, "Album"
	except Exception as e:
		logging.error(f"[search_theaudiodb] ❌ {e}")
	return None, None, None

def search_radio_paradise(station_name, artist, title):
	"""Radio Paradise API — direct cover for RP stations."""
	def rp_channel_key(name):
		if not name: return None
		lname = name.lower()
		if "radio paradise" not in lname: return None
		if "main"   in lname: return "0" # moode name: "Main Mix"
		if "mellow" in lname: return "1"
		if "rock"   in lname: return "2"
		if "global" in lname: return "3"
		if "world"  in lname: return "3" # moode name: "World"
		return None

	channel_key = rp_channel_key(station_name)
	if channel_key is None: return None

	try:
		r = requests.get(
			f"https://api.radioparadise.com/api/now_playing?chan={channel_key}",
			timeout=REQUEST_TIMEOUT
		)
		r.raise_for_status()
		data = r.json()
		api_artist = data.get("artist") or ""
		api_title  = data.get("title")  or ""
		cover = data.get("cover") or data.get("cover_med") or data.get("cover_small")

		if artist.lower() != api_artist.lower() or title.lower() != api_title.lower():
			logging.debug(f"[RadioParadise] ⚠️ Mismatch MPD:'{artist}-{title}' RP:'{api_artist}-{api_title}'")
			return None

		if cover:
			logging.info(f"[RadioParadise] 🟢 Cover found chan={channel_key}: {cover}")
			return cover
	except Exception as e:
		logging.error(f"[RadioParadise] ❌ {e}")
	return None

# ================= SEARCH COVER PARALLEL =================
def search_cover_parallel(artist, title, attempt=1):
	"""
	Parallel cover search across all enabled providers.
	Timebox: FAST_DEADLINE_S → TOTAL_DEADLINE_S.
	Early stop if score >= EARLY_STOP_SCORE.
	Cover selected by resolution: regex → head → pil.
	x1.5 bonus if title exactly matches album name.
	"""
	RELEASE_TYPE_WEIGHTS = {
		"album":	  1.0,
		"single":	 0.9,
		"compilation":0.4,
		"appears_on": 0.5,
		"ep":		 0.7,
		"live":	   0.8,
	}

	all_providers = [
		("Spotify",	 search_spotify),
		("iTunes",	  search_itunes),
		("Deezer",	  search_deezer),
		("LastFM",	  search_lastfm),
		("MusicBrainz", search_musicbrainz),
		("Discogs",	 search_discogs),
		("TheAudioDB",  search_theaudiodb),
	]
	providers = [(n, fn) for n, fn in all_providers if PROVIDERS_LIST.get(n, False)]

	results	  = []
	future_start = {}
	reason	   = "all_done"
	stopped_early = False

	def _compute_best(res):
		album_groups = defaultdict(list)
		album_scores = defaultdict(float)
		for cover_u, album_u, weight_u, provider_u in res:
			placed = False
			for key in album_groups:
				if similarity(album_u, key) >= 0.8:
					album_groups[key].append((cover_u, provider_u))
					album_scores[key] += similarity(album_u, key) * weight_u
					placed = True
					break
			if not placed:
				album_groups[album_u].append((cover_u, provider_u))
				album_scores[album_u] += 1.0 * weight_u
		if not album_scores:
			return None, 0.0, None, None
		best_album	= max(album_scores.items(), key=lambda x: x[1])[0]
		best_score	= album_scores[best_album]
		cover_data	= album_groups[best_album]
		# Resolution selection: regex → head → pil (3 passes)
		def _best_res(url):
			w, h = get_image_resolution(url, "regex")
			if w > 0: return w * h
			w, h = get_image_resolution(url, "head")
			if w > 0: return w * h
			w, h = get_image_resolution(url, "pil")
			return w * h
		cover_data.sort(key=lambda x: _best_res(x[0]), reverse=True)
		chosen		  = cover_data[0][0] if cover_data else None
		chosen_provider = cover_data[0][1] if cover_data else None
		return chosen, float(best_score), best_album, chosen_provider

	logging.info(f"[search_cover_parallel] START attempt={attempt} "
				 f"fast={FAST_DEADLINE_S:.1f}s total={TOTAL_DEADLINE_S:.1f}s "
				 f"early>={EARLY_STOP_SCORE:.1f} "
				 f"artist='{artist}' title='{title}'")

	executor = ThreadPoolExecutor(max_workers=max(len(providers), 1))
	try:
		future_to_provider = {}
		futures = set()
		for name, fn in providers:
			fut = executor.submit(fn, artist, title, None)
			future_to_provider[fut] = name
			future_start[fut]	   = time.monotonic()
			futures.add(fut)

		t_start = time.monotonic()

		while futures:
			elapsed   = time.monotonic() - t_start
			remaining = TOTAL_DEADLINE_S - elapsed
			if remaining <= 0:
				reason = "timeout"
				break

			done, _ = wait(futures, timeout=min(0.25, remaining), return_when=FIRST_COMPLETED)
			if not done:
				if time.monotonic() - t_start >= FAST_DEADLINE_S and results:
					reason = "fast_deadline"
					break
				continue

			for future in done:
				futures.discard(future)
				name  = future_to_provider.get(future, "Unknown")
				try:
					cover, album_found, album_type = future.result()
					if cover and album_found:
						if album_type:
							weight = RELEASE_TYPE_WEIGHTS.get(album_type.lower(), 1.0) + 0.05
						else:
							weight = 0.95
						if artist and artist.lower() in album_found.lower():
							weight *= 0.85
						alb_low = album_found.lower()
						art_low = artist.lower() if artist else ""
						ti_low  = title.lower()  if title  else ""
						# x1.5 bonus if title exactly matches album name
						if ti_low and ti_low == alb_low:
							weight *= 1.5
						if "vol." in alb_low or "volume" in alb_low:
							weight = 0.7
						if "best of" in alb_low or "greatest hits" in alb_low:
							weight = 0.6 if art_low and art_low in alb_low else 0.4
						results.append((cover, album_found, weight, name))
						logging.debug(f"[search_cover_parallel] ✅ {name}: '{album_found}' type={album_type} w={weight:.2f}")
					else:
						logging.debug(f"[search_cover_parallel] ❌ {name}: no cover")
				except Exception as e:
					logging.error(f"[search_cover_parallel] ❌ {name} error: {e}")

			# Early stop
			if results:
				_, score_now, _, _ = _compute_best(results)
				if score_now >= EARLY_STOP_SCORE:
					reason = "early_stop"
					stopped_early = True
					break

			# Fast deadline
			if not stopped_early and time.monotonic() - t_start >= FAST_DEADLINE_S and results:
				reason = "fast_deadline"
				break

	finally:
		pass

	elapsed_ms = (time.monotonic() - t_start) * 1000.0
	done_cnt   = len(providers) - len(futures)
	chosen, score, album_name, provider = _compute_best(results) if results else (None, 0.0, None, None)

	logging.info(f"[search_cover_parallel] END attempt={attempt} reason={reason} "
				 f"done={done_cnt}/{len(providers)} ms={elapsed_ms:.0f} "
				 f"best_score={score:.1f} album='{album_name}' provider={provider}")

	if not results or not chosen:
		logging.info(f"[search_cover_parallel] ❌ No cover found")
		return None, 0.0, None

	logging.info(f"[search_cover_parallel] ✅ Album chosen: '{album_name}' "
				 f"provider={provider} (weighted votes [{score:.1f}])")
	return chosen, score, provider

def search_for_cover(raw_title, station_name):
	# Parse raw title
	artist, title = split_artist_title(raw_title)

	# Check noise gate — catches ADBREAK_END and other segments
	clean_artist = normalize_id(artist or "Unknown")
	clean_title  = normalize_id(title  or "Unknown")
	noise, segment = is_noise(clean_title, station_name)
	if noise:
		logging.info(f"[search_for_cover] Noise detected for segment [{segment}]")
		return None

	# Decode HTML entities before search
	raw_artist = html.unescape(artist) if artist else ""
	raw_title  = html.unescape(title)  if title  else ""
	cover_url = None

	# Radio Paradise API bypass
	if "radio paradise" in station_name.lower():
		rp_artist = artist
		rp_title = title
		if not rp_artist: rp_artist = raw_artist
		cover_url = search_radio_paradise(station_name, rp_artist, rp_title)
		if cover_url:
			provider = "RadioParadise"
			logging.info(f"[worker] ✅ RadioParadise cover found")

	if not cover_url:
		# ATTEMPT 1 — light cleanup
		artist_1 = artist
		title_1 = title
		if not artist_1:
			artist_1 = raw_artist
			title_1  = raw_title
		artist_1 = normalize_for_search(artist_1)
		title_1  = normalize_for_search(title_1)
		logging.info(f"[worker] 🔍 ATTEMPT 1: '{artist_1}' - '{title_1}'")
		cover_url, score1, provider = search_cover_parallel(artist_1, title_1, attempt=1)
		if cover_url and score1 >= 1.5:
			logging.info(f"[worker] ✅ Attempt 1 accepted score={score1:.1f} provider={provider}")
		elif cover_url and score1 < 1.5:
			logging.info(f"[worker] ⚠️  Attempt 1 score too low ({score1:.1f}), discarding")
			cover_url = None

		# ATTEMPT 2 — aggressive cleanup (only if attempt 1 failed)
		if not cover_url:
			artist_2 = clean_artist_name(raw_artist) if raw_artist else artist_1
			title_2  = normalize_title(raw_title, artist=raw_artist) if raw_title else title_1
			logging.info(f"[worker] 🔄 ATTEMPT 2: '{artist_2}' - '{title_2}'")
			cover_url, score2, provider = search_cover_parallel(artist_2, title_2, attempt=2)
			if cover_url and score2 >= 0.9:
				logging.info(f"[worker] ✅ Attempt 2 accepted score={score2:.1f} provider={provider}")
			elif cover_url and score2 < 0.9:
				logging.info(f"[worker] ⚠️  Attempt 2 score too low ({score2:.1f}), discarding")
				cover_url = None
			else:
				logging.info(f"[worker] ❌ No cover after 2 attempts")

	return cover_url

def main():
	# Setup argument parser
	parser = argparse.ArgumentParser(
		description="Fetch album art URL using Title and Name tags from MPD.")

	# Define arguments
	parser.add_argument("--title", required=True, help="Title tag from MPD enclosed in dbl quotes")
	parser.add_argument("--station", required=True, help="Name tag from MPD enclosed in dbl quotes")
	parser.add_argument('--version', action='version', version='%(prog)s {}'.format(__version__))

	# Parse arguments
	args = parser.parse_args()

	# Search for cover
	read_global()
	log_config_summary()
	cover_url = search_for_cover(args.title, args.station)
	print(cover_url) # URL or None

if __name__ == "__main__":
	main()
