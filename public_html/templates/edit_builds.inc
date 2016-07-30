
<div class="hidden" id="edit_builds">
	<h2>Builds</h2>
	<div class="button" id="edit_new_build">New Build</div>
	<div id="edit_builds_container">
		{:children}
	</div>
</div>
<script type="text/javascript">
	$(function(){
		$('#edit_new_build').click(function(e){
			e.preventDefault();
			$.getJSON('?build={id}',function(data){
				if(data.success){
					alert('Build triggered!');
					$.getJSON('?get_build='+data.id,function(data){
						if(data.success){
							$('#edit_builds_container').prepend(data.html);
						}
					});
				}else{
					alert('Couldn\'t trigger build, perhaps one is already pending or you didn\'t set a build command?');
				}
			})
		});
	});
</script>
