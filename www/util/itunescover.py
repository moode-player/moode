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
# 2026 modifications by Tim Curtis
# - Use artist match test to help improve finding correct cover
# - Add timeout to requests
# - Add debug logging
#

import requests
import argparse
import os
import base64

REQUEST_TIMEOUT_DEFAULT = 3
QUERY_RESULT_LIMIT = 10

def search_for_cover(artist_name, track_title, request_timeout):
	# Base URL for iTunes Search API
	url = "https://itunes.apple.com/search"

	# Query parameters
	query = {
		"term": f"{artist_name} {track_title}",
		"media": "music",
		"entity": "musicTrack",
		"limit": QUERY_RESULT_LIMIT
	}
	#print("timeout=" + str(request_timeout))
	#print(query)

	# Submit query
	response = requests.get(url, params=query, timeout=request_timeout)

	if response.status_code != 200:
		#print("No results were returned from the query")
		return None

	# Parse JSON results
	data = response.json()
	#print(data)

	if len(data['results']) == 0:
		#print("No results were returned from the query")
		return None

	# Extract the album art URL
	artwork_url = None
	for item in data['results']:
		#print(f"Checking {item['collectionName']}")
		if item["artistName"].replace(" ", "").lower() == artist_name.replace(" ", "").lower():
			artwork_url = item['artworkUrl100']
			#print(f"Artist match found")
			break


	if not artwork_url:
		#print("No album found for the given artist name and track title")
		return None

	# Modify the URL to specify the highest resolution image available
	# Can be 10000x10000 but just use 1000x1000
	high_res_artwork_url = artwork_url.replace("100x100", "1000x1000")

	# Test the URL
	response = requests.head(high_res_artwork_url, timeout=request_timeout)
	if response.status_code == 200:
		#print(high_res_artwork_url)
		return high_res_artwork_url
	else:
		#print("Cover at 1000x1000px not found")
		return None

def main():
	# Setup argument parser
	parser = argparse.ArgumentParser(
		description="Fetch cover URL using artist name and track title.")

	# Define arguments
	parser.add_argument("--artist", required=True, help="artist name enclosed in dbl quotes")
	parser.add_argument("--title", required=True, help="title enclosed in dbl quotes")
	parser.add_argument("--timeout", required=False, default=REQUEST_TIMEOUT_DEFAULT, help="query timeout")

	# Parse arguments
	args = parser.parse_args()
	cover_url = search_for_cover(args.artist, args.title, int(args.timeout))
	print(cover_url) # URL or None

if __name__ == "__main__":
	main()
