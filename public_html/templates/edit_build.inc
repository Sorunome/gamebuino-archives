{:global $user}
<div class="build{#if {{status}}==4} duplicate{#endif}{#if {{status}}==0} fail{#endif}">
	<div>
		Build from {date($user->data['user_dateformat'],{{ts}})}<br>
		Status:
		{#switch {{status}}}
			{#case 0}failed{#break}
			{#case 1}{#case 2}Pending...{#break}
			{#case 3}{#case 4}Success {#if {{status}}==4}{!getHelpHTML('This build is the same as a previous build, thus the output files have been deleted')}{#endif}{#break}
		{#endswitch}
	</div>
	<div>
		<a href="#" class="view_output" data-id="{id}">View Output</a>
	</div>
</div>
