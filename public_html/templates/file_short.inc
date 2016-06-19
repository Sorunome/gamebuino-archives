<?php
if(!$this->exists){
	return;
}
?>
<div class="filecont">
	<div class="name"><?=$this->name?></div>
	<div class="author"><a href="?author=<?=$this->authorId?>"><?=$this->author?></a></div>
	<a href="?file=<?=$this->id?>">
		<div class="popup">
			<div class="description"><?=$this->description?></div>
			<div class="downloads"><?=$this->downloads?></div>
			<div class="rating">+<?=$this->upvotes?>/-<?=$this->downvotes?></div>
		</div>
		<img src="<?=$this->image?>" alt="<?=$this->name?>" class="<?=$class?>">
	</a>
	<input class="fileDlCheckbox" type="checkbox" data-id="<?=$this->id?>">
</div>
