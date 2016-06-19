<?php
if(!$this->exists){
	echo '<b>Error: file not found</b>';
	return;
}
$versions = array(
	'',
	'<img src="/wiki/gamelist/alpha.png" alt="alpha"> Alpha',
	'<img src="/wiki/gamelist/beta.png" alt="beta"> Beta',
	'<img src="/wiki/gamelist/release.png" alt="release"> Finished'
);

$complexities = array(
	'',
	'<img src="/wiki/gamelist/basic.png" alt="basic"> Basic code complexity',
	'<img src="/wiki/gamelist/intermediate.png" alt="intermediate"> Intermediate code complexity',
	'<img src="/wiki/gamelist/advanced.png" alt="advanced"> Advanced code complexity'
);
?>
<?php if($userid == $this->authorId || $isAdmin): ?>
	<a href="?edit=<?=$this->id?>" id="editfile">Edit</a>
<?php endif ?>

<table id="fileDescription" cellspacing="0" cellpadding="0">
	<tr><th colspan="2"><?=$this->name?> ( <a href="?dl=<?=$this->id?>" download>Download</a> )</th></tr>
	<tr><td>Author</td><td><a href="?author=<?=$this->authorId?>"><?=$this->author?></a></td></tr>
	<tr><td>Hits</td><td><?=$this->hits?></td></tr>
	<tr><td>Downloads</td><td><?=$this->downloads?></td></tr>
	<tr><td>Rating</td><td>+<span id="upvotes"><?=$this->upvotes?></span>/-<span id="downvotes"><?=$this->downvotes?></span>&nbsp;&nbsp;&nbsp;
	<?php if($isLoggedIn): ?>
		<a id="upvote" href="?rate=<?=$this->id?>&amp;dir=1">+</a> <a id="downvote" href="?rate=<?=$this->id?>&amp;dir=-1">-</a>
		<script type="text/javascript">
			$(function(){
				var vote = function(dir){
					$.getJSON('?rate=<?=$this->id?>&dir='+dir+'&json').done(function(data){
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
	<?php else: ?>
		<a href="/forum/ucp.php?mode=login">Login</a> to rate!
	<?php endif ?>
	</td></tr>
	<tr><td>Added</td><td><?=date($user->data['user_dateformat'],$this->ts_added)?></td></tr>
	<tr><td>Last&nbsp;Updated</td><td><?=date($user->data['user_dateformat'],$this->ts_updated)?></td></tr>
	<tr><td>Description</td><td><?=str_replace("\n",'<br>',$this->description)?></td></tr>
	<?php if($this->version > 0): ?>
		<tr><td>Version</td><td><?=$versions[$this->version]?></td></tr>
	<?php endif ?>
	<?php if($this->complexity > 0): ?>
		<tr><td>Complexity</td><td><?=$complexities[$this->complexity]?></td></tr>
	<?php endif ?>
	<?php if($this->forum_url != ''): ?>
		<tr><td>Forum-Topic</td><td><a href="<?=$this->forum_url?>" target="_blank"><?=$this->forum_url?></a></td></tr>
	<?php endif ?>
	<?php if($this->repo_url != ''): ?>
		<tr><td>Code-Repository</td><td><a href="<?=$this->repo_url?>" target="_blank"><?=$this->repo_url?></a></td></tr>
	<?php endif ?>
	<tr><td>Tags</td><td>
	<?php
		$cats = getCategoryList();
		foreach($this->categories as $c){
			echo '<a href="?tags=['.$c.']">'.$cats[$c].'</a> ';
		}
	?>
	</td></tr>
</table>
<br>

<h2>SCREENSHOTS</h2>
<?php foreach($this->images as $i): ?>
	<?php if($i != ''): ?>
		<img src="uploads/screenshots/<?=$i?>" alt="<?=$this->name?>" class="fileDescImage">
	<?php endif ?>
<?php endforeach ?>
