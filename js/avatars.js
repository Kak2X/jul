// Launch immediately without waiting
var mood = document.getElementById('moodid');
var prev = document.getElementById('prev');
mood.addEventListener('change', refreshMoodPrev);
refreshMoodPrev();

function refreshMoodPrev() {
	var opt = mood.options[mood.selectedIndex];
	if (opt.dataset.act == "clear")
		prev.src = "images/_.gif";
	else if (opt.dataset.f) // file url
		prev.src = opt.dataset.f;
	else if (!opt.dataset.noas) // no avatar storage flag
		prev.src = "userpic/"+mood.dataset.u + "_" + mood.value;
	else
		prev.src = "images/_.gif";
}

