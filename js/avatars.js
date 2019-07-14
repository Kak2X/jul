function newavatarpreview(uid,pic,nostorage=false) {
	if (!nostorage) {
		document.getElementById('prev').src="userpic/"+uid+"_"+pic;
	} else if (nostorage !== true) {
		document.getElementById('prev').src=nostorage;
	} else { // magic value for blank userpic
		document.getElementById('prev').src="images/_.gif";
	}
}

var moodav = "";
function avatarpreview(uid,pic,dummy=0) {
	if (pic > 0) {
		document.getElementById('prev').src=moodav.replace("$", pic);
	} else {
		document.getElementById('prev').src="images/_.gif";
	}
}
function setmoodav(path) {
	moodav = path;
}