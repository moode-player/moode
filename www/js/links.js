/*!
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * tsunamp player ui (C) 2013 Andrea Coiutti & Simone De Gregori
 * http://www.tsunamp.com
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
 */

$(document).on('click', 'a', function(event) {
		//debugLog('links.js: this.id: ' + this.id);
		//debugLog('links.js: this.className(s): ' + this.className);
		//debugLog('links.js: includes target-blank-link: ' + this.className.includes('target-blank-link'));
		//debugLog('links.js: this.attributes: ' + this.attributes);
		//debugLog('links.js: $(this).attr(tabindex): ' + $(this).attr('tabindex'));
		//return;

	    // Don't modify link if matches condition below
		if (
			// Specific links
			this.className.includes('target-blank-link') ||
			// Input dropdowns on config pages
			(this.className == 'active' && $(this).attr('tabindex') == 0)
		) {
			//debugLog('links.js: link not modified, match found in exclusion list');
			return;
		}

	    if (!$(this).hasClass('external')) {
			//debugLog('links.js: link will be modified, does not have class external');
	        event.preventDefault();
	        if (!$(event.target).attr('href')) {
       			//debugLog('links.js: link modified, case 1: does not have attr href');
	            location.href = $(event.target).parent().attr('href');
	        }
            else {
       			//debugLog('links.js: link modified, case 2: has attr href');
	            location.href = $(event.target).attr('href');
	        }
	    }
        else {
			//debugLog('links.js: link not modified, not in exclusion list but has class external');
			// Placeholder
	    }
    }
);
