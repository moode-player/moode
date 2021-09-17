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
 * - Accept argv priority in go_realtime()
 */

#include <sched.h>
#include <stdio.h>
#include <unistd.h>

#include "sched.h"

int go_realtime(const unsigned int rtprio)
{
	int max_pri;
	struct sched_param sp;

	if (sched_getparam(0, &sp)) {
		perror("sched_getparam");
		return -1;
	}

	max_pri = sched_get_priority_max(SCHED_FIFO);
	sp.sched_priority = rtprio;

	if (sp.sched_priority > max_pri) {
		fprintf(stderr, "Invalid priority (maximum %d)\n", max_pri);
		return -1;
	}

	if (sched_setscheduler(0, SCHED_FIFO, &sp)) {
		perror("sched_setscheduler");
		return -1;
	}

	return 0;
}

int go_daemon(const char *pid_file)
{
	FILE *f;

	if (daemon(0, 0) == -1) {
		perror("daemon");
		return -1;
	}

	if (!pid_file)
		return 0;

	f = fopen(pid_file, "w");
	if (!f) {
		perror("fopen");
		return -1;
	}

	fprintf(f, "%d", getpid());

	if (fclose(f) != 0) {
		perror("fclose");
		return -1;
	}

	return 0;
}
