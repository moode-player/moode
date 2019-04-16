/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Compilation:
 * sudo gcc -std=c99 rotenc.c -orotenc -lwiringPi
 * NOTE: std=c99 required if using ‘for’ loop initial declarations
 *
 * Usage:
 * rotenc <DELAY> <ACCEL> <STEP> <PIN_A> <PIN_B> <DEBUG 1|2>
 * rotenc 100 2 3 4 5 1
 *
 * 2019-04-12 TC moOde 5.0
 *
 */

#include <stdio.h>
#include <string.h>
#include <errno.h>
#include <stdlib.h>
#include <wiringPi.h>

static volatile int currentPos = 0;
static volatile int lastPos = 0;
static volatile int currentState;
static volatile int lastState = 0;
static volatile int strBufSize = (sizeof(int) * 8) + 1;
static volatile int isrActive = FALSE;
static volatile int PIN_A = 4;
static volatile int PIN_B = 5;
static volatile int ISRDBG = FALSE;

// Function prototypes
void encoderISR();
void int2bin(int bitMask, char *strBuf, int strBufSize, char *binStr);

//
// MAIN
//
int main(int argc, char * argv[])
{
	// Defaults
	int DELAY = 100; 
	int ACCEL = 2;
	int STEP = 3;
	//int PRIORITY = 42;
	int DEBUG = 0;

	// Print program version and exit
	if (argc == 2 && strcmp(argv[1], "-v") == 0) {
		printf("rotenc version: 1.1 \n");
		exit(0);
	}

	// Override defaults with values from input args if they are present
	if (argc > 1) {
		DELAY = atoi(argv[1]);
		ACCEL = atoi(argv[2]);
		STEP = atoi(argv[3]);
		PIN_A = atoi(argv[4]);
		PIN_B = atoi(argv[5]);
	}

	if (argc > 6) {
		int tmp = atoi(argv[6]);
		if (tmp > 0) DEBUG = TRUE;
		if (tmp == 2) ISRDBG = TRUE;
		printf("DEBUG: %d \n", DEBUG);
		printf("ISRDBG: %d \n", ISRDBG);
	}

	if (DEBUG) {
		printf("delay: %d \n", DELAY);
		printf("accel: %d \n", ACCEL);
		printf("step: %d \n", STEP);
		printf("pin_a: %d \n", PIN_A);
		printf("pin_b: %d \n", PIN_B);
	}

	// Format volume step command strings
	char cmdUpMore[33];
	char cmdDnMore[33];
	char volStep[1];
	strcpy(cmdUpMore, "/var/www/command/rotvol.sh -up ");
	strcpy(cmdDnMore, "/var/www/command/rotvol.sh -dn ");
	sprintf(volStep, "%d", STEP);
	strcat(cmdUpMore, volStep);
	strcat(cmdDnMore, volStep);

	// Initialize for WiringPi
	wiringPiSetup ();
	//piHiPri(PRIORITY);
	pinMode(PIN_A, INPUT); 
	pinMode(PIN_B, INPUT); 
	pullUpDnControl (PIN_A, PUD_UP); // turn on pull-up resistors
	pullUpDnControl (PIN_B, PUD_UP); 
	wiringPiISR (PIN_A, INT_EDGE_BOTH, &encoderISR);
	wiringPiISR (PIN_B, INT_EDGE_BOTH, &encoderISR);

	if (DEBUG) printf("start \n");
	
	// Update volume
	while(1) {
		if (currentPos > lastPos) {
			if ((currentPos - lastPos) < ACCEL) {
				system("/var/www/command/rotvol.sh -up 1");
			}
			else {
				system(cmdUpMore);
			}

			if (DEBUG) printf("up: %d \n", (currentPos - lastPos));
		}
	  	else if (currentPos < lastPos) {
			if ((lastPos - currentPos) < ACCEL) {
				system("/var/www/command/rotvol.sh -dn 1");
			}
			else {
				system(cmdDnMore);
			}

			if (DEBUG) printf("dn: %d \n", (lastPos - currentPos));
		}

		lastPos = currentPos;

		delay(DELAY);
	}
} 

//
// Interrupt Service Routine
//
// Check transition from lastState to currentState
// to determine encoder direction.
//
void encoderISR() {
    char strBuf[strBufSize];
    strBuf[strBufSize - 1] = '\0';
	char binStr[5];
	binStr[4] = '\0';

	if (isrActive == TRUE) return;
	isrActive = TRUE;

	int pinAState = digitalRead(PIN_A);
    int pinBState = digitalRead(PIN_B);

    int currentState = (pinAState << 1) | pinBState;	// 0000, 0001, 0010, 0011
    int bitMask = (lastState << 2) | currentState;		// 00xx, 01xx, 10xx, 11xx

    if (ISRDBG) int2bin(bitMask, strBuf, strBufSize, binStr);

	// CW state transitions (hex d, b, 4, 2)
    if (bitMask == 0b1101 || bitMask == 0b1011 || bitMask == 0b0100 || bitMask == 0b0010) {
		currentPos++;
		if (ISRDBG) printf("up %s %x %d \n", binStr, bitMask, currentPos);
	}
	// CCW state transitions (hex e, 8, 7, 1)
	else if (bitMask == 0b1110 || bitMask == 0b1000 || bitMask == 0b0111 || bitMask == 0b0001) {
		currentPos--;
		if (ISRDBG) printf("dn %s %x %d \n", binStr, bitMask, currentPos);
	}
	// The remaining state transitions represent (a) no state transition (b) both pins swapped states
	else {
    	if (ISRDBG) printf("-- %s %x \n", binStr, bitMask);
	}

    lastState = currentState;

	isrActive = FALSE;
}

//
// Integer to binary string
// Write to the buffer backwards so that the binary representation is in
// the correct MSB...LSB order. strBuf must have size >= sizeof(int) + 1
//
void int2bin(int bitMask, char *strBuf, int strBufSize, char *binStr) {
    strBuf += (strBufSize - 2);

    for (int i = 31; i >= 0; i--) {
        *strBuf-- = (bitMask & 1) + '0';
        bitMask >>= 1;
    }

	memcpy(binStr, &strBuf[strBufSize - 4], 4);
    //return subBuf;
}

/*
 *	pin AB bitmask
 *		lastAB|currentAB
 *
 *	states that indicate encoder is turning
 *		0001 = 1 DN, B goes low to high, A low 
 *		0111 = 7 DN, A goes low to high, B high
 *		1000 = 8 DN, A goes high to low, B low
 *		1110 = e DN, B goes high to low, A high
 *		
 *		0010 = 2 UP, A goes low to high, B low
 *		0100 = 4 UP, B goes high to low, A low
 *		1011 = b UP, B goes low to high, A high
 *		1101 = d UP, A goes high to low, B high
 *
 *	states where encoder direction can't be determined
 *		0000 = 0 no change
 *		0101 = 5 no change
 *		1010 = a no change
 *		1111 = f no change
 *		0011 = 3 both go high
 *		0110 = 6 both switch states
 *		1001 = 9 both switch states
 *		1100 = c both go low
 *
*/