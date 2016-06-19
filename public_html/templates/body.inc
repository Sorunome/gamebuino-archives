<!DOCTYPE html>
<html>
<head>
	<title>Gamebuino Archive - <?=$this->title?></title>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="style.css">
	<meta http-equiv="content-language" content="en-gb">
	<link rel="shortcut icon" href="/favicon.ico">
	<script type="text/javascript" src="jquery-2.0.3.min.js"></script>
</head>
<body><?php include('../navbar/navbar.html'); ?>
	<h1><a href="."><img src="/navbar/gamebuino_logo_160.png" alt="gamebuino"> Games</a></h1><br>
	<div class="centercont buttongroup">
	<?php if(FOLDERS): ?>
		<a class="button" href=".">Recent files</a>
		<a class="button" href="?cat=1">Browse files</a>
	<?php else: ?>
		'<a class="button" href=".">Show files</a>'
	<?php endif ?>
	<?php if($isLoggedIn): ?>
		<a class="button" href="/forum/ucp.php?mode=logout">Logout [ <?=$username?> ]</a>
		<a class="button" href="?newfile">Upload file</a>
		<?php if($asAdmin): ?>
			<span class="button">Admin</span>
		<?php endif ?>
	<?php else: ?>
		<a class="button" href="/forum/ucp.php?mode=register">Register</a>
		<a class="button" href="/forum/ucp.php?mode=login">Login</a>
	<?php endif ?>
	</div>
	<article>
		<?php $this->renderChildren(); ?>
	</article>
	<footer>Archives software &copy;<a href="https://www.sorunome.de" target="_blank">Sorunome</a><br>Gamebuino &copy;Rodot<br>Something isn\'t working? <a href="https://github.com/Sorunome/gamebuino-archives/issues" target="_blank">Report the issue!</a></footer>
	</body>
</html>
