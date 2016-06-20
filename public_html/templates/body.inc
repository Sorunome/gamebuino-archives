{:global $isLoggedIn,$isAdmin,$username}
<!DOCTYPE html>
<html>
<head>
	<title>Gamebuino Archive - {title}</title>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="style.css">
	<meta http-equiv="content-language" content="en-gb">
	<link rel="shortcut icon" href="/favicon.ico">
	<script type="text/javascript" src="jquery-2.0.3.min.js"></script>
</head>
<body><?php include('../navbar/navbar.html'); ?>
	<h1><a href="."><img src="/navbar/gamebuino_logo_160.png" alt="gamebuino"> Games</a></h1><br>
	<div class="centercont buttongroup">
		<a class="button" href=".">Show files</a>
		{#if $isLoggedIn}
			<a class="button" href="/forum/ucp.php?mode=logout">Logout [ {$username} ]</a>
			<a class="button" href="?edit=-1">Upload file</a>
			{#if $isAdmin}
				<span class="button">Admin</span>
			{#endif}
		{#else}
			<a class="button" href="/forum/ucp.php?mode=register">Register</a>
			<a class="button" href="/forum/ucp.php?mode=login">Login</a>
		{#endif}
	</div>
	<article>
		{:children}
	</article>
	<footer>
		Archives software &copy;<a href="https://www.sorunome.de" target="_blank">Sorunome</a><br>
		Gamebuino &copy;Rodot<br>
		Something isn't working? <a href="https://github.com/Sorunome/gamebuino-archives/issues" target="_blank">Report the issue!</a>
	</footer>
	</body>
</html>
