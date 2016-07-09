
<div class="hidden" id="edit_builds">
	<h2>Builds</h2>
	<div class="button" id="edit_new_build">New Build</div> <a href="#" id="edit_builds_refresh">Refresh</a>
	<div id="edit_builds_container">
		{:children}
	</div>
</div>
<script type="text/javascript">
	$(function(){
		var parseBuilds = function(){
				$('#edit_builds_container .view_output').click(function(e){
					var _self = this;
					e.preventDefault();
					if($(this).next().is('pre')){
						$(this).next().remove();
						return;
					}
					$.get('?build_message='+this.dataset.id).done(function(data){
						$(_self).after($('<pre>').text(data));
					});
				});
			},
			fetchBuilds = function(){
				$.get('?edit_builds={id}').done(function(data){
					$('#edit_builds_container').empty().append(data);
					parseBuilds();
				});
			};
		$('#edit_new_build').click(function(e){
			e.preventDefault();
			$.getJSON('?build={id}',function(data){
				if(data.success){
					alert('Build triggered!');
					fetchBuilds();
				}else{
					alert('Couldn\'t trigger build, perhaps one is already pending or you didn\'t set a build command?');
				}
			})
		});
		$('#edit_builds_refresh').click(function(e){
			e.preventDefault();
			fetchBuilds();
		});
		parseBuilds();
	});
</script>
