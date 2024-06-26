<?php

/**
 * Email User Notes
 *
 * This plugin allows you to add notes to emails.
 *
 * Author: Vadim Kalinnikov <moose@ylsoftware.com>
 *
 * @version 1.0
 * @license MIT License
 * @url https://github.com/moose-kazan/roundcube-plugin-email-user-notes
 * @package email_user_notes
 */

class email_user_notes extends rcube_plugin
{
	public $task = 'mail';
	private $rc;
	private $db;
	private $config;

	function init()
	{
		$this->rc = rcmail::get_instance();

		$this->config = $this->rc->config->get('email_user_notes');
		
		if (!$this->rc->config->get('message_show_email')) {
			die("Please enable \"message_show_email\" setting first!");
		}

		$this->include_script('email_user_notes.js');
		$this->include_stylesheet('email_user_notes.css');

		$this->add_hook('message_objects', array($this, 'message_objects'));
		$this->register_action('email_user_notes.save_note', array($this, 'save_note'));

	}

	function message_objects($args)
	{
		$message = $args['message'];

		$current_user_email = mb_strtolower($this->rc->get_user_email());
		$sender_email = mb_strtolower($message->sender['mailto']);

		$to_emails = rcube_mime::decode_address_list($message->get_header('to'));
		$to_email = array_shift($to_emails);
		$receiver_email = mb_strtolower($to_email['mailto']);

		$user_email = "";
		$user_id = null;

		//if ($current_user_email != $sender) {
		if ($message->folder == "INBOX") {
			$user_email = $sender_email;
			$user_id = $this->get_user_id($receiver_email);
		}
		else {
			$user_email = $receiver_email;
			$user_id = $this->get_user_id($sender_email);
		}

		if (!$user_id) {
			$user_id = $this->rc->get_user_id();
		}



		$note = $this->get_note($user_id, $user_email);

		$content = $args['content'];

		$div = '
		<div>
			<div class="email_user_note" data-user-id="' . htmlspecialchars($user_id) . '" data-user-email="'.htmlspecialchars($user_email).'">
				<div class="icon"></div>
				<div class="content">
					<div class="text">'.$note.'</div>
				</div>
				<div class="edit">
					<svg xmlns="http://www.w3.org/2000/svg" height="16" width="16" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2023 Fonticons, Inc.--><path d="M471.6 21.7c-21.9-21.9-57.3-21.9-79.2 0L362.3 51.7l97.9 97.9 30.1-30.1c21.9-21.9 21.9-57.3 0-79.2L471.6 21.7zm-299.2 220c-6.1 6.1-10.8 13.6-13.5 21.9l-29.6 88.8c-2.9 8.6-.6 18.1 5.8 24.6s15.9 8.7 24.6 5.8l88.8-29.6c8.2-2.7 15.7-7.4 21.9-13.5L437.7 172.3 339.7 74.3 172.4 241.7zM96 64C43 64 0 107 0 160V416c0 53 43 96 96 96H352c53 0 96-43 96-96V320c0-17.7-14.3-32-32-32s-32 14.3-32 32v96c0 17.7-14.3 32-32 32H96c-17.7 0-32-14.3-32-32V160c0-17.7 14.3-32 32-32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96z"/></svg>
				</div>
			</div>
		</div>';

		array_push($content, $div);

		return ['content' => $content];
	}

	public function get_user_id($user_email)
	{
		$db = $this->get_dbh();

		$result = $db->query('select user_id from identities where del<>1 and standard = 1 and email = ?', $user_email);
		$row = $db->fetch_assoc($result);

		if (!$row)
			return null;
		return $row['user_id'];
	}

	public function get_note($user_id, $user_email)
	{
		$db = $this->get_dbh();

		$result = $db->query('select note from email_user_notes where user_id = ? and user_email = ?', $user_id, $user_email);
		$row = $db->fetch_assoc($result);

		if(!$row)
			return null;
		
		return $row['note'];
	}

	public function save_note()
	{
		//$user_id = $this->rc->get_user_id();
		$user_id = rcube_utils::get_input_value('user_id', rcube_utils::INPUT_GPC);
		$user_email = rcube_utils::get_input_value('user_email', rcube_utils::INPUT_GPC);
		$note = rcube_utils::get_input_value('note', rcube_utils::INPUT_GPC);

		$this->get_dbh()->query('delete from email_user_notes where user_id = ? and user_email = ?', $user_id, $user_email);
		$this->get_dbh()->query('insert into email_user_notes (user_id, user_email, note) values (?, ?, ?)', $user_id, $user_email, $note);
	}

	function get_dbh(): rcube_db
	{
		if (!isset($this->db)) {
			$this->db = $this->rc->get_dbh();
		}

		$this->db->query('CREATE TABLE IF NOT EXISTS email_user_notes (
			user_id int,
			user_email VARCHAR(1000),
			note TEXT
		);
		');

		return $this->db;
	}
}
