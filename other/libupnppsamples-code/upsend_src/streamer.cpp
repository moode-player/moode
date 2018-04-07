/* Copyright (C) 2014 J.F.Dockes
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
#include "config.h"

#include <string.h>
#include <arpa/inet.h>
#include <sys/types.h>
#include <sys/select.h>
#include <sys/socket.h>

#include <iostream>
#include <queue>
#include <mutex>
#include <condition_variable>

#include <microhttpd.h>

#include "streamer.h"
#include "libupnpp/log.h"

using namespace std;

#ifndef MIN
#define MIN(A,B) ((A)<(B)?(A):(B))
#endif

// #define PRINT_KEYS

// Only accept HTTP connections from localhost: no
#define ACCEPT_LOCALONLY 0

// The queue for audio blocks coming our way
static queue<AudioMessage*> dataqueue;
static std::mutex dataqueueLock;
static std::condition_variable dataqueueWaitCond;

#ifdef PRINT_KEYS
static const char *ValueKindToCp(enum MHD_ValueKind kind)
{
    switch (kind) {
    case MHD_RESPONSE_HEADER_KIND: return "Response header";
    case MHD_HEADER_KIND: return "HTTP header";
    case MHD_COOKIE_KIND: return "Cookies";
    case MHD_POSTDATA_KIND: return "POST data";
    case MHD_GET_ARGUMENT_KIND: return "GET (URI) arguments";
    case MHD_FOOTER_KIND: return "HTTP footer";
    default: return "Unknown";
    }
}

static int print_out_key (void *cls, enum MHD_ValueKind kind, 
                          const char *key, const char *value)
{
    LOGDEB(ValueKindToCp(kind) << ": " << key << " -> " << value << endl);
    return MHD_YES;
}
#endif /* PRINT_KEYS */

struct DataGenContext {
    DataGenContext() :
        eof(false) {
    }
    bool eof;
};

// This gets called by microhttpd when it needs data.
static ssize_t
data_generator(void *cls, uint64_t pos, char *buf, size_t max)
{
    LOGDEB1("data_generator: " << " max " << max << endl);
    DataGenContext *dgc = (DataGenContext *)cls;
    std::unique_lock<std::mutex> lock(dataqueueLock);
    if (dgc->eof) {
        LOGDEB1("data_generator: already eof\n");
        return -1;
    }
    
    // Loop reading on the input queue until we have satistified the request
    size_t bytes = 0;
    while (bytes < max) {
        while (dataqueue.empty()) {
            LOGDEB1("data_generator: waiting for buffer" << endl);
            dataqueueWaitCond.wait(lock);
        }

        AudioMessage *m = dataqueue.front();
        if (m->m_bytes == 0) {
            // EOF
            LOGDEB1("data_generator: empty buffer\n");
            dgc->eof = true;
            // Do not notify or clear the queue: freeCallback will do it.
            break;
        }
        LOGDEB1("data_generator: data buffer\n");

        size_t newbytes = MIN(max - bytes, m->m_bytes - m->m_curoffs);
        memcpy(buf + bytes, m->m_buf + m->m_curoffs, newbytes);
        m->m_curoffs += newbytes;
        bytes += newbytes;
        if (m->m_curoffs == m->m_bytes) {
            delete dataqueue.front();
            dataqueue.pop();
            dataqueueWaitCond.notify_all();
        }
    }

    LOGDEB1("data_generator: returning " << bytes << " bytes" << endl);
    return bytes;
}

static void ContentReaderFreeCallback(void *cls)
{
    LOGDEB1("ContentReaderFreeCallback\n");
    DataGenContext *dgc = (DataGenContext*)cls;
    std::unique_lock<std::mutex> lock(dataqueueLock);
    while (!dataqueue.empty()) {
        delete dataqueue.front();
        dataqueue.pop();
    }
    delete dgc;
    dataqueueWaitCond.notify_all();
}

static int answer_to_connection(void *cls, struct MHD_Connection *connection, 
                                const char *url, 
                                const char *method, const char *version, 
                                const char *upload_data, 
                                size_t *upload_data_size, void **con_cls)
{
    AudioSink::Context *ctxt = (AudioSink::Context *)cls;

#ifdef PRINT_KEYS
    MHD_get_connection_values(connection, MHD_HEADER_KIND, &print_out_key, 0);
#endif

    static int aptr;
    if (&aptr != *con_cls) {
        /* do not respond on first call ?*/
        *con_cls = &aptr;
        return MHD_YES;
    }

    LOGDEB("answer_to_connection: url " << url << " method " << method << 
           " version " << version << endl);

    long long size = MHD_SIZE_UNKNOWN;
    DataGenContext *dgc = new DataGenContext();

    // the block size seems to be flatly ignored by libmicrohttpd
    // Any random value would probably work the same
    struct MHD_Response *response = 
        MHD_create_response_from_callback(size, 4096, &data_generator, 
                                          dgc, ContentReaderFreeCallback);
    if (response == NULL) {
        LOGERR("httpgate: answer: could not create response" << endl);
        return MHD_NO;
    }

    MHD_add_response_header(response, "Content-Type",
                            ctxt->content_type.c_str());

// #define FORCE_CHUNKED
#if defined(FORCE_CHUNKED)
#warning content-length is needed for mpd to play wav (else tries to seek).
        MHD_add_response_header(response, "Transfer-Encoding", "chunked");
#else
        char cl[100];
        sprintf(cl, "%lld", (long long)ctxt->filesize);
        MHD_add_response_header(response, "Content-Length", cl);
#endif

    int ret = MHD_queue_response(connection, MHD_HTTP_OK, response);
    MHD_destroy_response(response);
    return ret;
}

static void *audioEater(AudioSink::Context *ctxt)
{
    LOGDEB1("audioEater\n");
    string value;
    int port = 8869;
    auto it = ctxt->config.find("httpport");
    if (it != ctxt->config.end()) {
        port = atoi(it->second.c_str());
    }

    WorkQueue<AudioMessage*> *queue = ctxt->queue;

    LOGDEB1("audioEater: queue " << ctxt->queue << " HTTP port " << port 
           << endl);

    struct MHD_Daemon *daemon = 
        MHD_start_daemon(
            MHD_USE_SELECT_INTERNALLY, 
            port, 
            /* Accept policy callback and arg */
            NULL, NULL, 
            /* handler and arg */
            &answer_to_connection, ctxt,
            MHD_OPTION_END);

    if (NULL == daemon) {
        queue->workerExit();
        delete ctxt;
        return (void *)0;
    }

    bool eof = false;
    while (true) {
        AudioMessage *tsk = nullptr;
        size_t qsz;
        if (!queue->take(&tsk, &qsz)) {
            tsk = nullptr;
            eof = true;
        }
        std::unique_lock<std::mutex> lock(dataqueueLock);
        if (eof) {
            LOGDEB1("audioEater: pushing empty buffer\n");
            dataqueue.push(new AudioMessage(nullptr, 0, 0));
            dataqueueWaitCond.notify_all();
        }

        /* limit size of queuing / wait for drain. */
        while (dataqueue.size() > (eof ? 0 : 2)) {
            if (eof) {
                LOGDEB1("audioEater: waiting for queue drain, sz " <<
                        dataqueue.size() << endl);
            }
            dataqueueWaitCond.wait(lock);
        }
        if (eof)
            break;
        dataqueue.push(tsk);
        dataqueueWaitCond.notify_all();
    }
    LOGDEB0("audioEater: returning\n");
    MHD_stop_daemon(daemon);
    queue->workerExit();
    delete ctxt;
    return (void*)1;
}

AudioSink httpAudioSink(&audioEater);
