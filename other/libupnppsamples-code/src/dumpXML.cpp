// This libupnpp sample program downloads all the XML description data
// from a given devices and writes it to a target directory.

#include <fcntl.h>
#include <sys/stat.h>
#include <sys/types.h>

#include <string>
#include <iostream>
#include <unordered_map>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/log.hxx"
#include "libupnpp/control/description.hxx"
#include "libupnpp/control/discovery.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

static void neutchars(const string& str, string& out, const string& chars)
{
    string::size_type startPos, pos;
    out.clear();
    for (pos = 0;;) {
        // Skip initial chars, break if this eats all.
        if ((startPos = str.find_first_not_of(chars, pos)) == string::npos) {
            break;
        }
        // Find next delimiter or end of string (end of token)
        pos = str.find_first_of(chars, startPos);
        // Add token to the output. Note: token cant be empty here
        if (pos == string::npos) {
            out += str.substr(startPos);
        } else {
            out += str.substr(startPos, pos - startPos) + "_";
        }
    }
}

static bool make_file(const string& nm, const string& content)
{
    int fd = open(nm.c_str(), O_CREAT|O_WRONLY|O_TRUNC, 0600);
    if (fd < 0) {
        cerr << "Could not create/open " << nm << endl;
        perror("open");
        return false;
    }
    if (write(fd, content.c_str(), content.size()) != content.size()) {
        close(fd);
        cerr << "Could not write to  " << nm << endl;
        perror("write");
        return false;
    }
    close(fd);
    return true;
}

int main(int argc, char *argv[])
{
    argv++;argc--;
    if (argc != 2) {
        cerr << "Usage: dumpXML <devNameOrUid> <targetdir>\n";
        cerr << "<targetdir> will be created if it does not exist\n";
        return 1;
    }
    string devname(*argv++); 
    argc--;
    string dirname(*argv++);
    argc--;
    
    // Initialize libupnpp logging
    Logger::getTheLog("")->setLogLevel(Logger::LLDEB);

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

    if (access(dirname.c_str(), X_OK|W_OK)) {
        if (mkdir(dirname.c_str(), 0755)) {
            cerr << "Could not create " << dirname << endl;
            perror("mkdir");
            return 1;
        }
    }

    string deviceXML;
    unordered_map<string, string> srvsXML;
    if (!superdir->getDescriptionDocuments(devname, deviceXML, srvsXML)) {
        cerr << "Could not retrieve description documents\n";
        return 1;
    }

    string path, fn, fn1;
    fn = devname + "-description.xml";
    neutchars(fn, fn1, "/ \n\r\t");
    path = dirname + "/" + fn1;
    if (!make_file(path, deviceXML)) {
        return 1;
    }
    for (auto entry : srvsXML) {
        fn = entry.first + ".xml";
        neutchars(fn, fn1, "/ \n\r\t");
        path = dirname + "/" + fn1;
        if (!make_file(path, entry.second)) {
            return 1;
        }
    }        
    return 0;
}
