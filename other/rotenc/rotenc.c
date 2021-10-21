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
 * rotenc <poll_interval> <accel_factor> <volume_step> <pin_a> <pin_b> <print_debug 1|2>
 * rotenc 100 2 3 23 24 1
 *
 */

#include <stdio.h>
#include <string.h>
#include <errno.h>
#include <stdlib.h>
#include <wiringPi.h>

static volatile int current_pos = 0;
static volatile int last_pos = 0;
static volatile int current_state;
static volatile int last_state = 0;
static volatile int pin_a = 23; // SoC pin numbering
static volatile int pin_b = 24;
static volatile int str_buf_size = (sizeof(int) * 8) + 1;
static volatile int isr_active = FALSE;
static volatile int print_isr_debug = FALSE;

// Function prototypes
void encoder_isr();
void int_to_bin(int bit_mask, char *str_buf, int str_buf_size, char *bin_str);

//
// MAIN
//
int main(int argc, char * argv[])
{
	// Defaults
	int poll_interval = 100; // milliseconds
	int accel_factor = 2;
	int volume_step = 3;
	int print_debug = 0;

	// Print program version and exit
	if (argc == 2 && strcmp(argv[1], "-v") == 0) {
		printf("rotenc.c version: 1.3.0 \n");
		exit(0);
	}

	// Override defaults with values from input args if they are present
	if (argc > 1) {
		poll_interval = atoi(argv[1]);
		accel_factor = atoi(argv[2]);
		volume_step = atoi(argv[3]);
		pin_a = atoi(argv[4]);
		pin_b = atoi(argv[5]);
	}

	if (argc > 6) {
		int tmp = atoi(argv[6]);
		if (tmp > 0) print_debug = TRUE;
		if (tmp == 2) print_isr_debug = TRUE;
		printf("print_debug: %d \n", print_debug);
		printf("print_isr_debug: %d \n", print_isr_debug);
	}

	if (print_debug) {
		printf("poll_interval: %d \n", poll_interval);
		printf("accel_factor: %d \n", accel_factor);
		printf("volume_step: %d \n", volume_step);
		printf("pin_a: %d \n", pin_a);
		printf("pin_b: %d \n", pin_b);
	}

	// Format volume step command strings
	char cmd_up_more[33];
	char cmd_dn_more[33];
	char volume_step_str[1];
	strcpy(cmd_up_more, "/var/www/command/rotvol.sh -up ");
	strcpy(cmd_dn_more, "/var/www/command/rotvol.sh -dn ");
	sprintf(volume_step_str, "%d", volume_step);
	strcat(cmd_up_more, volume_step_str);
	strcat(cmd_dn_more, volume_step_str);

	// Setup GPIO
	wiringPiSetupGpio();
	pinMode(pin_a, INPUT);
	pinMode(pin_b, INPUT);
	pullUpDnControl(pin_a, PUD_UP); // Turn on pull-up resistors
	pullUpDnControl(pin_b, PUD_UP);
	wiringPiISR(pin_a, INT_EDGE_BOTH, &encoder_isr);
	wiringPiISR(pin_b, INT_EDGE_BOTH, &encoder_isr);

	if (print_debug) printf("Start \n");

	// Polling loop for updating volume
	while(1) {
		if (current_pos > last_pos) {
			if ((current_pos - last_pos) < accel_factor) {
				system("/var/www/command/rotvol.sh -up 1");
			}
			else {
				system(cmd_up_more);
			}

			if (print_debug) printf("+ %d \n", (current_pos - last_pos));
		}
	  	else if (current_pos < last_pos) {
			if ((last_pos - current_pos) < accel_factor) {
				system("/var/www/command/rotvol.sh -dn 1");
			}
			else {
				system(cmd_dn_more);
			}

			if (print_debug) printf("- %d \n", (last_pos - current_pos));
		}

		last_pos = current_pos;

		delay(poll_interval);
	}
}

//
// Interrupt service routine (ISR)
// Check transition from last_state to current_state to determine encoder direction
//
void encoder_isr() {
    char str_buf[str_buf_size];
    str_buf[str_buf_size - 1] = '\0';
	char bin_str[5];
	bin_str[4] = '\0';

	if (isr_active == TRUE) return;
	isr_active = TRUE;

	int pin_a_state = digitalRead(pin_a);
    int pin_b_state = digitalRead(pin_b);

    int current_state = (pin_a_state << 1) | pin_b_state;	// 0000, 0001, 0010, 0011
    int bit_mask = (last_state << 2) | current_state;		// 00xx, 01xx, 10xx, 11xx

    if (print_isr_debug) int_to_bin(bit_mask, str_buf, str_buf_size, bin_str);

	// CW state transitions (hex d, b, 4, 2)
    if (bit_mask == 0b1101 || bit_mask == 0b1011 || bit_mask == 0b0100 || bit_mask == 0b0010) {
		current_pos++;
		if (print_isr_debug) printf("up %s %x %d \n", bin_str, bit_mask, current_pos);
	}
	// CCW state transitions (hex e, 8, 7, 1)
	else if (bit_mask == 0b1110 || bit_mask == 0b1000 || bit_mask == 0b0111 || bit_mask == 0b0001) {
		current_pos--;
		if (print_isr_debug) printf("dn %s %x %d \n", bin_str, bit_mask, current_pos);
	}
	// The remaining state transitions represent (a) no state transition (b) both pins swapped states
	else {
    	if (print_isr_debug) printf("-- %s %x \n", bin_str, bit_mask);
	}

    last_state = current_state;

	isr_active = FALSE;
}

//
// Integer to binary string
// Write to the buffer backwards so that the binary representation is in the correct MSB...LSB order
// str_buf must have size >= sizeof(int) + 1
//
void int_to_bin(int bit_mask, char *str_buf, int str_buf_size, char *bin_str) {
    str_buf += (str_buf_size - 2);

    for (int i = 31; i >= 0; i--) {
        *str_buf-- = (bit_mask & 1) + '0';
        bit_mask >>= 1;
    }

	memcpy(bin_str, &str_buf[str_buf_size - 4], 4);
    //return subBuf;
}

/*
 *	Pin A/B bit_mask
 *		last_AB|current_AB
 *
 *	States that indicate encoder is turning
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
 *	States where encoder direction can't be determined
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
