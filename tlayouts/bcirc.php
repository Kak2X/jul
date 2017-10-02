<?php

function userfields(){return 'u.posts,u.sex,u.`group`,u.displayname,u.main_subgroup,u.ban_expire,u.birthday,u.aka,u.namecolor';}

function postcode($post, $set, $controls){
	// special "on demand" tlayout - do not add to tlayout list
	return "<div class='font w'>&lt;{$set['userlink']}&gt; ".strip_tags($post['text'])."</div>\n";
}