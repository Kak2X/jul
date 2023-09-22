// Must be the first registered event
window.addEventListener("DOMContentLoaded", (e) => {
	var toHide = document.getElementsByClassName("nojs-jshide");
	for (var i = 0; i < toHide.length; i++) {
		toHide[i].style.display = "unset"; // display:none until moved off-screen
		toHide[i].style.position = "fixed";
		toHide[i].style.top = "-10000px";
	}
	
	document.getElementById("jshidecss").remove();
});
// Twitter embed jsonp helper
function twembed(data) { 
	var parts = data.url.split("/");
	var id = parts[parts.length-1];
	var elems = document.getElementsByClassName("twembed-"+id);
	for (var i = 0; i < 1; i++)
		elems[i].outerHTML = data.html;
}