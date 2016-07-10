<div class="hidden" id="edit_build_settings">
	<h2>Build Settings</h2>
	<label>Automatic builds: <input type="checkbox" name="autobuild" value="1"{#if {{autobuild}}} checked="checked"{#endif}></label>
			{!getHelpHTML('Checking this will automatically re-build your game if there are library updates, making sure that everything is kept up-to-date!')}<br>
	{#if {{build_command}} == 'DETECTING'}
		<strong id="build_detecting">Currently detecting settings, please stand by</strong>
		<input type="hidden" name="omit_build_settings" value="1" />
		<div id="edit_build_settings_fields" class="hidden">
	{#endif}
	<label>Automatically pick settings: <input type="checkbox" name="autopick_build_settings" value="1"{#if !{{exists}}} checked="checked"{#endif}></label>
			{!getHelpHTML('Automatically try to detect the values needed to build your package')}<br>
	<span id="autobuild"{#if {{exists}}} class="hidden"{#endif}><label>Automatically build on success: <input type="checkbox" name="autobuild_after_autopick" value="1" checked="checked"></label>
			{!getHelpHTML('Automatically build your program if the build settings could be detected')}<br></span>
	<label>Build path: <input type="text" name="build_path" value="{build_path}" maxlength="32"></label>
			{!getHelpHTML('The path in which the building process will happen')}<br>
	<label>Build command: <input type="text" name="build_command" value="{build_command}" maxlength="64"></label>
			{!getHelpHTML('The shell command to run to be able to build this game')}<br>
	<label>Include makefile: <input type="checkbox" name="build_makefile"{#if {{build_makefile}}} checked="checked"{#endif}></label>
			{!getHelpHTML('Include a makefile to easily compile gamebuino projects')}<br>
	<label>Output Filename: <input type="text" name="build_filename" value="{build_filename}" maxlength="8"></label>
			{!getHelpHTML('Filename of the output file, max. 8 chars, all uppercase, without .HEX, e.g. MYFILE')}<br>
	<label>Move Path: <input type="text" name="build_movepath" value="{build_movepath}"></label>
			{!getHelpHTML('The Arduino IDE requires the main file to be located in a folder with the same name, if that is not the case you can automatically move the files in the source directory. This is relative to the build path, and the new build path will be inside this new directory')}
	{#if {{build_command}} == 'DETECTING'}
		</div>
	{#endif}
</div>
<script type="text/javascript">
	$(function(){
		$('input[name="autopick_build_settings"]').click(function(e){
			if(this.checked && $('input[name="build_command"]').val() != ''){
				this.checked = confirm('Are you sure you want to over-write the current build settings?');
			}
			if(this.checked){
				$('#autobuild').removeClass('hidden');
			}else{
				$('#autobuild').addClass('hidden');
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
								$('input[name="build_movepath"]').val(data.build_movepath);
								
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
</script>
