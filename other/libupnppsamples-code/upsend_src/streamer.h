/* Copyright (C) 2016 J.F.Dockes
 *	 This program is free software; you can redistribute it and/or modify
 *	 it under the terms of the GNU General Public License as published by
 *	 the Free Software Foundation; either version 2 of the License, or
 *	 (at your option) any later version.
 *
 *	 This program is distributed in the hope that it will be useful,
 *	 but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	 GNU General Public License for more details.
 *
 *	 You should have received a copy of the GNU General Public License
 *	 along with this program; if not, write to the
 *	 Free Software Foundation, Inc.,
 *	 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */
#ifndef _STREAMER_H_INCLUDED_
#define _STREAMER_H_INCLUDED_

#include "workqueue.h"

#include <unordered_map>

// The audio messages which get passed between the reader and the http
// server part.
class AudioMessage {
public:
    // buf is is a malloced buffer, and we take ownership. The caller
    // MUST NOT free it. Bytes is data count, allocbytes is the buffer size.
    AudioMessage(char *buf, size_t bytes, size_t allocbytes) 
        : m_bytes(bytes), m_allocbytes(allocbytes), m_buf(buf), m_curoffs(0) {
    }

    ~AudioMessage() {
        if (m_buf)
            free(m_buf);
    }
    unsigned int m_bytes; // Useful bytes
    unsigned int m_allocbytes; // buffer size
    char *m_buf;
    unsigned int m_curoffs; /* Used by the http data emitter */
};

class AudioSink {
public:
    struct Context {
        Context(WorkQueue<AudioMessage*> *q)
            : queue(q), config(0), filesize(0) {
        }
        WorkQueue<AudioMessage*> *queue;
        std::unordered_map<std::string,std::string> config;
        std::string filename;
        std::string content_type;
        std::string ext;
        off_t filesize;
    };

    AudioSink(void *(*w)(Context *))
        : worker(w) {
    }

    /** Worker routine for fetching bufs from the rcvqueue and sending them
     * further. The param is actually an AudioSink::Context */
    void *(*worker)(Context *);
};

extern AudioSink httpAudioSink;

#endif /* _STREAMER_H_INCLUDED_ */
