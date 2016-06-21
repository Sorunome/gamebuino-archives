{:default url,'?';limit,false}
{:set $cursort (int)request_var('sort',0)}
{:set $curdir (int)request_var('direction',0)}
{:set $curlimit (int)request_var('limit',50)}
{:set $tags request_var('tags','[0]')}
{#if !preg_match("/^(\[\d+\])+$/",$tags)}
	{:set $tags '[0]'}
{#endif}

<div id="fileSorter">
	<div class="buttongroup">
	{#foreach array('Date updated','Date added','Name','Author','Rating','Downloads') as $i => $s}
		{#if $i == $cursort}
			<div class="button is-checked changestuff sortorder" data-num="{$i}">{$s}</div>
		{#else}
			<a class="button changestuff sortorder" data-num="<?=$i?>"
				href="{url}&amp;sort={$i}&amp;direction={$curdir}{#if {{limit}}}&amp;limit={$curlimit}{#endif}&amp;tags={$tags}">{$s}</a>
		{#endif}
	{#endforeach}

	</div>
	<div class="buttongroup">';
		{#if $curdir == 0}
			<div class="button is-checked changestuff sortdir" data-num="0">▼</div>
			<a class="button changestuff sortdir" data-num="1"
				href="{url}&amp;sort={$cursort}&amp;direction=1{#if {{limit}}}&amp;limit={$curlimit}{#endif}&amp;tags={$tags}">▲</a>
		{#else}
			<a class="button changestuff sortdir" data-num="0"
			href="{url}&amp;sort={$cursort}&amp;direction=0{#if {{limit}}}&amp;limit={$curlimit}{#endif}&amp;tags={$tags}">▼</a>
			<div class="button is-checked changestuff sortdir" data-num="1">▲</div>';
		{#endif}
	</div>
	{#if {{limit}}}
		<div class="buttongroup"><div class="button is-checked" style="cursor:default;">Limit:</div>
			{#foreach array(10,20,50,100,200) as $l}
				{#if $l == $curlimit}
					<div class="button is-checked changestuff sortlimit" data-num="{$l}">{$l}</div>
				{#else}
					<a class="button changestuff sortlimit" data-num="<?=$l?>"
						href="{url}&amp;sort={$cursort}&amp;direction={$curdir}&amp;limit={$l}&amp;tags={$tags}">{$l}</a>
				{#endif}
			{#endforeach}
			
			{#if $curlimit == -1}
				<div class="button is-checked changestuff sortlimit" data-num="-1">All</div>';
			{#else}
				<a class="button changestuff sortlimit" data-num="-1" href="{url}&amp;sort={$cursort}&amp;direction={$curdir}&amp;limit=-1&amp;tags={$tags}">All</a>
			{#endif}
		</div>
	{#else}
		{:set $curlimit -1}
	{#endif}


	<div class="buttongroup"><div class="button is-checked" style="cursor:default;">Tags:</div>
		<span id="taglistpicker"><div class="button error">Please enable Javascript</div></span>
		<input type="hidden" id="taglist" value="<?=$tags?>">
		
		<script type="text/javascript">
			$(function(){
				var taglist = <?=json_encode(array('_0' => 'All') + getCategoryListDropdown())?>,
					tags = <?=json_encode(explode('][',substr($tags,1,strlen($tags)-2)))?>,
					updateTagList = function(){
						var tagIdsMix = $(".tagDropdown select").map(function(){return this.value;}),
							tagIds = [];
						$.each(tagIdsMix,function(i,el){
							if($.inArray("["+el+"]",tagIds) === -1){
								tagIds.push("["+el+"]");
							}
						});
						$("#taglist").val(tagIds.join("")).trigger("change");
					},
					makeTagList = function(v){
						return $("<div>").addClass("button tagDropdown").append(
							$("<select>").attr("size","1").append(
								$.map(taglist,function(c,i){
									i = i.substr(1);
									return $("<option>").text(c).attr(((i==v)?"selected":"false"),"selected").val(i);
								})
							).change(function(){
								updateTagList();
							})
						);
					};
				$("#taglistpicker").empty().append(
					$.map(tags,function(v){
						return makeTagList(v);
					})
				);
			});
		</script>
	</div>


	<div class="buttongroup" id="selectedfilesgroup">
		<div class="button" id="selectallfiles">Select all</div>
		<div class="button" id="deselectallfiles">Deselect all</div>
		<a class="button" id="downloadselectedfiles" download>Download selected</a>
	</div>
</div>

<div id="files">
	{:children}
</div>

<script type="text/javascript">
	$(function(){
		var DLIds = [],
			updateDlUrl = function(){
				$("#downloadselectedfiles").attr("href","?dlmult="+DLIds.join(","));
			};
		$(".fileDlCheckbox").prop("checked",false).change(function(){
			var fid = this.dataset.id;
			if(this.checked){
				DLIds.push(fid);
			}else{
				DLIds = $.grep(DLIds,function(v){
					return v != fid;
				});
			}
			updateDlUrl();
		});
		$("#selectallfiles").click(function(){
			DLIds = $(".fileDlCheckbox").prop("checked",true).map(function(){
				return this.dataset.id;
			}).get();
			updateDlUrl();
		});
		$("#deselectallfiles").click(function(){
			$(".fileDlCheckbox").prop("checked",false);
			DLIds = [];
			updateDlUrl();
		});
		$("#downloadselectedfiles").click(function(e){
			if(DLIds.length < 1){
				e.preventDefault();
				alert("No files selected!");
				return;
			}
			$(".stabilitywarning").remove();
			$.getJSON(this.href+"&info",function(data){
				if(data.stability > 0){
					var $elem = $("<div>").addClass("button stabilitywarning").text("Stability errors");
					switch(data.stability){
						case 1:
							$elem.text("Stability errors may occur due to file renaming").addClass("warning");
							break;
						case 2:
							$elem.text("Stability errors will occur as not all files could be re-named").addClass("error");
							break;
					}
					$("#selectedfilesgroup").append($elem);
				}
			})
		})
	});
	$(function(){
		var cursort = <?=$cursort?>,
			curdir = <?=$curdir?>,
			curlimit = <?=$curlimit?>,
			curtags = "[0]",
			getNewFiles = function(){
				var url = "<?=$this->url?>&getFiles&sort="+cursort+"&direction="+curdir+"&limit="+curlimit;
				if(curtags){
					url += "&tags="+curtags;
				}
				$.get(url).done(function(data){
					$("#files").empty().append(data);
				});
			};
		$(".changestuff").click(function(e){
			e.preventDefault();
			if($(this).hasClass("is-checked")){
				return;
			}
			var num = $(this).attr("data-num");
			if(!num && num!==0){
				return;
			}
			if($(this).hasClass("sortorder")){
				cursort = num;
				$(".sortorder").removeClass("is-checked");
			}else if($(this).hasClass("sortdir")){
				curdir = num;
				$(".sortdir").removeClass("is-checked");
			}else if($(this).hasClass("sortlimit")){
				curlimit = num;
				$(".sortlimit").removeClass("is-checked");
			}
			$(this).addClass("is-checked");
			getNewFiles();
		});
		$("#taglist").change(function(){
			curtags = $(this).val();
			getNewFiles();
		});
		curtags = $("#taglist").val();
	});
</script>
