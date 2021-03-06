{:global $isLoggedIn,$user}
{#if !{{exists}} || (!{{can_view}})}
	<b>Error: file not found</b>
	{:return}
{#endif}


{#if !{{public}}}
	<strong>This file isn't public!</strong>
{#endif}
<table id="fileDescription" class="descriptionTable" cellspacing="0" cellpadding="0">
	<tr><th colspan="2">{name} (&nbsp;<a href="download.php?id={id}" download>Download</a>&nbsp;)
	{#if {{can_edit}}}
		<a href="edit.php?id={id}" id="editfile">Edit</a>
	{#endif}</th></tr>
	<tr><td>Author{#if sizeof({{extra_authors}})>0}s{#endif}</td><td><a href="?author={authorId}">{author}</a>
		{#foreach {{extra_authors}} as $id => $name}
			<a href="?author={$id}">{$name}</a>
		{#endforeach}
	</td></tr>
	<tr><td>Hits</td><td>{hits}</td></tr>
	<tr><td>Downloads</td><td>{downloads}</td></tr>
	<tr><td>Rating</td><td>+<span id="upvotes">{upvotes}</span>/-<span id="downvotes">{downvotes}</span>&nbsp;&nbsp;&nbsp;
	{#if $isLoggedIn}
		<a id="upvote" href="?rate={id}&amp;dir=1">+</a> <a id="downvote" href="?rate={id}&amp;dir=-1">-</a>
		<script type="text/javascript">
			$(function(){
				var vote = function(dir){
					$.getJSON('?rate={id}&dir='+dir+'&json').done(function(data){
						$('#upvotes').text(data.upvotes);
						$('#downvotes').text(data.downvotes);
					});
				};
				$("#upvote").click(function(e){
					e.preventDefault();
					vote(1);
				});
				$("#downvote").click(function(e){
					e.preventDefault();
					vote(-1);
				});
			});
		</script>
	{#else}
		<a href="/forum/ucp.php?mode=login">Login</a> to rate!
	{#endif}
	</td></tr>
	<tr><td>Added</td><td>{date($user->data['user_dateformat'],{{ts_added}})}</td></tr>
	<tr><td>Last&nbsp;Updated</td><td>{date($user->data['user_dateformat'],{{ts_updated}})}</td></tr>
	<tr><td>Description</td><td>{!str_replace("\n",'<br>',htmlspecialchars({{description}}))}</td></tr>
	{#if {{version}}}
		<tr><td>Version</td><td>{version}</td></tr>
	{#endif}
	{#if {{forum_url}}}
		<tr><td>Forum-Topic</td><td><a href="{forum_url}" target="_blank">{forum_replies} Replies</a></td></tr>
	{#endif}
	{#if {{repo_url}}}
		<tr><td>Code-Repository</td><td><a href="{repo_url}" target="_blank">{repo_url}</a></td></tr>
	{#endif}
	<tr><td>Tags</td><td>
	{:set $cats getCategoryList()}
	{#foreach {{categories}} as $c}
		<a href="?tags=[{$c}]">{!$cats[$c]}</a>
	{#endforeach}
	</td></tr>
</table>
<br>

<h2>SCREENSHOTS</h2>
{#foreach {{images}} as $i}
	{#if $i}
		<img src="uploads/screenshots/{$i}" alt="{name}" class="fileDescImage">
	{#endif}
{#endforeach}
{#if {{zip}}}
	<div id="zipcontentswrap"><div id="zipcontents">
		<div id="zipcontentsheader">Archive contents ( <a href="./download.php?id={id}&amp;all" download>Download</a> )</div>
		{#foreach {{zip}} as $z}
			<div class="zipcontentsitem">{$z}</div>
		{#endforeach}
	</div></div>
{#endif}
