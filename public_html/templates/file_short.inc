<div class="filecont{#if !{{public}}} private{#endif}">
	<div class="name">{name}</div>
	<div class="author"><a href="?author={authorId}">{author}</a></div>
	<a href="?file={id}">
		<div class="popup">
			<div class="description">{description}</div>
			<div class="downloads">Downloads: {downloads}</div>
			<div class="rating">Rating: +{upvotes}/-{downvotes}</div>
		</div>
		<img src="{image}" alt="{name}" class="{class}">
	</a>
	<input class="fileDlCheckbox" type="checkbox" data-id="{id}">
</div>
