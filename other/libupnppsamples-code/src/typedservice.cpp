#include <string>
#include <iostream>

#include "libupnpp/upnpplib.hxx"
#include "libupnpp/control/typedservice.hxx"

using namespace std;
using namespace UPnPClient;
using namespace UPnPP;

class MReporter : public UPnPClient::VarEventReporter {
public:
    void changed(const char *nm, int value) {
        cerr << "Reporter: changed(char *, int) invoked for nm " << nm <<
            " ??\n";
    }
    void changed(const char *nm, const char *value)  {
        cout << "Changed: " << nm << " : " << value << endl;
    }
};

int main(int argc, char **argv)
{
    argv++;argc--;
    if (argc < 3) {
        cerr << "Usage: tpservice NameOrUid partialservicetype action "
            "[arg [...]]\n";
        return 1;
    }
    string devname(*argv++);
    argc--;
    string servtp(*argv++);
    argc--;
    string actnm(*argv++);
    argc--;

    vector<string> args;
    while (argc--) {
        args.push_back(*argv++);
    }

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


    TypedService *srv = findTypedService(devname, servtp, true);

    if (!srv) {
        cerr << "Service " << devname << "/" << servtp << " not found" << endl;
        return 1;
    }

    map<string, string> data;
    int ret = srv->runAction(actnm, args, data);
    if (ret == 0) {
        for (auto& entry: data) {
            cout << entry.first << "->" << entry.second << endl;
        }
    } else {
        cerr << "runAction failed with code " << ret << endl;
        return 1;
    }

    MReporter reporter;
    srv->installReporter(&reporter);
    sleep(1000);
    return 0;
}
