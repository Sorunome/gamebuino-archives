{#if {{success}}}
	Successfully logged in with {name}!<br>
	<script type="text/javascript">
	$(function(){
		localStorage.setItem('reload_{strtolower({{name}})}',(new Date).toString());
	});
	</script>
{#else}
	Something went wrong logging in with {name}, perhaps you denied access?<br>
{#endif}
