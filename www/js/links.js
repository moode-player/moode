/*!
 * SPDX-License-Identifier: GPL-3.0-or-later
 * Copyright 2014 The moOde audio player project / Tim Curtis
 * Copyright 2013 The tsunamp player ui / Andrea Coiutti & Simone De Gregori
*/

$(document).on('click', 'a', function(event) {
	/*
	// DEBUG:
	console.log('links.js: ID:     = ' + this.id);
	console.log('links.js: Class:  = ' + this.className);
	console.log('links.js: Tabndx: = ' + $(this).attr('tabindex'));
	console.log('links.js: TBlank: = ' + this.className.includes('target-blank-link'));
	console.log('links.js: Href:   = ' + $(event.target).attr('href'));
	console.log('links.js: PHref:  = ' + $(event.target).parent().attr('href'));
	*/

	if (this.className.includes('target-blank-link') ||						// The "m" dropdown menu and Links that open documents
		(this.className == 'active' && $(this).attr('tabindex') == 0) ||	// A selection change in the Config dropdowns
		$(event.target).attr('href')) {										// A normal href
		//console.log('links.js: Branch A: [no action]');
		return;
	} else {																// Parent has the href ()
		//console.log('links.js: Branch B: [redirect]: ' + $(event.target).parent().attr('href'));
		event.preventDefault();
		location.href = $(event.target).parent().attr('href');
	}
});
