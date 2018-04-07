/* Copyright (C) 2013 J.F.Dockes
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

#include <stdlib.h>
#include <unistd.h>
#include <getopt.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>
#include <strings.h>

#include <string>
#include <iostream>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/log.hxx"
#include "libupnpp/soaphelp.hxx"
#include "libupnpp/upnpputils.hxx"
#include "libupnpp/control/mediarenderer.hxx"
#include "libupnpp/control/avtransport.hxx"
#include "libupnpp/control/discovery.hxx"

#include "streamer.h"
#include "wav.h"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

WorkQueue<AudioMessage*> audioqueue("audioqueue", 4);

// @param name can be uuid or friendly name, we try both. The chance that a
//     device would have a uuid which would be the friendly name of
//     another is small...
MRDH getRenderer(const string& name)
{
    static UPnPDeviceDirectory *superdir;
    if (superdir == 0) {
        superdir = UPnPDeviceDirectory::getTheDir();
        if (superdir == 0) {
            cerr << "Discovery init failed\n";
            return MRDH();
        }
    }
    
    UPnPDeviceDesc ddesc;
    if (superdir->getDevByFName(name, ddesc)) {
        return MRDH(new MediaRenderer(ddesc));
    } else if (superdir->getDevByUDN(name, ddesc)) {
        return MRDH(new MediaRenderer(ddesc));
    }
    cerr << "Can't connect to " << name << endl;
    return MRDH();
}

static string path_suffix(const string& s)
{
    string::size_type dotp = s.rfind('.');
    if (dotp == string::npos) {
        return string();
    }
    return s.substr(dotp + 1);
}

static bool whatfile(const string& audiofile, AudioSink::Context *ctxt)
{
    if (access(audiofile.c_str(), R_OK) != 0) {
        cerr << "No read access " << audiofile << " errno " << errno << endl;
        return false;
    }
    struct stat st;
    if (stat(audiofile.c_str(), &st)) {
        cerr << "Can't stat " << audiofile << " errno " << errno << endl;
        return false;
    }

    string ext = path_suffix(audiofile);
    ctxt->filename = audiofile;
    ctxt->filesize = st.st_size;
    ctxt->ext = ext;
    const char* cext = ext.c_str();
    if (!strcasecmp("flac", cext)) {
        ctxt->content_type = "audio/flac";
    } else if (!strcasecmp("mp3", cext)) {
        ctxt->content_type = "audio/mpeg";
    } else if (!strcasecmp("wav", cext)) {
        ctxt->content_type = "audio/wav";
    } else {
        cerr << "Unknown extension " << ext << endl;
        return false;
    }
    return true;
}

void *readworker(void *a)
{
    AudioSink::Context *ctxt = (AudioSink::Context *)a;

    int fd = 0;
    if (ctxt->filename.compare("stdin")) {
        if ((fd = open(ctxt->filename.c_str(), O_RDONLY)) < 0) {
            cerr << "readWorker: can't open " << ctxt->filename <<
                " for reading, errno " << errno << endl;
            exit(1);
        }
    }

    for (;;) {
        unsigned int allocbytes = 4096;
        char *buf = (char *)malloc(allocbytes);
        if (buf == 0) {
            cerr << "readWorker: can't allocate " << allocbytes << " bytes\n";
            exit(1);
        }
        ssize_t readbytes = read(fd, buf, allocbytes);
        //cerr << "readworker: got " << readbytes << "bytes\n";
        if (readbytes < 0) {
            cerr << "readWorker: read error on " << ctxt->filename <<
                " errno " << errno << endl;
            exit(1);
        } else if (readbytes == 0) {
            audioqueue.waitIdle();
            audioqueue.setTerminateAndWait();
            return nullptr;
        }
        AudioMessage *ap = new AudioMessage(buf, readbytes, allocbytes);
        if (!audioqueue.put(ap, false)) {
            cerr << "readWorker: queue dead: exiting\n";
            exit(1);
        }
    }
}

string didlmake(const string& uri, const string& mime)
{
    ostringstream ss;
    ss << "<?xml version=\"1.0\" encoding=\"utf-8\"?>"
       "<DIDL-Lite xmlns:dc=\"http://purl.org/dc/elements/1.1/\" "
       "xmlns:upnp=\"urn:schemas-upnp-org:metadata-1-0/upnp/\" "
       "xmlns=\"urn:schemas-upnp-org:metadata-1-0/DIDL-Lite/\" "
       "xmlns:dlna=\"urn:schemas-dlna-org:metadata-1-0/\">"
       << "<item restricted=\"1\">";

    ss << "<dc:title>" << SoapHelp::xmlQuote("Streaming") << "</dc:title>";
    ss << "<upnp:class>object.item.audioItem.musicTrack</upnp:class>";

#warning "problem with resource values!"
    ss << "<res " << "duration=\"" << upnpduration(30)
       << "\" "
       << "sampleFrequency=\"44100\" audioChannels=\"2\" "
       << "protocolInfo=\"http-get:*:" << mime << ":*\""
       << ">"
       << SoapHelp::xmlQuote(uri)
       << "</res>"
       << "</item></DIDL-Lite>";
    return ss.str();
}

static char *thisprog;
static char usage [] =
"<audiofile> <renderer> : play audio on given renderer\n"
                                           ;
static void Usage(void)
{
    fprintf(stderr, "%s: usage:\n%s", thisprog, usage);
    exit(1);
}
static int	   op_flags;
#define OPT_h    0x1
#define OPT_p    0x2
static struct option long_options[] = {
    {"host", required_argument, 0, 'h'},
    {"port", required_argument, 0, 'p'},
    {0, 0, 0, 0}
};


int main(int argc, char *argv[])
{
    thisprog = argv[0];
    string host = "localhost";
    int port = 8869;
    
    int option_index = 0;
    int ret;
    while ((ret = getopt_long(argc, argv, "h:p:", 
                              long_options, &option_index)) != -1) {
        cerr << "ret is " << ret << endl;
        switch (ret) {
        case 'h': op_flags |= OPT_h; host = optarg; break;
        case 'p': op_flags |= OPT_h; port = atoi(optarg); break;
        default:  Usage();
        }
    }

    if (optind != argc - 2) 
        Usage();
    string audiofile = argv[optind++];
    string renderer = argv[optind++];
            
    if (Logger::getTheLog("stderr") == 0) {
        cerr << "Can't initialize log" << endl;
        return 1;
    }
    Logger::getTheLog("")->setLogLevel(Logger::LLDEB1);

    string hwa;
    LibUPnP *mylib = LibUPnP::getLibUPnP(false, &hwa);
    if (!mylib) {
        cerr << "Can't get LibUPnP" << endl;
        return 1;
    }

    if (!(op_flags & OPT_h)) {
#if FUTURE
        host = mylib->host();
        if (host.empty()) {
            cerr << "Can't retrieve IP address\n";
            return 1;
        }
#else
        char hostname[1024];
        if (gethostname(hostname, 1024)) {
            perror("gethostname failed. use -h:");
            return 1;
        }
        host = hostname;
#endif
    }
    if (!mylib->ok()) {
        cerr << "Lib init failed: " <<
            mylib->errAsString("main", mylib->getInitError()) << endl;
        return 1;
    }
    //mylib->setLogFileName("/tmp/libupnp.log", LibUPnP::LogLevelDebug);

    MRDH rdr = getRenderer(renderer);
    if (!rdr) {
        cerr << "Can't connect to renderer\n";
        return 1;
    }
    AVTH avth = rdr->avt();
    if (!avth) {
        cerr << "Device has no AVTransport service" << endl;
        return 1;
    }

    
    // Identify file
    AudioSink::Context *ctxt = new AudioSink::Context(&audioqueue);
    bool makewav = false;
    if (!audiofile.compare("stdin")) {
        ctxt->filename = audiofile;
        ctxt->ext = "wav";
        ctxt->content_type = "audio/wav";
        makewav = true;
    } else {
        if (!whatfile(audiofile, ctxt)) {
            cerr << "Can't identify file " << audiofile << endl;
            return 1;
        }
    }

    unordered_map<string,string> c{{"httpport", SoapHelp::i2s(port)},
        {"httphost", host}};
    ctxt->config = c;
    
    // Start the http thread
    audioqueue.start(1, (void *(*)(void *))(httpAudioSink.worker), ctxt);

    if (makewav) {
        unsigned int allocbytes = 512;
        char *buf = (char *)malloc(allocbytes);
        if (buf == 0) {
            cerr << "Can't allocate " << allocbytes << " bytes\n";
            exit(1);
        }
        int freq = 44100;
        int bits = 16;
        int chans = 2;
        int databytes = 2 * 1000 * 1000 * 1000;
        // Using buf+bytes in case we ever insert icy before the audio
        int sz = makewavheader(buf, allocbytes, freq, bits, chans, databytes);
        AudioMessage *ap = new AudioMessage(buf, sz, allocbytes);
        audioqueue.put(ap, false);
    }
    
    // Start the reading thread
    std::thread readthread(readworker, ctxt);

    string uri("http://" + host + ":" + SoapHelp::i2s(port) + "/stream." +
               ctxt->ext);

    // We'd need a few options here to decide what to do if already playing:
    // wait (would allow to queue multiple songs), or interrupt.

    // Start the renderer
    if (avth->setAVTransportURI(uri, didlmake(uri, ctxt->content_type)) != 0) {
        cerr << "setAVTransportURI failed\n";
        return 1;
    }

    if (avth->play() != 0) {
        cerr << "play failed\n";
        return 1;
    }
    readthread.join();
    return 0;
}
