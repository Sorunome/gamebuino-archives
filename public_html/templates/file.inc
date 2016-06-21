{:global $isAdmin,$userid,$isLoggedIn,$user}
{#if !{{exists}}}
	<b>Error: file not found</b>
	{:return}
{#endif}
{:set $versions array(
	'',
	'<img src="/wiki/gamelist/alpha.png" alt="alpha"> Alpha',
	'<img src="/wiki/gamelist/beta.png" alt="beta"> Beta',
	'<img src="/wiki/gamelist/release.png" alt="release"> Finished'
)}
{:set $complexities array(
	'',
	'<img src="/wiki/gamelist/basic.png" alt="basic"> Basic code complexity',
	'<img src="/wiki/gamelist/intermediate.png" alt="intermediate"> Intermediate code complexity',
	'<img src="/wiki/gamelist/advanced.png" alt="advanced"> Advanced code complexity'
)}

{#if $userid == $this->authorId || $isAdmin}
	<a href="?edit={id}" id="editfile">Edit</a>
{#endif}

<table id="fileDescription" cellspacing="0" cellpadding="0">
	<tr><th colspan="2"><?=$this->name?> ( <a href="?dl={id}" download>Download</a> )</th></tr>
	<tr><td>Author</td><td><a href="?author={authorId}">{author}</a></td></tr>
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
		<tr><td>Version</td><td>{!$versions[{{version}}]}</td></tr>
	{#endif}
	{#if {{complexity}}}
		<tr><td>Complexity</td><td>{!$complexities[{{complexity}}]}</td></tr>
	{#endif}
	{#if {{forum_url}}}
		<tr><td>Forum-Topic</td><td><a href="{forum_url}" target="_blank">{forum_url}</a></td></tr>
	{#endif}
	{#if {{repo_url}}}
		<tr><td>Code-Repository</td><td><a href="{repo_url}" target="_blank">{repo_url}</a></td></tr>
	{#endif}
	<tr><td>Tags</td><td>
	<?php $cats = getCategoryList(); ?>
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
