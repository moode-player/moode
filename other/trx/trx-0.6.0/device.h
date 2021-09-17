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
 */

#ifndef DEVICE_H
#define DEVICE_H

void aerror(const char *msg, int r);
int set_alsa_hw(snd_pcm_t *pcm,
		unsigned int rate, unsigned int channels,
		unsigned int buffer);
int set_alsa_sw(snd_pcm_t *pcm);

#endif
