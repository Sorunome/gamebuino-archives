{:global $user}
<div id="build_{id}" class="build{#if {{status}}==4} duplicate{#endif}{#if {{status}}==0} fail{#endif}">
	<div>
		Build from {date($user->data['user_dateformat'],{{ts}})}<br>
		Status:
		{#switch {{status}}}
			{#case 0}failed{#break}
			{#case 1}{#case 2}Pending<span id="build_{id}_pending"></span>{#break}
			{#case 3}{#case 4}Success {#if {{status}}==4}{!getHelpHTML('This build is the same as a previous build, thus the output files have been deleted')}{#endif}{#break}
		{#endswitch}
	</div>
	<div>
		<a href="#" class="view_output" data-id="{id}">View Output</a>
	</div>
</div>
{#if {{status}} == 1 || {{status}} == 2}
	<script type="text/javascript">
	$(function(){
		var $msg = $('#build_{id}_pending'),
			append = '',
			timer = setInterval(function(){
				$msg.text(append);
				append += '.';
				if(append.length >= 5){
					append = '';
					$.getJSON('index.php?get_build={id}',function(data){
						if(!data.success){
							clearTimeout(timer);
							$('#build_{id}').text('ERROR: something went wrong!').addClass('fail');
							return;
						}
						if(!data.pending){
							clearTimeout(timer);
							$('#build_{id}').replaceWith(data.html);
						}
					});
				}
			},1000);
	});
	</script>
{#endif}
