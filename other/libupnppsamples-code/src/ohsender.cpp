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

// Code to exercise the libupnpp OhSender class

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <getopt.h>
#include <string.h>

#include <string>
#include <iostream>
#include <vector>
#include <algorithm>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/log.hxx"
#include "libupnpp/upnpputils.hxx"
#include "libupnpp/control/cdirectory.hxx"
#include "libupnpp/control/discovery.hxx"
#include "libupnpp/control/mediarenderer.hxx"
#include "libupnpp/control/ohsender.hxx"
#include "libupnpp/control/linnsongcast.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

class MReporter : public UPnPClient::VarEventReporter {
public:
    void changed(const char *nm, int value) {
        cout << "Changed: " << nm << " : " << value << endl;
    }
    void changed(const char *nm, const char *value)  {
        cout << "Changed: " << nm << " : " << value << endl;
    }

    void changed(const char *nm, UPnPDirObject meta) {
        cout << "Changed: " << nm << " : " << meta.dump() << endl;
    }
};

void rdMonitor(OHSNH hdl)
{
    MReporter reporter;
    hdl->installReporter(&reporter);

    while (true) {
        sleep(2);
        string uri;
        string meta;
        int ret;
        if ((ret = hdl->metadata(uri, meta)) == 0) {
            cout << "Uri: " << uri << " metadata " << meta << endl;
        } else {
            cerr << "Metadata: failed: " << ret << endl;
        }
    }
}

void metadata(OHSNH hdl)
{
    string uri, meta;
    int ret;
    if ((ret = hdl->metadata(uri, meta)) != 0) {
        cerr << "metadata failed: " << ret << endl;
        return;
    }
    cout << "read: uri: [" << uri << "] meta: " << meta << endl;
}

static char *thisprog;
static char usage [] =
" -M <renderer>: monitor OHSender\n"
" -m <renderer>: run metadata\n"
" \n"
;

static void
Usage(void)
{
    fprintf(stderr, "%s: usage:\n%s", thisprog, usage);
    exit(1);
}
static int	   op_flags;
#define OPT_M    0x1
#define OPT_m    0x2

static struct option long_options[] = {
    {0, 0, 0, 0}
};

int main(int argc, char *argv[])
{
    string fname;
    string arg;

    thisprog = argv[0];

    int ret;
    int option_index = 0;
    while ((ret = getopt_long(argc, argv, "Mm", 
                              long_options, &option_index)) != -1) {
        switch (ret) {
        case 'M': if (op_flags) Usage(); op_flags |= OPT_M; break;
        case 'm': if (op_flags) Usage(); op_flags |= OPT_m; break;
        default:
            Usage();
        }
    }
    if (!op_flags)
        Usage();
    
    if (op_flags & (OPT_M|OPT_m)) {
            if (optind != argc - 1) 
                Usage();
            fname = argv[optind++];
    }
            
    if (Logger::getTheLog("/tmp/ohsender.log") == 0) {
        cerr << "Can't initialize log" << endl;
        return 1;
    }
    Logger::getTheLog("")->setLogLevel(Logger::LLDEB1);

    LibUPnP *mylib = LibUPnP::getLibUPnP();
    if (!mylib) {
        cerr << "Can't get LibUPnP" << endl;
        return 1;
    }

    if (!mylib->ok()) {
        cerr << "Lib init failed: " <<
            mylib->errAsString("main", mylib->getInitError()) << endl;
        return 1;
    }
    mylib->setLogFileName("/tmp/libupnp.log", LibUPnP::LogLevelDebug);

    string reason;
    OHSNH hdl = Songcast::getSender(fname, reason);
    if (!hdl) {
        cerr << "Device has no OpenHome Sender service" << endl;
        return 1;
    }
    
    if ((op_flags & OPT_M)) {
        rdMonitor(hdl);
    } else if ((op_flags & OPT_m)) {
        metadata(hdl);
    } else {
        Usage();
    }

    return 0;
}
