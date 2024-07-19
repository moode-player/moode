--
-- File generated with SQLiteStudio v3.1.0 on Sun Jun 23 09:20:46 2024
--
-- Text encoding used: UTF-8
--
PRAGMA foreign_keys = off;
BEGIN TRANSACTION;

-- Table: cfg_radio
CREATE TABLE cfg_radio (id INTEGER PRIMARY KEY, station CHAR (128), name CHAR (128), type CHAR (1), logo CHAR (128), genre CHAR (32), broadcaster CHAR (32), language CHAR (32), country CHAR (32), region CHAR (32), bitrate CHAR (32), format CHAR (32), geo_fenced CHAR (3), home_page CHAR (32), monitor CHAR (32));
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (1, 'http://strm112.1.fm/blues_mobile_mp3', '1.FM - Blues Radio', 'r', 'local', 'Blues', '1.FM', 'English', 'Switzerland', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (2, 'https://21363.live.streamtheworld.com/2BOB.mp3', '2BOB Radio 104.7 FM', 'r', 'local', 'Alternative', 'BOB 2.00', 'English', 'Australia', 'Asia', '64', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (3, 'https://iheart.4zzz.org.au/4zzz', '4ZZZ FM 102.1 - Alternative', 'r', 'local', 'Alternative', '4ZZZ FM', 'English', 'Australia', 'Asia', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (4, 'http://54.173.171.80:8000/6forty', '6forty Radio', 'r', 'local', 'Alternative, Post-Rock, Post-Metal, Modern, Experimental, Deep Indie', '6forty Radio', 'English', 'United States', 'North America', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (5, 'http://radio.stereoscenic.com/ama-h', 'A.M. Ambient', 'r', 'local', 'Electronica, Ambient', 'Stereoscenic', 'English', 'United States', 'North America', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (6, 'http://live-radio01.mediahubaustralia.com/CTRW/mp3/', 'ABC Country', 'r', 'local', 'Country', 'ABC', 'English', 'Australia', 'Asia', '96', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (7, 'http://live-radio01.mediahubaustralia.com/JAZW/mp3/', 'ABC Jazz', 'r', 'local', 'Jazz', 'ABC', 'English', 'Australia', 'Asia', '96', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (8, 'https://edge68.live-sm.absolutradio.de/absolut-hot/stream/aacp?aggregator=smk-m3u-aac', 'Absolut Hot', 'r', 'local', 'Pop, Top 40, Chart, Electro, Hip Hop', 'Absolut', 'German', 'Germany', 'Europe', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (9, 'https://absolut-musicxl.live-sm.absolutradio.de/absolut-musicxl/stream/mp3', 'Absolut music XL', 'r', 'local', 'Pop, New Releases, Oldies, Rock, Pop', 'Absolut', 'German', 'Germany', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (10, 'https://edge56.live-sm.absolutradio.de/absolut-relax/stream/mp3', 'Absolut Relax', 'r', 'local', 'Pop, 80''s, 90''s', 'Absolut', 'German', 'Germany', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (11, 'http://radio.stereoscenic.com/asp-h', 'Ambient Sleeping Pill', 'r', 'local', 'Electronica, Ambient', 'Stereoscenic', 'English', 'United States', 'North America', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (12, 'https://mediaserv73.live-streams.nl:18058/stream', 'Ancient FM - Mediaeval and Renaissance Music', 'r', 'local', 'Classical, Mediaeval, Renaissance', 'Ancient FM', 'English', 'Canada', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (13, 'https://stream.artsound.fm/mp3', 'ArtSound FM 92.7', 'r', 'local', 'Classical, Jazz, Folk, World Music', 'ArtSound FM', 'English', 'Australia', 'Asia', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (14, 'https://dispatcher.rndfnk.com/br/br2/live/mp3/mid', 'Bayern 2', 'r', 'local', 'Eclectic', 'Bayern Radio', 'German', 'Germany', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (15, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_asian_network/bbc_asian_network.isml/bbc_asian_network-audio%3d96000.norewind.m3u8', 'BBC Asian Network', 'r', 'local', 'Contemporary, Bollywood, Bhangra, Pop, Urban', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (16, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_asian_network/bbc_asian_network.isml/bbc_asian_network-audio%3d320000.norewind.m3u8', 'BBC Asian Network (320K)', 'r', 'local', 'Contemporary, Bollywood, Bhangra, Pop, Urban', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (17, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_radio_one/bbc_radio_one.isml/bbc_radio_one-audio%3d96000.norewind.m3u8', 'BBC Radio 1', 'r', 'local', 'Pop, Top 40, Chart', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (18, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_radio_one/bbc_radio_one.isml/bbc_radio_one-audio%3d320000.norewind.m3u8', 'BBC Radio 1 (320K)', 'r', 'local', 'Pop, Top 40, Chart', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (19, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_radio_two/bbc_radio_two.isml/bbc_radio_two-audio%3d96000.norewind.m3u8', 'BBC Radio 2', 'r', 'local', 'Pop, Contemporary', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (20, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_radio_two/bbc_radio_two.isml/bbc_radio_two-audio%3d320000.norewind.m3u8', 'BBC Radio 2 (320K)', 'r', 'local', 'Pop, Contemporary', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (21, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_radio_three/bbc_radio_three.isml/bbc_radio_three-audio%3d96000.norewind.m3u8', 'BBC Radio 3', 'r', 'local', 'Classical, Jazz, World Music', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (22, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_radio_three/bbc_radio_three.isml/bbc_radio_three-audio%3d320000.norewind.m3u8', 'BBC Radio 3 (320K)', 'r', 'local', 'Classical, Jazz, World Music', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (23, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_radio_fourfm/bbc_radio_fourfm.isml/bbc_radio_fourfm-audio%3d96000.norewind.m3u8', 'BBC Radio 4 FM', 'r', 'local', 'Spoken Word', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (24, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_radio_fourfm/bbc_radio_fourfm.isml/bbc_radio_fourfm-audio%3d320000.norewind.m3u8', 'BBC Radio 4 FM (320K)', 'r', 'local', 'Spoken Word', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (25, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_radio_five_live/bbc_radio_five_live.isml/bbc_radio_five_live-audio%3d96000.norewind.m3u8', 'BBC Radio 5 live', 'r', 'local', 'News, Discussion, Sports, Interviews, Phone-ins', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (26, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_radio_five_live/bbc_radio_five_live.isml/bbc_radio_five_live-audio%3d320000.norewind.m3u8', 'BBC Radio 5 live (320K)', 'r', 'local', 'News, Discussion, Sports, Interviews, Phone-ins', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (27, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_radio_five_live_sports_extra/bbc_radio_five_live_sports_extra.isml/bbc_radio_five_live_sports_extra-audio%3d320000.norewind.m3u8', 'BBC Radio 5 live sports extra (320K)', 'r', 'local', 'News, Sports', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (28, 'http://as-hls-ww-live.akamaized.net/pool_904/live/ww/bbc_6music/bbc_6music.isml/bbc_6music-audio%3d96000.norewind.m3u8', 'BBC Radio 6 music', 'r', 'local', 'Alternative, Rock, Funk', 'BBC', 'English', 'United Kingdom', 'Europe', '96', 'AAC-LC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (29, 'http://as-hls-uk-live.akamaized.net/pool_904/live/uk/bbc_6music/bbc_6music.isml/bbc_6music-audio%3d320000.norewind.m3u8', 'BBC Radio 6 music (320K)', 'r', 'local', 'Alternative, Rock, Funk', 'BBC', 'English', 'United Kingdom', 'Europe', '320', 'AAC-LC', 'Yes', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (30, 'https://dispatcher.rndfnk.com/br/brklassik/live/mp3/high', 'BR-Klassik', 'r', 'local', 'Classical', 'Bayern Radio', 'German', 'Germany', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (31, 'https://legacy.scahw.com.au/2buddha_32', 'Buddha Radio', 'r', 'local', 'Chill Out', 'Buddah', 'English', 'Australia', 'Asia', '32', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (32, 'https://bytefm.cast.addradio.de/bytefm/main/high/stream', 'ByteFM', 'r', 'local', 'Eclectic', 'Alsterradio', 'German', 'Germany', 'Europe', '192', 'MP3', 'No', 'https://www.byte.fm/', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (33, 'http://msmn7.co:8018/stream', 'CDNX', 'r', 'local', 'Alternative', 'Camden Market', 'English', 'United Kingdom', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (34, 'http://media-ice.musicradio.com/ClassicFMMP3', 'Classic FM', 'r', 'local', 'Classical', 'Global Radio', 'English', 'United Kingdom', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (35, 'https://rozhlas.stream/ddur_mp3_256.mp3', 'Czech Radio Classical', 'r', 'local', 'Classical', 'ČRo D-Dur', 'Czech', 'Czech Republic', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (36, 'http://stream.dandelionradio.com:9414', 'Dandelion Radio', 'r', 'local', 'Alternative', 'Dandelion Radio', 'English', 'United Kingdom', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (37, 'https://streaming04.liveboxstream.uk/proxy/davideof?mp=/stream', 'Davide of MIMIC', 'r', 'local', 'Classical', 'Davide of MIMIC Radio', 'English', 'United Kingdom', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (38, 'http://live-icy.dr.dk/A/A03H.mp3', 'DR P1', 'r', 'local', 'News, Talk', 'DR', 'Danish', 'Denmark', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (39, 'http://live-icy.dr.dk/A/A04H.mp3', 'DR P2', 'r', 'local', 'Eclectic, Music, Culture', 'DR', 'Danish', 'Denmark', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (40, 'http://live-icy.dr.dk/A/A05H.mp3', 'DR P3', 'r', 'local', 'Pop, Rock', 'DR', 'Danish', 'Denmark', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (41, 'http://live-icy.dr.dk/A/A08H.mp3', 'DR P4', 'r', 'local', 'Pop, News', 'DR', 'Danish', 'Denmark', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (42, 'http://live-icy.dr.dk/A/A29H.mp3', 'DR P6 Beat', 'r', 'local', 'Alternative, Alt Rock, Rock', 'DR', 'Danish', 'Denmark', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (43, 'http://live-icy.dr.dk/A/A22H.mp3', 'DR P8 Jazz', 'r', 'local', 'Jazz', 'DR', 'Danish', 'Denmark', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (44, 'https://DRliveradio.akamaized.net/hls/live/2022411/p8jazz/playlist-320000.m3u8', 'DR P8 Jazz (320K)', 'r', 'local', 'Jazz', 'DR', 'Danish', 'Denmark', 'Europe', '320', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (45, 'http://81.92.238.33:80', 'Eldoradio', 'r', 'local', 'Pop, Top 40, Chart', 'Eldoradio', 'Luxembourgish', 'Luxembourg', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (46, 'https://fluxmusic.api.radiosphere.io/channels/FluxFM/stream.aac?quality=4', 'FluxFM', 'r', 'local', 'News, Various Genres', 'FluxFM', 'German', 'Germany', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (47, 'http://streams.fluxfm.de/flx_2000/mp3-320/streams.fluxfm.de/', 'FluxFM - 2000''s Naughty', 'r', 'local', '2000''s', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (48, 'https://streams.fluxfm.de/60er/mp3-320/streams.fluxfm.de/', 'FluxFM - 60s', 'r', 'local', '60s', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (49, 'http://streams.fluxfm.de/70er/mp3-320/audio/', 'FluxFM - 70s', 'r', 'local', '70s', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (50, 'http://streams.fluxfm.de/80er/mp3-320/streams.fluxfm.de/', 'FluxFM - 80s', 'r', 'local', '80s', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (51, 'https://fluxmusic.api.radiosphere.io/channels/90s/stream.aac?quality=4', 'FluxFM - 90s', 'r', 'local', '90s', 'FluxFM', 'German', 'Germany', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (52, 'http://streams.fluxfm.de/event01/mp3-320/streams.fluxfm.de/', 'FluxFM - B-Funk', 'r', 'local', 'Funk', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (53, 'https://streams.fluxfm.de/bbeachhouse/mp3-320/streams.fluxfm.de/', 'FluxFM - Berlin Beach House Radio', 'r', 'local', 'Electronica', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (54, 'http://streams.fluxfm.de/boomfm/mp3-320/audio/', 'FluxFM - BoomFM', 'r', 'local', 'HipHop', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (55, 'http://streams.fluxfm.de/boomfmclassics/mp3-320/audio/', 'FluxFM - BoomFM Classics', 'r', 'local', 'HipHop, Oldschool', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (56, 'https://streams.fluxfm.de/Chillhop/mp3-320/streams.fluxfm.de/', 'FluxFM - ChillHop', 'r', 'local', 'Chill Out, Laidback', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (57, 'https://streams.fluxfm.de/chillout/mp3-320/streams.fluxfm.de/', 'FluxFM - Chillout Radio', 'r', 'local', 'Chill Out, Laidback', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (58, 'https://streams.fluxfm.de/clubsandwich/mp3-320/streams.fluxfm.de/', 'FluxFM - Clubsandwich', 'r', 'local', 'Electronica', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (59, 'http://streams.fluxfm.de/dubradio/mp3-320/streams.fluxfm.de/', 'FluxFM - Dub Radio', 'r', 'local', 'Dub, Reggae', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (60, 'https://streams.fluxfm.de/elektro/mp3-320/streams.fluxfm.de/', 'FluxFM - ElectroFlux', 'r', 'local', 'Electronica, Pop', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (61, 'http://streams.fluxfm.de/forward/mp3-320/audio/', 'FluxFM - FluxForward', 'r', 'local', 'Various Genres, Releases', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (62, 'https://streams.fluxfm.de/fluxkompensator/mp3-320/streams.fluxfm.de/', 'FluxFM - FluxKompensator', 'r', 'local', 'Various Genres', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (63, 'https://streams.fluxfm.de/lounge/mp3-320/streams.fluxfm.de/', 'FluxFM - FluxLounge', 'r', 'local', 'Lounge, Neo-Soul, Trip-Hop, Jazz', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (64, 'https://streams.fluxfm.de/flux-hamburg/mp3-320/audio/', 'FluxFM - Hamburg', 'r', 'local', 'Pop, Culture', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (65, 'http://streams.fluxfm.de/hardrock/mp3-320/streams.fluxfm.de/', 'FluxFM - Hard Rock FM', 'r', 'local', 'Rock, Hard Rock', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (66, 'https://fluxmusic.api.radiosphere.io/channels/htgp/stream.mp3?quality=4', 'FluxFM - Hippie Trippy Garden Pretty', 'r', 'local', 'Electronica, Soundscape, Atmospheric', 'FluxFM', 'German', 'Germany', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (67, 'https://streams.fluxfm.de/indiedisco/mp3-320/streams.fluxfm.de/', 'FluxFM - Indie Disco', 'r', 'local', 'Indie, Disco, Dance', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (68, 'http://streams.fluxfm.de/studio56/mp3-320/audio/', 'FluxFM - JaegerMusic Radio', 'r', 'local', 'Various Genres', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (69, 'https://streams.fluxfm.de/jazzschwarz/mp3-320/streams.fluxfm.de/', 'FluxFM - Jazzradio Schwarzenstein', 'r', 'local', 'Jazz', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (70, 'https://streams.fluxfm.de/john-reed/mp3-320/streams.fluxfm.de/', 'FluxFM - John Reed Radio', 'r', 'local', 'Various Genres', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (71, 'https://streams.fluxfm.de/klubradio/mp3-320/streams.fluxfm.de/', 'FluxFM - Klubradio', 'r', 'local', 'Electronica', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (72, 'https://streams.fluxfm.de/Melides/mp3-320/streams.fluxfm.de/', 'FluxFM - Melides Art Radio', 'r', 'local', 'Indie, Eclectic', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (73, 'http://streams.fluxfm.de/metalfm/mp3-320/streams.fluxfm.de/', 'FluxFM - MetalFM', 'r', 'local', 'Metal', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (74, 'https://fluxmusic.api.radiosphere.io/channels/mini-flux/stream.aac?quality=4', 'FluxFM - Mini Flux', 'r', 'local', 'Various Genres', 'FluxFM', 'German', 'Germany', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (75, 'http://streams.fluxfm.de/neofm/mp3-320/streams.fluxfm.de/', 'FluxFM - neoFM', 'r', 'local', 'Classical, Contemporary', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (76, 'http://streams.fluxfm.de/passport/mp3-320/audio/', 'FluxFM - Passport Approved', 'r', 'local', 'Various Genres', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (77, 'http://streams.fluxfm.de/radioalt/mp3-320/streams.fluxfm.de/', 'FluxFM - Radio Alternative', 'r', 'local', 'Alternative', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (78, 'http://streams.fluxfm.de/rastaradio/mp3-320/streams.fluxfm.de/', 'FluxFM - Rasta Radio', 'r', 'local', 'Reggae', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (79, 'https://streams.fluxfm.de/soundofberlin/mp3-320/streams.fluxfm.de/', 'FluxFM - Sound Of Berlin', 'r', 'local', 'Electronica, House', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (80, 'https://streams.fluxfm.de/technoug/mp3-320/streams.fluxfm.de/', 'FluxFM - Techno Underground', 'r', 'local', 'Electronica, Techno', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (81, 'http://streams.fluxfm.de/xjazz/mp3-320/streams.fluxfm.de/', 'FluxFM - XJAZZ', 'r', 'local', 'Jazz', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (82, 'http://streams.fluxfm.de/yogasounds/mp3-320/streams.fluxfm.de/', 'FluxFM - Yoga Sounds', 'r', 'local', 'Chill Out, Ambient, Yoga', 'FluxFM', 'German', 'Germany', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (83, 'http://direct.franceculture.fr/live/franceculture-midfi.mp3', 'France Culture Live', 'r', 'local', 'Spoken Word, Current Affairs', 'Radio France', 'French', 'France', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (84, 'http://direct.fipradio.fr/live/fip-midfi.mp3', 'France Inter Paris (FIP)', 'r', 'local', 'Classical, Jazz, Rock, World Music', 'Radio France', 'French', 'France', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (85, 'http://direct.francemusique.fr/live/francemusiqueclassiqueplus-hifi.mp3', 'France Musique Classique Plus', 'r', 'local', 'Classical', 'Radio France', 'French', 'France', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (86, 'http://direct.francemusique.fr/live/francemusiquelacontemporaine-hifi.mp3', 'France Musique La Contemporaine', 'r', 'local', 'Contemporary', 'Radio France', 'French', 'France', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (87, 'http://direct.francemusique.fr/live/francemusiquelajazz-hifi.mp3', 'France Musique La Jazz', 'r', 'local', 'Jazz', 'Radio France', 'French', 'France', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (88, 'http://direct.francemusique.fr/live/francemusique-midfi.mp3', 'France Musique Live', 'r', 'local', 'Classical, Jazz', 'Radio France', 'French', 'France', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (89, 'http://hd.stream.frequence3.net/frequence3.flac', 'frequence3 (FLAC)', 'r', 'local', 'Pop, Top 40, Chart', 'Frequence3 Association', 'French', 'France', 'Europe', '900', 'FLAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (90, 'http://mediaserv30.live-streams.nl:8088/live', 'Hi On Line - Classical', 'r', 'local', 'Classical', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (91, 'http://mediaserv21.live-streams.nl:8000/live', 'Hi On Line - France', 'r', 'local', 'Contemporary', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (92, 'http://mediaserv30.live-streams.nl:8000/live', 'Hi On Line - Gold', 'r', 'local', 'Pop, Golden Oldies', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (93, 'http://mediaserv38.live-streams.nl:8006/live', 'Hi On Line - Jazz', 'r', 'local', 'Jazz', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (94, 'http://mediaserv33.live-streams.nl:8034/live', 'Hi On Line - Latin', 'r', 'local', 'Latin', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (95, 'http://mediaserv33.live-streams.nl:8036/live', 'Hi On Line - Lounge', 'r', 'local', 'Lounge', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (96, 'http://mediaserv30.live-streams.nl:8086/live', 'Hi On Line - Pop (320K)', 'r', 'local', 'Pop', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (97, 'http://mscp2.live-streams.nl:8100/flac.flac', 'Hi On Line - Pop (FLAC)', 'r', 'local', 'Pop', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '900', 'FLAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (98, 'http://mediaserv38.live-streams.nl:8027/live', 'Hi On Line - World', 'r', 'local', 'World Music', 'Hi.Fine', 'English', 'Netherlands', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (99, 'http://edge-bauerall-01-gos2.sharp-stream.com/jazzhigh.aac?aw_0_1st.skey=1650998937', 'Jazz FM', 'r', 'local', 'Jazz, Blues. Soul', 'Bauer Planet Radio', 'English', 'United Kingdom', 'Europe', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (100, 'https://live.amperwave.net/direct/ppm-jazz24aac256-ibc1', 'Jazz24', 'r', 'local', 'Jazz', 'Jazz24.org', 'English', 'United States', 'North America', '256', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (101, 'https://maggie.torontocast.com:8076/aac', 'JB Radio2 (320K)', 'r', 'local', 'Alternative, Rock, Eclectic', 'JB Radio', 'English', 'Canada', 'North America', '320', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (102, 'http://193.197.85.26:8000/;stream/1', 'Kanal K', 'r', 'local', 'Alternative', 'Regionalradio Aargaudio AG', 'German', 'Switzerland', 'Europe', '256', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (103, 'http://current.stream.publicradio.org/kcmp.mp3', 'KCMP 89.3 FM - The Current', 'r', 'local', 'Alternative', 'PBS', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (104, 'http://ice5.securenetsystems.net/KCSM', 'KCSM', 'r', 'local', 'Jazz', 'KCSM FM', 'English', 'United States', 'North America', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (105, 'http://kdhx-ice.streamguys1.com:80/live', 'KDHX 88.1 FM St. Louis', 'r', 'local', 'Alternative', 'KDHX', 'English', 'United States', 'North America', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (106, 'http://live-aacplus-64.kexp.org/kexp64.aac', 'KEXP 90.3 FM Seattle', 'r', 'local', 'Alternative, Indie', 'PBS', 'English', 'United States', 'North America', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (107, 'http://stream1.opb.org/kmhd.mp3', 'KMHD Portland FM 89.1 -  Jazz', 'r', 'local', 'Jazz', 'KMHD', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (108, 'http://kuvo-ice.streamguys.org/kuvo-aac-128', 'KUVO 89.3 FM Denver', 'r', 'local', 'Jazz', 'PBS', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (109, 'http://kuvo-ice.streamguys.org/kuvohd2-aac-128', 'KUVO HD2', 'r', 'local', 'Pop, R&B, Hip Hop', 'PBS', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (110, 'http://radio.linn.co.uk:8004/autodj', 'Linn Classical', 'r', 'local', 'Classical', 'Linn', 'English', 'United Kingdom', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (111, 'http://radio.linn.co.uk:8000/autodj', 'Linn Jazz', 'r', 'local', 'Jazz', 'Linn', 'English', 'United Kingdom', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (112, 'http://radio.linn.co.uk:8003/autodj', 'Linn Radio', 'r', 'local', 'Eclectic', 'Linn', 'English', 'United Kingdom', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (113, 'http://albireo.shoutca.st:8250/stream', 'Mad Music Asylum', 'r', 'local', 'Rock, Eclectic', 'Mad Music Asylum', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (114, 'http://mdr-284350-0.cast.mdr.de/mdr/284350/0/aac/high/stream.aac', 'MDR Klassik', 'r', 'local', 'Classical', 'Mitteldeutscher Rundfunk', 'German', 'Germany', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (115, 'http://stream.fr.morow.com:8080/morow_hi.aacp', 'Morow - Retro Progressive Rock', 'r', 'local', 'Progressive Rock, Rock', 'Morow', 'English', 'France', 'Europe', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (116, 'https://radios.rtbf.be/musiq3-128.aac', 'Musiq 3', 'r', 'local', 'Classical', 'RTBF', 'French', 'Belgium', 'Europe', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (117, 'http://mscp3.live-streams.nl:8250/class-high.aac', 'Naim Classical', 'r', 'local', 'Classical', 'Naim', 'English', 'United Kingdom', 'Europe', '320', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (118, 'http://mscp3.live-streams.nl:8340/jazz-high.aac', 'Naim Jazz', 'r', 'local', 'Jazz', 'Naim', 'English', 'United Kingdom', 'Europe', '320', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (119, 'http://mscp3.live-streams.nl:8360/high.aac', 'Naim Radio', 'r', 'local', 'Eclectic', 'Naim', 'English', 'United Kingdom', 'Europe', '320', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (120, 'http://listen-nme.sharp-stream.com/nme1high.mp3', 'NME 1 - Classic & New Indie Alt', 'r', 'local', 'Indie', 'NME', 'English', 'United Kingdom', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (121, 'http://listen-nme.sharp-stream.com/nme2high.mp3', 'NME 2 - New & Upfront Indie Alt', 'r', 'local', 'Indie', 'NME', 'English', 'United Kingdom', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (122, 'http://icecast.omroep.nl/radio1-bb-aac', 'NPO Radio 1', 'r', 'local', 'News', 'NPO', 'Dutch', 'Netherlands', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (123, 'http://icecast.omroep.nl/radio2-bb-aac', 'NPO Radio 2', 'r', 'local', 'Pop, Dance, Oldies, Rock', 'NPO', 'Dutch', 'Netherlands', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (124, 'http://icecast.omroep.nl/radio4-bb-aac', 'NPO Radio 4', 'r', 'local', 'Classical', 'NPO', 'Dutch', 'Netherlands', 'Europe', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (125, 'http://stream-relay-geo.ntslive.net/stream2', 'NTS Live International', 'r', 'local', 'Alternative, Underground, Club, Live', 'NTS', 'English', 'United States', 'North America', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (126, 'http://stream-relay-geo.ntslive.net/stream', 'NTS Live London - Don''t Assume', 'r', 'local', 'Alternative, Underground, Club, Live', 'NTS', 'English', 'United Kingdom', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (127, 'https://d3isaxd2t6q8zm.cloudfront.net/icecast/omroepzeeland/omroepzeeland_radio', 'Omroep Zeeland', 'r', 'local', 'Pop', 'Omroep Zeeland', 'Dutch', 'Netherlands', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (128, 'http://play.organlive.com:8002/320oe', 'Organ Experience', 'r', 'local', 'Classical', 'ORGAN.MEDIA', 'English', 'United States', 'North America', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (129, 'http://play.organlive.com:8002/320', 'OrganLive.com', 'r', 'local', 'Classical', 'ORGAN.MEDIA', 'English', 'United States', 'North America', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (130, 'https://sverigesradio.se/topsy/direkt/132-hi-aac', 'P1', 'r', 'local', 'News, Culture', 'Sveriges Radio', 'Swedish', 'Sweden', 'Europe', '192', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (131, 'https://http-live.sr.se/p2musik-aac-320', 'P2', 'r', 'local', 'Classical, Jazz', 'Sveriges Radio', 'Swedish', 'Sweden', 'Europe', '320', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (132, 'https://sverigesradio.se/topsy/direkt/164-hi-aac', 'P3', 'r', 'local', 'Pop, Culture', 'Sveriges Radio', 'Swedish', 'Sweden', 'Europe', '192', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (133, 'http://play.organlive.com:8002/320pb', 'Positivly Baroque', 'r', 'local', 'Classical, Baroque', 'ORGAN.MEDIA', 'English', 'United States', 'North America', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (134, 'http://shoutcastunlimited.com:8512', 'PRM - Prog Rock & Metal', 'r', 'local', 'Progressive Rock, Metal', 'Will Mangold', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (135, 'https://stream.rcs.revma.com/an1ugyygzk8uv', 'Radio 357', 'r', 'local', 'Rock, Alternative, Jazz', 'Radio 357', 'Polish', 'Poland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (136, 'http://sc3.radiocaroline.net:8030', 'Radio Caroline', 'r', 'local', 'Rock, Classic Rock', 'Radio Caroline', 'English', 'United Kingdom', 'Europe', '96', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (137, 'https://orf-live.ors-shoutcast.at/fm4-q2a', 'Radio FM4', 'r', 'local', 'Alternative, Alt Rock, Electronic', 'ORF', 'English', 'Austria', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (138, 'http://radionz-ice.streamguys.com:80/concert.mp3', 'Radio New Zealand - Concert', 'r', 'local', 'Classical', 'Radio New Zealand', 'English', 'New Zealand', 'Asia', '64', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (139, 'http://radionz-ice.streamguys.com:80/national.mp3', 'Radio New Zealand - National', 'r', 'local', 'Eclectic, Music, Current Affairs', 'Radio New Zealand', 'English', 'New Zealand', 'Asia', '64', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (140, 'https://stream.nowyswiat.online/mp3', 'Radio Nowy Swiat', 'r', 'local', 'Rock, Alternative, Jazz', 'Radio Nowy Swiat', 'Polish', 'Poland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (141, 'https://stream.radioparadise.com/flacm', 'Radio Paradise - Main Mix', 'r', 'local', 'Eclectic', 'Radio Paradise', 'English', 'United States', 'North America', '900', 'FLAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (142, 'https://stream.radioparadise.com/mellow-flacm', 'Radio Paradise - Mellow', 'r', 'local', 'Rock, Mellow Rock', 'Radio Paradise', 'English', 'United States', 'North America', '900', 'FLAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (143, 'https://stream.radioparadise.com/rock-flacm', 'Radio Paradise - Rock', 'r', 'local', 'Rock', 'Radio Paradise', 'English', 'United States', 'North America', '900', 'FLAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (144, 'https://stream.radioparadise.com/world-flacm', 'Radio Paradise - World', 'r', 'local', 'World Music', 'Radio Paradise', 'English', 'United States', 'North America', '900', 'FLAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (145, 'http://streaming.swisstxt.ch/m/drs1/mp3_128', 'Radio SRF 1', 'r', 'local', 'News, Entertainment, News', 'SRF', 'German', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (146, 'http://streaming.swisstxt.ch/m/drs2/mp3_128', 'Radio SRF 2 Kultur', 'r', 'local', 'Classical, Jazz', 'SRF', 'German', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (147, 'http://streaming.swisstxt.ch/m/drs3/mp3_128', 'Radio SRF 3', 'r', 'local', 'Eclectic', 'SRF', 'German', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (148, 'http://streaming.swisstxt.ch/m/drs4news/mp3_128', 'Radio SRF 4 News', 'r', 'local', 'News', 'SRF', 'German', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (149, 'http://streaming.swisstxt.ch/m/drsmw/mp3_128', 'Radio SRF Musikwelle', 'r', 'local', 'Pop, Schlager', 'SRF', 'German', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (150, 'http://streaming.swisstxt.ch/m/drsvirus/mp3_128', 'Radio SRF Virus', 'r', 'local', 'Alternative', 'SRF', 'German', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (151, 'http://stream.srg-ssr.ch/m/rsc_de/aacp_96', 'Radio Swiss Classic', 'r', 'local', 'Classical', 'Swiss Broadcasting Corporation', 'German', 'Switzerland', 'Europe', '96', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (152, 'http://stream.srg-ssr.ch/m/rsj/aacp_96', 'Radio Swiss Jazz', 'r', 'local', 'Jazz', 'Swiss Broadcasting Corporation', 'German', 'Switzerland', 'Europe', '96', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (153, 'http://stream.srg-ssr.ch/m/rsp/aacp_96', 'Radio Swiss Pop', 'r', 'local', 'Pop', 'Swiss Broadcasting Corporation', 'German', 'Switzerland', 'Europe', '96', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (154, 'https://ice.cr1.streamzilla.xlcdn.com:8000/sz=RCOLiveWebradio=mp3-192', 'RCO Live', 'r', 'local', 'Classical', 'RCO', 'Dutch', 'Netherlands', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (155, 'http://stream.resonance.fm/resonance', 'Resonance Radio 104.4 FM', 'r', 'local', 'Eclectic', 'Resonance Radio', 'English', 'United Kingdom', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (156, 'http://rootslegacy.fr:8080/;listen.mp3', 'Roots Legacy Radio - Dub UK & Roots Reggae', 'r', 'local', 'Dub, Dub UK, Roots Reggae', 'Roots Legacy Radio', 'English', 'France', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (157, 'http://stream.srg-ssr.ch/m/retedue/mp3_128', 'RSI - Rete Due', 'r', 'local', 'Classical, Music, Culture', 'RSI', 'Italian', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (158, 'http://stream.srg-ssr.ch/m/retetre/mp3_128', 'RSI - Rete Tre', 'r', 'local', 'Alternative, Pop', 'RSI', 'Italian', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (159, 'http://stream.srg-ssr.ch/m/reteuno/mp3_128', 'RSI - Rete Uno', 'r', 'local', 'News, Entertainment, News', 'RSI', 'Italian', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (160, 'http://stream.srg-ssr.ch/m/rr/mp3_128', 'RTR Radio', 'r', 'local', 'Pop', 'SRG', 'Romansh', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (161, 'http://stream.srg-ssr.ch/m/couleur3/mp3_128', 'RTS - Couleur 3', 'r', 'local', 'Eclectic', 'SRG', 'French', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (162, 'http://stream.srg-ssr.ch/m/espace-2/mp3_128', 'RTS - Espace 2', 'r', 'local', 'Classical', 'SRG', 'French', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (163, 'http://stream.srg-ssr.ch/m/la-1ere/mp3_128', 'RTS - La Premiere', 'r', 'local', 'Pop', 'SRG', 'French', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (164, 'http://stream.srg-ssr.ch/m/option-musique/mp3_128', 'RTS - option musique', 'r', 'local', 'Alternative, Indie', 'SRG', 'French', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (165, 'http://listen.jazz88.org/ksds.mp3', 'San Diego Jazz 88.3', 'r', 'local', 'Jazz', 'KSDS', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (166, 'http://edge-bauerall-01-gos2.sharp-stream.com/scalahigh.aac?aw_0_1st.skey=1650896299', 'Scala Radio', 'r', 'local', 'Classical, News, Classical, Requests', 'Bauer Planet Radio', 'English', 'United Kingdom', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (167, 'https://smoothjazz.cdnstream1.com/2585_320.mp3', 'SmoothJazz Global', 'r', 'local', 'Jazz, Smooth Jazz', 'Global Radio', 'English', 'United States', 'North America', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (168, 'https://smoothjazz.cdnstream1.com/2586_320.mp3', 'SmoothLounge Global', 'r', 'local', 'Lounge, Smooth Lounge', 'Global Radio', 'English', 'United States', 'North America', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (169, 'https://sohoradiomusic.doughunt.co.uk:8010/320mp3', 'Soho Radio London', 'r', 'local', 'Eclectic, Music, Culture', 'Soho Radio', 'English', 'United Kingdom', 'Europe', '320', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (170, 'http://ice1.somafm.com/beatblender-128-aac', 'Soma FM - Beat Blender', 'r', 'local', 'Electronica, Deep House, Down-Tempo Chill', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (171, 'http://ice1.somafm.com/brfm-128-aac', 'Soma FM - Black Rock FM', 'r', 'local', 'Electronica, Burning Man Festival Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (172, 'http://ice1.somafm.com/bootliquor-128-aac', 'Soma FM - Boot Liquor', 'r', 'local', 'Country, Americana Roots Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (173, 'https://ice5.somafm.com/bossa-128-aac', 'Soma FM - Bossa Beyond', 'r', 'local', 'Bossanova, World', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (174, 'http://ice1.somafm.com/cliqhop-128-aac', 'Soma FM - cliqhop idm', 'r', 'local', 'Electronica, Beats With Clicks and Bleeps', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (175, 'http://ice1.somafm.com/covers-128-mp3', 'Soma FM - Covers', 'r', 'local', 'Pop, Cover Songs', 'Soma FM', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (176, 'http://ice1.somafm.com/deepspaceone-128-aac', 'Soma FM - Deep Space One', 'r', 'local', 'Electronica, Ambient, Experimental, Space Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (177, 'http://ice1.somafm.com/defcon-128-aac', 'Soma FM - DEF CON Radio', 'r', 'local', 'Electronica, DEF CON Conference Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (178, 'http://ice1.somafm.com/digitalis-128-aac', 'Soma FM - Digitalis', 'r', 'local', 'Rock, Digitally Affected Analog Rock', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (179, 'http://ice1.somafm.com/dronezone-128-aac', 'Soma FM - Drone Zone', 'r', 'local', 'Electronica, Ambient, Texture, Atmospheric Texture, Minimal Beats', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (180, 'http://ice1.somafm.com/dubstep-128-aac', 'Soma FM - Dub Step Beyond', 'r', 'local', 'Dub, Dubstep, Deep Bass', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (181, 'http://ice1.somafm.com/fluid-128-aac', 'Soma FM - Fluid', 'r', 'local', 'Electronica, Instrumental Hiphop, Future Soul, Liquid Trap', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (182, 'http://ice1.somafm.com/folkfwd-128-aac', 'Soma FM - Folk Forward', 'r', 'local', 'Folk, Indie Folk, Alternative Folk', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (183, 'http://ice1.somafm.com/groovesalad-128-aac', 'Soma FM - Groove Salad', 'r', 'local', 'Electronica, Ambient, Down-Tempo', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (184, 'http://ice2.somafm.com/gsclassic-128-aac', 'Soma FM - Groove Salad Classic', 'r', 'local', 'Electronica, Ambient, Down-Tempo, Early 2000''s', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (185, 'http://ice4.somafm.com/reggae-128-aac', 'Soma FM - Heavyweight Reggae', 'r', 'local', 'Reggae, Ska, Rocksteady Classic and Deep', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (186, 'http://ice1.somafm.com/illstreet-128-aac', 'Soma FM - Illinois Street Lounge', 'r', 'local', 'Lounge, Bachelor Pad, Exotica, Vintage Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (187, 'http://ice1.somafm.com/indiepop-128-aac', 'Soma FM - Indie Pop Rocks!', 'r', 'local', 'Pop, Indie Pop', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (188, 'http://ice1.somafm.com/seventies-128-aac', 'Soma FM - Left Coast 70s', 'r', 'local', 'Rock, 70''s Mellow Rock', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (189, 'http://ice1.somafm.com/live-128-aac', 'Soma FM - Live', 'r', 'local', 'Electronica, Live, Special Events', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (190, 'http://ice1.somafm.com/lush-128-aac', 'Soma FM - Lush', 'r', 'local', 'Electronica, Mellow Vocals Mostly female', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (191, 'http://ice1.somafm.com/metal-128-aac', 'Soma FM - Metal Detector', 'r', 'local', 'Metal', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (192, 'http://ice1.somafm.com/missioncontrol-128-aac', 'Soma FM - Mission Control', 'r', 'local', 'Electronica, Ambient, NASA Radio Traffic', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (193, 'https://ice6.somafm.com/n5md-128-aac', 'Soma FM - n5MD Radio', 'r', 'local', 'Electronica, Ambient, NASA Radio Traffic', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (194, 'http://ice1.somafm.com/poptron-128-aac', 'Soma FM - PopTron', 'r', 'local', 'Pop, Electro-Pop, indie Dance Rock', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (195, 'http://ice1.somafm.com/secretagent-128-aac', 'Soma FM - Secret Agent', 'r', 'local', 'Pop, Easy-Tempo, 60''s European Pop', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (196, 'http://ice1.somafm.com/7soul-128-aac', 'Soma FM - Seven Inch Soul', 'r', 'local', 'Soul, Vintage Soul From Vinyl 45 RPM Records', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (197, 'http://ice1.somafm.com/sf1033-128-aac', 'Soma FM - SF 10-33', 'r', 'local', 'Electronica, Ambient, San Francisco Public Safety Radio Traffic', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (198, 'http://ice1.somafm.com/sonicuniverse-128-aac', 'Soma FM - Sonic Universe', 'r', 'local', 'Jazz, Nu Jazz, Euro Jazz, Avant-Garde', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (199, 'http://ice1.somafm.com/spacestation-128-aac', 'Soma FM - Space Station Soma', 'r', 'local', 'Electronica, Mid-Tempo', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (200, 'http://ice1.somafm.com/suburbsofgoa-128-aac', 'Soma FM - Suburbs of Goa', 'r', 'local', 'World Music, Desi-Influenced Asian, World Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (201, 'https://ice6.somafm.com/synphaera-128-aac', 'Soma FM - Synphaera', 'r', 'local', 'Electronica, Synth', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (202, 'https://ice6.somafm.com/darkzone-128-aac', 'Soma FM - The Dark Zone', 'r', 'local', 'Electronica, Ambient, Deep Ambient', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (203, 'https://ice5.somafm.com/insound-128-aac', 'Soma FM - The In-Sound', 'r', 'local', 'Pop, Oldies', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (204, 'http://ice1.somafm.com/thetrip-128-aac', 'Soma FM - The Trip', 'r', 'local', 'Pop, Progressive House, Trance', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (205, 'http://ice1.somafm.com/thistle-128-aac', 'Soma FM - ThistleRadio', 'r', 'local', 'Folk, Celtic, Roots Music', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (206, 'https://ice6.somafm.com/tikitime-128-aac', 'Soma FM - Tiki Time', 'r', 'local', 'Tiki, World', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (207, 'http://ice1.somafm.com/u80s-128-aac', 'Soma FM - Underground 80s', 'r', 'local', 'Pop, 80''s, Synth-Pop, New Wave', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (208, 'https://ice1.somafm.com/vaporwaves-128-aac', 'Soma FM - Vaporwaves', 'r', 'local', 'Electronica, Electro-acoustic, IDM, Shoegaze, Post-rock', 'Soma FM', 'English', 'United States', 'North America', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (209, 'http://subfm.radioca.st/Sub.FM', 'SUB.FM - Where Bass Matters', 'r', 'local', 'Dub, Dubstep, Garage, Grime, Deep House, Techno, Juke, Jungle Trap', 'SUB.FM', 'English', 'United Kingdom', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (210, 'http://158.69.74.203:80/', 'SwissGroove', 'r', 'local', 'Jazz, Funk, Soul, World, Latin, Lounge, Nu Grooves', 'SwissGroove', 'English', 'Switzerland', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (211, 'https://liveradio.swr.de/sw331ch/swr2/play.aac', 'SWR 2', 'r', 'local', 'Classical, Jazz', 'Südwestdeutscher Rundfunk', 'German', 'Germany', 'Europe', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (212, 'http://west-aac-64.streamthejazzgroove.com/stream', 'The Jazz Groove', 'r', 'local', 'Jazz', 'The Jazz Groove', 'English', 'United States', 'North America', '64', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (213, 'http://live-radio01.mediahubaustralia.com/2TJW/mp3/', 'Triple J', 'r', 'local', 'Alternative', 'ABC', 'English', 'Australia', 'Asia', '96', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (214, 'http://live-radio01.mediahubaustralia.com/UNEW/mp3/', 'Triple J Unearthed', 'r', 'local', 'Alternative, Indie', 'ABC', 'English', 'Australia', 'Asia', '96', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (215, 'https://uk2.streamingpulse.com/ssl/vcr1', 'Venice Classic Radio Italia', 'r', 'local', 'Classical', 'Venice Classic Radio', 'Italian', 'Italy', 'Europe', '128', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (216, 'http://icecast.vrtcdn.be/klara-high.mp3', 'VRT - Klara', 'r', 'local', 'Classical, Jazz', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (217, 'http://icecast.vrtcdn.be/klaracontinuo-high.mp3', 'VRT - Klara Continuo', 'r', 'local', 'Classical', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (218, 'http://icecast.vrtcdn.be/mnm-high.mp3', 'VRT - MNM', 'r', 'local', 'Pop', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (219, 'http://icecast.vrtcdn.be/mnm_hits-high.mp3', 'VRT - MNM Hits', 'r', 'local', 'Pop, Top 40, Chart', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (220, 'http://icecast.vrtcdn.be/radio1-high.mp3', 'VRT - Radio 1', 'r', 'local', 'Pop, Contemporary, Rock, News', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (221, 'http://icecast.vrtcdn.be/ra2vlb-high.mp3', 'VRT - Radio 2', 'r', 'local', 'Eclectic', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (222, 'http://icecast.vrtcdn.be/stubru-high.mp3', 'VRT - Studio Brussel', 'r', 'local', 'Alternative', 'VRT', 'Dutch', 'Belgium', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (223, 'https://ice64.securenetsystems.net/WBJC', 'WBJC Baltimore 91.5 - Classical', 'r', 'local', 'Classical', 'Baltimore City Community College', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (224, 'http://audio1.ideastream.org/wclv.mp3', 'WCLV Cleveland 104.9 - Classical', 'r', 'local', 'Classical', 'Ideastream', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (225, 'https://wgbh-live.streamguys1.com/classical-hi', 'WCRB Boston 99.5 - Classical', 'r', 'local', 'Classical', 'WCRB', 'English', 'United States', 'North America', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (226, 'http://wdcb-ice.streamguys.org:80/wdcb128', 'WDCB Chicago FM 90.9 - Jazz & Blues', 'r', 'local', 'Blues, Jazz', 'DuPage College', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (227, 'http://wdr-1live-live.icecast.wdr.de/wdr/1live/live/mp3/128/stream.mp3', 'WDR 1LIVE', 'r', 'local', 'Pop', 'WDR', 'German', 'Germany', 'Europe', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (228, 'https://wdr-wdr3-live.icecastssl.wdr.de/wdr/wdr3/live/mp3/256/stream.mp3', 'WDR 3', 'r', 'local', 'Classical, Jazz', 'Westdeutscher Rundfun', 'German', 'Germany', 'Europe', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (229, 'https://playerservices.streamtheworld.com/api/livestream-redirect/WEMUFM.mp3', 'WEMU Ypsilanti FM 89.1 - Jazz', 'r', 'local', 'Jazz', 'WEMU', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (230, 'http://stream.wfmt.com/main', 'WFMT Chicago 98.7 - Classical', 'r', 'local', 'Classical', 'WYMT', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (231, 'http://stream0.wfmu.org/freeform-best-available', 'WFMU 91.1 FM', 'r', 'local', 'Classical', 'WFMU', 'English', 'United States', 'North America', '256', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (232, 'http://wkcr.streamguys1.com:80/live', 'WKCR 89.9 FM', 'r', 'local', 'Jazz, Classical', 'WKCR (Columbia University)', 'English', 'United States', 'North America', '96', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (233, 'http://ice-1.streamhoster.com/lv_wqed--893', 'WQED Pittsburgh 89.3 - Classical', 'r', 'local', 'Classical', 'WQED', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (234, 'http://stream.wqxr.org/wqxr', 'WQXR New York - Classical Music', 'r', 'local', 'Classical', 'New York Public Radio', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (235, 'http://q2stream.wqxr.org/q2', 'WQXR Q2 - Living Music, Living Composers', 'r', 'local', 'Classical', 'New York Public Radio', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (236, 'http://playerservices.streamtheworld.com/api/livestream-redirect/WRTI_CLASSICAL.mp3', 'WRTI Philadelphia 90.1 - Classical', 'r', 'local', 'Classical', 'Temple University', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (237, 'http://playerservices.streamtheworld.com/api/livestream-redirect/WRTI_JAZZ.mp3', 'WRTI Philadelphia 90.1 - Jazz', 'r', 'local', 'Jazz', 'Temple University', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (238, 'https://www.wwoz.org/listen/hi', 'WWOZ New Orleans FM 90.7 - Various Artists', 'r', 'local', 'Jazz, Blues, Latin, Cajun, Funk', 'WWOZ', 'English', 'United States', 'North America', '128', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (239, 'http://streams.norbert.de:8000/zappa.aac', 'Zappa Stream Radio', 'r', 'local', 'Progressive Rock, Rock', 'Zappa Stream Radio', 'English', 'United States', 'North America', '256', 'AAC', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (240, 'https://22653.live.streamtheworld.com/TOPZEN.mp3', 'Zen FM', 'r', 'local', 'Lounge', 'Zen FM', 'Dutch', 'Belgium', 'Europe', '192', 'MP3', 'No', '', 'No');
INSERT INTO cfg_radio (id, station, name, type, logo, genre, broadcaster, language, country, region, bitrate, format, geo_fenced, home_page, monitor) VALUES (499, 'zx reserved 499', 'zx reserved 499', 'r', 'zx reserved 499', '', '', '', '', '', '', '', '', '', '');

-- Table: cfg_sl
CREATE TABLE cfg_sl (id INTEGER PRIMARY KEY, param CHAR (20), value CHAR (64));
INSERT INTO cfg_sl (id, param, value) VALUES (1, 'PLAYERNAME', 'Moode');
INSERT INTO cfg_sl (id, param, value) VALUES (2, 'AUDIODEVICE', '_audioout');
INSERT INTO cfg_sl (id, param, value) VALUES (3, 'ALSAPARAMS', '80:4::1');
INSERT INTO cfg_sl (id, param, value) VALUES (4, 'OUTPUTBUFFERS', '40000:100000');
INSERT INTO cfg_sl (id, param, value) VALUES (5, 'TASKPRIORITY', '45');
INSERT INTO cfg_sl (id, param, value) VALUES (6, 'CODECS', 'flac,pcm,mp3,ogg,aac,alac,dsd');
INSERT INTO cfg_sl (id, param, value) VALUES (7, 'OTHEROPTIONS', '-W -D 500 -R E -S /var/local/www/commandw/slpower.sh');

-- Table: cfg_upnp
CREATE TABLE cfg_upnp (id INTEGER PRIMARY KEY, param CHAR (32), value CHAR (32));
INSERT INTO cfg_upnp (id, param, value) VALUES (1, 'upnpav', '1');
INSERT INTO cfg_upnp (id, param, value) VALUES (2, 'openhome', '0');
INSERT INTO cfg_upnp (id, param, value) VALUES (3, 'checkcontentformat', '1');
INSERT INTO cfg_upnp (id, param, value) VALUES (10, 'qobuzuser', '');
INSERT INTO cfg_upnp (id, param, value) VALUES (11, 'qobuzpass', '');
INSERT INTO cfg_upnp (id, param, value) VALUES (12, 'qobuzformatid', '6');
INSERT INTO cfg_upnp (id, param, value) VALUES (20, 'tidalenablepkce', '0');
INSERT INTO cfg_upnp (id, param, value) VALUES (21, 'tidaltokentype', 'Bearer');
INSERT INTO cfg_upnp (id, param, value) VALUES (22, 'tidalaccesstoken', 'your_oauth2_access_token');
INSERT INTO cfg_upnp (id, param, value) VALUES (23, 'tidalrefreshtoken', 'your_oauth2_refresh_token');
INSERT INTO cfg_upnp (id, param, value) VALUES (24, 'tidalexpirytime', '1697143990.40669');
INSERT INTO cfg_upnp (id, param, value) VALUES (25, 'tidalpkcetokentype', 'Bearer');
INSERT INTO cfg_upnp (id, param, value) VALUES (26, 'tidalpkceaccesstoken', 'your_pkce_access_token');
INSERT INTO cfg_upnp (id, param, value) VALUES (27, 'tidalpkcerefreshtoken', 'your_pkce_refresh_token');
INSERT INTO cfg_upnp (id, param, value) VALUES (28, 'tidalpkcesessionid', 'your_pkce_session_id');
INSERT INTO cfg_upnp (id, param, value) VALUES (29, 'tidalaudioquality', 'LOSSLESS');

-- Table: cfg_source
CREATE TABLE cfg_source (
id INTEGER PRIMARY KEY,
name CHAR(25),
type CHAR(8),
address CHAR(15),
remotedir CHAR(30),
username CHAR(30),
password CHAR(60),
charset CHAR(15),
rsize INT(4),
wsize INT(4)
, options CHAR(60), error CHAR(150));

-- Table: cfg_spotify
CREATE TABLE cfg_spotify (id INTEGER PRIMARY KEY, param CHAR (32), value CHAR (32));
INSERT INTO cfg_spotify (id, param, value) VALUES (1, 'bitrate', '320');
INSERT INTO cfg_spotify (id, param, value) VALUES (2, 'initial_volume', '0');
INSERT INTO cfg_spotify (id, param, value) VALUES (3, 'volume_curve', 'log');
INSERT INTO cfg_spotify (id, param, value) VALUES (4, 'volume_normalization', 'No');
INSERT INTO cfg_spotify (id, param, value) VALUES (5, 'normalization_pregain', '0');
INSERT INTO cfg_spotify (id, param, value) VALUES (6, 'autoplay', 'No');
INSERT INTO cfg_spotify (id, param, value) VALUES (7, 'normalization_method', 'dynamic');
INSERT INTO cfg_spotify (id, param, value) VALUES (8, 'normalization_gain_type', 'auto');
INSERT INTO cfg_spotify (id, param, value) VALUES (9, 'normalization_threshold', '-2');
INSERT INTO cfg_spotify (id, param, value) VALUES (10, 'normalization_attack', '5');
INSERT INTO cfg_spotify (id, param, value) VALUES (11, 'normalization_release', '100');
INSERT INTO cfg_spotify (id, param, value) VALUES (12, 'normalization_knee', '1');
INSERT INTO cfg_spotify (id, param, value) VALUES (13, 'format', 'S16');
INSERT INTO cfg_spotify (id, param, value) VALUES (14, 'dither', '');
INSERT INTO cfg_spotify (id, param, value) VALUES (15, 'volume_range', '60');

-- Table: cfg_theme
CREATE TABLE cfg_theme (id INTEGER PRIMARY KEY, theme_name CHAR (32), tx_color CHAR (32), bg_color CHAR (32), mbg_color CHAR (32));
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (1, 'Default', 'ddd', '32,32,32', '50, 50, 50, 0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (2, 'Cinnamon', 'ddd', '128,60,38', '140,66,42,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (3, 'Chikory Root', 'ddd', '63,62,60', '71,70,67,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (4, 'Fern', 'ddd', '61,105,56', '67,115,61,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (5, 'Green Tea', '333', '205,216,156', '211,220,167,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (6, 'Lilium', '333', '243,234,187', '245,238,200,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (7, 'Mango', '333', '222,178,102', '225,184,115,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (8, 'Marooned', 'ddd', '96,18,19', '109,20,22,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (9, 'Nightshade', 'ddd', '27,24,48', '33,29,58,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (10, 'Pure Black', 'ddd', '0,0,0', '50, 50, 50, 0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (11, 'Purple Rain', 'ddd', '38,21,63', '45,25,74,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (12, 'Putty', '333', '176,176,176', '184,184,184,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (13, 'Sandstone', 'ddd', '120,106,88', '129,114,94,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (14, 'Serene Sky', 'ddd', '84,109,155', '89,116,165,0.75');
INSERT INTO cfg_theme (id, theme_name, tx_color, bg_color, mbg_color) VALUES (15, 'Whiteshade', '333', '243,243,243', '251,251,251,0.75');

-- Table: cfg_ssid
CREATE TABLE cfg_ssid (id INTEGER PRIMARY KEY, ssid CHAR (32), uuid CHAR (32), psk CHAR (32), method CHAR (32), ipaddr CHAR (32), netmask CHAR (32), gateway CHAR (32), pridns CHAR (32), secdns CHAR (32));

-- Table: cfg_system
CREATE TABLE cfg_system (id INTEGER PRIMARY KEY, param CHAR (32), value CHAR (32));
INSERT INTO cfg_system (id, param, value) VALUES (1, 'sessionid', 'ho7vk67sqrjua8sme0pqhsjgdq');
INSERT INTO cfg_system (id, param, value) VALUES (2, 'timezone', 'America/Detroit');
INSERT INTO cfg_system (id, param, value) VALUES (3, 'i2sdevice', 'None');
INSERT INTO cfg_system (id, param, value) VALUES (4, 'hostname', 'moode');
INSERT INTO cfg_system (id, param, value) VALUES (5, 'browsertitle', 'moOde Player');
INSERT INTO cfg_system (id, param, value) VALUES (6, 'airplayname', 'Moode AirPlay');
INSERT INTO cfg_system (id, param, value) VALUES (7, 'upnpname', 'Moode UPNP');
INSERT INTO cfg_system (id, param, value) VALUES (8, 'dlnaname', 'Moode DLNA');
INSERT INTO cfg_system (id, param, value) VALUES (9, 'airplaysvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (10, 'upnpsvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (11, 'dlnasvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (12, 'alsa_output_mode', 'iec958');
INSERT INTO cfg_system (id, param, value) VALUES (13, 'paactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (14, 'autoplay', '0');
INSERT INTO cfg_system (id, param, value) VALUES (15, 'rbsvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (16, 'res_plugin_upd_url', 'https://raw.githubusercontent.com/moode-player/plugins/main');
INSERT INTO cfg_system (id, param, value) VALUES (17, 'rbactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (18, 'adevname', 'Pi HDMI 1');
INSERT INTO cfg_system (id, param, value) VALUES (19, 'clkradio_mode', 'Disabled');
INSERT INTO cfg_system (id, param, value) VALUES (20, 'clkradio_item', '0');
INSERT INTO cfg_system (id, param, value) VALUES (21, 'clkradio_name', '');
INSERT INTO cfg_system (id, param, value) VALUES (22, 'clkradio_start', '06,00,AM,0,0,0,0,0,0,0');
INSERT INTO cfg_system (id, param, value) VALUES (23, 'clkradio_stop', '07,00,AM,0,0,0,0,0,0,0');
INSERT INTO cfg_system (id, param, value) VALUES (24, 'clkradio_volume', '10');
INSERT INTO cfg_system (id, param, value) VALUES (25, 'clkradio_action', 'None');
INSERT INTO cfg_system (id, param, value) VALUES (26, 'playhist', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (27, 'phistsong', '');
INSERT INTO cfg_system (id, param, value) VALUES (28, 'library_utf8rep', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (29, 'current_view', 'playback,folder');
INSERT INTO cfg_system (id, param, value) VALUES (30, 'timecountup', '0');
INSERT INTO cfg_system (id, param, value) VALUES (31, 'accent_color', 'Carrot');
INSERT INTO cfg_system (id, param, value) VALUES (32, 'volknob', '0');
INSERT INTO cfg_system (id, param, value) VALUES (33, 'volmute', '0');
INSERT INTO cfg_system (id, param, value) VALUES (34, 'alsavolume_max', '100');
INSERT INTO cfg_system (id, param, value) VALUES (35, 'alsavolume', '0');
INSERT INTO cfg_system (id, param, value) VALUES (36, 'amixname', 'HDMI');
INSERT INTO cfg_system (id, param, value) VALUES (37, 'mpdmixer', 'software');
INSERT INTO cfg_system (id, param, value) VALUES (38, 'extra_tags', 'encoded,output,track,date,composer');
INSERT INTO cfg_system (id, param, value) VALUES (39, 'rsmafterapl', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (40, 'lcdup', '0');
INSERT INTO cfg_system (id, param, value) VALUES (41, 'library_show_genres', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (42, 'extmeta', '0');
INSERT INTO cfg_system (id, param, value) VALUES (43, 'i2soverlay', 'None');
INSERT INTO cfg_system (id, param, value) VALUES (44, 'folder_pos', '-1');
INSERT INTO cfg_system (id, param, value) VALUES (45, 'crossfeed', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (46, 'bluez_pcm_buffer', '500000');
INSERT INTO cfg_system (id, param, value) VALUES (47, 'fs_nfs_options', 'rw,sync,no_subtree_check,no_root_squash');
INSERT INTO cfg_system (id, param, value) VALUES (48, 'library_onetouch_album', 'Show tracks');
INSERT INTO cfg_system (id, param, value) VALUES (49, 'radio_pos', '-1');
INSERT INTO cfg_system (id, param, value) VALUES (50, 'aplactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (51, 'ipaddr_timeout', '90');
INSERT INTO cfg_system (id, param, value) VALUES (52, 'ashufflesvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (53, 'ashuffle', '0');
INSERT INTO cfg_system (id, param, value) VALUES (54, 'camilladsp', 'off');
INSERT INTO cfg_system (id, param, value) VALUES (55, 'cdsp_fix_playback', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (56, 'camilladsp_quickconv', '0;Sennheiser_HD800S_L_44100Hz_32b.raw;Sennheiser_HD800S_R_44100Hz_32b.raw;S32LE''');
INSERT INTO cfg_system (id, param, value) VALUES (57, 'alsa_loopback', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (58, 'keyboard', 'us');
INSERT INTO cfg_system (id, param, value) VALUES (59, 'localui', '0');
INSERT INTO cfg_system (id, param, value) VALUES (60, 'toggle_songid', '');
INSERT INTO cfg_system (id, param, value) VALUES (61, 'slsvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (62, 'ap_network_addr', '172.24.1.1/24');
INSERT INTO cfg_system (id, param, value) VALUES (63, 'cpugov', 'ondemand');
INSERT INTO cfg_system (id, param, value) VALUES (64, 'pasvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (65, 'pkgid_suffix', '');
INSERT INTO cfg_system (id, param, value) VALUES (66, 'lib_pos', '-1,-1,-1');
INSERT INTO cfg_system (id, param, value) VALUES (67, 'mpdcrossfade', '0');
INSERT INTO cfg_system (id, param, value) VALUES (68, 'eth0chk', '0');
INSERT INTO cfg_system (id, param, value) VALUES (69, 'usb_auto_mounter', 'udisks-glue');
INSERT INTO cfg_system (id, param, value) VALUES (70, 'rsmafterbt', '0');
INSERT INTO cfg_system (id, param, value) VALUES (71, 'rotenc_params', '100 2 3 23 24');
INSERT INTO cfg_system (id, param, value) VALUES (72, 'shellinabox', '0');
INSERT INTO cfg_system (id, param, value) VALUES (73, 'alsaequal', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (74, 'eqfa12p', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (75, 'p3wifi', '1');
INSERT INTO cfg_system (id, param, value) VALUES (76, 'p3bt', '1');
INSERT INTO cfg_system (id, param, value) VALUES (77, 'cardnum', '0');
INSERT INTO cfg_system (id, param, value) VALUES (78, 'btsvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (79, 'btname', 'Moode Bluetooth');
INSERT INTO cfg_system (id, param, value) VALUES (80, 'camilladsp_volume_sync', 'off');
INSERT INTO cfg_system (id, param, value) VALUES (81, 'feat_bitmask', '97207');
INSERT INTO cfg_system (id, param, value) VALUES (82, 'library_recently_added', '2592000000');
INSERT INTO cfg_system (id, param, value) VALUES (83, 'btactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (84, 'touchscn', '1');
INSERT INTO cfg_system (id, param, value) VALUES (85, 'scnblank', '600');
INSERT INTO cfg_system (id, param, value) VALUES (86, 'scnrotate', '0');
INSERT INTO cfg_system (id, param, value) VALUES (87, 'scnbrightness', '255');
INSERT INTO cfg_system (id, param, value) VALUES (88, 'themename', 'Default');
INSERT INTO cfg_system (id, param, value) VALUES (89, 'res_software_upd_url', 'https://raw.githubusercontent.com/moode-player/updates/main/moode-player');
INSERT INTO cfg_system (id, param, value) VALUES (90, 'alphablend', '0.75');
INSERT INTO cfg_system (id, param, value) VALUES (91, 'adaptive', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (92, 'audioout', 'Local');
INSERT INTO cfg_system (id, param, value) VALUES (93, 'audioin', 'Local');
INSERT INTO cfg_system (id, param, value) VALUES (94, 'slactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (95, 'rsmaftersl', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (96, 'mpdmixer_local', 'software');
INSERT INTO cfg_system (id, param, value) VALUES (97, 'wrkready', '0');
INSERT INTO cfg_system (id, param, value) VALUES (98, 'scnsaver_timeout', 'Never');
INSERT INTO cfg_system (id, param, value) VALUES (99, 'pixel_aspect_ratio', 'Default');
INSERT INTO cfg_system (id, param, value) VALUES (100, 'favorites_name', 'Favorites');
INSERT INTO cfg_system (id, param, value) VALUES (101, 'spotifysvc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (102, 'spotifyname', 'Moode Spotify');
INSERT INTO cfg_system (id, param, value) VALUES (103, 'spotactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (104, 'rsmafterspot', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (105, 'library_covsearchpri', 'Embedded cover');
INSERT INTO cfg_system (id, param, value) VALUES (106, 'library_hiresthm', '600px,60');
INSERT INTO cfg_system (id, param, value) VALUES (107, 'library_pixelratio', '1');
INSERT INTO cfg_system (id, param, value) VALUES (108, 'RESERVED_108', '');
INSERT INTO cfg_system (id, param, value) VALUES (109, 'cover_backdrop', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (110, 'cover_blur', '5px');
INSERT INTO cfg_system (id, param, value) VALUES (111, 'cover_scale', '1.25');
INSERT INTO cfg_system (id, param, value) VALUES (112, 'rsmafterrb', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (113, 'library_tagview_artist', 'Artist');
INSERT INTO cfg_system (id, param, value) VALUES (114, 'scnsaver_style', 'Gradient (Linear)');
INSERT INTO cfg_system (id, param, value) VALUES (115, 'rsmafterpa', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (116, 'mpd_httpd', '0');
INSERT INTO cfg_system (id, param, value) VALUES (117, 'mpd_httpd_port', '8000');
INSERT INTO cfg_system (id, param, value) VALUES (118, 'mpd_httpd_encoder', 'lame');
INSERT INTO cfg_system (id, param, value) VALUES (119, 'invert_polarity', '0');
INSERT INTO cfg_system (id, param, value) VALUES (120, 'inpactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (121, 'rsmafterinp', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (122, 'gpio_svc', '0');
INSERT INTO cfg_system (id, param, value) VALUES (123, 'library_ignore_articles', 'a,an,the');
INSERT INTO cfg_system (id, param, value) VALUES (124, 'volknob_mpd', '-1');
INSERT INTO cfg_system (id, param, value) VALUES (125, 'volknob_preamp', '0');
INSERT INTO cfg_system (id, param, value) VALUES (126, 'library_albumview_sort', 'Artist/Year');
INSERT INTO cfg_system (id, param, value) VALUES (127, 'library_thmgen_scan', 'Default');
INSERT INTO cfg_system (id, param, value) VALUES (128, 'wake_display', '0');
INSERT INTO cfg_system (id, param, value) VALUES (129, 'usb_volknob', '0');
INSERT INTO cfg_system (id, param, value) VALUES (130, 'led_state', '1,1');
INSERT INTO cfg_system (id, param, value) VALUES (131, 'library_tagview_covers', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (132, 'library_tagview_sort', 'Album/Year');
INSERT INTO cfg_system (id, param, value) VALUES (133, 'library_ellipsis_limited_text', 'No');
INSERT INTO cfg_system (id, param, value) VALUES (134, 'preferences_modal_state', '1,0,0,0,0');
INSERT INTO cfg_system (id, param, value) VALUES (135, 'font_size', 'Normal');
INSERT INTO cfg_system (id, param, value) VALUES (136, 'volume_step_limit', '5');
INSERT INTO cfg_system (id, param, value) VALUES (137, 'volume_mpd_max', '100');
INSERT INTO cfg_system (id, param, value) VALUES (138, 'library_thumbnail_columns', '6/2 (Default)');
INSERT INTO cfg_system (id, param, value) VALUES (139, 'library_encoded_at', '1');
INSERT INTO cfg_system (id, param, value) VALUES (140, 'first_use_help', 'y,y');
INSERT INTO cfg_system (id, param, value) VALUES (141, 'playlist_art', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (142, 'library_onetouch_ralbum', 'No action');
INSERT INTO cfg_system (id, param, value) VALUES (143, 'radioview_sort_group', 'Name,No grouping');
INSERT INTO cfg_system (id, param, value) VALUES (144, 'radioview_show_hide', 'No action,No action');
INSERT INTO cfg_system (id, param, value) VALUES (145, 'renderer_backdrop', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (146, 'library_flatlist_filter', 'full_lib');
INSERT INTO cfg_system (id, param, value) VALUES (147, 'library_flatlist_filter_str', '');
INSERT INTO cfg_system (id, param, value) VALUES (148, 'library_misc_options', 'No,Album@Artist (Default)');
INSERT INTO cfg_system (id, param, value) VALUES (149, 'recorder_status', 'Not installed');
INSERT INTO cfg_system (id, param, value) VALUES (150, 'recorder_storage', '/mnt/SDCARD');
INSERT INTO cfg_system (id, param, value) VALUES (151, 'volume_db_display', '1');
INSERT INTO cfg_system (id, param, value) VALUES (152, 'search_site', 'Google');
INSERT INTO cfg_system (id, param, value) VALUES (153, 'cuefiles_ignore', '0');
INSERT INTO cfg_system (id, param, value) VALUES (154, 'recorder_album_tag', 'Recorded YYYY-MM-DD');
INSERT INTO cfg_system (id, param, value) VALUES (155, 'inplace_upd_applied', '0');
INSERT INTO cfg_system (id, param, value) VALUES (156, 'show_npicon', 'Waveform');
INSERT INTO cfg_system (id, param, value) VALUES (157, 'show_cvpb', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (158, 'multiroom_tx', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (159, 'multiroom_rx', 'Disabled');
INSERT INTO cfg_system (id, param, value) VALUES (160, 'rxactive', '0');
INSERT INTO cfg_system (id, param, value) VALUES (161, 'library_onetouch_radio', 'Play');
INSERT INTO cfg_system (id, param, value) VALUES (162, 'library_tagview_genre', 'Genre');
INSERT INTO cfg_system (id, param, value) VALUES (163, 'auto_coverview', '-off');
INSERT INTO cfg_system (id, param, value) VALUES (164, 'maint_interval', '21600');
INSERT INTO cfg_system (id, param, value) VALUES (165, 'library_track_play', 'Track');
INSERT INTO cfg_system (id, param, value) VALUES (166, 'playlist_pos', '-1');
INSERT INTO cfg_system (id, param, value) VALUES (167, 'plview_sort_group', 'Name,No grouping');
INSERT INTO cfg_system (id, param, value) VALUES (168, 'fs_smb', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (169, 'fs_nfs', 'Off');
INSERT INTO cfg_system (id, param, value) VALUES (170, 'fs_nfs_access', '');
INSERT INTO cfg_system (id, param, value) VALUES (171, 'native_lazyload', 'Yes');
INSERT INTO cfg_system (id, param, value) VALUES (172, 'library_onetouch_pl', 'Show items');
INSERT INTO cfg_system (id, param, value) VALUES (173, 'scnsaver_mode', 'Cover art');
INSERT INTO cfg_system (id, param, value) VALUES (174, 'scnsaver_layout', 'Default');
INSERT INTO cfg_system (id, param, value) VALUES (175, 'scnsaver_xmeta', 'Yes');

-- Table: cfg_plugin
CREATE TABLE cfg_plugin (id INTEGER PRIMARY KEY, component CHAR (32), type CHAR (32), plugin CHAR (32));
INSERT INTO cfg_plugin (id, component, type, plugin) VALUES (1, 'camilladsp', 'sample-configs', 'v2-sample-configs');

-- Table: cfg_outputdev
CREATE TABLE cfg_outputdev (id INTEGER PRIMARY KEY, device_name CHAR (32), mpd_volume_type CHAR (32), alsa_output_mode CHAR (32), alsa_max_volume CHAR (32));

-- Table: cfg_eqalsa
CREATE TABLE cfg_eqalsa (id INTEGER PRIMARY KEY, curve_name CHAR (32), curve_values CHAR (32));
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (1, 'Flat', '60,60,60,60,60,60,60,60,60,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (2, 'Lo Boost', '60,72,60,60,60,60,60,60,60,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (3, 'Lo Boost Plus', '68,78,68,60,60,60,60,60,60,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (4, 'Hi Boost', '60,60,60,60,60,60,60,60,72,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (5, 'Hi Boost Plus', '60,60,60,60,60,60,60,68,78,68');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (6, 'Hi-Lo Boost', '60,72,60,60,60,60,60,60,72,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (7, 'Hi-Lo Boost Plus', '68,78,68,60,60,60,60,68,78,68');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (8, 'Midrange Suppress', '60,60,60,60,39,39,60,60,60,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (9, 'Shallow V', '60,68,60,54,44,44,54,60,68,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (10, 'Classic V', '60,72,60,60,39,39,60,60,72,60');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (11, 'Classic V Plus', '68,78,68,60,39,39,60,68,78,68');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (12, 'Vinyl Touch', '60,66,62,61,60,60,60,54,48,68');
INSERT INTO cfg_eqalsa (id, curve_name, curve_values) VALUES (13, 'Vinyl Touch Plus', '60,68,64,60,60,60,60,46,41,68');

-- Table: cfg_airplay
CREATE TABLE cfg_airplay (id INTEGER PRIMARY KEY, param CHAR (32), value CHAR (32));
INSERT INTO cfg_airplay (id, param, value) VALUES (1, 'airplaymeta', 'deprecated');
INSERT INTO cfg_airplay (id, param, value) VALUES (2, 'airplayvol', 'deprecated');
INSERT INTO cfg_airplay (id, param, value) VALUES (3, 'interpolation', 'soxr');
INSERT INTO cfg_airplay (id, param, value) VALUES (4, 'output_format', 'S16');
INSERT INTO cfg_airplay (id, param, value) VALUES (5, 'output_rate', '44100');
INSERT INTO cfg_airplay (id, param, value) VALUES (6, 'allow_session_interruption', 'no');
INSERT INTO cfg_airplay (id, param, value) VALUES (7, 'session_timeout', '120');
INSERT INTO cfg_airplay (id, param, value) VALUES (8, 'audio_backend_latency_offset_in_seconds', '0.0');
INSERT INTO cfg_airplay (id, param, value) VALUES (9, 'audio_backend_buffer_desired_length_in_seconds', '0.2');

-- Table: cfg_audiodev
CREATE TABLE cfg_audiodev (id INTEGER PRIMARY KEY, name CHAR (64), alt_name CHAR (64), dacchip CHAR (64), chipoptions CHAR (64), iface CHAR (32), list CHAR (10), driver CHAR (64), drvoptions CHAR (64));
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (1, 'Allo Boss 2 DAC', '', 'Cirrus Logic CS43198', 'off,Fast,on,off,off,off', 'I2S', 'yes', 'allo-boss2-dac-audio', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (2, 'Allo Boss DAC', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'allo-boss-dac-pcm512x-audio', 'slave');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (3, 'Allo DigiOne', '', 'Cirrus Logic WM8805', '', 'I2S', 'yes', 'allo-digione', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (4, 'Allo DigiOne Signature', '', 'Cirrus Logic WM8805', '', 'I2S', 'yes', 'allo-digione', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (5, 'Allo Katana DAC', '', 'ESS Sabre ES9038Q2M', 'Apodizing Fast Roll-off Filter,Bypass,on', 'I2S', 'yes', 'allo-katana-dac-audio', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (6, 'Allo MiniBoss DAC', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'allo-boss-dac-pcm512x-audio', 'slave');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (7, 'Allo Piano 2.1 Hi-Fi DAC', '', 'Burr Brown PCM5142', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'allo-piano-dac-plus-pcm512x-audio', 'glb_mclk');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (8, 'Allo Piano Hi-Fi DAC', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'allo-piano-dac-pcm512x-audio', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (9, 'Audiophonics ES9018 DAC', '', 'ESS Sabre ES9018 K2M', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (10, 'Audiophonics ES9023 DAC', '', 'ESS Sabre ES9023', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (11, 'Audiophonics ES9028/9038 DAC', '', 'ESS Sabre ES9028/9038 Q2M', 'brick wall,I2S', 'I2S', 'yes', 'i-sabre-q2m', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (12, 'Audiophonics PCM5102 DAC', '', 'Burr Brown PCM5102A', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (13, 'Audiophonics PCM5122 DAC', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (14, 'Audiophonics TDA1387 DAC', '', 'Philips TDA1387', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (15, 'DDDAC1794 NOS', '', 'Burr Brown PCM1794', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (16, 'Generic-I2S (hifiberry-dac)', '', 'Passive I2S DAC', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (17, 'Generic-I2S (i2s-dac)', '', 'Passive I2S DAC', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (18, 'HIFI DAC', '', 'Burr Brown PCM5102A', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (19, 'HIFI DAC+', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (20, 'HIFI Digi', '', 'Wolfson WM8804G', '', 'I2S', 'yes', 'hifiberry-digi', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (21, 'HIFI Digi+', '', 'Wolfson WM8804G', '', 'I2S', 'yes', 'hifiberry-digi', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (22, 'HiFiBerry Amp(Amp+)', '', 'Burr Brown TAS5713', '', 'I2S', 'yes', 'hifiberry-amp', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (23, 'HiFiBerry Amp2/4', '', 'Burr Brown TAS5756M', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplus-std', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (24, 'HiFiBerry Beocreate', '', 'Burr Brown PCM4104 DAC, TPA3128 Amp, Analog Devices ADAU1451 DSP', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (25, 'HiFiBerry DAC', '', 'Burr Brown PCM5102A', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (26, 'HiFiBerry DAC+', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplus-std', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (27, 'HiFiBerry DAC+ ADC', '', 'Burr Brown PCM5122, PCM1861 ADC', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplusadc', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (28, 'HiFiBerry DAC+ DSP', '', 'Burr Brown PCM5102A, Analog Devices ADAU1451 DSP', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (29, 'HiFiBerry DAC+ Light', '', 'ESS Sabre ES9023', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (30, 'HiFiBerry DAC+ Pro', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplus-pro', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (31, 'HiFiBerry DAC+ Zero', '', 'Burr Brown PCM5101A', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (32, 'HiFiBerry Digi(Digi+)', '', 'Cirrus Logic WM8804', '', 'I2S', 'yes', 'hifiberry-digi', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (33, 'HiFiBerry Digi+ Pro', '', 'Cirrus Logic WM8804', '', 'I2S', 'yes', 'hifiberry-digi-pro', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (34, 'HiFiBerry MiniAmp', '', 'Burr Brown PCM5101A, Diodes PAM8403', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (35, 'IQaudIO Pi-AMP+', '', 'Burr Brown TPA3118', '', 'I2S', 'yes', 'iqaudio-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (36, 'IQaudIO Pi-DAC', '', 'Burr Brown PCM5122', '', 'I2S', 'yes', 'iqaudio-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (37, 'IQaudIO Pi-DAC PRO', '', 'Burr Brown PCM5242', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'iqaudio-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (38, 'IQaudIO Pi-DAC+', '', 'Burr Brown PCM5122', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'iqaudio-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (39, 'IQaudIO Pi-DACZero', '', 'Burr Brown PCM5122', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'iqaudio-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (40, 'IQaudIO Pi-Digi+', '', 'Wolfson WM8804', '', 'I2S', 'yes', 'iqaudio-digi-wm8804-audio', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (41, 'IQaudIO Pi-DigiAMP+', '', 'Burr Brown TAS5756', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'iqaudio-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (42, 'JustBoom AMP HAT(Zero)', '', 'Burr Brown TAS5756', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'justboom-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (43, 'JustBoom DAC HAT(Zero)', '', 'Burr Brown PCM5122 (PCM5121)', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'justboom-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (44, 'JustBoom Digi HAT(Zero)', '', 'Wolfson WM8804G', '', 'I2S', 'yes', 'justboom-digi', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (45, 'Mamboberry HiFi DAC+', '', 'ESS Sabre ES9032', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (46, 'Mamboberry LS DAC+', '', 'ESS Sabre ES9023p', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (47, 'MERUS(tm) Amp piHAT ZW', '', 'Infineon MA12070P', 'PMF0', 'I2S', 'yes', 'merus-amp', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (48, 'Pi2Design 502DAC', '', 'Burr Brown PCM5122, Wolfson WM8804', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'hifiberry-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (49, 'Pi2Design 502DAC PRO', '', 'Burr Brown PCM1792, Wolfson WM8804', '', 'I2S', 'yes', 'hifiberry-digi-pro', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (50, 'Pi2Design 503HTA Hybrid Tube Amp', '', 'Burr Brown PCM5102A', '', 'I2S', 'yes', 'hifiberry-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (51, 'ProtoDAC TDA1387 X8', '', 'Philips TDA1387 (8 chip module)', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (52, 'ProtoDAC TDA1387 X8 (FifoPiMa)', '', 'Philips TDA1387 (8 chip module)', '', 'I2S', 'yes', 'hifiberry-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (53, 'Raspberry Pi Codec Zero', '', 'Dialog Semiconductor DA7212', '', 'I2S', 'yes', 'rpi-codeczero', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (54, 'Raspberry Pi DAC Pro', '', 'Burr Brown PCM5242', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'rpi-dacpro', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (55, 'Raspberry Pi DAC+', '', 'Burr Brown PCM5242', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'rpi-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (56, 'Raspberry Pi DigiAMP+', '', 'Burr Brown TAS5756', '100,100,FIR interpolation with de-emphasis', 'I2S', 'yes', 'rpi-digiampplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (57, 'Soekris DAM', '', 'FPGA based', '', 'I2S', 'yes', 'i2s-dac', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (58, 'Suptronics x400', '', 'Burr Brown PCM5122', '100,0,FIR interpolation with de-emphasis', 'I2S', 'yes', 'iqaudio-dacplus', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (200, 'b1', 'Pi HDMI 1', 'Broadcom SoC', '', 'SOC', 'yes', '', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (201, 'b2', 'Pi HDMI 2', 'Broadcom SoC', '', 'SOC', 'yes', '', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (202, 'Headphones', 'Pi Headphone jack', 'Broadcom SoC', '', 'SOC', 'yes', '', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (203, 'vc4hdmi0', 'Pi HDMI 1', 'Broadcom SoC (KMS driver)', '', 'SOC', 'yes', '', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (204, 'vc4hdmi1', 'Pi HDMI 2', 'Broadcom SoC (KMS driver)', '', 'SOC', 'yes', '', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (300, 'Revolution', 'Allo Revolution DAC', 'ESS Sabre ES9038Q2M', '', 'USB', 'yes', '', '');
INSERT INTO cfg_audiodev (id, name, alt_name, dacchip, chipoptions, iface, list, driver, drvoptions) VALUES (301, 'DAC8STEREO', 'okto research dac8 Stereo', 'ESS Sabre ES9028PRO', '', 'USB', 'yes', '', '');

-- Table: cfg_eqp12
CREATE TABLE cfg_eqp12 (id INTEGER PRIMARY KEY, curve_name CHAR (32), settings TEXT, active BOOLEAN, bands INTEGER);
INSERT INTO cfg_eqp12 (id, curve_name, settings, active, bands) VALUES (1, 'Default curve', '0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0 20 1 0  0', 0, NULL);

-- Table: cfg_gpio
CREATE TABLE cfg_gpio (id INTEGER PRIMARY KEY, pin CHAR (2), enabled CHAR (1), pull CHAR (32), command CHAR (64), param CHAR (32), value CHAR (32));
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (1, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (2, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (3, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (4, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (5, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (6, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (7, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (8, '2', '0', '22', '', '', '');
INSERT INTO cfg_gpio (id, pin, enabled, pull, command, param, value) VALUES (99, '', '', '', '', 'bounce_time', '1000');

-- Table: cfg_network
CREATE TABLE cfg_network (id INTEGER PRIMARY KEY, iface CHAR (5), method CHAR (6), ipaddr CHAR (15), netmask CHAR (15), gateway CHAR (15), pridns CHAR (15), secdns CHAR (15), wlanssid CHAR (32), wlanuuid CHAR (4), wlanpwd CHAR (64), wlanpsk CHAR (64), wlancc CHAR (2));
INSERT INTO cfg_network (id, iface, method, ipaddr, netmask, gateway, pridns, secdns, wlanssid, wlanuuid, wlanpwd, wlanpsk, wlancc) VALUES (1, 'eth0', 'dhcp', '', '', '', '', '', '', '', '', '', '');
INSERT INTO cfg_network (id, iface, method, ipaddr, netmask, gateway, pridns, secdns, wlanssid, wlanuuid, wlanpwd, wlanpsk, wlancc) VALUES (2, 'wlan0', 'dhcp', '', '', '', '', '', 'None', '', '', '', 'US');
INSERT INTO cfg_network (id, iface, method, ipaddr, netmask, gateway, pridns, secdns, wlanssid, wlanuuid, wlanpwd, wlanpsk, wlancc) VALUES (3, 'apd0', '', '', '', '', '', '', 'Moode', '', '', '', '');

-- Table: cfg_mpd
CREATE TABLE cfg_mpd (id INTEGER PRIMARY KEY, param CHAR (32), value CHAR (32));
INSERT INTO cfg_mpd (id, param, value) VALUES (1, 'music_directory', '/var/lib/mpd/music');
INSERT INTO cfg_mpd (id, param, value) VALUES (2, 'playlist_directory', '/var/lib/mpd/playlists');
INSERT INTO cfg_mpd (id, param, value) VALUES (3, 'db_file', '/var/lib/mpd/database');
INSERT INTO cfg_mpd (id, param, value) VALUES (4, 'log_file', '/var/log/mpd/log');
INSERT INTO cfg_mpd (id, param, value) VALUES (5, 'pid_file', '/var/run/mpd/pid');
INSERT INTO cfg_mpd (id, param, value) VALUES (6, 'state_file', '/var/lib/mpd/state');
INSERT INTO cfg_mpd (id, param, value) VALUES (7, 'sticker_file', '/var/lib/mpd/sticker.sql');
INSERT INTO cfg_mpd (id, param, value) VALUES (8, 'user', 'mpd');
INSERT INTO cfg_mpd (id, param, value) VALUES (9, 'group', 'audio');
INSERT INTO cfg_mpd (id, param, value) VALUES (10, 'bind_to_address', 'any');
INSERT INTO cfg_mpd (id, param, value) VALUES (11, 'port', '6600');
INSERT INTO cfg_mpd (id, param, value) VALUES (12, 'log_level', 'default');
INSERT INTO cfg_mpd (id, param, value) VALUES (13, 'restore_paused', 'yes');
INSERT INTO cfg_mpd (id, param, value) VALUES (14, 'auto_update', 'no');
INSERT INTO cfg_mpd (id, param, value) VALUES (15, 'follow_outside_symlinks', 'yes');
INSERT INTO cfg_mpd (id, param, value) VALUES (16, 'follow_inside_symlinks', 'yes');
INSERT INTO cfg_mpd (id, param, value) VALUES (17, 'zeroconf_enabled', 'no');
INSERT INTO cfg_mpd (id, param, value) VALUES (18, 'zeroconf_name', 'Moode MPD');
INSERT INTO cfg_mpd (id, param, value) VALUES (19, 'filesystem_charset', 'UTF-8');
INSERT INTO cfg_mpd (id, param, value) VALUES (20, 'metadata_to_use', '+comment');
INSERT INTO cfg_mpd (id, param, value) VALUES (21, 'device', '0');
INSERT INTO cfg_mpd (id, param, value) VALUES (22, 'mixer_type', 'hardware');
INSERT INTO cfg_mpd (id, param, value) VALUES (23, 'dop', 'no');
INSERT INTO cfg_mpd (id, param, value) VALUES (24, 'audio_output_format', 'disabled');
INSERT INTO cfg_mpd (id, param, value) VALUES (25, 'sox_quality', 'high');
INSERT INTO cfg_mpd (id, param, value) VALUES (26, 'sox_multithreading', '1');
INSERT INTO cfg_mpd (id, param, value) VALUES (27, 'replaygain', 'off');
INSERT INTO cfg_mpd (id, param, value) VALUES (28, 'replaygain_preamp', '0');
INSERT INTO cfg_mpd (id, param, value) VALUES (29, 'replay_gain_handler', 'software');
INSERT INTO cfg_mpd (id, param, value) VALUES (30, 'volume_normalization', 'no');
INSERT INTO cfg_mpd (id, param, value) VALUES (31, 'audio_buffer_size', '4096');
INSERT INTO cfg_mpd (id, param, value) VALUES (32, 'input_cache', 'Disabled');
INSERT INTO cfg_mpd (id, param, value) VALUES (33, 'max_output_buffer_size', '131072');
INSERT INTO cfg_mpd (id, param, value) VALUES (34, 'auto_resample', 'yes');
INSERT INTO cfg_mpd (id, param, value) VALUES (35, 'auto_channels', 'yes');
INSERT INTO cfg_mpd (id, param, value) VALUES (36, 'auto_format', 'yes');
INSERT INTO cfg_mpd (id, param, value) VALUES (37, 'buffer_time', '500000');
INSERT INTO cfg_mpd (id, param, value) VALUES (38, 'period_time', '125000');
INSERT INTO cfg_mpd (id, param, value) VALUES (39, 'selective_resample_mode', '0');
INSERT INTO cfg_mpd (id, param, value) VALUES (40, 'sox_precision', '20');
INSERT INTO cfg_mpd (id, param, value) VALUES (41, 'sox_phase_response', '50');
INSERT INTO cfg_mpd (id, param, value) VALUES (42, 'sox_passband_end', '95');
INSERT INTO cfg_mpd (id, param, value) VALUES (43, 'sox_stopband_begin', '100');
INSERT INTO cfg_mpd (id, param, value) VALUES (44, 'sox_attenuation', '0');
INSERT INTO cfg_mpd (id, param, value) VALUES (45, 'sox_flags', '0');
INSERT INTO cfg_mpd (id, param, value) VALUES (46, 'max_playlist_length', '16384');
INSERT INTO cfg_mpd (id, param, value) VALUES (47, 'stop_dsd_silence', 'no');
INSERT INTO cfg_mpd (id, param, value) VALUES (48, 'thesycon_dsd_workaround', 'no');
INSERT INTO cfg_mpd (id, param, value) VALUES (49, 'proxy', '');
INSERT INTO cfg_mpd (id, param, value) VALUES (50, 'proxy_user', '');
INSERT INTO cfg_mpd (id, param, value) VALUES (51, 'proxy_password', '');

-- Table: cfg_multiroom
CREATE TABLE cfg_multiroom (id INTEGER PRIMARY KEY, param CHAR (32), value CHAR (32));
INSERT INTO cfg_multiroom (id, param, value) VALUES (1, 'tx_bfr', '64');
INSERT INTO cfg_multiroom (id, param, value) VALUES (2, 'tx_host', '239.0.0.1');
INSERT INTO cfg_multiroom (id, param, value) VALUES (3, 'tx_port', '1350');
INSERT INTO cfg_multiroom (id, param, value) VALUES (4, 'tx_sample_rate', '48000');
INSERT INTO cfg_multiroom (id, param, value) VALUES (5, 'tx_channels', '2');
INSERT INTO cfg_multiroom (id, param, value) VALUES (6, 'tx_frame_size', '480');
INSERT INTO cfg_multiroom (id, param, value) VALUES (7, 'tx_bitrate', '128');
INSERT INTO cfg_multiroom (id, param, value) VALUES (8, 'tx_rtprio', '45');
INSERT INTO cfg_multiroom (id, param, value) VALUES (9, 'RESERVED_9', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (10, 'RESERVED_10', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (11, 'RESERVED_11', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (12, 'RESERVED_12', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (13, 'tx_query_timeout', '1');
INSERT INTO cfg_multiroom (id, param, value) VALUES (14, 'rx_bfr', '64');
INSERT INTO cfg_multiroom (id, param, value) VALUES (15, 'rx_host', '239.0.0.1');
INSERT INTO cfg_multiroom (id, param, value) VALUES (16, 'rx_port', '1350');
INSERT INTO cfg_multiroom (id, param, value) VALUES (17, 'rx_sample_rate', '48000');
INSERT INTO cfg_multiroom (id, param, value) VALUES (18, 'rx_channels', '2');
INSERT INTO cfg_multiroom (id, param, value) VALUES (19, 'rx_jitter_bfr', '64');
INSERT INTO cfg_multiroom (id, param, value) VALUES (20, 'rx_frame_size', '480');
INSERT INTO cfg_multiroom (id, param, value) VALUES (21, 'rx_rtprio', '45');
INSERT INTO cfg_multiroom (id, param, value) VALUES (22, 'RESERVED_22', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (23, 'RESERVED_23', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (24, 'RESERVED_24', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (25, 'RESERVED_25', '');
INSERT INTO cfg_multiroom (id, param, value) VALUES (26, 'rx_alsa_volume_max', '100');
INSERT INTO cfg_multiroom (id, param, value) VALUES (27, 'rx_alsa_output_mode', 'plughw');
INSERT INTO cfg_multiroom (id, param, value) VALUES (28, 'rx_mastervol_opt_in', '1');
INSERT INTO cfg_multiroom (id, param, value) VALUES (29, 'initial_volume', '0');

COMMIT TRANSACTION;
PRAGMA foreign_keys = on;
