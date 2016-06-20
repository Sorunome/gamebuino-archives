<?php
class Template {
	private $_templateDir = 'templates/';
	private $_cacheDir = 'cache/';
	private $_file = '';
	private $_children = array();
	public $properties = array();
	public function __construct($file){
		$this->_file = $file;
	}
	private function _setDefault($a){
		foreach($a as $k => $v){
			if(!isset($this->properties[$k])){
				$this->properties[$k] = $v;
			}
		}
	}
	private function _renderChildren($key = '_children'){
		foreach($this->$key as $c){
			if(is_string($c)){
				echo $c;
			}else{
				$c->render();
			}
		}
	}
	private function _renderManually(){
		if(!file_exists($this->_templateDir.$this->_file)){
			throw new Exception("Couldn't find template file {$this->_templateDir}{$this->_file} !");
		}
		$lines = explode("\n",file_get_contents($this->_templateDir.$this->_file));
		foreach($lines as &$l){
			if(strpos($l,'{') !== false){ // lets do some more stuff!
				$l = preg_replace('/{{(\w[\w$\[\]\->]*)}}/','\$this->$1',$l);
				$l = preg_replace_callback('/{([!#:]?)([^}]+)}/',function($match){
					switch($match[1]){
						case '':
							if(strpos($match[2],';') !== false || strpos($match[2],':') !== false){
								return $match[0];
							}
							if($match[2][0] == '$' || strpos($match[2],'(') !== false){
								return '<?=htmlspecialchars('.$match[2].')?>';
							}
							return '<?=htmlspecialchars($this->'.$match[2].')?>';
						case '!':
							if($match[2][0] == '$' || strpos($match[2],'(') !== false){
								return '<?='.$match[2].'?>';
							}
							return '<?=$this->'.$match[2].'?>';
						case '#':
							$match = explode(' ',$match[2],2);
							switch($match[0]){
								case 'if':
								case 'while':
								case 'for':
								case 'foreach':
								case 'switch':
									return '<?php '.$match[0].'('.$match[1].'): ?>';
								case 'case':
									return '<?php case '.$match[1].': ?>';
								case 'else':
								case 'default':
									return '<?php '.$match[0].': ?>';
								case 'endif':
								case 'endwhile':
								case 'endfor':
								case 'endforeach':
								case 'endswitch':
									return '<?php '.$match[0].'; ?>';
							}
							return '';
						case ':':
							$match = explode(' ',$match[2],2);
							switch($match[0]){
								case 'global':
									return '<?php global '.$match[1].'; ?>';
								case 'children':
									return '<?php $this->_renderChildren(); ?>';
								case 'default':
									$s = '';
									foreach(explode(';',$match[1]) as $var){
										$a = explode(',',$var);
										$s .= '"'.trim($a[0]).'" => '.$a[1].',';
									}
									
									return '<?php $this->_setDefault(array('.rtrim($s,',').')); ?>';
							}
					}
					return '';
				},$l);
			}
		}
		$f = implode("\n",$lines);
		if(file_put_contents($this->_cacheDir.md5($this->_file).'.inc',$f)){
			$this->render();
		}else{
			eval('?>'.$f);
		}
	}
	public function render(){
		if(file_exists($this->_cacheDir.md5($this->_file).'.inc')){
			include($this->_cacheDir.md5($this->_file).'.inc');
		}else{
			$this->_renderManually();
		}
	}
	public function addChildren($c,$key = '_children'){
		if(is_array($c)){
			$this->$key = array_merge($this->$key,$c);
		}else{
			$this->$key[] = $c;
		}
	}
	public function addChild($c,$key = '_children'){
		$this->addChildren($c,$key);
	}
	public function loadJSON($j){
		foreach($j as $k => $v){
			$this->properties[$k] = $v;
		}
	}
	public function __set($k,$v){
		$this->properties[$k] = $v;
	}
	public function __get($k){
		return $this->properties[$k];
	}
}