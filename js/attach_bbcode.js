function copy_acp_link(key) {
	var copyText = document.getElementById('acp-input-'+key);
	copyText.select();
	document.execCommand('copy');
}
window.addEventListener("DOMContentLoaded", (e) => {
	var links = document.getElementsByClassName("acp-js-link");
	for (var i = 0; i < links.length; i++) {
		links[i].addEventListener("click", (e) => { e.preventDefault(); copy_acp_link(e.target.dataset['key']) } );
	}	
});