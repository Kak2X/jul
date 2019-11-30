document.getElementById("main-form").addEventListener("submit", function(e) {
	var stepElem = document.activeElement;
	var oldContent = ["","",""];
	if (stepElem.getAttribute("name") == "step") {
		var step = stepElem.value;
		
		var req = new XMLHttpRequest();
		req.open("POST", "?ajax=1");
		req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
		req.timeout = 100000;
		req.onload = function() {
			var data = JSON.parse(req.response);
			
			// Page title
			document.getElementById("page-title").innerHTML = data.title;
			document.title = data.title + " -- Acmlmboard Installer";
			
			// Button status
			setButtonStatus(data.step, data.btn);
			document.getElementById("button-area").style.display = "block";
			
			// Page contents and saved variables
			document.getElementById("page-contents").innerHTML = saveVars(data.vars) + data.text;
		};
		req.onError = function() {
			restoreContent(oldContent, "An unknown error occurred. Try again.");
		}
		req.ontimeout = function (e) {		
			restoreContent(oldContent, "The browser timed out during the request.\nIf the timeout happened during the actual installation process, it may be possible that the board is fully installed.");
		};
		var params = getFormInputs(step);
		
		// Loader setup
		oldContent = [
			document.getElementById("page-contents").innerHTML,
			document.getElementById("button-area").innerHTML,
			document.getElementById("page-title").innerHTML,
		];
		document.getElementById("page-contents").innerHTML = "<div class='center color-rot'><img src='nowloading.png' style='height: 32px'><br>This process may take a while to finish.</div>";
		document.getElementById("button-area").style.display = "none";
		
		// We do this at the very last point
		// if something breaks, then the normal non-JS POST request will be used
		e.preventDefault();
		
		req.send(params);
	}
});

function setButtonStatus(step, btnMask) {
	var btnArea = ""
	+ (btnMask & 1
		? "<button type='submit' name='step' value='"+(step + 1)+"' style='left: 0px'>Next</button>"
		: "<button type='button' disabled style='left: 0px'>Next</button>")
	+ (btnMask & 2
		? "<button type='submit' name='step' value='"+(step - 1)+"' style='right: 0px'>Back</button>"
		: "<button type='button' disabled style='right: 0px'>Back</button>");	
	document.getElementById("button-area").innerHTML = btnArea;
}

function restoreContent(oldContent, message = "") {
	alert(message);
	document.getElementById("page-contents").innerHTML = oldContent[0];
	document.getElementById("button-area").innerHTML = oldContent[1];
	document.getElementById("page-title").innerHTML = oldContent[2];
	document.getElementById("button-area").style.display = "block";
}

function getFormInputs(step) {
	var out = "step=" + step + "";
	var list;
	
	// General input types
	list = document.querySelectorAll("#main-form input"); // :not([name='step']) // commented out since it's a submit type, not included in the switch
	for (var i = 0; i < list.length; i++) {
		var x = list[i];
		switch (x.type) {
			case 'hidden':
				out += "&"+x.name+"="+x.value;
				break;
			case 'text':
			case 'password':
				out += "&"+x.name+"="+amp(x.value);
				break;
			case 'checkbox':
			case 'radio':
				if (x.checked)
					out += "&"+x.name+"="+amp(x.value);
				break;
		}
	}
	
	// Textarea
	list = document.querySelectorAll("#main-form textarea");
	for (var i = 0; i < list.length; i++) {
		var x = list[i];
		out += "&"+x.name+"="+amp(x.value);
	}
	
	// Select
	list = document.querySelectorAll("#main-form select");
	for (var i = 0; i < list.length; i++) {
		var x = list[i];
		var sel = x.querySelectorAll(":checked");
		if (sel.length > 1) {
			for (var j = 0; j < sel.length; j++) {
				out += "&"+x.name+"[]="+amp(sel[j].value);
			}
		} else {
			out += "&"+x.name+"="+amp(sel[0].value);
		}
	}
	
	return out;
}

function saveVars(arr, nested = "") {
	var out = "";
	for (var key in arr) {
		var val = arr[key];
		// Generate the associative key if needed (nests to config[something][dfgdsg]
		var name = (nested) ? nested + "["+key+"]" : key + "";
		if (typeof val === 'object') {
			out += saveVars(val, name);
		} else {
			out += "<input type='hidden' name='"+name+"' value=\""+quote(val)+"\">";
		}
	}
	return out;
}

//--
//	Utilities
function amp(str) {
	return typeof str === "string" ? str.replace(/&/g, "&amp;") : str;
}
function quote(str) {
	return typeof str === "string" ? str.replace(/"/g, "&quot;") : str;
}