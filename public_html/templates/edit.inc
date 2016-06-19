<?php
if(!($userid == $this->authorId || $isAdmin || !$this->exists)){
	echo 'Access Denied';
	return;
}
function getHelpHTML($s){
	return '<div class="help"><img src="help_icon.png"><div class="text">'.$s.'</div></div>';
}
$versionsDropdown = array(
	'--none--',
	'Alpha',
	'Beta',
	'Finished'
);
$complexitiesDropdown = array(
	'--none--',
	'Basic',
	'Intermediate',
	'Advanced'
);
?>
<?php if($this->exists): ?>
	<h1>Editing file <i><?=$this->name?></i></h1><a href="?file=<?=$this->id?>">Back</a><br><br>
<?php else: ?>
	<?php if($isLoggedIn): ?>
		<h1>Upload new file</h1>
	<?php else: ?>
		You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!
		<?php return ?>
	<?php endif ?>
<?php endif ?>
<form id="fileeditform" action="?save=<?=$this->id?>" method="post" enctype="multipart/form-data">
	Name:<input type="text" name="name" value="<?=$this->name?>">
			<?=getHelpHTML('The name of the file as it will be displayed')?><br>
	<?php if($this->exists): ?>
		New zip-file (leave blank if it didn't change):<input type="file" name="zip">
	<?php else: ?>
		Zip-file:<input type="file" name="zip">
	<?php endif ?>
			<?=getHelpHTML('The Zip-file containing the actual game, HEX (and INF) files')?><br>
	Forum-Topic (optional):<input type="url" name="forum_url" value="<?=$this->forum_url?>">
			<?=getHelpHTML('A link to the forum topic where discussion about this program/game occured')?><br>
	Code-Repository (optional):<input type="url" name="repo_url" value="<?=$gamefile['repo_url']?>">
			<?=getHelpHTML('A link to a repository (for example <a href="https://github.com" target="_blank">Github</a>) where the code is hosted on')?><br>
	Version:<select name="version" size="1">
		<?php foreach($versionsDropdown as $i => $v): ?>
				<option value="<?=$i?>" <?=($gamefile['version']==$i?'selected':'')?>><?=$v?></option>
		<?php endforeach ?>
	</select>
			<?=getHelpHTML('The version of the game, defined as the following:<br><table>
					<tr>
						<td>alpha</td><td>game under development with the basic gameplay implemented</td>
					</tr>
					<tr>
						<td>beta</td><td>a real game you can enjoy, but it still lacks a few features and a final polish</td>
					</tr>
					<tr>
						<td>release</td><td>the game is finished, working and will no longer evolve</td>
					</tr>
				</table>')?><br>
	Complexity:<select name="complexity" size="1">
		<?php foreach($complexitiesDropdown as $i => $c): ?>
			<option value="<?=$i?>" <?=($gamefile['complexity']==$i?'selected':'')?>><?=$c?></option>
		<?php endforeach ?>
	</select>
			<?=getHelpHTML('How complex the code is, defined as the following:<br><table>
				<tr>
					<td>basic</td><td>the code fits in one file <&nbsp;1500 lines and is easy to understand for a beginner</td>
				</tr>
				<tr>
					<td>intermediate</td><td>program across several files, object oriented, PROGMEM, tile maps, etc.</td>
				</tr>
				<tr>
					<td>advanced</td><td>involves assembly, pointers, 3D, streaming from the SD card, multi-player, etc.</td>
				</tr>
			</table>')?><br>
	<input type="hidden" name="category" value="<?='['.implode('][',$this->categories).']'?>">
	Tags:<?=getHelpHTML('The tags your game should have, you can add multiple')?>
	<span id="categoriesContent">Please enable Javascript!</span><br>
	Description:<?=getHelpHTML('A long description of your game')?><br>
	<textarea name="description"><?=$this->description?></textarea>
	<br><br>
	Screenshots (all optional<?=($this->exists?', only saved if changed':'')?>):
			<?=getHelpHTML('Nothing describes a game better than a screenshot! You can upload up to four, the first one will be the main screenshot.
						Animated GIFs are allowed.')?><br>
	<?php for($i = 0;$i < 4;$i++): ?>
		Image <?=($i+1).($i == 0?' (main image)':'')?>:<input type="file" name="image<?=$i?>">
		<?php if($this->exists): ?>
			| Delete old: <input type="checkbox" name="delimage<?=$i?>" value="true">
		<?php endif ?>
		<br>
	<?php endfor ?>
	<br>
	<input type="submit" value="<?=$this->exists?'Save Edit':'Upload File'?>">
</form>
<script type="text/javascript">
	$(function(){
		var catlist = <?=json_encode(getCategoryListDropdown())?>,
			cats = <?=json_encode($this->categories)?>,
			makeCatList = function(v){
				return $("<div>").addClass("categoryDropdown").append(
					$("<select>").attr("size","1").append(
						$.map(catlist,function(c,i){
							i = i.substr(1);
							return $("<option>").text(c).attr((i==v?"selected":"false"),"selected").val(i);
						})
					),"&nbsp;",
					$("<a>").text("x").attr("href","http://remove").click(function(e){
						e.preventDefault();
						$(this).parent().remove();
					})
				);
			};
		$("#categoriesContent").empty().append(
			$.map(cats,function(v){
				return makeCatList(v);
			})
		).after($("<a>").text("+ add Category").attr("href","http://add").click(function(e){
			e.preventDefault();
			$("#categoriesContent").append(makeCatList());
		}));
		$("#fileeditform").submit(function(e){
			var catIdsMix = $(".categoryDropdown select").map(function(){return this.value;}),
				catIds = [];
			$.each(catIdsMix,function(i,el){
				if($.inArray("["+el+"]",catIds) === -1){
					catIds.push("["+el+"]");
				}
			});
			this.category.value = catIds.join("");
			
			// no e.preventDefault() as we still want to send it
		});
	});
</script>
