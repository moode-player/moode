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
 * - Define default priority
 * - Pass argv priority in go_realtime()
 */

#ifndef MISC_H
#define MISC_H

#define DEFAULT_RTPRIO 45

int go_realtime(const unsigned int rtprio);
int go_daemon(const char *pid_file);

#endif
