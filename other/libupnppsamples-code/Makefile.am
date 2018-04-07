
AM_CXXFLAGS = -std=c++11

LDADD = -lupnpp -lpthread -lrt

bin_PROGRAMS = typedservice ohsender ohradio upexplorer multirdr \
             upsend uplistdir dumpXML \
             rdcvolume myrdcvolume hellodevice helloctl

typedservice_SOURCES = src/typedservice.cpp
upexplorer_SOURCES = src/upexplo.cpp
multirdr_SOURCES = src/multirdr.cpp
ohradio_SOURCES = src/ohradio.cpp
ohsender_SOURCES = src/ohsender.cpp
uplistdir_SOURCES = src/uplistdir.cpp
rdcvolume_SOURCES = src/rdcvolume.cpp
myrdcvolume_SOURCES = src/myrdcvolume.cpp
dumpXML_SOURCES = src/dumpXML.cpp
hellodevice_SOURCES = hello_src/hellodevice.cpp
helloctl_SOURCES = hello_src/helloctl.cpp

upsend_SOURCES = upsend_src/upsend.cpp \
               upsend_src/streamer.cpp \
               upsend_src/wav.cpp 
upsend_LDADD = -lupnpp -lmicrohttpd -lpthread -lrt 
upsend_CPPFLAGS = -I/usr/include/libupnpp -I$(top_srcdir)/src -DHAVE_STD_FUTURE=1
