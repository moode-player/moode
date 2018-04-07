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

/////////////// libupnpp OhRadio trial driver

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
#include "libupnpp/control/ohradio.hxx"
#include "libupnpp/control/ohinfo.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

UPnPDeviceDirectory *superdir;

int channelid;

class MReporter : public UPnPClient::VarEventReporter {
public:
    void changed(const char *nm, int value) {
        if (!strcmp(nm, "TransportState")) {
            string tpstate;
            switch(value) {
            case OHPlaylist::TPS_Unknown: tpstate = "Unknown"; break;
            case OHPlaylist::TPS_Buffering: tpstate = "Buffering"; break;
            case OHPlaylist::TPS_Paused: tpstate = "Paused"; break;
            case OHPlaylist::TPS_Playing: tpstate = "Playing"; break;
            case OHPlaylist::TPS_Stopped: tpstate = "Stopped"; break;
            }
            cout << "Changed: " << nm << " : " << tpstate << endl;
        } else if (!strcmp(nm, "Id")) {
            cout << "Changed: " << nm << " : " << value << endl;
            channelid = value;
        } else {
            cout << "Changed: " << nm << " : " << value << endl;
        }
    }
    void changed(const char *nm, const char *value)  {
        cout << "Changed: " << nm << " : " << value << endl;
    }

    void changed(const char *nm, UPnPDirObject meta) {
        cout << "Changed: " << nm << " : " << meta.dump() << endl;
    }

    void changed(const char * nm, std::vector<int> ids) {
        cout << "Changed: " << nm << " : ";
        for (unsigned int i = 0; i < ids.size(); i++) {
            cout << SoapHelp::i2s(ids[i]) << " ";
        }
        cout << endl;
    }
};

MRDH getRenderer(const string& friendlyName)
{
    if (superdir == 0) {
        superdir = UPnPDeviceDirectory::getTheDir();
    }

    UPnPDeviceDesc ddesc;
    if (superdir->getDevByFName(friendlyName, ddesc)) {
        return MRDH(new MediaRenderer(ddesc));
    }
    cerr << "getDevByFname failed" << endl;
    return MRDH();
}

void rdMonitor(OHRDH hdl, OHIFH hdlif)
{
    MReporter reporter;
    hdl->installReporter(&reporter);
    hdlif->installReporter(&reporter);
    while (true) {
        static int prevchan;
        sleep(2);
        string uri;
        UPnPDirObject dirent;
        if (0&& prevchan != channelid) {
            cerr << "New ChannelId: " << channelid << endl;
            prevchan = channelid;
#if 0
            if (hdl->channel(&uri, &dirent) == 0) {
                cout << "Channel: uri " << uri << "\nMetadata " <<
                    dirent.dump() << endl;
            }
#endif
            int ret;
            if ((ret = hdlif->metatext(&dirent)) == 0) {
                cout << "Metatext: " << dirent.dump() << endl;
            } else {
                cerr << "Metatext: failed: " << ret << endl;
            }
            
        }
    }
}

void rdIdArray(OHRDH hdl)
{
    vector<int> ids;
    int token = 0;
    int ret;
    if ((ret = hdl->idArray(&ids, &token)) != 0) {
        cerr << "idArray failed: " << ret << endl;
        return;
    }

    cout << "token: " << token << ". " << ids.size() << " ids: ";
    for (unsigned int i = 0; i < ids.size(); i++) {
        cout << SoapHelp::i2s(ids[i]) << " ";
    }
    cout << endl;
}

string rdReadList(OHRDH hdl, int id = -1)
{
    vector<int> ids;
    int token = 0;
    int ret;
    if ((ret = hdl->idArray(&ids, &token)) != 0) {
        cerr << "idArray failed: " << ret << endl;
        return string();
    }
    vector<OHPlaylist::TrackListEntry> ents;
    if ((ret = hdl->readList(ids, &ents)) != 0) {
        cerr << "readList failed: " << ret << endl;
        return string();
    }

    for (unsigned int i = 0; i < ents.size(); i++) {
        if (id == -1) {
            cout << "Id: " << SoapHelp::i2s(ents[i].id) <<
                " url " << ents[i].url << 
                "\nmetadata: " << ents[i].dirent.dump() << "\n";
        } else {
            if (ents[i].id == id) {
                return ents[i].url;
            }
        }
    }
    cout << endl;
    return string();
}

// Could not get this to work. Gets UPNP_E_BAD_RESPONSE on sendAction??
void rdRead(OHRDH hdl, int id)
{
    UPnPDirObject dirent;
    int ret;
    if ((ret = hdl->read(id, &dirent)) != 0) {
        cerr << "read failed: " << ret << endl;
        return;
    }
    cout << "read: metadata: " << dirent.dump() << endl;
}

void rdSetId(OHRDH hdl, int id)
{
    int ret;
    string url = rdReadList(hdl, id);
    if (url.empty()) {
        cerr << "Id " << id << " not found\n";
        return;
    }
    if ((ret = hdl->setId(id, url)) != 0) {
        cerr << "setId failed: " << ret << endl;
        return;
    }
    cout << "setId ok\n";
}

static char *thisprog;
static char usage [] =
" -a <renderer>: run idArray\n"
" -M <renderer>: monitor OHRadio\n"
" -p <renderer>: pause radio\n"
" -P <renderer>: play radio\n"
" -r <renderer> id: run read\n"
" -R <renderer>: run ReadList\n"
" -s <renderer> id: run setId\n"
" -S <renderer>: stop\n"
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
#define OPT_a    0x2
#define OPT_r    0x4
#define OPT_R    0x8
#define OPT_s    0x10
#define OPT_P    0x20
#define OPT_p    0x40
#define OPT_S    0x80

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
    while ((ret = getopt_long(argc, argv, "aMPpRrSs", 
                              long_options, &option_index)) != -1) {
        switch (ret) {
        case 'a': if (op_flags) Usage(); op_flags |= OPT_a; break;
        case 'M': if (op_flags) Usage(); op_flags |= OPT_M; break;
        case 'P': if (op_flags) Usage(); op_flags |= OPT_P; break;
        case 'p': if (op_flags) Usage(); op_flags |= OPT_p; break;
        case 'R': if (op_flags) Usage(); op_flags |= OPT_R; break;
        case 'r': if (op_flags) Usage(); op_flags |= OPT_r; break;
        case 's': if (op_flags) Usage(); op_flags |= OPT_s; break;
        case 'S': if (op_flags) Usage(); op_flags |= OPT_S; break;
        default:
            Usage();
        }
    }

    if (op_flags & (OPT_M|OPT_a|OPT_R|OPT_p|OPT_P|OPT_S)) {
            if (optind != argc - 1) 
                Usage();
            fname = argv[optind++];
    }
    if (op_flags & (OPT_r|OPT_s)) {
        if (optind != argc - 2) 
                Usage();
            fname = argv[optind++];
            arg = argv[optind++];
    }
            
    if (Logger::getTheLog("/tmp/ohradio.log") == 0) {
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

    MRDH rdr = getRenderer(fname);
    if (!rdr) {
        cerr << "Can't connect torenderer\n";
        return 1;
    }
    OHRDH hdl = rdr->ohrd();
    if (!hdl) {
        cerr << "Device has no OHRadio service" << endl;
        return 1;
    }
    OHIFH hdlif = rdr->ohif();
    if (!hdlif) {
        cerr << "Device has no OHInfo service" << endl;
        return 1;
    }
    
    if ((op_flags & OPT_M)) {
        rdMonitor(hdl, hdlif);
    } else if ((op_flags & OPT_a)) {
        rdIdArray(hdl);
    } else if ((op_flags & OPT_R)) {
        rdReadList(hdl);
    } else if ((op_flags & OPT_p)) {
        int ret = hdl->pause();
        if (ret) {
            cerr << "Pause: " << SoapHelp::i2s(ret);
            return 1;
        } else {
            cout << "Pause Ok\n";
        }
    } else if ((op_flags & OPT_P)) {
        int ret = hdl->play();
        if (ret) {
            cerr << "Play: " << SoapHelp::i2s(ret);
            return 1;
        } else {
            cout << "Play Ok\n";
        }
    } else if ((op_flags & OPT_S)) {
        int ret = hdl->stop();
        if (ret) {
            cerr << "Stop: " << SoapHelp::i2s(ret);
            return 1;
        } else {
            cout << "Stop Ok\n";
        }
    } else if ((op_flags & OPT_r)) {
        rdRead(hdl, atoi(arg.c_str()));
    } else if ((op_flags & OPT_s)) {
        rdSetId(hdl, atoi(arg.c_str()));
    } else {
        Usage();
    }

    return 0;
}
