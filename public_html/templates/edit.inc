{:global $isLoggedIn,$userid,$isAdmin}
{#if !{{can_edit}}}
	Access Denied
	{:return}
{#endif}

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
		<div class="button" id="edit_builds_button">Builds</div>
	</div>
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
			$('#edit_build_settings,#edit_builds').addClass('hidden');
			$('#edit_file_settings').removeClass('hidden');
		});
		$('#edit_build_settings_button').click(function(e){
			e.preventDefault();
			$('#edit_file_settings,#edit_builds').addClass('hidden');
			$('#edit_build_settings').removeClass('hidden');
		});
		$('#edit_builds_button').click(function(e){
			e.preventDefault();
			$('#edit_file_settings,#edit_build_settings').addClass('hidden');
			$('#edit_builds').removeClass('hidden');
		});
		$('#edit_file_settings').removeClass('hidden');
	});
</script>
