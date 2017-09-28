function avatarpreview(uid,pic,nopic=0) {
	if (!nopic) {
		document.getElementById('prev').src="userpic/"+uid+"/"+pic;
	} else {
		document.getElementById('prev').src="images/_.gif";
	}
}