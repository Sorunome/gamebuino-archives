{:set $versionsDropdown array(
	'--none--',
	'Alpha',
	'Beta',
	'Finished'
)}
{:set $complexitiesDropdown array(
	'--none--',
	'Basic',
	'Intermediate',
	'Advanced'
)}
<div class="hidden" id="edit_file_settings">
	<h2>File Settings</h2>
	<label>Forum-Topic (optional):<input type="url" name="forum_url" value="{forum_url}"></label>
			{!getHelpHTML('A link to the forum topic where discussion about this program/game occured')}<br>
	<label>Version:<select name="version" size="1">
		{#foreach $versionsDropdown as $i => $v}
				<option value="{$i}" {#if {{version}} == $i}selected{#endif}>{$v}</option>
		{#endforeach}
	</select></label>
			{!getHelpHTML('The version of the game, defined as the following:<br><table>
					<tr>
						<td>alpha</td><td>game under development with the basic gameplay implemented</td>
					</tr>
					<tr>
						<td>beta</td><td>a real game you can enjoy, but it still lacks a few features and a final polish</td>
					</tr>
					<tr>
						<td>release</td><td>the game is finished, working and will no longer evolve</td>
					</tr>
				</table>')}<br>
	<label>Complexity:<select name="complexity" size="1">
		{#foreach $complexitiesDropdown as $i => $c}
			<option value="{$i}" {#if {{complexity}} == $i}selected{#endif}>{$c}</option>
		{#endforeach}
	</select></label>
			{!getHelpHTML('How complex the code is, defined as the following:<br><table>
				<tr>
					<td>basic</td><td>the code fits in one file <&nbsp;1500 lines and is easy to understand for a beginner</td>
				</tr>
				<tr>
					<td>intermediate</td><td>program across several files, object oriented, PROGMEM, tile maps, etc.</td>
				</tr>
				<tr>
					<td>advanced</td><td>involves assembly, pointers, 3D, streaming from the SD card, multi-player, etc.</td>
				</tr>
			</table>')}<br>
	<input type="hidden" name="category" value="{'['.implode('][',{{categories}}).']'}">
	Tags:{!getHelpHTML('The tags your game should have, you can add multiple')}
	<span id="categoriesContent">Please enable Javascript!</span><br>
	Description:{!getHelpHTML('A long description of your game')}<br>
	<textarea name="description">{description}</textarea>
	<br><br>
	Screenshots (all optional{#if {{exists}}}, only saved if changed{#endif}):
			{!getHelpHTML('Nothing describes a game better than a screenshot! You can upload up to four, the first one will be the main screenshot.
						Animated GIFs are allowed.')}<br>
	{#for $i = 0;$i < 4;$i++}
		<label>Image {$i+1}{#if $i==0} (main image){#endif}: <input type="file" name="image{$i}"></label>
		{#if {{exists}}}
			| <label>Delete old: <input type="checkbox" name="delimage{$i}" value="true"></label>
		{#endif}
		<br>
	{#endfor}
</div>
<script type="text/javascript">
	// category picker
	$(function(){
		var catlist = {!json_encode(getCategoryListDropdown())},
			cats = {!json_encode({{categories}})},
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
