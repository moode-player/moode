// This libupnpp sample is the control side for the hellodevice sample
// device implementation

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

// Locally defined control class for the Rendering Control
// service. We're just copying code from the libupnpp actually, this is
// just to show that it can be done outside the library.
class HelloCTL : public UPnPClient::Service {
public:

    /* Construct by copying data from device and service objects.*/
    HelloCTL(const UPnPClient::UPnPDeviceDesc& device,
          const UPnPClient::UPnPServiceDesc& service)
        : UPnPClient::Service(device, service) {
        // No event handling. Look at, e.g. rdcvolume.cpp for an example
    }

    virtual ~HelloCTL() {}

    virtual bool serviceTypeMatch(const std::string& tp) {
        return isHelloService(tp);
    }

    /* Test that a service type matches ours. This can be used
       with the directory traversal routine */
    static bool isHelloService(const std::string& st) {
        // Note that we do not care about the version
        return st.find("urn:upnpp-schemas:service:HelloService") == 0;
    }

    int hello();
};

int HelloCTL::hello()
{
    // Outgoing parameters. The object is constructed with the service
    // type (comes from the description we were built on), and the
    // action name. This is sufficient for some actions (ie stop())
    UPnPP::SoapOutgoing args(getServiceType(), "Hello");

    // This call does not need further outgoing arguments (see
    // rdcvolume for an example of these.

    // We have to declare a return parameter, even if we don't care
    // about the contents.
    UPnPP::SoapIncoming data;

    int ret = runAction(args, data);
    if (ret != UPNP_E_SUCCESS) {
        return ret;
    }
    string value;
    if (!data.get("MyValue", &value)) {
        cerr << "HelloCTL:getVolume: missing MyValue in response\n";
        return UPNP_E_BAD_RESPONSE;
    }

    cout << "Hello : " << value << endl;
    return 0;
}

// Device discovery part. We do it the easy way here: use a blocking
// call which will wait for the initial window to complete.  We could
// traverse the device directory in search, for example of a device of
// a specific kind instead of using a device name like we do here (there is
// an example of UPnPDeviceDirectory::traverse() usage in uplistdir.cpp).
//
// See rdcvolume.cpp for a version using callbacks to get the device asap
shared_ptr<HelloCTL> getService(const string& name)
{
    // Initialize and get a discovery directory handle.
    auto *superdir = UPnPClient::UPnPDeviceDirectory::getTheDir(1);
    if (nullptr == superdir) {
        cerr << "Discovery init failed\n";
        return shared_ptr<HelloCTL>();
    }

    UPnPClient::UPnPDeviceDesc devicedesc;
    // We look-up the device by either friendlyname or udn as the 2
    // namespaces are unlikely to overlap, no need to complicate things
    if (!superdir->getDevByUDN(name, devicedesc) && 
        !superdir->getDevByFName(name, devicedesc)) {
        cerr << "Can't connect to " << name << endl;
        return shared_ptr<HelloCTL>();
    }

    // Walk the device description service list, looking for ours
    for (const auto& ent : devicedesc.services) {
        // cout << ent.dump() << endl;
        if (HelloCTL::isHelloService(ent.serviceType)) {
            return make_shared<HelloCTL>(devicedesc, ent);
        }
    }
    cerr << name << " has no hello service\n";
    return shared_ptr<HelloCTL>();
}

int main(int argc, char **argv)
{
    // Initialize libupnpp logging
    Logger::getTheLog("")->setLogLevel(Logger::LLERR);

    argv++;argc--;
    if (argc != 1) {
        cerr << "Usage: rdcvolume rendererNameOrUid\n";
        return 1;
    }
    string devname(*argv++); 
    argc--;
    

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

    shared_ptr<HelloCTL> hlo = getService(devname);
    if (!hlo) {
        cerr << "Device " << devname << 
            " has no Hello service" << endl;
        return 1;
    }
    hlo->hello();
    return 0;
}

