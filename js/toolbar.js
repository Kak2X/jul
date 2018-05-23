/*
	Reply toolbar replacement
*/

var hooked_once = false;
var hooks = [];
var smilies = JSON.parse(document.getElementById('js_smilies').value); // Receives JSON of smilies from PHP
var overMenu = false;
var popup = null; // Currently opened menu
var stat = []; // Held button status


/* Menu / button definitions go here */

// Text color submenu
var textMenu = [
	{title: "Red", 		img: "fred.gif", 		action: 'insertText', arguments: ['[red]', '[/color]']},
	{title: "Yellow", 	img: "fyellow.gif", 	action: 'insertText', arguments: ['[yellow]', '[/color]']},
	{title: "Orange", 	img: "forange.gif", 	action: 'insertText', arguments: ['[orange]', '[/color]']},
	{title: "Green", 	img: "fgreen.gif", 		action: 'insertText', arguments: ['[green]', '[/color]']},
	{title: "Blue", 	img: "fblue.gif", 		action: 'insertText', arguments: ['[blue]', '[/color]']},
	{title: "Pink", 	img: "fpink.gif", 		action: 'insertText', arguments: ['[pink]', '[/color]']},
	{title: "Black", 	img: "fblack.gif", 		action: 'insertText', arguments: ['[black]', '[/color]']},
	{title: "White", 	img: "bgblack.gif", 	action: 'insertText', arguments: ['[white]', '[/color]']},
];

// Smilies submenu
var smilMenu = [];
for (var i = 0; i < smilies.length; i++) 
	if (smilies[i]) // the ../../ breaks smilies using external URLs
		smilMenu.push({title: smilies[i][0], img: '../../'+smilies[i][1], action: 'insertText', arguments: [smilies[i][0]]});

// Main menu
var buttons = [
	{title: "Text Color", 		img: "fcolor.gif", 		action: 'createMenu', arguments: ['textMenu', 200, 4]},
	{title: null},
	{title: "Bold", 			img: "bold.gif", 		action: 'insertText', arguments: ['[b]', '[/b]']},
	{title: "Italic", 			img: "italic.gif", 		action: 'insertText', arguments: ['[i]', '[/i]']},
	{title: "Underline", 		img: "underline.gif", 	action: 'insertText', arguments: ['[u]', '[/u]']},
	{title: "Strikethrough",	img: "strike.gif", 		action: 'insertText', arguments: ['[s]', '[/s]']},
	{title: null},
	{title: "Link",				img: "link.gif",		action: 'insertText', arguments: ['[url]', '[/url]']},
	{title: "Image", 			img: "image.gif", 		action: 'insertText', arguments: ['[image]', '[/image]']},
	{title: "Smilies", 			img: "smiley.gif", 		action: 'createMenu', arguments: ['smilMenu', 100, 7]},
];
/* --- */

// Toolbar loader
function toolbarHook(elem) {
	var td			= document.getElementById(elem + 'td'); // Insert element
	var textarea	= document.getElementById(elem + 'txt'); // Hooks
	hooks.push([td, textarea]);
	
	td.insertAdjacentHTML("afterbegin", toolbarHtml(hooks.length - 1));
	
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
	stat[id][i] = 1 - stat[id][i]; // Reverse status
	var val = (end.length && stat[id][i] == 0) ? end : start;
	hooks[id][1].insertAtCaret(val);
	hooks[id][1].focus();
}

function toolbarHtml(id) {
	var out = "";
	stat[id] = [];
	for (var i = 0; i < buttons.length; i++) {
		stat[id][i] = 0;
		if (buttons[i].title !== null)
			out += "<td class='toolbar-button font' onclick=\"actionCaller('buttons',"+id+","+i+",event)\" title='"+buttons[i].title+"'><img src='images/toolbar/"+buttons[i].img+"' alt='"+buttons[i].title+"'></td>";
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
		+ "<img src='images/toolbar/"+btn[i].img+"' alt='"+btn[i].title+"'>"
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
HTMLTextAreaElement.prototype.insertAtCaret = function (text) {
  text = text || '';
  if (document.selection) {
    // IE
    this.focus();
    var sel = document.selection.createRange();
    sel.text = text;
  } else if (this.selectionStart || this.selectionStart === 0) {
    // Others
    var startPos = this.selectionStart;
    var endPos = this.selectionEnd;
    this.value = this.value.substring(0, startPos) +
      text +
      this.value.substring(endPos, this.value.length);
    this.selectionStart = startPos + text.length;
    this.selectionEnd = startPos + text.length;
  } else {
    this.value += text;
  }
};
