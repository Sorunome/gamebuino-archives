{#if {{success}}}
	Successfully logged in with github!<br>
	<script type="text/javascript">
	$(function(){
		localStorage.setItem('reload_github',(new Date).toString());
	});
	</script>
{#else}
	Something went wrong logging in with github, perhaps you denied access?<br>
{#endif}
