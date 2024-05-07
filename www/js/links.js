/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
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
