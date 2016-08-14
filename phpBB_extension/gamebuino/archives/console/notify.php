<?php
namespace gamebuino\archives\console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class notify extends \phpbb\console\command\command {
	protected $db;
	protected $notification_manager;
	function __construct(\phpbb\user $user, \phpbb\db\driver\driver_interface $db, \phpbb\notification\manager $notification_manager){
		$this->db = $db;
		$this->notification_manager = $notification_manager;
		parent::__construct($user);
	}
	protected function configure(){
		$this->user->add_lang_ext('gamebuino/archives','cli');
		$this
			->setName('archive:notify')
			->setDescription($this->user->lang('ARCHIVES_CLI_BUILD_FAILED'))
			->addArgument(
				'qid',
				InputArgument::REQUIRED,
				$this->user->lang('ARCHIVES_CLI_QID')
			)
			->addOption(
				'no-newline',
				null,
				InputOption::VALUE_NONE,
				$this->user->lang('CLI_CONFIG_PRINT_WITHOUT_NEWLINE')
			)
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$qid = $input->getArgument('qid');
		if(strpos($qid,'=') !== false){
			$qid = substr($qid,4);
		}
		$qid = (int)$qid;
		$res = $this->db->sql_query("SELECT q.`id`,q.`status`,q.`type`,q.`file` AS `fid`,f.`author` AS `uid`,f.`name` FROM `archive_queue` AS q INNER JOIN `archive_files` AS f ON q.`file` = f.`id` WHERE q.`id` = ".(int)$qid);
		$notification_type = '';
		if($d = $this->db->sql_fetchrow($res)){
			$data = array(
				'query_id' => (int)$d['id'],
				'user_id' => (int)$d['uid'],
				'file_id' => (int)$d['fid'],
				'file_name' => htmlspecialchars($d['name']),
				'time' => time()
			);
			var_dump($data);
			switch((int)$d['status']){
				case 0: // error!
					$notification_type = 'gamebuino.archives.notification.type.build_failed';
					break;
				case 3: // build successful
				case 4:
					$notification_type = 'gamebuino.archives.notification.type.build_success';
					break;
			}
		}
		$this->db->sql_freeresult($res);
		if($notification_type){
			$this->notification_manager->add_notifications($notification_type,$data);
		}
		$output->write("hello world");
		if(!$input->getOption('no-newline')){
			$output->write("\n");
		}
	}
}
