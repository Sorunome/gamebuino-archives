<h1>Admin Panel</h1>
{#foreach {{messages}} as $m}
	<div class="admin_message">{$m}</div>
{#endforeach}
-&gt; <a href="admin.php?clearTemplateCache">Clear template cache</a><br>
-&gt; <a href="admin.php?wipeBoxTemplate">Wipe the sandbox template</a><br>
-&gt; <a href="admin.php?triggerBuilds">Trigger automatic builds (with wiping template)</a>
