<?php
namespace gamebuino\archives\notification;

require_once(realpath(dirname(__FILE__)).'/common.php');
class build_failed extends notification_base {
	public function get_type(){
		return 'gamebuino.archives.notification.type.build_failed';
	}
	protected $language_key = 'ARCHIVES_FAILED_BUILD';
	public static $notification_option = array(
		'group' => 'ARCHIVES_NOTIFICATION_GROUP',
		'lang' => 'ARCHIVES_NG_BUILDFAIL'
	);
	public function get_avatar(){
		return '<img src="https://img.ourl.ca/muffin.png" alt="muffin">';
	}
	public function get_url(){
		return '/archives/?file='.$this->get_data('file_id');
	}
	public function get_reference(){
		return 'nuuuuu :(';
	}
	public function get_email_template(){
		return '@gamebuino_archives/build_failed';
	}
	public function get_email_template_variables(){
		return array(
			'NOTIFICATION_SUBJECT' => htmlspecialchars_decode($this->get_title())
		);
	}
}
