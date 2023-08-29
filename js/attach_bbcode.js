function copy_acp_link(key) {
	var copyText = document.getElementById('acp-input-'+key);
	copyText.select();
	document.execCommand('copy');
}
window.addEventListener("load", (e) => {
	var toHide = document.getElementsByClassName("acp-js-hide");
	for (var i = 0; i < toHide.length; i++) {
		toHide[i].style.position = "fixed";
		toHide[i].style.top = "-10000px";
	}	
	
	var links = document.getElementsByClassName("acp-js-link");
	for (var i = 0; i < links.length; i++)
		links[i].addEventListener("click", (e) => { e.preventDefault(); copy_acp_link(e.target.dataset['key']) } );
});