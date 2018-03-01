function newavatarpreview(uid,pic,nopic=false) {
	if (!nopic) {
		document.getElementById('prev').src="userpic/"+uid+"/"+pic;
	} else {
		document.getElementById('prev').src="images/_.gif";
	}
}

var moodav = "";
function avatarpreview(uid,pic) {
	if (pic > 0) {
		document.getElementById('prev').src=moodav.replace("$", pic);
	} else {
		document.getElementById('prev').src="images/_.gif";
	}
}
function setmoodav(path) {
	moodav = path;
}