/*\
title: $:/plugins/sendwheel/tw-receiver/tw-receiver-handler.js
type: application/javascript
module-type: saver

Handles saving wiki via POST to server storage

\*/

(function(){

/*jslint node: true, browser: true */
/*global $tw: false */
"use strict";

// helper function returns a sha256 hex digest using the sjcl lib
var getSHA256 = function(data) {
	var sjcl = $tw.node ? (global.sjcl || require("./sjcl.js")) : window.sjcl;
	return sjcl.codec.hex.fromBits(sjcl.hash.sha256.hash(data))
}

/*
Create a saver module
*/
var ReceiverSaver = function(wiki) {
};

ReceiverSaver.prototype.save = function(text,method,callback) {
	
	// if this module is not enabled by the user return false so that saver-handler can move on to next option
	// we do this here, instead of in canSave(), so that the saver can be enabled/disabled without a page refresh
	if(($tw.wiki.getTextReference("$:/tw-receiver-enabled") || "").toLowerCase() !== "yes") {
		return false;
	}
	
	// retrieve parameters from the ui form
	//var isEnabled = $tw.wiki.getTextReference("$:/tw-receiver-enabled");
	var seckey = $tw.utils.getPassword("tw-receiver-seckey"); //reminder: this util uses html5.localStorage (secvio)
	var wikiname = $tw.wiki.getTextReference("$:/tw-receiver-wikiname");
	var serverurl = $tw.wiki.getTextReference("$:/tw-receiver-serverurl");
	var cdauthentication = true; //challenge digest auth
	var signdata = true; //integrity check
	var stalecheck = true; //overwrite stale instance check 
	
	// enable/disable based on ui setting
	if($tw.wiki.getTextReference("$:/tw-receiver-stalecheck") == "no") {
		stalecheck = false;
	}
	
	if($tw.wiki.getTextReference("$:/tw-receiver-signdata") == "no") {
		signdata = false;
	}
	
	// if we're not provided a filename, try to get the name of the wiki from the URL
	if(!wikiname) {
		var p = document.location.pathname.lastIndexOf("/");
		if(p !== -1) {
			// We decode the pathname because document.location is URL encoded by the browser
			wikiname = decodeURIComponent(document.location.pathname.substr(p+1));
		}
	}
	
	if(!wikiname) {
		wikiname = "tiddlywiki.html";
	}
	
	// construct the server url if not provided
	if(!serverurl) {
		serverurl = "tw-receiver-server.php";
	}
	
	// fail to save if we're missing any critical parameters
	if(
		!seckey || seckey.toString().trim() === "" ||
		!wikiname || wikiname.toString().trim() === "" ||
		!serverurl || serverurl.toString().trim() === "" 
	){
		return callback("TW Receiver: \n Missing some user input parameters. Save Failed");
		// we don't return false because we want the user to fix the problem or disable this module
		//return false;
	}
	
	// everything seems in order, lets attempt the save
	// display the save starting notification
	$tw.notifier.display("$:/language/Notifications/Save/Starting");
	
	// helper function returns a sha256 hex digest using the sjcl lib
	var getSHA256 = function(data) {
		var sjcl = $tw.node ? (global.sjcl || require("./sjcl.js")) : window.sjcl;
		return sjcl.codec.hex.fromBits(sjcl.hash.sha256.hash(data))
	}
	
	// helper function encapsulates the actual data post
	// modified version of the upload.js saver from TiddlyWiki core
	var postToServer = function(){
		// text,seckey,filename,serverurl
		var retResp = false;
		// assemble the header
		var boundary = "-------" + "81fd830c85363675edb98d2879916d8c";	
		var header = [];
		header.push("--" + boundary + "\r\nContent-disposition: form-data; name=\"twreceiverparams\"\r\n");
		header.push("seckey=" + seckey + "&wikiname=" + wikiname + "&datasig=" + datasig + "&stalehash=" + stalehash); 
		header.push("\r\n" + "--" + boundary);
		header.push("Content-disposition: form-data; name=\"userfile\"; filename=\"" + wikiname + "\"");
		header.push("Content-Type: text/html;charset=UTF-8");
		header.push("Content-Length: " + text.length + "\r\n");
		header.push("");
		// assemble the tail and the data itself
		var tail = "\r\n--" + boundary + "--\r\n";
		var	data = header.join("\r\n") + text + tail;
		// do the HTTP post
		var http = new XMLHttpRequest();
		http.open("POST",serverurl,true);
		http.setRequestHeader("Content-Type","multipart/form-data; charset=UTF-8; boundary=" + boundary);
		http.onreadystatechange = function() {
			if(http.readyState == 4 && http.status == 200) {
				if(http.responseText.substr(0,8) === "000 - ok") {
					callback(null);
					if(stalecheck) {
						// update stale hash to current
						$tw.wiki.setTextReference('$:/temp/tw-receiver-stalehash',getSHA256(text));
					}
				} else {
					callback("Error:\n" + http.responseText);
				}
			}
		};
		try {
			http.send(data);
		} catch(ex) {
			return callback($tw.language.getString("Error/Caption") + ":" + ex);
		}
	};
	
	// if signdata is enabled, create a data integrity signature
	var datasig = "";
	if(signdata){
		datasig = getSHA256(text+seckey);
	}
	
	// if stalecheck is enabled, grab the stale hash
	// send this to the server for comparison
	var stalehash = "";
	if(stalecheck){
		stalehash = $tw.wiki.getTextReference("$:/temp/tw-receiver-stalehash");
	}
	
	// cdauthentication mode check
	if(cdauthentication) {
		var xhrequest = new XMLHttpRequest();
		xhrequest.onreadystatechange = function() {
			if(xhrequest.readyState == 4 && xhrequest.status == 200) {
				var challengetoken = xhrequest.responseText;
				seckey = getSHA256(seckey + challengetoken);
				// post helper
				postToServer();
			}
		};
		xhrequest.open("GET", serverurl + "?md=gct", true);
		try {
			xhrequest.send(null);
		} catch(ex) {
			return callback($tw.language.getString("Error/Caption") + ":" + ex);
		}
	}
	else {
		// post helper
		postToServer();
	}
	
	// we return true because the attempt was completed
	// either saved success or error reported via callback()
	return true;
};

/*
Information about this saver
*/
ReceiverSaver.prototype.info = {
	name: "tw-receiver",
	priority: 3000, // priority: higher # is first
	capabilities: ["save", "autosave"]
};

/*
Static method that returns true if this saver is capable of working
Called onload (wiki start) to enable this saver, requires refresh to change
*/
exports.canSave = function(wiki) {
	// stale check calculation
	// we call this here because we want the value at startup
	if($tw.wiki.getTextReference("$:/tw-receiver-stalecheck") == "yes") {
		var data = wiki.wiki.renderTiddler("text/plain","$:/core/save/all"); 
		$tw.wiki.setTextReference('$:/temp/tw-receiver-stalehash',getSHA256(data));
	}
	
	// return true regardless
	return true;
};

/*
Create an instance of this saver
*/
exports.create = function(wiki) {
	return new ReceiverSaver(wiki);
};

})();
