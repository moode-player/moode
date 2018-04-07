// Just monitor multiple renderers.

#include <stdio.h>
#include <unistd.h>

#include <string>
#include <iostream>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/control/discovery.hxx"
#include "libupnpp/control/mediarenderer.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

template <class T> class MyReporter : public VarEventReporter {
public:
    MyReporter(const string& nm, T srvh)
        : m_srv(srvh), m_nm(nm)
    {
        m_srv->installReporter(this);
    }
    virtual ~MyReporter() {
        m_srv->installReporter(0);
    }

    // TransportState, Repeat, Shuffle, Id, TracksMax
    virtual void changed(const char *nm, int value)
    {
        cerr << m_nm << ": Changed: " << nm << " (int): " << value << endl;
    }

    // Stuff
    virtual void changed(const char *nm, const char *value)
    {
        cerr << m_nm << ": Changed: " << nm << " (char*): " << value << endl;
    }

    // IdArray
    virtual void changed(const char *nm, std::vector<int> ids)
    {
        cerr << m_nm << ": Changed: " << nm << " (vector<int>)" << endl;
    }

private:
    T m_srv;
    string m_nm;
};

UPnPClient::UPnPDeviceDirectory *superdir;

UPnPClient::MRDH getRenderer(const string& friendlyName)
{
    UPnPClient::UPnPDeviceDesc ddesc;
    if (superdir->getDevByFName(friendlyName, ddesc)) {
        return UPnPClient::MRDH(new UPnPClient::MediaRenderer(ddesc));
    }
    cerr << "getDevByFname failed" << endl;
    return UPnPClient::MRDH();
}

int main(int argc, char **argv)
{
    argc--; argv++;
    if (argc == 0) {
        cerr << "Usage: multirdr <friendlyname1> [<friendlyname2> ...]\n";
        return 1;
    }
    
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
    superdir = UPnPClient::UPnPDeviceDirectory::getTheDir();
    if (superdir == 0) {
        cerr << "multirdr: can't get superdir" << endl;
        return 1;
    }

    while (argc) {
        
        string friendlyName(*argv++); 
        argc--;

        UPnPClient::MRDH rdr = getRenderer(friendlyName);
        if (!rdr) {
            cerr << "Renderer " << friendlyName << " not found" << endl;
            return 1;
        }

        // Create a RenderingControl event monitor.
        UPnPClient::RDCH rdc = rdr->rdc();
        if (!rdc) {
            cerr << "Device " << friendlyName << 
                " has no RenderingControl service" << endl;
            return 1;
        }
        new MyReporter<RDCH>(friendlyName, rdc);

        // AVTransport?
        UPnPClient::AVTH avt = rdr->avt();
        if (avt) {
            new MyReporter<AVTH>(friendlyName, avt);
        }

        // Maybe this is an openhome device ? monitor the playlist
        UPnPClient::OHPRH ohpr = rdr->ohpr();
        UPnPClient::OHPLH ohpl;
        if (!ohpr) {
            cerr << "Device " << friendlyName << 
                " has no OpenHome support" << endl;
        } else {
            ohpl = rdr->ohpl();
            if (ohpl) {
                new MyReporter<OHPLH>(friendlyName, ohpl);
            }
        }
    }

    // Just stay around and let the reporter print events
    sleep(1000);
    return 0;
}
