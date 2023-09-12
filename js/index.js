/*
	Refresh the index every 30 seconds.
	If the tab/window isn't focused when the event triggers, the refresh is delayed	to when the tab regains focus again.
*/
var content;
var focused = true;
var pendingLoad = false;
var TIMEOUT_DELAY = 30 * 1000;
window.addEventListener("DOMContentLoaded", (e) => {
	content = document.getElementById("page-content");
	timeout = setTimeout(index_timeoutTrigger, TIMEOUT_DELAY);	
});
window.addEventListener('blur', (e) => {
	focused = false;
});
window.addEventListener('focus', (e) => {
	focused = true;
	if (pendingLoad) {
		pendingLoad = false;
		index_refresh();
	}
});
function index_timeoutTrigger() {
	if (focused) {
		index_refresh();
	} else {
		pendingLoad = true;
	}
}
function index_refresh() {
	var x = new XMLHttpRequest();
	x.open("GET", "index.php", true);
	x.setRequestHeader("X-Requested-With", "XMLHttpRequest");
	x.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			content.innerHTML = x.responseText;
			setTimeout(index_timeoutTrigger, TIMEOUT_DELAY);
		}
	};
	x.send();
}