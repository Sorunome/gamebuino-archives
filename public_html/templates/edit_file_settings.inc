<div class="hidden" id="edit_file_settings">
	<h2>File Settings</h2>
	<label>Forum-Topic (optional):<input type="url" name="forum_url" value="{forum_url}"></label>
			{!getHelpHTML('A link to the forum topic where discussion about this program/game occured')}<br>
	<input type="hidden" name="category" value="{'['.implode('][',{{categories}}).']'}">
	Tags:{!getHelpHTML('The tags your game should have, you can add multiple')}
	<div id="categoriesContent">Please enable Javascript!</div><br>
	Extra authors:{!getHelpHTML('Did you develop your game with other people? Here you can add them as an author!')}
	<input type="hidden" name="extra_authors" value="">
	<div id="extraAuthorsContent">
		{#foreach {{extra_authors}} as $id => $name}
			<div data-id="{$id}">{$name} <a href="#">x</a></div>
		{#endforeach}
		<input type="text"><a href="#">Add author</a>
	</div><br>
	Description:{!getHelpHTML('A long description of your game')}<br>
	<textarea name="description">{description}</textarea>
	<br><br>
	Screenshots (all optional{#if {{exists}}}, only saved if changed{#endif}):
			{!getHelpHTML('Nothing describes a game better than a screenshot! You can upload up to four, the first one will be the main screenshot.
						Animated GIFs are allowed.')}<br>
	{#for $i = 0;$i < 4;$i++}
		<label>Image {$i+1}{#if $i==0} (main image){#endif}: <input type="file" name="image{$i}"></label>
		{#if {{exists}}}
			| <label>Delete old: <input type="checkbox" name="delimage{$i}" value="true"></label>
		{#endif}
		<br>
	{#endfor}
</div>
<script type="text/javascript">
	// category picker
	$(function(){
		var catlist = {!json_encode(getCategoryListDropdown())},
			cats = {!json_encode({{categories}})},
			makeCatList = function(v){
				return $("<div>").addClass("categoryDropdown").append(
					$("<select>").attr("size","1").append(
						$.map(catlist,function(c,i){
							i = i.substr(1);
							return $("<option>").text(c).attr((i==v?"selected":"false"),"selected").val(i);
						})
					),"&nbsp;",
					$("<a>").text("x").attr("href","http://remove").click(function(e){
						e.preventDefault();
						$(this).parent().remove();
					})
				);
			};
		$("#categoriesContent").empty().append(
			$.map(cats,function(v){
				return makeCatList(v);
			})
		).after($("<a>").text("+ add Tag").attr("href","http://add").click(function(e){
			e.preventDefault();
			$("#categoriesContent").append(makeCatList());
		}));
		$("#fileeditform").submit(function(e){
			var catIdsMix = $(".categoryDropdown select").map(function(){return this.value;}),
				catIds = [];
			$.each(catIdsMix,function(i,el){
				if($.inArray("["+el+"]",catIds) === -1){
					catIds.push("["+el+"]");
				}
			});
			this.category.value = catIds.join("");
			
			// no e.preventDefault() as we still want to send it
		});
	});
	// extra authors picker
	$(function(){
		var reloadAuthorIds = function(){
				var authorIds = $('#extraAuthorsContent > div').map(function(){
					$(this).children('a').off('click').click(function(e){
						e.preventDefault();
						$(this).parent().remove();
						reloadAuthorIds();
					});
					return this.dataset.id;
				}).get().join(',');
				$('input[name="extra_authors"]').val(authorIds);
			};
		reloadAuthorIds();
		$('#extraAuthorsContent > a').click(function(e){
			e.preventDefault();
			var name = $('#extraAuthorsContent > input').val();
			$.getJSON('./?edit_authorcheck='+encodeURIComponent(name),function(data){
				if(data.id != -1){
					$('#extraAuthorsContent').prepend(
						$('<div>').attr('data-id',data.id).text(data.username).append(' ',
							$('<a>').attr('href','#').text('x')
						)
					);
					$('#extraAuthorsContent > input').val('');
				}else{
					alert('Username not found!');
				}
			});
		});
	});
</script>
