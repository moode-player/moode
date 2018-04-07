// This libupnpp sample control program connects to a media renderer,
// designated by its friendly name or uuid, and lets you adjust the
// volume from the keyboard. It uses the MediaRenderer and
// RenderingControl device and service classes from libupnpp.

#include <termios.h>

#include <string>
#include <iostream>
#include <mutex>
#include <condition_variable>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/control/discovery.hxx"
#include "libupnpp/control/mediarenderer.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

// Event reporter. We are not really using it here, apart from printing
// stuff to the console.
class MyReporter : public VarEventReporter {
public:
    MyReporter(const string& nm, RDCH rdc)
        : m_nm(nm), m_srv(rdc)
    {
        m_srv->installReporter(this);
    }
    virtual ~MyReporter() {
        m_srv->installReporter(0);
    }
        
    virtual void changed(const char *nm, int value)
    {
        cerr << m_nm << " : Changed: " << nm << " (int): " << value << endl;
    }

    virtual void changed(const char *nm, const char *value)
    {
        cerr << m_nm << " : Changed: " << nm << " (str): " << value << endl;
    }

private:
    string m_nm;
    RDCH m_srv;
};


//
// Device discovery part. We can't just connect to the device, UPnP
// does not work like this. The lib is going to broadcast a request
// for devices to signal their presence, withing a fixed time window
// (a few seconds). We could just wait for the full window and then
// connect, but here, we are doing the fancy thing, setting up a
// callback which will be called as each new device manifests itself,
// so that we can connect asap. The callback is called from a
// different thread, so we need locking.
std::mutex discolock;
std::condition_variable discocv;

// Using shared variables, but the callback is an std::function, so
// there are other possibilities.
UPnPDeviceDesc o_devicedesc;
string o_name;

static bool discoCB(const UPnPDeviceDesc& device, const UPnPServiceDesc&)
{
    std::unique_lock<std::mutex> lock(discolock);
    //cerr << "discoCB: got " << device.friendlyName << endl;
    if (!device.UDN.compare(o_name) || !device.friendlyName.compare(o_name)) {
        //cerr << "discoCB: FOUND\n";
        o_devicedesc = device;
        discocv.notify_all();
    }
    return true;
}

MRDH getRenderer(const string& name)
{
    // Add a discovery callback, and remember about it in case we're
    // called several times (not the case in this program).
    o_name = name;
    static int cbindex = -1;
    if (cbindex == -1) {
        cbindex = UPnPDeviceDirectory::addCallback(discoCB);
    }

    // Initialize and get a discovery directory handle. This must be
    // done *after* the callback is set up, else we may miss devices.
    static UPnPDeviceDirectory *superdir;
    if (superdir == 0) {
        superdir = UPnPDeviceDirectory::getTheDir();
        if (superdir == 0) {
            cerr << "Discovery init failed\n";
            return MRDH();
        }
    }

    // Until the initial delay is through, use the reporter to test
    // devices as they come, so that we may respond asap
    for (;;) {
        std::unique_lock<std::mutex> lock(discolock);
#if FUTURE
        // Older versions of the lib don't have this.
        int ms = superdir->getRemainingDelayMs();
#else
        int ms = superdir->getRemainingDelay() * 1000;
#endif
        if (ms > 0) {
            discocv.wait_for(lock, std::chrono::milliseconds(ms));
            if (!o_devicedesc.UDN.compare(name) ||
                !o_devicedesc.friendlyName.compare(name)) {
                //cerr << "getRenderer: early wakeup\n";
                return MRDH(new MediaRenderer(o_devicedesc));
            }
        } else {
            // Initial delay done. We'll try one last time to ask the
            // directory about our device
            break;
        }
    }

    // Try one last time just in case.
    if (superdir->getDevByUDN(name, o_devicedesc)) {
        return MRDH(new MediaRenderer(o_devicedesc));
    } else if (superdir->getDevByFName(name, o_devicedesc)) {
        return MRDH(new MediaRenderer(o_devicedesc));
    }

    cerr << "Can't connect to " << name << endl;
    return MRDH();
}

// nothing to see here: character reading, one at a time.
int mygetch()
{
    struct termios oldt, newt;
    int ch;
    tcgetattr(STDIN_FILENO, &oldt);
    newt = oldt;
    newt.c_lflag &= ~(ICANON | ECHO);
    tcsetattr(STDIN_FILENO, TCSANOW, &newt);
    ch = getchar();
    tcsetattr(STDIN_FILENO, TCSANOW, &oldt);
    return ch;
}

int main(int argc, char **argv)
{
    argv++;argc--;
    if (argc != 1) {
        cerr << "Usage: rdcvolume rendererNameOrUid\n";
        return 1;
    }
    string devname(*argv++); 
    argc--;
    
    // Initialize libupnpp logging
    Logger::getTheLog("stderr")->setLogLevel(Logger::LLDEB1);

    // Explicitely initialize libupnpp so that we can display a
    // possible error
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


    // Connect to the device
    MRDH rdr = getRenderer(devname);
    if (!rdr) {
        cerr << "Renderer " << devname << " not found" << endl;
        return 1;
    }

    // The MediaRender class has magic to create the well-know service
    // class instances, here RenderingControl
    RDCH rdc = rdr->rdc();
    if (!rdc) {
        cerr << "Device " << devname << 
            " has no RenderingControl service" << endl;
        return 1;
    }

    // Create the event-reporting object. Not used here actually, but
    // it will print volume change events.
    new MyReporter(devname, rdc);

    cout << "q = quit, 'u' = up, 'd' = down\n";
    for (;;) {
        int vol = rdc->getVolume();
        cout << "Volume now " << vol << endl;
        int key = mygetch();

        if (key == 'q') {
            cout << "QUIT\n";
            break;
        } else if (key == 'u') {
            vol += 5;
            if (vol > 100) {
                vol = 100;
            }
        } else if (key == 'd') {
            vol -= 5;
            if (vol < 0) {
                vol = 0;
            }
        } else {
            cout << "Bad key: " << (char)key << endl;
            continue;
        }
        if (rdc->setVolume(vol)) {
            cerr << "setVolume(" << vol << ") failed\n";
        }
    }

    return 0;
}

