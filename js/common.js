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
// JS upload loader
function addJsUploadBtn(key, maxSize, allowed, mode, callback) {
	var fileInput = document.getElementById(key+"js");
	document.getElementById(key+"jsbtn").addEventListener('click', function () {
		fileInput.click();
	});
	fileInput.addEventListener('input', function(e) {
		var errors = "";
		for (var file of e.target.files) {
			var rdr = new FileReader();
			rdr.onload = callback;
			if (allowed && allowed.indexOf(file.type) === -1) {
				errors += "The file \""+file.name+"\" is not of a valid type.\r\n";
			} else if (file.size > maxSize) {
				errors += "The file \""+file.name+"\" is over the size limit! ("+sizeunits(file.size)+"/"+sizeunits(maxSize)+")\r\n";
			} else switch (mode) {
				case 'text':
					rdr.readAsText(file);
					break;
				case 'base64':
					rdr.readAsDataURL(file);
					break;
				default:
					rdr.readAsArrayBuffer(file);
					break;
			}
		}
		if (errors)
			alert(errors);
	});
}

function sizeunits(bytes) {
	var sizes = ['B', 'KB', 'MB', 'GB'];
	var i = 0, sbar = 1;
	for (; bytes > sbar * 1024 && i < sizes.length - 1; i++, sbar *= 1024);
	return (bytes / sbar).toFixed(2).replace('.00', '')+ ' ' + sizes[i];
}

function getCookie(name, defVar = "") {
    var ret = document.cookie.split("; ").find(row => row.startsWith(name));
    return ret ? ret.split('=')[1] : defVar;
}

function setCookie(name, val) {
    document.cookie = name + '=' + val + ';';
}

function remCookie(name) {
	document.cookie = name + '=; Max-Age=-99999999;';
}