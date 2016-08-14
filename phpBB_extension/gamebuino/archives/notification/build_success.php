<?php
namespace gamebuino\archives\notification;

require_once(realpath(dirname(__FILE__)).'/common.php');
class build_success extends notification_base {
	public function get_type(){
		return 'gamebuino.archives.notification.type.build_success';
	}
	protected $language_key = 'ARCHIVES_SUCCESS_BUILD';
	public static $notification_option = array(
		'group' => 'ARCHIVES_NOTIFICATION_GROUP',
		'lang' => 'ARCHIVES_NG_BUILDSUCCESS'
	);
	public function get_avatar(){
		return '<img src="https://img.ourl.ca/muffin.png" alt="muffin">';
	}
	public function get_url(){
		return '/archives/?file='.$this->get_data('file_id');
	}
	public function get_reference(){
		return 'yaay';
	}
	public function get_email_template(){
		return '@gamebuino_archives/build_success';
	}
	public function get_email_template_variables(){
		return array(
			'NOTIFICATION_SUBJECT' => htmlspecialchars_decode($this->get_title())
		);
	}
}
