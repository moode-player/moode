/*
 * Copyright (C) 2020 Mark Hills <mark@xwax.org>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2, as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License version 2 for more details.
 *
 * You should have received a copy of the GNU General Public License
 * version 2 along with this program; if not, write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 * 2021-09-11   Tim Curtis <tim@moodeaudio.org>
 * - Modifications and enhancements to support integration into moOde audio player
 */

#include <netdb.h>
#include <string.h>
#include <alsa/asoundlib.h>
#include <opus/opus.h>
#include <ortp/ortp.h>
#include <sys/socket.h>
#include <sys/types.h>

#include "defaults.h"
#include "device.h"
#include "notice.h"
#include "sched.h"

static unsigned int verbose = DEFAULT_VERBOSE;

static RtpSession* create_rtp_send(const char *addr_desc, const int port)
{
	RtpSession *session;

	session = rtp_session_new(RTP_SESSION_SENDONLY);
	assert(session != NULL);

	rtp_session_set_scheduling_mode(session, 0);
	rtp_session_set_blocking_mode(session, 0);
	rtp_session_set_connected_mode(session, FALSE);
	if (rtp_session_set_remote_addr(session, addr_desc, port) != 0)
		abort();
	if (rtp_session_set_payload_type(session, 0) != 0)
		abort();
	if (rtp_session_set_multicast_ttl(session, 16) != 0)
		abort();
	if (rtp_session_set_dscp(session, 40) != 0)
		abort();

	return session;
}

static int send_one_frame(snd_pcm_t *snd,
		const unsigned int channels,
		const snd_pcm_uframes_t samples,
		OpusEncoder *encoder,
		const size_t bytes_per_frame,
		const unsigned int ts_per_frame,
		RtpSession *session)
{
	int16_t *pcm;
	void *packet;
	ssize_t z;
	snd_pcm_sframes_t f;
	static unsigned int ts = 0;

	pcm = alloca(sizeof(*pcm) * samples * channels);
	packet = alloca(bytes_per_frame);

	f = snd_pcm_readi(snd, pcm, samples);
	if (f < 0) {
		if (f == -ESTRPIPE)
			ts = 0;

		f = snd_pcm_recover(snd, f, 0);
		if (f < 0) {
			aerror("snd_pcm_readi", f);
			return -1;
		}
		return 0;
	}

	/* Opus encoder requires a complete frame, so if we xrun
	 * mid-frame then we discard the incomplete audio. The next
	 * read will catch the error condition and recover */

	if (f < samples) {
		fprintf(stderr, "Short read, %ld\n", f);
		return 0;
	}

	z = opus_encode(encoder, pcm, samples, packet, bytes_per_frame);
	if (z < 0) {
		fprintf(stderr, "opus_encode_float: %s\n", opus_strerror(z));
		return -1;
	}

	rtp_session_send_with_ts(session, packet, z, ts);
	ts += ts_per_frame;

	return 0;
}

static int run_tx(snd_pcm_t *snd,
		const unsigned int channels,
		const snd_pcm_uframes_t frame,
		OpusEncoder *encoder,
		const size_t bytes_per_frame,
		const unsigned int ts_per_frame,
		RtpSession *session)
{
	for (;;) {
		int r;

		r = send_one_frame(snd, channels, frame,
				encoder, bytes_per_frame, ts_per_frame,
				session);
		if (r == -1)
			return -1;

		if (verbose > 1)
			fputc('>', stderr);
	}
}

static void usage(FILE *fd)
{
	fprintf(fd, "Usage: tx [<parameters>]\n"
		"Real-time audio transmitter over IP\n");

	fprintf(fd, "\nAudio device (ALSA) parameters:\n");
	fprintf(fd, "  -d <dev>    Device name (default '%s')\n",
		DEFAULT_DEVICE);
	fprintf(fd, "  -m <ms>     Buffer time (default %d milliseconds)\n",
		DEFAULT_BUFFER);

	fprintf(fd, "\nNetwork parameters:\n");
	fprintf(fd, "  -h <addr>   IP address to send to (default %s)\n",
		DEFAULT_ADDR);
	fprintf(fd, "  -p <port>   UDP port number (default %d)\n",
		DEFAULT_PORT);

	fprintf(fd, "\nEncoding parameters:\n");
	fprintf(fd, "  -r <rate>   Sample rate (default %d Hz, see (1) below.)\n",
		DEFAULT_RATE);
	fprintf(fd, "  -c <n>      Number of channels (default %d)\n",
		DEFAULT_CHANNELS);
	fprintf(fd, "  -f <n>      Frame size (default %d samples, see (2) below)\n",
		DEFAULT_FRAME);
	fprintf(fd, "  -b <kbps>   Bitrate (approx., default %d)\n",
		DEFAULT_BITRATE);

	fprintf(fd, "\nProgram parameters:\n");
	fprintf(fd, "  -v <n>      Verbosity level (default %d)\n",
		DEFAULT_VERBOSE);
	fprintf(fd, "  -D <file>   Run as a daemon, writing process ID to the given file\n");
	fprintf(fd, "  -R <prio>   Realtime priority (default %d)\n",
		DEFAULT_RTPRIO);
	fprintf(fd, "  -H          Print program help\n");

	fprintf(fd, "\n(1) Sampling rate (-r) of the input signal (Hz) This must be one\n"
	 	"of 8000, 12000, 16000, 24000, or 48000.\n"
		"\n(2) Allowed frame sizes (-f) are defined by the Opus codec. For example,\n"
		"at 48000 Hz the permitted values are 120, 240, 480, 960, 1920 or 2880 which\n"
		"correspond to 2.5, 7.5, 10, 20, 40 or 60 milliseconds respectively.\n");
}

int main(int argc, char *argv[])
{
	int r, error;
	size_t bytes_per_frame;
	unsigned int ts_per_frame;
	snd_pcm_t *snd;
	OpusEncoder *encoder;
	RtpSession *session;

	/* command-line options */
	const char *device = DEFAULT_DEVICE,
		*addr = DEFAULT_ADDR,
		*pid = NULL;
	unsigned int buffer = DEFAULT_BUFFER,
		rate = DEFAULT_RATE,
		channels = DEFAULT_CHANNELS,
		frame = DEFAULT_FRAME,
		kbps = DEFAULT_BITRATE,
		port = DEFAULT_PORT,
		rtprio = DEFAULT_RTPRIO;

	fputs(VERSION "\n" COPYRIGHT "\n\n", stderr);

	for (;;) {
		int c;

		c = getopt(argc, argv, "b:c:d:f:h:m:p:r:v:D:R:H");
		if (c == -1)
			break;

		switch (c) {
		case 'b':
			kbps = atoi(optarg);
			break;
		case 'c':
			channels = atoi(optarg);
			break;
		case 'd':
			device = optarg;
			break;
		case 'f':
			frame = atol(optarg);
			break;
		case 'h':
			addr = optarg;
			break;
		case 'm':
			buffer = atoi(optarg);
			break;
		case 'p':
			port = atoi(optarg);
			break;
		case 'r':
			rate = atoi(optarg);
			break;
		case 'v':
			verbose = atoi(optarg);
			break;
		case 'D':
			pid = optarg;
			break;
		case 'R':
			rtprio = atoi(optarg);
			break;
		case 'H':
			usage(stderr);
			return -1;
		default:
			usage(stderr);
			return -1;
		}
	}

	/* No options present on cmd line */
	if (optind == 1) {
		usage(stderr);
		return -1;
	}

	encoder = opus_encoder_create(rate, channels, OPUS_APPLICATION_AUDIO,
				&error);
	if (encoder == NULL) {
		fprintf(stderr, "opus_encoder_create: %s\n",
			opus_strerror(error));
		return -1;
	}

	bytes_per_frame = kbps * 1024 * frame / rate / 8;

	/* Follow the RFC, payload 0 has 8kHz reference rate */

	ts_per_frame = frame * 8000 / rate;

	ortp_init();
	ortp_scheduler_init();
	ortp_set_log_level_mask(NULL, ORTP_WARNING|ORTP_ERROR);
	session = create_rtp_send(addr, port);
	assert(session != NULL);

	r = snd_pcm_open(&snd, device, SND_PCM_STREAM_CAPTURE, 0);
	if (r < 0) {
		aerror("snd_pcm_open", r);
		return -1;
	}
	if (set_alsa_hw(snd, rate, channels, buffer * 1000) == -1)
		return -1;
	if (set_alsa_sw(snd) == -1)
		return -1;

	if (pid)
		go_daemon(pid);

	go_realtime(rtprio);
	r = run_tx(snd, channels, frame, encoder, bytes_per_frame,
		ts_per_frame, session);

	if (snd_pcm_close(snd) < 0)
		abort();

	rtp_session_destroy(session);
	ortp_exit();
	ortp_global_stats_display();

	opus_encoder_destroy(encoder);

	return r;
}
