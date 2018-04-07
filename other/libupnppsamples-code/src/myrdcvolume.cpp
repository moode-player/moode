// This libupnpp sample does about the same thing as rdcvolume, but it defines
// its own control classes instead of the ones predefined by libupnpp.

#include <termios.h>

#include <string>
#include <iostream>
#include <mutex>
#include <condition_variable>
#include <functional>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/soaphelp.hxx"
#include "libupnpp/control/discovery.hxx"
#include "libupnpp/control/device.hxx"
#include "libupnpp/control/service.hxx"

using namespace std;
using namespace std::placeholders;

//using namespace UPnPClient;
//using namespace UPnPP;

// Locally defined control class for the Rendering Control
// service. We're just copying code from the libupnpp actually, this is
// just to show that it can be done outside the library.
class MyRDC : public UPnPClient::Service {
public:

    /* Construct by copying data from device and service objects.*/
    MyRDC(const UPnPClient::UPnPDeviceDesc& device,
          const UPnPClient::UPnPServiceDesc& service)
        : UPnPClient::Service(device, service) {
        serviceInit(device, service);
    }
    MyRDC() {}
    virtual ~MyRDC() {}

    bool serviceInit(const UPnPClient::UPnPDeviceDesc& device,
                     const UPnPClient::UPnPServiceDesc& service) {
        // We want to have a look at our service description file
        // (xml) to retrieve the min/max/step values for the
        // volume. Not all services need to do this.
        UPnPClient::UPnPServiceDesc::Parsed sdesc;
        if (service.fetchAndParseDesc(device.URLBase, sdesc)) {
            auto it = sdesc.stateTable.find("Volume");
            if (it != sdesc.stateTable.end() && it->second.hasValueRange) {
                m_volmin = it->second.minimum;
                m_volmax = it->second.maximum;
                m_volstep = it->second.step;
            } else {
                // ??
                m_volmin = 0;
                m_volmax = 100;
                m_volstep = 1;
            }
        }
        return true;
    }
    
    virtual bool serviceTypeMatch(const std::string& tp) {
        return isRDCService(tp);
    }

    /* Test that a service type matches ours. This can be used
       with the directory traversal routine */
    static bool isRDCService(const std::string& st) {
        // Note that we do not care about the version
        return st.find("urn:schemas-upnp-org:service:RenderingControl") == 0;
    }

    int setVolume(int volume, const std::string& channel = "Master");
    int getVolume(const std::string& channel = "Master");

    /* Volume settings params */
    int m_volmin;
    int m_volmax;
    int m_volstep;

private:

    void evtCallback(const unordered_map<string, string>& props) {
        // The callback gets a map of changed properties as
        // parameter. In turn, the classes defined by libupnpp
        // (e.g. RenderingControl) call a client event reporter in an
        // uniform way, and after massaging the data a bit, but you
        // can do whatever you like here. UPnP AV is special
        // because it coalesces the values inside a LastChange XML
        // string. Many services just report them individually.
        cerr << "evtCallback: props size " << props.size() << endl;
        for (const auto& ent : props) {
            cout << ent.first << " -> " << ent.second << endl;
        }
    }

    // Register our member function callback. It's just an
    // std::function, other approaches may be possible.
    void registerCallback() {
        UPnPClient::Service::registerCallback(
            std::bind(&MyRDC::evtCallback, this, _1));
    }
};

// The libupnpp equivalent checks and converts the range, and also
// that a volume change is actually required, and does appropriate
// rounding. We're just showing how to send a parameter here.  The arg
// names are defined by the service description XML file, so it would
// be possible to construct the call after the XML data (a la
// upnp-inspector), there is nothing in libupnpp to prevent it.
int MyRDC::setVolume(int ivol, const string& channel)
{
    // Outgoing parameters. The object is constructed with the service
    // type (comes from the description we were built on), and the
    // action name. This is sufficient for some actions (ie stop())
    UPnPP::SoapOutgoing args(getServiceType(), "SetVolume");

    // This call needs further outgoing arguments, which goes in there
    // through an operator() overload
    args("InstanceID", "0")("Channel", channel)
        ("DesiredVolume", UPnPP::SoapHelp::i2s(ivol));

    // We have to declare a return parameter, even if we don't care
    // about the contents.
    UPnPP::SoapIncoming data;

    return runAction(args, data);
}

// Same as setVolume really, except that we look at the return data.
int MyRDC::getVolume(const string& channel)
{
    UPnPP::SoapOutgoing args(getServiceType(), "GetVolume");
    args("InstanceID", "0")("Channel", channel);
    UPnPP::SoapIncoming data;
    int ret = runAction(args, data);
    if (ret != UPNP_E_SUCCESS) {
        return ret;
    }
    int volume;
    if (!data.get("CurrentVolume", &volume)) {
        cerr << "MyRDC:getVolume: missing CurrentVolume in response\n";
        return UPNP_E_BAD_RESPONSE;
    }

    return volume;
}


// Device discovery part. We do it the easy way here: use a blocking
// call which will wait for the initial window to complete.  We could
// traverse the device directory in search, for example of a device of
// a specific kind instead of using a device name like we do here (there is
// an example of UPnPDeviceDirectory::traverse() usage in uplistdir.cpp).
//
// See rdcvolume.cpp for a version using callbacks to get the device asap
shared_ptr<MyRDC> getService(const string& name)
{
    // Initialize and get a discovery directory handle.
    auto *superdir = UPnPClient::UPnPDeviceDirectory::getTheDir(2);
    if (nullptr == superdir) {
        cerr << "Discovery init failed\n";
        return shared_ptr<MyRDC>();
    }

    UPnPClient::UPnPDeviceDesc devicedesc;
    // We look-up the device by either friendlyname or udn as the 2
    // namespaces are unlikely to overlap, no need to complicate things
    if (!superdir->getDevByUDN(name, devicedesc) && 
        !superdir->getDevByFName(name, devicedesc)) {
        cerr << "Can't connect to " << name << endl;
        return shared_ptr<MyRDC>();
    }

    // UPnPClient::Device does nothing really interesting actually. It
    // just holds the device description. Derived device
    // implementations, for example for a MediaRenderer, add a bit of
    // value by creating objects for the well-known services. Here we
    // just dispense with the device creation, and directly create a
    // service object.
    
    // Walk the device description service list, looking for ours
    for (const auto& ent : devicedesc.services) {
        if (MyRDC::isRDCService(ent.serviceType)) {
            cout << ent.dump() << endl;
            return make_shared<MyRDC>(devicedesc, ent);
        }
    }
    cerr << name << " has no rendering control service\n";
    return shared_ptr<MyRDC>();
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
    Logger::getTheLog("")->setLogLevel(Logger::LLERR);

    // Explicitely initialize libupnpp so that we can display a
    // possible error
    UPnPP::LibUPnP *mylib = UPnPP::LibUPnP::getLibUPnP();
    if (!mylib) {
        cerr << "Can't get LibUPnP" << endl;
        return 1;
    }
    if (!mylib->ok()) {
        cerr << "Lib init failed: " <<
            mylib->errAsString("main", mylib->getInitError()) << endl;
        return 1;
    }


    shared_ptr<MyRDC> rdc = getService(devname);
    if (!rdc) {
        cerr << "Device " << devname << 
            " has no RenderingControl service" << endl;
        return 1;
    }

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

