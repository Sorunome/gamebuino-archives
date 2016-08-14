{:global $user}
<div id="build_{id}" class="build{#if {{status}}==4} duplicate{#endif}{#if {{status}}==0} fail{#endif}">
	{#if {{status}} == 0 || {{status}} == 4}
		<div class="delete">x</div>
		<script type="text/javascript">
			$('#build_{id} .delete').off('click').click(function(e){
				$.getJSON('?delete_build={id}',function(data){
					if(data.success){
						$('#build_{id}').remove();
					}
				});
			});
		</script>
	{#endif}
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
		<a href="#" class="view_output">View Output</a>
	</div>
<script type="text/javascript">
$(function(){
	$('#build_{id} .view_output').off('click').click(function(e){
		var _self = this;
		e.preventDefault();
		if($(this).next().is('pre')){
			$(this).next().remove();
			return;
		}
		$.get('edit.php?build_message={id}').done(function(data){
			$(_self).after($('<pre>').text(data));
		});
	})
});
{#if {{status}} == 1 || {{status}} == 2}
	$(function(){
		var $msg = $('#build_{id}_pending'),
			append = '',
			timer = setInterval(function(){
				$msg.text(append);
				append += '.';
				if(append.length >= 5){
					append = '';
					$.getJSON('edit.php?get_build={id}',function(data){
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
{#endif}
</script>

</div>
