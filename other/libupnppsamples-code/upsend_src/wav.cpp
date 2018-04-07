/* Copyright (C) 2014 J.F.Dockes
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

#include "config.h"

#include <string.h>

#include "wav.h"

inline int inttoichar4(unsigned char *cdb, unsigned int addr)
{
    cdb[3] = (addr & 0xff000000) >> 24;
    cdb[2] = (addr & 0x00ff0000) >> 16;
    cdb[1] = (addr & 0x0000ff00) >> 8;
    cdb[0] =  addr & 0x000000ff;
    return 4;
}

inline int inttoichar2(unsigned char *cdb, unsigned int cnt)
{
    cdb[1] = (cnt & 0x0000ff00) >> 8;
    cdb[0] =  cnt & 0x000000ff;
    return 2;
}


#if 0
// For reference: definition of a wav header
// commented values are given for 44100/16/2
struct wav_header {
    /*0 */char  riff[4];     /* = 'RIFF' */
    /*4 */int32 rifflen;     /* longueur des infos qui suivent= datalen+36 */
    /*8 */char  wave[4];     /* = 'WAVE' */

    /*12*/char  fmt[4];      /* = 'fmt ' */
    /*16*/int32 fmtlen;      /* = 16 */
    /*20*/int16 formtag;     /* = 1 : PCM */
    /*22*/int16 nchan;       /* = 2 : nombre de canaux */
    /*24*/int32 sampspersec; /* = 44100 : Nbr d'echantillons par seconde */
    /*28*/int32 avgbytpersec;/* = 176400 : Nbr moyen octets par seconde */
    /*32*/int16 blockalign;  /* = 4 : nombre d'octets par echantillon */
    /*34*/int16 bitspersamp; /* = 16 : bits par echantillon */

    /*36*/char  data[4];     /* = 'data' */
    /*40*/int32 datalen;     /* Nombre d'octets de son qui suivent */
    /*44*/char data[];
};
#endif /* if 0 */

#define WAVHSIZE 44
#define RIFFTOWAVCNT 36

// Format header. Note the use of intel format integers. Input buffer must 
// be of size >= 44
int makewavheader(char *buf, int maxsize, int freq, int bits, 
                  int chans, unsigned int databytecnt)
{
    if (maxsize < WAVHSIZE)
        return -1;

    unsigned char *cp = (unsigned char *)buf;
    memcpy(cp, "RIFF", 4);
    cp += 4;
    inttoichar4(cp, databytecnt + RIFFTOWAVCNT);
    cp += 4;
    memcpy(cp, "WAVE", 4);
    cp += 4;

    memcpy(cp, "fmt ", 4);
    cp += 4;
    inttoichar4(cp, 16);
    cp += 4;
    inttoichar2(cp, 1);
    cp += 2;
    inttoichar2(cp, chans);
    cp += 2;
    inttoichar4(cp, freq);
    cp += 4;
    inttoichar4(cp, freq * chans * (bits / 8));
    cp += 4;
    inttoichar2(cp, chans * bits / 8);
    cp += 2;
    inttoichar2(cp, bits);
    cp += 2;

    memcpy(cp, "data", 4);
    cp += 4;
    inttoichar4(cp, databytecnt);
    cp += 4;

    return WAVHSIZE;
}
