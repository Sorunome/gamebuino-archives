<?php
if(!$this->exists){
	echo '<b>ERROR: author not found!</b>';
	return;
}
?>
<table id="authorDescription" cellspacing="0" cellpadding="0">
	<tr><th colspan="2"><?=$this->name?></th></tr>
	<tr><td>Forum&nbsp;Profile</td><td><a href="/forum/memberlist.php?mode=viewprofile&amp;u=<?=$this->id?>"><?=$this->name?></a></td></tr>
	<tr><td>Number&nbsp;of&nbsp;files</td><td><?=$this->numFiles?></td></tr>
</table><br>
