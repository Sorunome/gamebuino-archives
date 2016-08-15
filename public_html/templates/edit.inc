{#if !{{can_edit}}}
	{#if {{exists}}}
		Access Denied
	{#else}
		You need to <a href="/forum/ucp.php?mode=register">Register</a> or <a href="/forum/ucp.php?mode=login">Login</a> to be able to upload a file!
	{#endif}
	{:return}
{#endif}

{#if {{exists}}}
	<h1>Editing file <i>{name}</i></h1><a href="./?file={id}">Back</a><br><br>
{#else}
	<h1>Adding new file</h1>
{#endif}
<form id="fileeditform" action="edit.php?save={id}" method="post" enctype="multipart/form-data">
	{#if !{{exists}}}
		<input type="hidden" name="name_83" value="">
		<div id="name_83_overlay" class="overlay">
			<div>
				<h1>Adding new File</h1>
				<p>You are about to add a new file to the archives! However, first you will have to set a filename following the <a href="https://en.wikipedia.org/wiki/8.3_filename" target="_blank">8.3 filename standard</a> (basically an 8-character all-uppercase name, some special characters, numbers and spaces are allowed)</p>
				<br>
				<label>8.3 Filename: <input type="text"></label> <button class="button">go!</button>
			</div>
		</div>
	{#endif}
	Name:<input type="text" name="name" value="{name}">
			{!getHelpHTML('The name of the file as it will be displayed')}<br>
	File type:
	{#foreach array('zip','Github','Bitbucket') as $i => $v}
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
	{#foreach array('github','bitbucket') as $i => $prefix}
		{:set $i $i+1}
		<div class="file_type_content hidden" id="file_type_content_{$i}" data-loaded="0">
			<div id="{$prefix}_loading">Loading...</div>
			<div id="{$prefix}_loginform" class="hidden">
				You haven't connected your account with {$prefix} yet, to be able to use a repo from {$prefix} you need to do this!<br>
				<button id="{$prefix}_login">Login with {$prefix}</button>
			</div>
			<div id="{$prefix}_reposform" class="hidden">
				<div style="font-size:1.5em;font-weight:bold;"><img id="{$prefix}_avatar" alt="{$prefix}_avatar" style="max-width:5em;max-height:5em;">&nbsp;<span id="{$prefix}_username" style="vertical-align:top;"></span></div>
				<div id="{$prefix}_repos"></div>
			</div>
		</div>
	{#endforeach}
	{#if {{exists}}}
		<div class="buttongroup">
			<div class="button" id="edit_file_settings_button">File Settings</div>
			<div class="button" id="edit_builds_button">Builds</div>
		</div>
	{#endif}
	{:children}
	
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
	
	// repo auth stuff
	$(function(){
		var repo_builder = function(prefix,reponum){
			var buildRepo_single = function(r,checkbox){
					if(checkbox === undefined){
						checkbox = true;
					}
					var current = r.full_name=={!json_encode({{file_type['git_repo']}})};
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
									name:'git_repo_'+reponum
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
					$('#'+prefix+'_repos').empty().append(
						$.map(data.repos,function(r){
							return buildRepo_single(r);
						})
					);
				};
			$('#file_type_content_'+reponum).on('switch',function(){
				if(this.dataset.loaded == 0){
					$(this).children('div').addClass('hidden');
					$('#'+prefix+'_loading').removeClass('hidden');
					this.dataset.loaded = true;
					$.getJSON(prefix+'.php?userinfo',function(data){
						$('#'+prefix+'_loading').addClass('hidden');
						if(!data.exists){
							$('#'+prefix+'_loginform').removeClass('hidden');
							return;
						}
						$('#'+prefix+'_avatar').attr('src',data.avatar);
						$('#'+prefix+'_username').text(data.username);
						{#if {{exists}}}
							if(reponum == {file_type['type']}){
								$('#'+prefix+'_repos').empty().append(
									$.map(data.repos,function(r){
										var current = r.full_name=={!json_encode({{file_type['git_repo']}})};
										if(current){
											return [
												buildRepo_single(r,false),
												$('<input>').attr({
													'type':'hidden',
													'name':'git_repo_'+reponum
												}).val(r.full_name),
												$('<a>').text('Change Repo').attr('href','#').click(function(e){
													e.preventDefault();
													buildRepos(data);
												})
											];
										}
									})
								);
							}else{
								buildRepos(data);
							}
						{#else}
							buildRepos(data);
						{#endif}
						$('#'+prefix+'_reposform').removeClass('hidden');
					});
				}
			});
			//userinfo
			$('#'+prefix+'_login').click(function(e){
				e.preventDefault();
				window.open(prefix+'.php?login','Login with '+prefix,'menubar=no,location=yes,resizable=yes,scrollbars=yes,status=yes');
			});
			$(window).bind('storage',function(e){
				var key = e.originalEvent.key;
				if(key == 'reload_'+prefix){
					localStorage.removeItem(key);
					var $elem = $('#file_type_content_'+reponum);
					$elem[0].dataset.loaded = 0;
					$elem.trigger('switch');
				}
			});
			if($('input[type=radio][name=file_type]:checked').val() == reponum){
				$('#file_type_content_'+reponum).trigger('switch');
			}
		};
		repo_builder('github',1);
		repo_builder('bitbucket',2);
	});
	
	// edit form switcher
	$(function(){
		$('#edit_file_settings_button').click(function(e){
			e.preventDefault();
			$('#edit_builds').addClass('hidden');
			$('#edit_file_settings').removeClass('hidden');
		});
		$('#edit_builds_button').click(function(e){
			e.preventDefault();
			$('#edit_file_settings').addClass('hidden');
			$('#edit_builds').removeClass('hidden');
		});
		$('#edit_file_settings').removeClass('hidden');
	});
	
	{#if !{{exists}}}
	// 8.3 filename picker
	$(function(){
		var checkName = function(){
			var name = $('#name_83_overlay input').val();
			if(name == '0' || name == ''){
				alert('invalid name');
				return;
			}
			$.getJSON('edit.php?check_name_83='+encodeURIComponent(name),function(data){
				if(!data.success){
					alert(data.msg);
					return;
				}
				if(data.suggest && !confirm('Name "'+name+'" is already taken, would you like to use "'+data.name+'" instead?')){
					alert('asdf');
					return;
				}
				// time to DO STUFF!!!!!
				$('input[name="name_83"]').val(data.name);
				$('#name_83_overlay').remove();
			});
		}
		$('#name_83_overlay input').keypress(function(e){
			if(e.which == 13){
				e.preventDefault();
				checkName();
			}
		});
		$('#name_83_overlay button').click(function(e){
			e.preventDefault();
			checkName();
		});
	});
	{#endif}
	
	// JS verifier
	$(function(){
		$('#fileeditform').submit(function(e){
			e.preventDefault();
			if($('input[name="name"]').val() == ''){
				alert('Please set a name!');
				return;
			}
			{#if !{{exists}}}
				if($('input[name="name_83"]').length == 0 || $('input[name="name_83"]').val() == ''){
					alert('WTF how did you manage to get this error? Anyhow, try refreshing the page');
					return;
				}
				var file_type = $('input[name="file_type"]:checked').val();
				if(file_type == 0){
					var name = $('input[name="zip"]').val();
					if(name == ''){
						alert('You need to add a zip file!');
						return;
					}else if(name.substr(name.length - 4) != '.zip'){
						alert('File needs to be a zip!');
						return;
					}
				}else{
					if($('input[name="git_repo_'+file_type+'"]:checked').length == 0){
						alert('You need to select a repository!');
						return;
					}
				}
				if($('input[name="image0"]').val() == ''){
					alert('Please add a main image!');
					return;
				}
			{#endif}
			if($('input[name="category"]').val() == ''){
				alert('You need to add at least one tag!');
				return;
			}
			$(this).unbind('submit').submit();
		});
	});
</script>
