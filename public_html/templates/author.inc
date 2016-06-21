{#if !{{exists}}}
	<b>Error: author not found!</b>
	{:return}
{#endif}

<table id="authorDescription" cellspacing="0" cellpadding="0">
	<tr><th colspan="2">{name}</th></tr>
	<tr><td>Forum&nbsp;Profile</td><td><a href="/forum/memberlist.php?mode=viewprofile&amp;u={id}">{name}</a></td></tr>
	<tr><td>Number&nbsp;of&nbsp;files</td><td>{numFiles}</td></tr>
</table><br>
