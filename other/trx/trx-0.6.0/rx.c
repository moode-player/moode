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

static void timestamp_jump(RtpSession *session, void *a, void *b, void *c)
{
	if (verbose > 1)
		fputc('|', stderr);
	rtp_session_resync(session);
}

static RtpSession* create_rtp_recv(const char *addr_desc, const int port,
		unsigned int jitter)
{
	RtpSession *session;

	session = rtp_session_new(RTP_SESSION_RECVONLY);
	rtp_session_set_scheduling_mode(session, FALSE);
	rtp_session_set_blocking_mode(session, FALSE);
	rtp_session_set_local_addr(session, addr_desc, port, -1);
	rtp_session_set_connected_mode(session, FALSE);
	rtp_session_enable_adaptive_jitter_compensation(session, TRUE);
	rtp_session_set_jitter_compensation(session, jitter); /* ms */
	rtp_session_set_time_jump_limit(session, jitter * 16); /* ms */
	if (rtp_session_set_payload_type(session, 0) != 0)
		abort();
	if (rtp_session_signal_connect(session, "timestamp_jump",
					timestamp_jump, 0) != 0)
	{
		abort();
	}

	/*
	 * oRTP in RECVONLY mode attempts to send RTCP packets and
	 * segfaults (v4.3.0 tested)
	 *
	 * https://stackoverflow.com/questions/43591690/receiving-rtcp-issues-within-ortp-library
	 */

	rtp_session_enable_rtcp(session, FALSE);

	return session;
}

static int play_one_frame(void *packet,
		size_t len,
		const snd_pcm_sframes_t frame,
		OpusDecoder *decoder,
		snd_pcm_t *snd,
		const unsigned int channels)
{
	int r;
	int16_t *pcm;
	/* NOTE: For moOde implementation samples (frame_size) is a command line arg with default of DEFAULT_FRAME */
	/*snd_pcm_sframes_t f, samples = 1920;*/
	snd_pcm_sframes_t f;

	pcm = alloca(sizeof(*pcm) * frame * channels);

	if (packet == NULL) {
		r = opus_decode(decoder, NULL, 0, pcm, frame, 1);
	} else {
		r = opus_decode(decoder, packet, len, pcm, frame, 0);
	}
	if (r < 0) {
		fprintf(stderr, "opus_decode: %s\n", opus_strerror(r));
		return -1;
	}

	f = snd_pcm_writei(snd, pcm, r);
	if (f < 0) {
		f = snd_pcm_recover(snd, f, 0);
		if (f < 0) {
			aerror("snd_pcm_writei", f);
			return -1;
		}
		return 0;
	}
	if (f < r)
		fprintf(stderr, "Short write %ld\n", f);

	return r;
}

static int run_rx(RtpSession *session,
		OpusDecoder *decoder,
		snd_pcm_t *snd,
		const unsigned int frame,
		const unsigned int channels,
		const unsigned int rate)
{
	int ts = 0;

	for (;;) {
		int r, have_more;
		char buf[65536]; /* Was 32768 */
		void *packet;

		r = rtp_session_recv_with_ts(session, (uint8_t*)buf,
				sizeof(buf), ts, &have_more);
		assert(r >= 0);
		assert(have_more == 0);
		if (r == 0) {
			packet = NULL;
			if (verbose > 1)
				fputc('#', stderr);
		} else {
			packet = buf;
			if (verbose > 1)
				fputc('.', stderr);
		}

		r = play_one_frame(packet, r, frame, decoder, snd, channels);
		if (r == -1)
			return -1;

		/* Follow the RFC, payload 0 has 8kHz reference rate */

		ts += r * 8000 / rate;
	}
}

static void usage(FILE *fd)
{
	fprintf(fd, "Usage: rx [<parameters>]\n"
		"Real-time audio receiver over IP\n");

	fprintf(fd, "\nAudio device (ALSA) parameters:\n");
	fprintf(fd, "  -d <dev>    Device name (default '%s')\n",
		DEFAULT_DEVICE);
	fprintf(fd, "  -m <ms>     Buffer time (default %d milliseconds)\n",
		DEFAULT_BUFFER);

	fprintf(fd, "\nNetwork parameters:\n");
	fprintf(fd, "  -h <addr>   IP address to listen on (default %s)\n",
		DEFAULT_ADDR);
	fprintf(fd, "  -p <port>   UDP port number (default %d)\n",
		DEFAULT_PORT);
	fprintf(fd, "  -j <ms>     Jitter buffer (default %d milliseconds)\n",
		DEFAULT_JITTER);

	fprintf(fd, "\nEncoding parameters (must match sender):\n");
	fprintf(fd, "  -r <rate>   Sample rate (default %d Hz)\n",
		DEFAULT_RATE);
	fprintf(fd, "  -c <n>      Number of channels (default %d)\n",
		DEFAULT_CHANNELS);
	fprintf(fd, "  -f <n>      Frame size (default %d samples, see (1) below)\n",
		DEFAULT_FRAME);

	fprintf(fd, "\nProgram parameters:\n");
	fprintf(fd, "  -v <n>      Verbosity level (default %d)\n",
		DEFAULT_VERBOSE);
	fprintf(fd, "  -D <file>   Run as a daemon, writing process ID to the given file\n");
	fprintf(fd, "  -R <prio>   Realtime priority (default %d)\n",
		DEFAULT_RTPRIO);
	fprintf(fd, "  -H          Print program help\n");

	fprintf(fd, "\n(1) Allowed frame sizes (-f) are defined by the Opus codec. For example,\n"
		"at 48000 Hz the permitted values are 120, 240, 480, 960, 1920 or 2880 which\n"
		"correspond to 2.5, 7.5, 10, 20, 40 or 60 milliseconds respectively.\n");
}

int main(int argc, char *argv[])
{
	int r, error;
	snd_pcm_t *snd;
	OpusDecoder *decoder;
	RtpSession *session;

	/* command-line options */
	const char *device = DEFAULT_DEVICE,
		*addr = DEFAULT_ADDR,
		*pid = NULL;
	unsigned int buffer = DEFAULT_BUFFER,
		rate = DEFAULT_RATE,
		frame = DEFAULT_FRAME,
		jitter = DEFAULT_JITTER,
		channels = DEFAULT_CHANNELS,
		port = DEFAULT_PORT,
		rtprio = DEFAULT_RTPRIO;

	fputs(VERSION "\n" COPYRIGHT "\n\n", stderr);

	for (;;) {
		int c;

		c = getopt(argc, argv, "c:d:f:h:j:m:p:r:v:D:R:H");
		if (c == -1)
			break;

		switch (c) {
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
		case 'j':
			jitter = atoi(optarg);
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

	decoder = opus_decoder_create(rate, channels, &error);
	if (decoder == NULL) {
		fprintf(stderr, "opus_decoder_create: %s\n",
			opus_strerror(error));
		return -1;
	}

	ortp_init();
	ortp_scheduler_init();
	session = create_rtp_recv(addr, port, jitter);
	assert(session != NULL);

	r = snd_pcm_open(&snd, device, SND_PCM_STREAM_PLAYBACK, 0);
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
	r = run_rx(session, decoder, snd, frame, channels, rate);

	if (snd_pcm_close(snd) < 0)
		abort();

	rtp_session_destroy(session);
	ortp_exit();
	ortp_global_stats_display();

	opus_decoder_destroy(decoder);

	return r;
}
