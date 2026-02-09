#!/usr/bin/python3
#
# SPDX-License-Identifier: MIT
# Copyright 2026 The moOde audio player project / Tim Curtis
# Copyright 2024 TM Fetch Album Art / Tom McFarlin / https://github.com/tommcfarlin
#
# This program uses the iTunes Search API to return the album art URL for the
# specified artist name and track title. It uses the highest resolution
# image available.
#
# Args (enclosed in double quotes):
#   artist_name: The name of the artist.
#	track_title: The title of the track.
#
# Returns:
#	URL to album art
#

import requests  # type: ignore
import argparse
import os
import base64

def fetch_album_art_apple(artist_name, track_title):
	# Base URL for iTunes Search API
	url = "https://itunes.apple.com/search"

	# Query parameters
	query = {
		"term": f"{artist_name} {track_title}",
		"media": "music",
		"entity": "musicTrack",
		"limit": 1
	}

	# Make the request to the API
	response = requests.get(url, params=query)

	if response.status_code != 200:
		print("Failed to retrieve data from iTunes API")
		return None

	# Parse the JSON response
	data = response.json()

	if len(data['results']) == 0:
		print("No album found for the given artist name and track title")
		return None

	# Extract the album art URL
	album_info = data['results'][0]
	artwork_url = album_info['artworkUrl100']

	# Modify the URL to specify the highest resolution image available
	high_res_artwork_url = artwork_url.replace("100x100", "10000x10000")

	# Test the URL
	response = requests.head(high_res_artwork_url)
	if response.status_code == 200:
		print(high_res_artwork_url)
	else:
		print("Album art not found")

def main():
	# Setup argument parser
	parser = argparse.ArgumentParser(
		description="Fetch album art URL using artist name and track title.")

	# Define arguments
	parser.add_argument("--artist", required=True, help="artist name enclosed in dbl quotes")
	parser.add_argument("--track", required=True, help="track title enclosed in dbl quotes")

	# Parse arguments
	args = parser.parse_args()
	fetch_album_art_apple(args.artist, args.track)

if __name__ == "__main__":
	main()
