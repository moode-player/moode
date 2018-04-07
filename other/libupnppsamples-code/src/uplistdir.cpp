// This libupnpp sample program lists all devices and services found
// on the local network

#include <string>
#include <iostream>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/log.hxx"
#include "libupnpp/control/description.hxx"
#include "libupnpp/control/discovery.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

static bool traverser(const UPnPDeviceDesc& device, const UPnPServiceDesc& srv)
{
    cout << device.friendlyName <<" ("<< device.deviceType << ") " << 
        srv.serviceType << endl;
    return true;
}

int main(int argc, char *argv[])
{
    // Initialize libupnpp logging
    Logger::getTheLog("")->setLogLevel(Logger::LLERR);

    // Get a handle to the main lib object. You don't really need to
    // do this actually. We just do it to check that the lib
    // initialized ok, but there are other possible uses, see the doc
    // in the include file.
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

    // Get a handle to the device directory. You can call this
    // multiple times, only the first call does something, any further
    // call will just return the pointer to the singleton.
    UPnPDeviceDirectory *superdir = UPnPDeviceDirectory::getTheDir();
    if (superdir == 0) {
        cerr << "Cant access device directory\n";
        return 1;
    }

    // Call the directory traversal. This will wait for the initial
    // time window. It's possible to see the devices as they appear
    // instead by using UPnPDeviceDirectory::addCallback(). See for
    // example rdcvolume.cpp
    superdir->traverse(traverser);
    
    return 0;
}
