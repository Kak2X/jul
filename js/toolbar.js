/*
	Reply toolbar replacement
*/

var hooked_once = false;
var hooks = []; // List of toolbar and textarea hooks
var smilies = JSON.parse(document.getElementById('js_smilies').value); // Receives JSON of smilies from PHP
var overMenu = false; // Determines if the opened menu is being mouse-hovered
var popup = null; // Currently opened menu
var stat = []; // Held button status
//var preload_images = false;


/* Menu / button definitions go here */

// Text color submenu
var textMenu = [
	{title: "Red", 		img: "images/toolbar/fred.gif", 		action: 'insertText', arguments: ['[red]', '[/color]']},
	{title: "Yellow", 	img: "images/toolbar/fyellow.gif", 		action: 'insertText', arguments: ['[yellow]', '[/color]']},
	{title: "Orange", 	img: "images/toolbar/forange.gif", 		action: 'insertText', arguments: ['[orange]', '[/color]']},
	{title: "Green", 	img: "images/toolbar/fgreen.gif", 		action: 'insertText', arguments: ['[green]', '[/color]']},
	{title: "Blue", 	img: "images/toolbar/fblue.gif", 		action: 'insertText', arguments: ['[blue]', '[/color]']},
	{title: "Pink", 	img: "images/toolbar/fpink.gif", 		action: 'insertText', arguments: ['[pink]', '[/color]']},
	{title: "Black", 	img: "images/toolbar/fblack.gif", 		action: 'insertText', arguments: ['[black]', '[/color]']},
	{title: "White", 	img: "images/toolbar/bgblack.gif", 		action: 'insertText', arguments: ['[white]', '[/color]']},
];

// Smilies submenu, which receives data from the JSON data included in the hidden element
var smilMenu = [];
for (var i = 0; i < smilies.length; i++) 
	if (smilies[i])
		smilMenu.push({title: smilies[i][0], img: smilies[i][1], action: 'insertText', arguments: [smilies[i][0]]});

// Main menu
var buttons = [
	{title: "Text Color", 		img: "images/toolbar/fcolor.gif", 		action: 'createMenu', arguments: ['textMenu', 200, 4]},
	{title: null},
	{title: "Bold", 			img: "images/toolbar/bold.gif", 		action: 'insertText', arguments: ['[b]', '[/b]']},
	{title: "Italic", 			img: "images/toolbar/italic.gif", 		action: 'insertText', arguments: ['[i]', '[/i]']},
	{title: "Underline", 		img: "images/toolbar/underline.gif", 	action: 'insertText', arguments: ['[u]', '[/u]']},
	{title: "Strikethrough",	img: "images/toolbar/strike.gif", 		action: 'insertText', arguments: ['[s]', '[/s]']},
	{title: null},
	{title: "Link",				img: "images/toolbar/link.gif",			action: 'insertText', arguments: ['[url]', '[/url]']},
	{title: "Image", 			img: "images/toolbar/image.gif", 		action: 'insertText', arguments: ['[img]', '[/img]']},
	{title: "Smilies", 			img: "images/toolbar/smiley.gif", 		action: 'createMenu', arguments: ['smilMenu', 100, 7]},
];
/* --- */

// Toolbar loader
function toolbarHook(elem) {
	var td			= document.getElementById(elem + 'td'); // Insert element
	var textarea	= document.getElementById(elem + 'txt'); // Hooks
	hooks.push([td, textarea]);
	
	td.insertAdjacentHTML("afterbegin", toolbarHtml(hooks.length - 1));
	
	/*
	if (!hooked_once && preload_images) {
		// Preload smilies in hidden div
		
		var preloader = document.createElement('div');
		preloader.setAttribute("id", "_toolbar_img_preload");
		preloader.style.cssText = 'display: none';
		

		//var preloader = document.getElementById('_toolbar_img_preload');
		for (var i = 0; i < smilMenu.length; i++)
			preloader.innerHTML += "<img src='"+ smilMenu[i].img +"'>";
		for (var i = 0; i < textMenu.length; i++)
			preloader.innerHTML += "<img src='"+ textMenu[i].img +"'>";
		
		document.body.appendChild(preloader);
	}
	*/
	hooked_once = true;
}


// Button click function
function actionCaller(menu, id, i, e, offset = 0) {
	var selOpt = window[menu][i];
	var arguments = [id, i + offset]; // Toolbar ID and button index are always the first two arguments
	if (selOpt.arguments !== undefined) {
		for (var j = 0; j < selOpt.arguments.length; j++) { // Custom arguments come later
			arguments.push(selOpt.arguments[j]);
		}
	}
	arguments.push(e); // The event comes last, since it's not mandatory
	window[selOpt.action].apply(null, arguments); // Call the function with arguments as array
}

// Base functions
function insertText(id, i, start, end = '') {

	var txt			= hooks[id][1];
	
	/*
		in short:
		
		NOTHING SELECTED
		smil   -> print first             [1]
		bbcode -> print first, then other [2]

		SELECTED TEXT
		smil   -> replace text with first [1]
		bbcode -> wrap both around text   [3]
	*/
	
	if (!end.length) { // Get rid of smiley-style inserts immediately
		// Inserts text which can't wrap other selected text, so the selected thing gets replaced.
		txt.replaceAtCaret(start);
	} else if (txt.selectionStart !== txt.selectionEnd) {
		// Wrap around text.
		txt.wrapAtCaret(start, end);
	} else {
		// Reverse button held status and determine the correct text to insert based on this
		stat[id][i] = 1 - stat[id][i];
		var val = (end.length && stat[id][i] == 0) ? end : start;
		txt.replaceAtCaret(val);
	}
	txt.focus();
}

function toolbarHtml(id) {
	var out = "";
	stat[id] = [];
	for (var i = 0; i < buttons.length; i++) {
		stat[id][i] = 0;
		if (buttons[i].title !== null)
			out += "<td class='toolbar-button font' onclick=\"actionCaller('buttons',"+id+","+i+",event)\" title='"+buttons[i].title+"'><img src='"+buttons[i].img+"' alt='"+"[]"+"'></td>"; // buttons[i].title
		else
			out += "<td class='toolbar-sep'></td>"
	}
	return "<table class='toolbar'><tr>" + out + "</td></table>";
}

function createMenu(id, i, menuName, offset, menuWidth, e) {
	var out = "";
	// Disallow double menus
	if (popup !== null) {
		destroyMenu(true);
		return;
	}
	
	// Read off the array of buttons to display in the selected menu
	var btn = window[menuName];
	// Print them out
	for (var i = 0; i < btn.length; i++) {
		if (stat[id][i + offset] === undefined) stat[id][i + offset] = 0; // When the menu first loads, initialize the held  button status
		if (i && i % menuWidth == 0) out += '</tr><tr>';
		out += ""
		+ "<td class='font' onMouseOver='mouseOverMenu()' onclick=\"actionCaller('"+menuName+"',"+id+","+i+", event, "+offset+")\" title='"+btn[i].title+"'>"
		+ "<img src='"+btn[i].img+"' alt='"+"-"+"'>" // btn[i].title
		+ "</td>";
	}
	
	// Create the "window" to display these buttons
	hooks[id][0].insertAdjacentHTML("afterbegin", "<table class='toolbar-popup' id='toolbarpopup' onMouseOver='mouseOverMenu()' onMouseOut='destroyMenu(false)'><tr>"+ out +"</tr></table>");
	
	popup = document.getElementById("toolbarpopup");
	
	// The window should be placed depending on the cursor position
	if (e.pageX || e.pageY) {
		popup.style.left = e.pageX;
		popup.style.top  = e.pageY;
	} else if (e.clientX || e.clientY) {
		popup.style.left = e.clientX + document.body.scrollLeft;
		popup.style.top  = e.clientY + document.body.scrollTop;
	}
	
	// Register events to destroy the window
	popup.addEventListener("mouseenter", mouseOverMenu );
	popup.addEventListener("mouseout", mouseOutMenu );
}

// stupid create/destroy menu, doesn't support nesting
function destroyMenu(confirm = false) {
	if (!confirm) { // Don't go away immediately
		setTimeout("destroyMenu(true)", 400);
	} else if (!overMenu && popup !== null) {
		// Unregister events
		popup.removeEventListener("mouseenter", mouseOverMenu );
		popup.removeEventListener("mouseout", mouseOutMenu );
		// Delete menu
		popup.parentNode.removeChild(popup);
		popup = null;
	}
	
}
function mouseOverMenu() { overMenu = true; }
function mouseOutMenu()  { overMenu = false;}

// ------------------
// https://stackoverflow.com/questions/11076975/insert-text-into-textarea-at-cursor-position-javascript
HTMLTextAreaElement.prototype.replaceAtCaret = function (text = '') {
	if (document.selection) {
	// IE
		this.focus();
		var sel = document.selection.createRange();
		sel.text = text;
	} else if (this.selectionStart || this.selectionStart === 0) {
		// Others
		var startPos = this.selectionStart;
		var endPos = this.selectionEnd;
		this.value = 
			this.value.substring(0, startPos) +
			text +
			this.value.substring(endPos, this.value.length);
		this.selectionStart = startPos + text.length;
		this.selectionEnd = startPos + text.length;
	} else {
		this.value += text;
	}
};

HTMLTextAreaElement.prototype.wrapAtCaret = function (text1 = '', text2 = '') {
	if (document.selection) {
	// IE
		this.focus();
		var sel = document.selection.createRange();
		sel.text = text1 + sel.text + text2;
	} else if (this.selectionStart || this.selectionStart === 0) {
		// Others
		var startPos	= this.selectionStart;
		var endPos		= this.selectionEnd;
		
		this.value = 
			this.value.substring(0, startPos) +
			text1 +
			this.value.substring(startPos, endPos) +
			text2 +
			this.value.substring(endPos, this.value.length);
		
		this.selectionStart = startPos + text1.length;
		this.selectionEnd = endPos + text1.length;
	} else {
		this.value += text1 + text2;
	}
};
