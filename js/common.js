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