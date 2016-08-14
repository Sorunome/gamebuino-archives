<?php
namespace gamebuino\archives;

class ext extends \phpbb\extension\base {
	public function enable_step($old_state){
		switch($old_state){
			case '':
				$phpbb_notifications = $this->container->get('notification_manager');
				$phpbb_notifications->enable_notifications('gamebuino.archives.notification.type.build_success');
				$phpbb_notifications->enable_notifications('gamebuino.archives.notification.type.build_failed');
				return 'notifications';
			default:
				return parent::enable_step($old_state);
		}
	}
	public function disable_step($old_state){
		switch($old_state){
			case '':
				$phpbb_notifications = $this->container->get('notification_manager');
				$phpbb_notifications->disable_notifications('gamebuino.archives.notification.type.build_success');
				$phpbb_notifications->disable_notifications('gamebuino.archives.notification.type.build_failed');
				return 'notifications';
			default:
				return parent::disable_step($old_state);
		}
	}
	public function purge_step($old_state){
		switch($old_state){
			case '':
				$phpbb_notifications = $this->container->get('notification_manager');
				$phpbb_notifications->purge_notifications('gamebuino.archives.notification.type.build_success');
				$phpbb_notifications->purge_notifications('gamebuino.archives.notification.type.build_failed');
				return 'notifications';
			default:
				return parent::purge_step($old_state);
		}
	}
}
