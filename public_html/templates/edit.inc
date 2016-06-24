{:global $isLoggedIn,$userid,$isAdmin}
{#if !($userid == {{authorId}} || $isAdmin || !{{exists}})}
	Access Denied
	{:return}
{#endif}
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

{#if {{exists}}}
	<h1>Editing file <i>{name}</i></h1><a href="?file={id}">Back</a><br><br>
{#else}
	{#if $isLoggedIn}
		<h1>Upload new file</h1>
	{#else}
		You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!
		{:return}
	{#endif}
{#endif}
<form id="fileeditform" action="?save={id}" method="post" enctype="multipart/form-data">
	Name:<input type="text" name="name" value="{name}">
			{!getHelpHTML('The name of the file as it will be displayed')}<br>
	File type:
	{#foreach array('zip','Github') as $i => $v}
		<label><input type="radio" name="file_type" value="{$i}" {#if $i=={{file_type['type']}}}checked="checked"{#endif}> {$v}</label>&nbsp;&nbsp;
	{#endforeach}<br>
	<div class="file_type_content">
		Please enable JavaScript!
	</div>
	<div class="file_type_content hidden" id="file_type_content_0">
		<label>
		{#if {{exists}}}
			New zip-file (leave blank if it didn't change):
		{#else}
			Zip-file:
		{#endif}<input type="file" name="zip"></label>
				{!getHelpHTML('The Zip-file containing the actual game, HEX (and INF) files')}<br>
		<label>Code-Repository (optional):<input type="url" name="repo_url" value="{repo_url}"></label>
				{!getHelpHTML('A link to a repository (for example <a href="https://github.com" target="_blank">Github</a>) where the code is hosted on')}
	</div>
	<div class="file_type_content hidden" id="file_type_content_1" data-loaded="0">
		<div id="github_loading">Loading...</div>
		<div id="github_loginform" class="hidden">
			You haven't connected your account with github yet, to be able to use automatic re-builds you need to do this!<br>
			<button id="github_login">Login with github</button>
		</div>
		<div id="github_reposform" class="hidden">
			<div style="font-size:1.5em;font-weight:bold;"><img id="github_avatar" alt="github_avatar" style="max-width:5em;max-height:5em;">&nbsp;<span id="github_username" style="vertical-align:top;"></span></div>
			<div id="github_repos"></div>
		</div>
	</div>
	<div class="buttongroup">
		<div class="button" id="edit_file_settings_button">File Settings</div>
		<div class="button" id="edit_build_settings_button">Build Settings</div>
	</div>
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
	<div class="hidden" id="edit_build_settings">
		<h2>Build Settings</h2>
		<label>Automatic builds: <input type="checkbox" name="autobuild" value="1"{#if {{autobuild}}} checked="checked"{#endif}></label>
				{!getHelpHTML('Checking this will automatically re-build your game if there are library updates, making sure that everything is kept up-to-date!')}<br>
		{#if {{build_command}} == 'DETECTING'}
			<strong id="build_detecting">Currently detecting settings, please hold by</strong>
			<input type="hidden" name="omit_build_settings" value="1" />
			<div id="edit_build_settings_fields" class="hidden">
		{#endif}
		<label>Automatically pick settings: <input type="checkbox" name="autopick_build_settings" value="1"{#if !{{exists}}} checked="checked"{#endif}></label>
				{!getHelpHTML('Automatically try to detect the values needed to build your package')}<br>
		<label>Build path: <input type="text" name="build_path" value="{build_path}" maxlength="32"></label>
				{!getHelpHTML('The path in which the building process will happen')}<br>
		<label>Build command: <input type="text" name="build_command" value="{build_command}" maxlength="64"></label>
				{!getHelpHTML('The shell command to run to be able to build this game')}<br>
		<label>Include makefile: <input type="checkbox" name="build_makefile"{#if {{build_makefile}}} checked="checked"{#endif}></label>
				{!getHelpHTML('Include a makefile to easily compile gamebuino projects')}<br>
		<label>Output Filename: <input type="text" name="build_filename" value="{build_filename}" maxlength="8"></label>
				{!getHelpHTML('Filename of the output file, max. 8 chars, all uppercase, without .HEX, e.g. MYFILE')}
		{#if {{build_command}} == 'DETECTING'}
			</div>
		{#endif}
	</div>
	<input type="submit" value="{#if {{exists}}}Save Edit{#else}Upload File{#endif}">
</form>
<script type="text/javascript">
	// file type switcher
	$(function(){
		$('input[type=radio][name=file_type]').change(function(){
			$('.file_type_content').addClass('hidden');
			$('#file_type_content_'+this.value).removeClass('hidden').trigger('switch');
		});
		$('.file_type_content').addClass('hidden');
		$('#file_type_content_'+$('input[type=radio][name=file_type]:checked').val()).removeClass('hidden');
	});
	
	// github auth stuff
	$(function(){
		var buildRepo_single = function(r,checkbox){
				if(checkbox === undefined){
					checkbox = true;
				}
				var current = r.full_name=={!json_encode({{file_type['github_repo']}})};
				return $('<div>').css({
					border:'1px solid black',
					borderRadius:5,
					padding:5
				}).append(
					$('<label>').append(
						[
							(checkbox?
							$('<input>').attr({
								type:'radio',
								name:'github_repo'
							}).attr((current?'checked':'false'),'checked').val(r.full_name)
							:'')
						],' ',
						$('<strong>').text(r.name),' ',
						$('<a>').text('(visit)').attr({
							href:r.html_url,
							target:'_blank'
						}),'<br>',$('<span>').text(r.description)
					)
				);
			},
			buildRepos = function(data){
				$('#github_repos').empty().append(
					$.map(data.repos,function(r){
						return buildRepo_single(r);
					})
				);
			};
		$('#file_type_content_1').on('switch',function(){
			if(this.dataset.loaded == 0){
				$(this).children('div').addClass('hidden');
				$('#github_loading').removeClass('hidden');
				this.dataset.loaded = true;
				$.getJSON('github.php?userinfo',function(data){
					$('#github_loading').addClass('hidden');
					if(!data.exists){
						$('#github_loginform').removeClass('hidden');
						return;
					}
					$('#github_avatar').attr('src',data.avatar);
					$('#github_username').text(data.username);
					{#if {{exists}}}
						$('#github_repos').empty().append(
							$.map(data.repos,function(r){
								var current = r.full_name=={!json_encode({{file_type['github_repo']}})};
								if(current){
									return [
										buildRepo_single(r,false),
										$('<input>').attr({
											'type':'hidden',
											'name':'github_repo'
										}).val(r.full_name),
										$('<a>').text('Change Repo').attr('href','#').click(function(e){
											e.preventDefault();
											buildRepos(data);
										})
									];
								}
							})
						);
					{#else}
						buildRepos(data);
					{#endif}
					$('#github_reposform').removeClass('hidden');
				});
			}
		});
		//userinfo
		$('#github_login').click(function(e){
			e.preventDefault();
			window.open('github.php?login','Login with Github','menubar=no,location=yes,resizable=yes,scrollbars=yes,status=yes');
		});
		$(window).bind('storage',function(e){
			var key = e.originalEvent.key;
			if(key == 'reload_github'){
				localStorage.removeItem(key);
				var $elem = $('#file_type_content_1');
				$elem[0].dataset.loaded = 0;
				$elem.trigger('switch');
			}
		});
		if($('input[type=radio][name=file_type]:checked').val() == 1){
			$('#file_type_content_1').trigger('switch');
		}
	});
	
	// edit form switcher
	$(function(){
		$('#edit_file_settings_button').click(function(e){
			e.preventDefault();
			$('#edit_build_settings').addClass('hidden');
			$('#edit_file_settings').removeClass('hidden');
		});
		$('#edit_build_settings_button').click(function(e){
			e.preventDefault();
			$('#edit_file_settings').addClass('hidden');
			$('#edit_build_settings').removeClass('hidden');
		});
		$('#edit_file_settings').removeClass('hidden');
	});
	
	// build setting stuff
	$(function(){
		$('input[name="autopick_build_settings"]').click(function(e){
			if(this.checked && $('input[name="build_command"]').val() != ''){
				this.checked = confirm('Are you sure you want to over-write the current build settings?');
			}
		});
		{#if {{build_command}} == 'DETECTING'}
		$(function(){
			var $msg = $('#build_detecting'),
				msg = $msg.text(),
				append = '',
				timer = setInterval(function(){
					$msg.text(msg+append);
					append += '.';
					if(append.length >= 5){
						append = '';
						$.getJSON('index.php?getBuildVars={id}',function(data){
							if(data.build_command != 'DETECTING'){
								clearTimeout(timer);
								$('input[name="build_path"]').val(data.build_path);
								$('input[name="build_command"]').val(data.build_command);
								$('input[name="build_filename"]').val(data.build_filename);
								$('input[name="build_makefile"]')[0].checked = data.build_makefile;
								
								$('input[name="omit_build_settings"]').remove();
								$msg.remove();
								$('#edit_build_settings_fields').removeClass('hidden');
							}
						});
					}
				},1000);
		});
		{#endif}
	});
	
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
