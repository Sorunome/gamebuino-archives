<?php
namespace gamebuino\archives\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface {
	static public function getSubscribedEvents(){
		return array(
			'core.user_setup' => 'load_language_on_setup'
		);
	}
	public function __construct(\phpbb\controller\helper $helper, \phpbb\template\template $template){
		$this->helper = $helper;
		$this->template = $template;
	}
	public function load_language_on_setup($event){
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'gamebuino/archives',
			'lang_set' => 'common',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
}
