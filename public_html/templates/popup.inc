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
<body>
	<h1><img src="/navbar/gamebuino_logo_160.png" alt="gamebuino"> Games</h1><br>
	<article>
		{:children}
	</article>
	<footer>
		Archives software &copy;Sorunome<br>
		Gamebuino &copy;Rodot
	</footer><br>
	<div class="buttongroup" style="text-align:center;">
		<div class="button" id="close_window">Close this window</div>
	</div>
	<script type="text/javascript">
	$(function(){
		$('#close_window').click(function(e){
			e.preventDefault();
			window.close();
		});
	});
	</script>
	</body>
</html>
