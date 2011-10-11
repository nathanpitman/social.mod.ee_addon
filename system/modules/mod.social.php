<?php

class Social {

    var $return_data = '';
	var $sentcheck = false;
	var $deletemulticheck = false;
	var $deletesingularcheck = false;
	var $prefs;
	var $searchmembers;
	var $sorted_members = array();

	//disallowed recommendation words
	var $ignored_words = array('the', 'and', 'or', 'out', 'my', 'you');

    function Social() {
		global $LANG, $SESS, $DB;
		$LANG->fetch_language_file('social');

		//get prefs
		$sql = "SELECT prefsJSON FROM exp_social_prefs";
		$query = $DB->query($sql);
		$prefs = json_decode($query->result[0]['prefsJSON'], true);
		$this->prefs = $prefs;
    }


    //public methods

    //message methods
    
	function messages() {
		global $TMPL, $LANG, $DB, $FNS, $LOC, $IN;

		//get parameters
		$member_id = $this->_get_member_id();
		$form_name = $TMPL->fetch_param('form_name');
		$form_class = $TMPL->fetch_param('form_class');
		$return = $TMPL->fetch_param('return');
		$pagination = $TMPL->fetch_param('pagination');
		$limit = $TMPL->fetch_param('limit');
		$lowerbound = $TMPL->fetch_param('lower_bound');
		$type = $TMPL->fetch_param('type');
		$recipient_id = $TMPL->fetch_param('recipient_id');

		//perform checks
		if ($member_id == '') {
			$this->return_data = $LANG->line('no_member_id');
			return $this->return_data;
		}
		if ($form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
			return $this->return_data;
		}
		if ($return == '') {
			$this->return_data = $LANG->line('no_return');
			return $this->return_data;
		}
		if ($pagination == '') {
			$pagination = false;
		}
		if ($pagination == true && $limit == '') {
			$limit = 10;
		}
		
		//lower bound is last url segment
		if ($lowerbound == 'last') {
			$lowerbound = $IN->QSTR;
			$lowerbound = str_replace('sent/', '', $lowerbound);
		}
		
		//sort out lowerbound (replace P)
		$lowerbound = str_replace('P', '', $lowerbound);
		if (!is_numeric($lowerbound) || $lowerbound == '') {
			$lowerbound = 0;
		}

		//sort out lowerbound (replace P)
		$lowerbound = str_replace('P', '', $lowerbound);

		//check for submission
		$this->_check_for_input($return);

		//get messages
		switch ($type) {
			default:
			case 'inbox':
				$sql = "SELECT * FROM exp_message_data, exp_message_copies
						WHERE exp_message_data.message_id = exp_message_copies.message_id
						AND exp_message_copies.recipient_id = '".$DB->escape_str($member_id)."'
						AND exp_message_copies.message_deleted = 'n'
						AND exp_message_copies.message_folder = 1
						ORDER BY exp_message_data.message_date DESC";
				break;
			case 'sent':
				$sql = "SELECT * FROM exp_message_data, exp_message_copies
						WHERE exp_message_data.message_id = exp_message_copies.message_id
						AND exp_message_copies.sender_id = '".$DB->escape_str($member_id)."'
						AND exp_message_copies.message_folder = 2
						ORDER BY exp_message_data.message_date DESC";
				break;
			case 'thread':
				$sql = "SELECT DISTINCT * FROM exp_message_data, exp_message_copies
						WHERE exp_message_data.message_id = exp_message_copies.message_id
						AND (
								(exp_message_copies.recipient_id = '".$DB->escape_str($recipient_id)."'
							  	 AND exp_message_copies.sender_id = '".$DB->escape_str($member_id)."'
							  	 AND exp_message_copies.message_folder = 2)
							OR
								(exp_message_copies.recipient_id = '".$DB->escape_str($member_id)."'
								 AND exp_message_copies.sender_id = '".$DB->escape_str($recipient_id)."'
								 AND exp_message_copies.message_folder = 1)
						)
						ORDER BY exp_message_data.message_date DESC";
				break;
		}
		
		if ($pagination == true) {
			$sql .= " LIMIT ".$lowerbound.", ".$limit;
		}
		elseif ($pagination == false && $limit != '') {
			$sql .= " LIMIT ".$limit;
		}
		$query = $DB->query($sql);

		//no messages
		if ($query->num_rows == 0) {
			$cond['no_messages'] = 1;
			$s = $FNS->prep_conditionals($TMPL->tagdata, $cond);

			//return
			$this->return_data = $s;
			return $this->return_data;
		}

		//messages
		$messages = $query->result;
		
		//remove duplicate thread messages
		/*if ($type == 'thread') {
			$i = 0;
			foreach ($messages as $key=>$message) {
				//woop! modulus!
				if ($i % 2 != 0) {
					unset($messages[$key]);
				}
				$i++;
			}
		}*/

		//if pagination, get all messages
		if ($pagination == true) {
			switch ($type) {
				default:
				case 'inbox':
					$sql = "SELECT * FROM exp_message_data, exp_message_copies
							WHERE exp_message_data.message_id = exp_message_copies.message_id
							AND exp_message_copies.recipient_id = '".$DB->escape_str($member_id)."'
							AND exp_message_copies.message_deleted = 'n'
							AND exp_message_copies.message_folder = 1";
					break;
				case 'sent':
					$sql = "SELECT * FROM exp_message_data, exp_message_copies
							WHERE exp_message_data.message_id = exp_message_copies.message_id
							AND exp_message_copies.sender_id = '".$DB->escape_str($member_id)."'
							AND exp_message_copies.message_folder = 2";
					break;
				case 'thread':
					$sql = "SELECT DISTINCT * FROM exp_message_data, exp_message_copies
							WHERE exp_message_data.message_id = exp_message_copies.message_id
							AND (
									(exp_message_copies.recipient_id = '".$DB->escape_str($recipient_id)."'
								  	 AND exp_message_copies.sender_id = '".$DB->escape_str($member_id)."'
								  	 AND exp_message_copies.message_folder = 2)
								OR
									(exp_message_copies.recipient_id = '".$DB->escape_str($member_id)."'
									 AND exp_message_copies.sender_id = '".$DB->escape_str($recipient_id)."'
									 AND exp_message_copies.message_folder = 1)
							)
							ORDER BY exp_message_data.message_date DESC";
					break;
			}
			$query = $DB->query($sql);
			$messagecount = $query->num_rows;
			
			//if thread, halve message count
			//if ($type == 'thread') {
				//$messagecount = $messagecount / 2;
				//$messagecount = round($messagecount);
			//}			
		}

		//get date formats and custom fields arrays
		$dateformats = $this->_get_date_formats_array();

		//check setting for messages
		$cond['accept_messages'] = (string) $this->_get_accept_message_val();
		$TMPL->tagdata = $FNS->prep_conditionals($TMPL->tagdata, $cond);

		//start form
		$s = '';
		$s .= '<form name="'.$form_name.'" class="'.$form_class.'" method="POST" action="'.$_SERVER['PHP_SELF'].'">';

		//perform swaps
		$swap = array();
		$count = 1;
		$tagdata = $s.$TMPL->tagdata;
		foreach ($TMPL->var_pair as $key=>$val) {
			if (ereg("^messages", $key)) {
				$s = '';
				preg_match("/".LD."$key".RD."(.*?)".LD.SLASH.'messages'.RD."/s", $TMPL->tagdata, $matches);
				foreach ($messages as $message) {
					$temp = $matches[1];

					//counts
					$swap['count'] = $count;

					//conditionals
					//unread conditional
					if ($message['message_read'] == 'n') {
						$cond['read'] = 0;
					}
					else {
						$cond['read'] = 1;
					}
					//replied conditional
					if ($message['message_status'] == 'replied') {
						$cond['replied'] = 1;
					}
					else {
						$cond['replied'] = 0;
					}
					$cond['no_messages'] = 0;
					//member id conditional
					if ($type == 'inbox') {
						if ($message['sender_id'] == 0) {
							$cond['sender_id'] = 0;
						}
						else {
							$cond['sender_id'] = $message['sender_id'];
						}						
					}
					elseif ($type == 'sent') {
						if ($message['recipient_id'] == 0) {
							$cond['recipient_id'] = 0;
						}
						else {
							$cond['recipient_id'] = $message['recipient_id'];
						}						
					}
					
					//date swaps
					$message['message_date_alt'] = $message['message_date'];					
					foreach ($dateformats as $tag=>$date) {						
						$swap[$date['key']] = $LOC->decode_date($date['format'], $message[$tag]);
					}
					
					//message swaps
					foreach ($message as $tag=>$value) {
						$swap[$tag] = $value;
					}

					//message swaps
					$swap['message_id'] = $message['message_id'];
					$swap['subject'] = $message['message_subject'];
					$swap['body'] = $message['message_body'];
					if($message['sender_id']==0) {
						$swap['sender'] = "Webmaster";
					} else {
						$swap['sender'] = $this->_get_forename($message['sender_id']).' '.$this->_get_surname($message['sender_id']);
						if ($swap['sender'] == ' ') {
							$swap['sender'] = $LANG->line('member_unknown');
						}
					}
					$swap['recipient'] = $this->_get_forename($message['recipient_id']).' '.$this->_get_surname($message['recipient_id']);
					if ($swap['recipient'] == ' ') {
						$swap['recipient'] = $LANG->line('member_unknown');
					}
					$swap['sender_id'] = $message['sender_id'];
					$swap['recipient_id'] = $message['recipient_id'];
					$swap['checkbox'] = '<input type="checkbox" value="'.$message['message_id'].'" class="deleteCheckbox" name="deleteMultiCheckbox[]" />';

					$s .= $FNS->var_swap($temp, $swap);
		  			$s = $FNS->prep_conditionals($s, $cond);
		  			$count++;
				}
				
				//do loop swap
				$tagdata = preg_replace("/".LD.'messages'.RD."(.*?)".LD.SLASH.'messages'.RD."/s", $s, $tagdata);
			}
			//pagination
			elseif (ereg("^paginate", $key) && $pagination == true) {
				preg_match("/".LD."$key".RD."(.*?)".LD.SLASH.'paginate'.RD."/s", $TMPL->tagdata, $matches);
				$temp = $matches[1];
				
				//current page
				$swap['current_page'] = $lowerbound/$limit + 1;
				
				//total pages
				$swap['total_pages'] = ceil($messagecount/$limit);
				
				//first link
				if ($lowerbound > 0 && $lowerbound >= $limit) {
					$url = $_SERVER['REQUEST_URI'];
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);
					$url = preg_replace('%/P([0-9]+)$%', '', $url);
					$first_link = '<a href="'.$url.'">&laquo;&nbsp;First</a>';
				}
				else {
					$first_link = '';
				}
				
				//previous link
				if ($lowerbound > 0 && $lowerbound >= $limit) {
					$url = $_SERVER['REQUEST_URI'];
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);
					$url = preg_replace('%([0-9]+)$%', $lowerbound-$limit, $url);
					$url = str_replace('/P0', '', $url);
					$page_left = '<a href="'.$url.'">&lt;</a>';
				}
				else {
					$page_left = '';
				}
				
				//pages_links
				$i = 1;
				$pages = array();
				$pages[] =  '<strong>'.$swap['current_page'].'</strong>';
				while ($i <= 4) {
					//create url
					$url = $_SERVER['REQUEST_URI'];
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);
					$url = preg_replace('%\/P([0-9]+)$%', '', $url);
					$s = ((($i * $limit) + ($limit * $swap['current_page'])) - $limit);
					$url .= '/P'.$s;
					
					//create link
					$next = $swap['current_page'] + $i;
					$page = '<a href="'.$url.'">'.$next.'</a>';
					
					//add to array					
					if ($s <= ($limit * ($swap['total_pages'] - 1)))	{
						$pages[] = $page;
					}
					
					$i++;
				}
				$page_links = implode('&nbsp;&nbsp;', $pages);
				
				//next link
				if (($lowerbound + $limit) < $messagecount) {
					preg_match('%(/P[0-9]+)$%', $_SERVER['REQUEST_URI'], $matches);
					if (count($matches) == 0) {						
						$url = preg_replace('%(/)$%', '', $_SERVER['REQUEST_URI']);
						$url .= '/P'.$limit;
					}
					else {
						//replace trailing slash
						$url = $_SERVER['REQUEST_URI'];
						$url = preg_replace('%\/$%', '', $url);
						$url = preg_replace('%([0-9]+)$%', $lowerbound+$limit, $_SERVER['REQUEST_URI']);
					}
					$page_right = '<a href="'.$url.'">&gt;</a>';
				}
				else {
					$page_right = '';
				}
				
				//last link
				if ($swap['current_page'] != $swap['total_pages']) {
					$url = $_SERVER['REQUEST_URI'];
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);	
					$url = preg_replace('%\/P([0-9]+)$%', '', $url);
					$url .= '/P'.($limit * ($swap['total_pages'] - 1));
					$last_link = '<a href="'.$url.'">Last&nbsp;&raquo;</a>';
				}
				else {
					$last_link = '';
				}
				
				//compose pagination_links
				$swap['pagination_links'] = $first_link.'&nbsp;&nbsp;'.$page_left.'&nbsp;&nbsp;'.$page_links.'&nbsp;&nbsp;'.$page_right.'&nbsp;&nbsp;'.$last_link;
				
				//do var swap
				$s = $FNS->var_swap($temp, $swap);
				
				//do loop swap
				$tagdata = preg_replace("/".LD.'paginate'.RD."(.*?)".LD.SLASH.'paginate'.RD."/s", $s, $tagdata);
			}			
		}

		//finish form
		$this->return_data = $tagdata;
		$this->return_data .= '<input type="hidden" name="deleteMultiCheck" value="true" />';
		switch ($type) {
			case 'inbox':
			default:
				$this->return_data .= '<input type="hidden" name="deleteType" value="inbox" />';
				break;
			case 'sent':
				$this->return_data .= '<input type="hidden" name="deleteType" value="sent" />';
				break;
		}
		$this->return_data .= '<input type="hidden" name="recipient_id" value="'.$member_id.'" />';
		$this->return_data .= '</form>';

		//return
		return $this->return_data;
	}

	function message_detail() {
		global $DB, $TMPL, $FNS, $LANG, $LOC;

		//instantiate typography class
		if (!class_exists('Typography')) {
			require PATH_CORE.'core.typography'.EXT;
		}
		$TYPE = new Typography;

		//get parameters
		$member_id = $this->_get_member_id();
		$message_id = $TMPL->fetch_param('message_id');
		$delete_form_name = $TMPL->fetch_param('delete_form_name');
		$delete_form_class = $TMPL->fetch_param('delete_form_class');
		$reply_form_name = $TMPL->fetch_param('reply_form_name');
		$reply_form_class = $TMPL->fetch_param('reply_form_class');
		$delete_return = $TMPL->fetch_param('delete_return');
		$compose_url = $TMPL->fetch_param('compose_url');

		//perform checks
		if ($member_id == '') {
			$this->return_data = $LANG->line('no_member_id');
			return $this->return_data;
		}
		if ($message_id == '') {
			$this->return_data = $LANG->line('no_message_id');
			return $this->return_data;
		}
		if ($delete_form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($delete_form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
			return $this->return_data;
		}
		if ($reply_form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($reply_form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
			return $this->return_data;
		}
		if ($delete_return == '') {
			$this->return_data = $LANG->line('no_return');
			return $this->return_data;
		}
		if ($compose_url == '') {
			$this->return_data = $LANG->line('no_reply_return');
			return $this->return_data;
		}

		//check for submission
		$this->_check_for_input($delete_return);

		//get message
		$sql = "SELECT * FROM exp_message_data, exp_message_copies
				WHERE exp_message_data.message_id = exp_message_copies.message_id
				AND exp_message_data.message_id = '".$DB->escape_str($message_id)."'
				AND (exp_message_copies.sender_id = '".$DB->escape_str($member_id)."'
					 OR exp_message_copies.recipient_id = '".$DB->escape_str($member_id)."')
				AND exp_message_copies.message_deleted = 'n'";
		$query = $DB->query($sql);
		if ($query->num_rows < 1) {
			$this->return_data = $LANG->line('message_not_found');
			return $this->return_data;
		}

		//message
		$message = $query->result[0];
		
		//get date formats and custom fields arrays
		$dateformats = $this->_get_date_formats_array();

		//mark as read
		$data['message_read'] = 'y';
		$data['message_time_read'] = date('U');
		$sql = $DB->update_string('exp_message_copies', $data, "message_id = '".$message_id."'");
		$DB->query($sql);

		//delete form
		$deleteForm = '';
		$deleteForm .= '<form name="'.$delete_form_name.'" class="'.$delete_form_class.'" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		$deleteForm .= '<input type="hidden" name="recipient_id" value="'.$member_id.'" />';
		$deleteForm .= '<input type="hidden" name="message_id" value="'.$message['message_id'].'" />';
		$deleteForm .= '<input type="submit" value="'.$LANG->line('delete').'" name="deleteSingularCheck" />';
		$deleteForm .= '</form>';

		//reply form
		$replyForm = '';
		$replyForm .= '<form name="'.$reply_form_name.'" class="'.$reply_form_class.'" method="POST" action="'.$compose_url.$message['sender_id'].'">';
		$replyForm .= '<input type="hidden" name="message_body" value="'.$message['message_body'].'" />';
		$replyForm .= '<input type="hidden" name="message_subject" value="'.$message['message_subject'].'" />';
		$replyForm .= '<input type="hidden" name="message_id" value="'.$message['message_id'].'" />';
		$replyForm .= '<input type="submit" value="'.$LANG->line('reply').'" name="replyCheck" />';
		$replyForm .= '</form>';

		//check setting for messages
		$cond['accept_messages'] = (string) $this->_get_accept_message_val();
		$TMPL->tagdata = $FNS->prep_conditionals($TMPL->tagdata, $cond);

		//do swaps
		$s = $TMPL->tagdata;
		//$swap['sender'] = $this->_get_sender_name($message['sender_id']);
		if($message['sender_id']==0) {
			$swap['sender'] = "Webmaster";
		} else {
			$swap['sender'] = $this->_get_forename($message['sender_id']).' '.$this->_get_surname($message['sender_id']);
			if ($swap['sender'] == ' ') {
				$swap['sender'] = $LANG->line('member_unknown');
			}
		}
		
		
		//$swap['sender'] = ($this->_get_forename($message['sender_id'])." ".$this->_get_surname($message['sender_id']));
		$swap['sender_id'] = $message['sender_id'];
		$swap['recipient'] = $this->_get_sender_name($message['recipient_id']);
		$swap['recipient_id'] = $message['recipient_id'];
		$swap['subject'] = $message['message_subject'];
		$swap['body'] = $TYPE->parse_type(stripslashes($message['message_body']), array('text_format'=>'xhtml', 'html_format'=>'safe', 'auto_links'=>'y', 'allow_img_url'=>'n'));
		$swap['reply_form'] = $replyForm;
		$swap['delete_form'] = $deleteForm;

		//date swaps
		foreach ($dateformats as $tag=>$date) {
			$swap[$date['key']] = $LOC->decode_date($date['format'], $message[$tag]);
		}
		
		$s = $FNS->var_swap($s, $swap);

		//return
		$this->return_data = $s;
		return $this->return_data;
	}

	public function message_compose() {
		global $TMPL, $LANG, $FNS;

		//get parameters
		$form_name = $TMPL->fetch_param('form_name');
		$form_class = $TMPL->fetch_param('form_class');
		$return = $TMPL->fetch_param('return');
		$to = $TMPL->fetch_param('to');
		$notification = $TMPL->fetch_param('notification');

		//perform checks
		if ($form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
			return $this->return_data;
		}
		if ($return == '') {
			$this->return_data = $LANG->line('no_return');
			return $this->return_data;
		}
		if ($to == '' && (empty($_POST['to']) || $_POST['to'] == '')) {
			$this->return_data = $LANG->line('no_recipient');
			return $this->return_data;
		}
		if ($notification == '') {
			$notification = "false";
		}
		if (isset($_POST['to']) && !empty($_POST['to'])) {
			$to = $_POST['to'];
		}

		//check for submitted form
		$this->_check_for_input($return, $notification);

		//check setting for messages
		$cond['accept_messages'] = (string) $this->_get_accept_message_val();
		$TMPL->tagdata = $FNS->prep_conditionals($TMPL->tagdata, $cond);

		//create form
		$tagdata = '<form name="'.$form_name.'" class="'.$form_class.'"  method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		$tagdata .= $TMPL->tagdata;
		//$tagdata .= '<input type="hidden" name="to" value="'.$to.'" />';

		//mark as potentiall replied id
		$mark_as_replied_id = '';
		if (isset($_POST['message_id'])) {
			$mark_as_replied_id = $_POST['message_id'];
		}
		$tagdata .= '<input type="hidden" name="mark_me_replied" value="'.$mark_as_replied_id.'" />';

		//finish form
		$tagdata .= '<input type="hidden" name="sentCheck" value="true" />';
		$tagdata .= '</form>';

		//perform swaps
		$swap['message_subject'] = '';
		if (isset($_POST['message_subject'])) {
			$swap['message_subject'] = 'RE: '.$_POST['message_subject'];
			unset($_POST['message_subject']);
		}
		$swap['message_body'] = '';
		if (isset($_POST['message_body'])) {
			$swap['message_body'] = "\n\n\n--\n[quote]".$_POST['message_body']."[/quote]";
			unset($_POST['message_body']);
		}
		$tagdata = $FNS->var_swap($tagdata, $swap);

		//return form
		$this->return_data = $tagdata;
		return $this->return_data;
	}

	public function messages_unread() {
		global $DB, $LANG, $IN;
		

		//get parameters
		$member_id = $this->_get_member_id();

		//perform checks
		if ($member_id == '') {
			return $LANG->line('no_member_id');
		}
		
		//attempt to mark message if read if user is currently looking at a message (only relevant for PP)
		$s = $IN->fetch_uri_segment('1');
		$t = $IN->fetch_uri_segment('2');
		$u = $IN->fetch_uri_segment('3');
		$id = $IN->fetch_uri_segment('4');
		
		if ($s == 'member' && $t == 'messages' && $u == 'message' && is_numeric($id)) {
			//mark message read
			$data['message_read'] = 'y';
			$data['message_time_read'] = date('U');
			$sql = $DB->update_string('exp_message_copies', $data, "message_id = '".$id."'");
			$DB->query($sql);
		}

		//get unread count
		$sql = "SELECT * FROM exp_message_copies
				WHERE recipient_id = '".$DB->escape_str($member_id)."'
				AND message_read = 'n'
				AND message_folder = '1'
				AND message_deleted ='n'";
		$query = $DB->query($sql);

		//return
		return $query->num_rows;
	}

	public function message_detail_rel() {
		global $DB, $TMPL, $LANG, $IN;

		//get parameters
		$urlsegments = $IN->SEGS;
		$message_id = end($urlsegments);
		
		//get possible weblog
		$weblog = $TMPL->fetch_param('weblog');
		if ($weblog != '') {
			//get weblog id
			$sql = "SELECT weblog_id FROM exp_weblogs
					WHERE blog_name = '".$weblog."'";
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				$weblog_id = $query->result[0]['weblog_id'];
			}
		}

		//perform checks
		if ($message_id == '') {
			$this->return_data = $LANG->line('no_message_id');
			return $this->return_data;
		}

		//get message
		$sql = "SELECT * FROM exp_message_data, exp_message_copies
				WHERE exp_message_data.message_id = exp_message_copies.message_id
				AND exp_message_data.message_id = '".$DB->escape_str($message_id)."'
				AND exp_message_copies.message_deleted = 'n'";
		$query = $DB->query($sql);
		if ($query->num_rows < 1) {
			$this->return_data = $LANG->line('message_not_found');
			return $this->return_data;
		}

		//message
		$message = $query->result[0];
		$subject = $message['message_subject'];
		$body = $message['message_body'];

		//get words
		$words = array();
		//message subject
		$subject = explode(' ', $subject);
		foreach ($subject as $word) {
			$words[] = strtolower($word);
		}
		//message body
		$body = explode(' ', $body);
		foreach ($body as $word) {
			$words[] = strtolower($word);
		}

		//remove recurring words
		$words = array_unique($words);

		//remove numerical words
		foreach ($words as $key=>$word) {
			if (is_numeric($word)) {
				unset($words[$key]);
			}
		}

		//remove words that are too short
		foreach ($words as $key=>$word) {
			if (strlen($word) < 3) {
				unset($words[$key]);
			}
		}

		//remove ignored words
		$words = array_diff($words, $this->ignored_words);

		$entry_ids = array();
		//find relevant entry ids
		foreach ($words as $word) {
			$word = '%'.$DB->escape_str($word).'%';
			$sql = "SELECT entry_id FROM exp_weblog_titles
					WHERE title LIKE '".$word."'";
			if (isset($weblog_id)) {
				$sql .= " AND weblog_id = '".$weblog_id."'";
			}
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				foreach ($query->result as $entry) {
					if (!in_array($entry['entry_id'], $entry_ids)) {
						$entry_ids[] = $entry['entry_id'];
					}
				}
			}
		}

		//return
		$entry_ids = implode('|', $entry_ids);
		$this->return_data = (string) $entry_ids;
		return $this->return_data;
	}

	public function messages_rel() {
		global $DB, $LANG, $IN, $TMPL;

		//get member_id
		$member_id = $this->_get_member_id();
		
		//get possible weblog
		$weblog = $TMPL->fetch_param('weblog');
		if ($weblog != '') {
			//get weblog id
			$sql = "SELECT weblog_id FROM exp_weblogs
					WHERE blog_name = '".$weblog."'";
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				$weblog_id = $query->result[0]['weblog_id'];
			}
		}

		//get all inbox messages
		$sql = "SELECT * FROM exp_message_data, exp_message_copies
				WHERE exp_message_data.message_id = exp_message_copies.message_id
				AND exp_message_copies.recipient_id = '".$DB->escape_str($member_id)."'
				AND exp_message_copies.message_deleted = 'n'
				AND exp_message_copies.message_folder = 1
				ORDER BY exp_message_data.message_date DESC";
		$query = $DB->query($sql);

		$words = array();
		//for each message, get words and add to array
		if ($query->num_rows > 0) {
			foreach ($query->result as $message) {
				$subject = $message['message_subject'];
				$subject = explode(' ', $subject);
				foreach ($subject as $word) {
					$words[] = strtolower($word);
				}
			}
		}

		//remove recurring words
		$words = array_unique($words);

		//remove numerical words
		foreach ($words as $key=>$word) {
			if (is_numeric($word)) {
				unset($words[$key]);
			}
		}

		//remove words that are too short
		foreach ($words as $key=>$word) {
			if (strlen($word) < 3) {
				unset($words[$key]);
			}
		}

		//remove ignored words
		$words = array_diff($words, $this->ignored_words);

		$entry_ids = array();
		//find relevant entry ids
		foreach ($words as $word) {
			$word = '%'.$DB->escape_str($word).'%';
			$sql = "SELECT entry_id FROM exp_weblog_titles
					WHERE title LIKE '".$word."'";
			if (isset($weblog_id)) {
				$sql .= " AND weblog_id = '".$weblog_id."'";
			}
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				foreach ($query->result as $entry) {
					if (!in_array($entry['entry_id'], $entry_ids)) {
						$entry_ids[] = $entry['entry_id'];
					}
				}
			}
		}

		//return
		$entry_ids = implode('|', $entry_ids);
		$this->return_data = (string) $entry_ids;
		return $this->return_data;
	}
	
	
	//following methods
	
	public function members() {
		global $DB, $TMPL, $FNS, $LOC, $PREFS;
		
		//disable caching
		$DB->enable_cache(FALSE);
		
		//get parameters
		$order_by = $TMPL->fetch_param('order_by');
		$sort = $TMPL->fetch_param('sort');
		$group_id = $TMPL->fetch_param('group_id');
		$type = $TMPL->fetch_param('type');
		$pagination = $TMPL->fetch_param('pagination');
		$limit = $TMPL->fetch_param('limit');
		$lowerbound = $TMPL->fetch_param('lower_bound');
		$weighting = $TMPL->fetch_param('weighting');
		$contributions_weblog_id = $TMPL->fetch_param('contributions_weblog_id');
		$contributions_status = $TMPL->fetch_param('contributions_status');

		//perform checks
		if ($order_by == '') {
			$order_by = 'screen_name';
		}		
		if ($sort == '') {
			$sort = 'asc';
		}
		if ($group_id == '') {
			$group_id = '';
		}
		else {
			$group_id = explode('|', $group_id);
			foreach ($group_id as $val) {
				$group_ids[] = "exp_members.group_id = '".$val."'";
			}
		}
		if ($type == '') {
			$type = 'list';
		}
		if ($pagination == '') {
			$pagination = false;
		}
		if ($pagination == true && $limit == '') {
			$limit = 10;
		}
		//if weighting params is set, unset order by
		if ($weighting == 'true') {
			$pagination = false;
			$limit = false;
			$sort = false;
			$pagination_reset = true;
		}
		
		//sort out lowerbound (replace P)
		$lowerbound = str_replace('P', '', $lowerbound);
		if (!is_numeric($lowerbound) || $lowerbound == '') {
			$lowerbound = 0;
		}
		
		//sort out lowerbound (replace P)
		$lowerbound = str_replace('P', '', $lowerbound);
		
		//if order by profile completeness, get custom field id
		if (isset($order_by) && $order_by == 'profile_completeness') {
			$sql = "SELECT m_field_id FROM exp_member_fields
					WHERE m_field_name = 'profile_completeness'";
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				$profile_completeness_id = $query->result[0]['m_field_id'];
			}
		}
		
		//simple/advanced search switch
		if (isset($_POST['advanced_search'])) {
			$cond['advanced_search'] = (string) $_POST['advanced_search'];
		}
		
		//create sql and get members
		$members = array();
		switch($type) {
			case 'list':
				$sql = "SELECT * FROM exp_members, exp_member_data
						WHERE exp_members.member_id = exp_member_data.member_id";
				
				//group ids
				if (isset($group_ids) && is_array($group_ids)) {
					$sql .= " AND (".implode($group_ids, ' OR ').")";
				}
				
				//order by switch
				if ($order_by != '') {
					if ($order_by != 'profile_completeness') {
						$sql .= " ORDER BY ".$order_by;
					}
					elseif (isset($profile_completeness_id)) {
						$sql .= " ORDER BY exp_member_data.m_field_id_".$profile_completeness_id;
					}
				}
				
				//sort direction
				if ($order_by != '' && $sort != '' && $sort != false) {
					$sql .= " ".$sort;
				}
				
				//pagination
				if ($pagination == true) {
					$sql .= " LIMIT ".$lowerbound.", ".$limit;
				}
				elseif ($pagination == false && $limit != '' && $limit != false) {
					$sql .= " LIMIT ".$limit;
				}

				//do query
				$query = $DB->query($sql);				
				$members = $query->result;
				
				//if pagination, get all members
				if ($pagination == true || $pagination_reset == true) {
					$sql = "SELECT member_id FROM exp_members";

					//group ids
					if (isset($group_ids) && is_array($group_ids)) {
						$sql .= " WHERE (".implode($group_ids, ' OR ').")";
					}
					
					$query = $DB->query($sql);
					$membercount = $query->num_rows;
				}
				break;
			case 'following':
				$follower_id = $this->_get_member_id();
		
				$sql = "SELECT * FROM exp_members, exp_social_followers
						WHERE exp_social_followers.leader_id = exp_members.member_id
						AND exp_social_followers.follower_id = ".$follower_id;
						
				//group ids
				if (isset($group_ids) && is_array($group_ids)) {
					$sql .= " AND (".implode($group_ids, ' OR ').")";
				}
				
				//order by switch
				if ($order_by != '') {
					if ($order_by != 'profile_completeness') {
						$sql .= " ORDER BY ".$order_by;
					}
					elseif (isset($profile_completeness_id)) {
						$sql .= " ORDER BY exp_member_data.m_field_id_".$profile_completeness_id;
					}
				}
				
				//sort direction
				if ($order_by != '' && $sort != '') {
					$sql .= " ".$sort;
				}
				
				//pagination
				if ($pagination == true) {
					$sql .= " LIMIT ".$lowerbound.", ".$limit;
				}
				elseif ($pagination == false && $limit != '') {
					$sql .= " LIMIT ".$limit;
				}
				
				//do query
				$query = $DB->query($sql);
				$members = $query->result;
				
				//get member count for paging
				if ($pagination == true || (isset($pagination_reset) && $pagination_reset == true)) {
					$sql = "SELECT * FROM exp_members, exp_social_followers
							WHERE exp_social_followers.leader_id = exp_members.member_id
							AND exp_social_followers.follower_id = ".$follower_id;

					//group ids
					if (isset($group_ids) && is_array($group_ids)) {
						$sql .= " AND (".implode($group_ids, ' OR ').")";
					}
					
					$query = $DB->query($sql);
					$membercount = $query->num_rows;
				}
				break;
			case 'search_results':				
				if (isset($_POST)) {
					$swap['keywords'] = @$_POST['keywords'];
					$membercount = $this->_member_search_results($pagination);
					$members = $this->searchmembers;
					$swap['total_results'] = $membercount;
					$TMPL->tagdata = $FNS->var_swap($TMPL->tagdata, $swap);
					$encsearch = base64_encode(serialize($_POST));
				}				
				break;			
		}

		//no members
		if (count($members) == 0) {
			$cond['no_members'] = true;
			$s = $FNS->prep_conditionals($TMPL->tagdata, $cond);

			//return
			$this->return_data = $s;
			return $this->return_data;
		}
		
		//do weighting sort if specified
		if ($weighting == 'true') {			
			
			//attempt to retrieve pagination parameters again
			$pagination = $TMPL->fetch_param('pagination');
			$limit = $TMPL->fetch_param('limit');
			$lowerbound = $TMPL->fetch_param('lower_bound');
			$sort = $TMPL->fetch_param('sort');
			
			//do checks
			if ($pagination == '') {
				$pagination = false;
			}
			if ($pagination == true && $limit == '') {
				$limit = 10;
			}
			
			//sort lower bound
			$lowerbound = str_replace('P', '', $lowerbound);
			if (!is_numeric($lowerbound) || $lowerbound == '') {
				$lowerbound = 0;
			}
						
			//cast vars
			$lowerbound = (int) $lowerbound;
			$limit = (int) $limit;

			
			//sort members based on weights	
			$sorted_m = array();
			$last_m = array();
			foreach ($members as $member) {
				//get rank
				$rank = $this->_get_member_rank($member['member_id']);
				
				//get weighting
				$member['weighting'] = $this->_get_member_weighting($member['member_id']);
				
				//add to array
				if ($rank != 'Unknown' && $rank != '0') {
					$sorted_m[$rank] = $member;
				}
				else {
					$last_m[] = $member;
				}
			}
			
			foreach ($last_m as $member) {
				$sorted_m[] = $member;
			}
			
			//sort depending on direction
			if ($sort == 'desc') {
				ksort($sorted_m);
			}
			else if ($sort == 'asc') {
				krsort($sorted_m);
			}
			
			$members = $sorted_m;
			
			if ($pagination != false) {
				
				//splice array for pagination
				//$members = array_slice($members, $lowerbound, $limit+$lowerbound);
				$members = array_slice($members, $lowerbound, $limit);
			}
		}
		
		//get date formats and custom fields arrays
		$dateformats = $this->_get_date_formats_array();
		$custom_fields = $this->_get_custom_fields_array();
		
		// get photo and avatar urls from prefs
		$photo_url = $PREFS->ini('photo_url', TRUE);
		$avatar_url	= $PREFS->ini('avatar_url', TRUE);

		//check setting for messages
		$cond['accept_messages'] = (string) $this->_get_accept_message_val();
		$TMPL->tagdata = $FNS->prep_conditionals($TMPL->tagdata, $cond);
		
		//output members
		//perform swaps
		$s = '';
		$swap = array();
		$count = 1;
		$tagdata = $TMPL->tagdata;
		foreach ($TMPL->var_pair as $key=>$val) {
			if (ereg("^members", $key)) {
				preg_match("/".LD."$key".RD."(.*?)".LD.SLASH.'members'.RD."/s", $TMPL->tagdata, $matches);
				foreach ($members as $k=>$member) {
					$temp = $matches[1];
			
					//member conditionals
					$cond['no_members'] = false;
					$cond['following'] = $this->_check_if_following($member['member_id']);
					$cond['following_and_followed'] = $this->_check_if_mutual($member['member_id'], $this->_get_member_id());
					$cond['member_id'] = $member['member_id'];
					
					//some quick debugging
					//echo $member['member_id'].':'.$this->_get_member_id().' '.$cond['following_and_followed'].'<br/>';
						
					//date swaps
					foreach ($dateformats as $tag=>$date) {
						$swap[$date['key']] = $LOC->decode_date($date['format'], $member[$tag]);
					}
					
					//custom field swaps
					foreach ($custom_fields as $fieldname=>$fieldid) {
						$swap[$fieldname] = $this->_get_custom_field_value($fieldid, $member['member_id']); 
					}
					
					//member swaps
					foreach ($member as $tag=>$value) {
						$swap[$tag] = $value;
					}
					
					//attempt to get forname/surname
					$swap['forename'] = $this->_get_forename($member['member_id']);
					$swap['surname'] = $this->_get_surname($member['member_id']);
					
					//other swaps
					$swap['count'] = $count;
					$swap['follower_count'] = $this->_get_follower_count($member['member_id']);
					
					if ($contributions_weblog_id!="" && $contributions_status!="") {
					$swap['weblog_entry_count'] = $this->_get_entries_count($member['member_id'], $contributions_weblog_id, $contributions_status);
					}
					
					$swap['photo_url'] = $photo_url;
					$swap['avatar_url'] = $avatar_url;
					
					//weighting
					if ($weighting == 'true') {
						$swap['weighting'] = $member['weighting'];
					}
					else {
						$swap['weighting'] = '';
					}
					
					$swap['rank'] = $this->_get_member_rank($member['member_id']);
					//if ($swap['rank'] == 0) {
						//$swap['rank'] = $count;
					//}
					
					$s .= $FNS->var_swap($temp, $swap);
		  			$s = $FNS->prep_conditionals($s, $cond);
		  			$count++;
				}
				
				//finish members swaps
				$tagdata = preg_replace("/".LD.'members'.RD."(.*?)".LD.SLASH.'members'.RD."/s", $s, $tagdata);
			}
			//pagination
			elseif (ereg("^paginate", $key) && $pagination == true) {
				preg_match("/".LD."$key".RD."(.*?)".LD.SLASH.'paginate'.RD."/s", $TMPL->tagdata, $matches);
				$temp = $matches[1];
				
				//remove encsearch GET request if required
				$_SERVER['REQUEST_URI'] = preg_replace('%\?encsearch=([A-Za-z0-9]+)$%', '', $_SERVER['REQUEST_URI']);
				
				//current page
				$swap['current_page'] = $lowerbound/$limit + 1;
				
				//total pages
				$swap['total_pages'] = ceil($membercount/$limit);
				
				//first link
				if ($lowerbound > 0 && $lowerbound >= $limit) {
					$url = $_SERVER['REQUEST_URI'];
					
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);
					$url = preg_replace('%/P([0-9]+)$%', '', $url);
					
					//encode search if required
					if (isset($encsearch)) {
						$url .= '?encsearch='.$encsearch;
					}
					
					$first_link = '<a href="'.$url.'">&laquo;&nbsp;First</a>';
				}
				else {
					$first_link = '';
				}
				
				//previous link
				if ($lowerbound > 0 && $lowerbound >= $limit) {
					$url = $_SERVER['REQUEST_URI'];
					
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);
					$url = preg_replace('%([0-9]+)$%', $lowerbound-$limit, $url);
					$url = str_replace('/P0', '', $url);
					
					//encode search if required
					if (isset($encsearch)) {
						$url .= '?encsearch='.$encsearch;
					}
					
					$page_left = '<a href="'.$url.'">&lt;</a>';
				}
				else {
					$page_left = '';
				}
				
				//pages_links
				$i = 1;
				$pages = array();
				
				//add current page if there are more than 1 pages
				if ($swap['total_pages'] != '1') {
					$pages[] =  '<strong>'.$swap['current_page'].'</strong>';
				}
				
				while ($i <= 4) {
					//create url
					$url = $_SERVER['REQUEST_URI'];
					
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);
					$url = preg_replace('%\/P([0-9]+)$%', '', $url);
					$s = ((($i * $limit) + ($limit * $swap['current_page'])) - $limit);
					$url .= '/P'.$s;
					
					//encode search if required
					if (isset($encsearch)) {
						$url .= '?encsearch='.$encsearch;
					}
					
					//create link
					$next = $swap['current_page'] + $i;
					$page = '<a href="'.$url.'">'.$next.'</a>';
					
					//add to array					
					if ($s <= ($limit * ($swap['total_pages'] - 1)))	{
						$pages[] = $page;
					}
					
					$i++;
				}
				$page_links = implode('&nbsp;&nbsp;', $pages);
				
				//next link
				if (($lowerbound + $limit) < $membercount) {
					preg_match('%(/P[0-9]+)$%', $_SERVER['REQUEST_URI'], $matches);
					if (count($matches) == 0) {						
						$url = preg_replace('%(/)$%', '', $_SERVER['REQUEST_URI']);
						$url .= '/P'.$limit;
					}
					else {
						//replace trailing slash
						$url = $_SERVER['REQUEST_URI'];
						$url = preg_replace('%\/$%', '', $url);
						$url = preg_replace('%([0-9]+)$%', $lowerbound+$limit, $_SERVER['REQUEST_URI']);
					}
					
					//encode search if required
					if (isset($encsearch)) {
						$url .= '?encsearch='.$encsearch;
					}
					
					$page_right = '<a href="'.$url.'">&gt;</a>';
				}
				else {
					$page_right = '';
				}
				
				//last link
				if ($swap['current_page'] != $swap['total_pages']) {
					$url = $_SERVER['REQUEST_URI'];
					
					//replace trailing slash
					$url = preg_replace('%\/$%', '', $url);	
					$url = preg_replace('%\/P([0-9]+)$%', '', $url);
					$url .= '/P'.($limit * ($swap['total_pages'] - 1));
					
					//encode search if required
					if (isset($encsearch)) {
						$url .= '?encsearch='.$encsearch;
					}
					
					$last_link = '<a href="'.$url.'">Last&nbsp;&raquo;</a>';
				}
				else {
					$last_link = '';
				}
				
				//compose pagination_links
				$swap['pagination_links'] = $first_link.'&nbsp;&nbsp;'.$page_left.'&nbsp;&nbsp;'.$page_links.'&nbsp;&nbsp;'.$page_right.'&nbsp;&nbsp;'.$last_link;
				
				//do var swap
				$s = $FNS->var_swap($temp, $swap);
				
				//do loop swap
				$tagdata = preg_replace("/".LD.'paginate'.RD."(.*?)".LD.SLASH.'paginate'.RD."/s", $s, $tagdata);
			}
		}
		
		$tagdata = $FNS->prep_conditionals($tagdata, $cond);		
		return $tagdata;
	}

	public function member_follow() {
		global $DB, $FNS, $TMPL, $LANG;
		
		//get parameters
		$leader_id = $TMPL->fetch_param('leader_id');
		$return = $TMPL->fetch_param('return');
		$form_name = $TMPL->fetch_param('form_name');
		$form_class = $TMPL->fetch_param('form_class');
		$email_template = $TMPL->fetch_param('email_template');
		$email_template_mutual = $TMPL->fetch_param('email_template_mutual');
		$button_follow = $TMPL->fetch_param('button_follow');
		$button_unfollow = $TMPL->fetch_param('button_unfollow');
		$button_class = $TMPL->fetch_param('button_class');

		//perform checks
		if ($leader_id == '') {
			$this->return_data = $LANG->line('no_member_id');
			return $this->return_data;
		}
		if ($return == '') {
			$this->return_data = $LANG->line('no_return');
			return $this->return_data;
		}
		if ($form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
			return $this->return_data;
		}
		
		if ($button_follow == '') {
			$button_follow = 'Add friend';
		}
		if ($button_unfollow == '') {
			$button_unfollow = 'Remove friend';
		}
		
		if (isset($_POST['submitValue'])) {
			//form has been submitted
			
			$follower_id = $this->_get_member_id();
			
			switch ($_POST['action']) {
				case 'follow':
					$sql = "INSERT INTO exp_social_followers(leader_id, follower_id) VALUES
							('".$leader_id."', '".$follower_id."')";
					$DB->query($sql);
					
					if ($this->_check_if_mutual($leader_id, $follower_id) == 'true') {
						$email_template = $email_template_mutual;
					}
					else {
						$email_template = $email_template;
					}
					
					//email new leader
					if ($email_template != '') {
						$this->_new_follower_email($leader_id, $follower_id, $email_template);
					}
					break;
				case 'unfollow':
					$sql = "DELETE FROM exp_social_followers
							WHERE leader_id = '".$leader_id."'
							AND follower_id = '".$follower_id."'";
					$DB->query($sql);					
					break;
			}
			
			//return
			$return = $FNS->create_url($return);
			$FNS->redirect($return);
		}
		else {
			//check setting for messages
			$cond['accept_messages'] = (string) $this->_get_accept_message_val();
			$TMPL->tagdata = $FNS->prep_conditionals($TMPL->tagdata, $cond);

			//form has not been submitted
			$s = '<form name="'.$form_name.'" class="'.$form_class.'" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
			$s .= $TMPL->tagdata;
			
			$cond['following'] = $this->_check_if_following($leader_id);
			if ($cond['following'] == false) {
				$swap['button'] = '<input type="hidden" name="action" value="follow" />';
				$swap['button'] .= '<input type="submit" class="'.$button_class.'" value="'.$button_follow.'" name="submitValue" />';
			}
			else {
				$swap['button'] = '<input type="hidden" name="action" value="unfollow" />';
				$swap['button'] .= '<input type="submit" class="'.$button_class.'" value="'.$button_unfollow.'" name="submitValue" />';				
			}
			$s .= '</form>';
			
			$s = $FNS->var_swap($s, $swap);
			$s = $FNS->prep_conditionals($s, $cond); 
			
			return $s;
		}
	}

	public function members_following_count($m_id = false) {
		global $DB, $TMPL;
	
		$follower_id = $TMPL->fetch_param('member_id');
		if ($follower_id == '') {
			$follower_id = false;
		}
			
		if ($m_id == false && $follower_id == false) {
			$follower_id = $this->_get_member_id();
		}
		elseif ($m_id != false && $follower_id == false) {
			$follower_id = $m_id;
		}
		
		$sql = "SELECT * FROM exp_social_followers, exp_members
				WHERE exp_members.member_id = exp_social_followers.leader_id
				AND exp_members.group_id != 4
				AND follower_id = '".$follower_id."'";
		$query = $DB->query($sql);
		
		return $query->num_rows;
	}
	
	public function members_follower_count($m_id = false) {
		global $DB, $TMPL;
	
		$leader_id = $TMPL->fetch_param('member_id');
		if ($leader_id == '') {
			$leader_id = false;
		}
			
		if ($m_id == false && $leader_id == false) {
			$leader_id = $this->_get_member_id();
		}
		elseif ($m_id != false && $leader_id == false) {
			$leader_id = $m_id;
		}
		
		$sql = "SELECT * FROM exp_social_followers
				WHERE leader_id = '".$leader_id."'";
		$query = $DB->query($sql);
		
		return $query->num_rows;
	}
	
	public function member_connections_count($m_id = false) {
		global $DB, $TMPL;
	
		$member_id = $TMPL->fetch_param('member_id');
		if ($member_id == '') {
			$member_id = false;
		}
			
		if ($m_id == false && $member_id == false) {
			$member_id = $this->_get_member_id();
		}
		elseif ($m_id != false && $member_id == false) {
			$member_id = $m_id;
		}
		
		$count = 0;
		
		$following_sql = "SELECT * FROM exp_social_followers
				WHERE leader_id = '".$member_id."'";
		$following = $DB->query($following_sql);
		if ($following->num_rows > 0) {
			foreach ($following->result as $row) {
				$followers_sql = "SELECT * FROM exp_social_followers
						WHERE leader_id = '".$row['follower_id']."'
						AND follower_id = '".$member_id."'";
				$followers = $DB->query($followers_sql);
				if ($followers->num_rows > 0) {
					$count++;
				}
			}
		}
		
		return $count;
	}
	
	public function member_valid() {
	// Returns the member id back to the template if the member id passed is valid
    	global $DB, $TMPL;
	   	$member_id = $TMPL->fetch_param('member_id');
	    if (is_numeric($member_id)) {
	    			$sql = "SELECT member_id FROM exp_members
					WHERE member_id = '".$member_id."'";
					$query = $DB->query($sql);
			if ($query->num_rows==1) {
				return $member_id;
			} else {
				return "";
			}
		} else {
    		return "";
	    }
	}
	

	//search methods
	
	public function member_search() {
		global $TMPL, $LANG, $DB, $FNS;
		
		//get parameters
		$return = $TMPL->fetch_param('return');
		$form_name = $TMPL->fetch_param('form_name');
		$form_id = $TMPL->fetch_param('form_id');
		$form_class = $TMPL->fetch_param('form_class');

		//perform checks
		if ($return == '') {
			$this->return_data = $LANG->line('no_return');
			return $this->return_data;
		}
		if ($form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($form_id == '') {
			$this->return_data = $LANG->line('no_form_id');
		}
		if ($form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
		}
		
		//create form
		$s = '';
		$s .= '<form name="'.$form_name.'" id="'.$form_id.'" class="'.$form_class.'" method="POST" action="/'.$return.'">';
		$s .= $TMPL->tagdata;
		$s .= '</form>';
		
		//replace select boxes
		foreach ($TMPL->var_pair as $key=>$val) {			
			if (ereg("^select", $key)) {
				preg_match("/".LD."$key".RD."(.*?)".LD.SLASH."$key".RD."/s", $TMPL->tagdata, $matches);
				$l = '';
				$tmp = $matches[1];
				$field = str_replace('select_', '', $key);
				$sql = "SELECT m_field_list_items FROM exp_member_fields
						WHERE m_field_name = '".$field."'";
				$query = $DB->query($sql);
				if ($query->num_rows > 0) {
					$items = $query->result[0]['m_field_list_items'];
					$items = explode("\n", $items);
					foreach ($items as $item) {
						$swap['value'] = $item;
						$l .= $FNS->var_swap($tmp, $swap);
					}
				}
				$s = preg_replace("/".LD."$key".RD."(.*?)".LD.SLASH."$key".RD."/s", $l, $s);
			}
		}
		
		//return
		$this->return_data = $s;
		return $this->return_data;
	}
	
	public function member_rank(){
		global $TMPL, $DB;
		
		//get parameters
		$weighting_params = $TMPL->fetch_param('weighting_params');
		$weighting_points = $TMPL->fetch_param('weighting_points');
		$credit_duration = $TMPL->fetch_param('credit_duration');
		$log = $TMPL->fetch_param('log');
	
		if ($weighting_params != '') {
			$weighting_params = explode('|', $weighting_params);
			$weighting_points = explode('|', $weighting_points);
		}
	
		//parameters
		//$weighting_params = array('weblog_entry_count', 'connections', 'profile_completeness', 'member_group');
		//$weighting_points = array('10', '2', '25', '25');
		//$credit_duration = 365;
			
		//check passed parameters
		//no arrays or count mismatch
		if (!is_array($weighting_params) || !is_array($weighting_points) || count($weighting_params) != count($weighting_points)) {
			return false;
		}
	
		//delete all from weighting table
		$sql = "DELETE FROM exp_social_ranks
				WHERE 1=1";
		$DB->query($sql);
	
		// get members from the relevant groups
		$sql = "SELECT * FROM exp_members, exp_member_data
				WHERE exp_members.member_id = exp_member_data.member_id
				AND (exp_members.group_id != '2'
				AND exp_members.group_id != '3'
				AND exp_members.group_id != '4')";
		$query = $DB->query($sql);
		$all_members = $query->result;
	
		//loop all members and recalculate weightings
		foreach ($all_members as $member) {
			$weighting = 0;
			$i = 0;
	
			//loop params
			foreach ($weighting_params as $param) {
			
				//switch on param count
				switch ($param) {
					
					//accepted content contributions
					case 'weblog_entry_count':
							
						//get weblog count
						$sql = "SELECT entry_id FROM exp_weblog_titles
								WHERE author_id = '".$member['member_id']."'
								AND status = 'open'
								AND weblog_id = '5'";
						$query = $DB->query($sql);
						$result = $query->result;
						$count = count($result);
							
						//add to weighting
						$weighting += $count * $weighting_points[$i];						
						break;
						
					//number of reciprocated connections
					case 'connections':
							
						//get follower count
						$count = 0;
			
						$sql = "SELECT * FROM exp_social_followers
								WHERE leader_id = '".$member['member_id']."'";
						$query = $DB->query($sql);
						$result = $query->result;
						
						//for each follower
						if (count($result) > 0) {
							foreach ($result as $r) {
							
								//check for reciprocation
								$sql = "SELECT * FROM exp_social_followers
										WHERE leader_id = '".$r['follower_id']."'
										AND follower_id = '".$member['member_id']."'";
								$query = $DB->query($sql);
								$s = $query->result;
								
								if (count($s) > 0) {
									$count++;
								}							
							}
						}
						
						$weighting += $count * $weighting_points[$i];
						break;
						
					//profile completeness
					case 'profile_completeness':
							
						//get profile completeness id
						$sql = "SELECT m_field_id FROM exp_member_fields
								WHERE m_field_name = 'profile_completeness'";
						$query = $DB->query($sql);
						$result = $query->result;
	
						if (count($result) > 0) {
						
							$profile_completeness_id = $result[0]['m_field_id'];
							
							//get profile completeness
							$sql = "SELECT m_field_id_".$profile_completeness_id." FROM exp_member_data
									WHERE member_id = '".$member['member_id']."'";
							$query = $DB->query($sql);
							$r = $query->result;
							
							//add to weighting if complete profile
							if ($r[0]['m_field_id_'.$profile_completeness_id] == '100') {
								$weighting += $weighting_points[$i];
							}
						}
						
						break;
					
					//scheme members
					case 'member_group':						
						
						// check to see if member belong to a premium member group
						
						if ($member['group_id']==7) {
							
							// Premium member
							$check = 1;
							
						} else {
						
							// Non premium member, let's check to see if they have credits assigned
							//get teams that member is assigned to
							$sql = "SELECT * FROM exp_teams_members, exp_teams
									WHERE exp_teams_members.teamID = exp_teams.teamID
									AND exp_teams_members.member_id = '".$member['member_id']."'";
							$query = $DB->query($sql);
							$teams = $query->result;
					
							$check = 0;
					
							//user as assigned to at least one team
							if (is_array($teams)) {
							
								//for each team, check to see if user is in credit		
								foreach ($teams as $team) {
								
									//get team members
									$sql = "SELECT * FROM exp_teams_members
											WHERE exp_teams_members.teamID = '".$team['teamID']."'
											ORDER BY exp_teams_members.dateAdded ASC";
									$query = $DB->query($sql);
									$team_members = $query->result;
									
									//get orders for this team
									$sql = "SELECT exp_foxee_orders.transaction_date, exp_foxee_order_detail.product_quantity FROM exp_foxee_orders, exp_foxee_order_detail
											WHERE exp_foxee_orders.id = exp_foxee_order_detail.order_id
											AND exp_foxee_orders.fe_memberid = '".$team['teamOwner']."'
											AND exp_foxee_order_detail.product_code = '00012'
											AND exp_foxee_orders.fe_orderstatus = '8'";
									$query = $DB->query($sql);
									$orders = $query->result;
									
									//figure out which orders are active
									$count = 0;
									list($usec, $sec) = explode(" ", microtime());
									$today_float = round(((float)$usec + (float)$sec));
									$active_period = 60 * 60 * 24 * $credit_duration;
		
									if (is_array($orders)) {							
										foreach ($orders as $order) {
											$transaction_date = $order['transaction_date'];
											$transaction_date_float = strtotime($transaction_date);
											
											$expiration_float = $transaction_date_float + $active_period;
											if ($today_float < $expiration_float) {
			
												//add the quantity of the active order to the count
												$count += $order['product_quantity'];
											}
										}
									}
									
									//based on the date the user was added to the team, determine if the user has a credit for that team
									$j = 1;
									if (is_array($team_members)) {
										foreach ($team_members as $team_member) {
											if ($j <= $count && $team_member['member_id'] == $member['member_id']) {
												$check++;
											}
											$j++;
										}
									}
								}
							}
											
						}
						
						//user has a credit
						if ($check > 0) {						
							$weighting += $weighting_points[$i];
						}
						
						break;				
					default:
						
						break;
				}
								
				$i++;
			}
			
			// add to weighting table
			$sql = "INSERT INTO exp_social_ranks (member_id, weighting) VALUES ('".$member['member_id']."', '".$weighting."')";
			$DB->query($sql);
		}
	
		if ($log=="true") {
		
			if( ! class_exists('Logger') ) {
		   		require PATH_CP . 'cp.log' . EXT;
			}
			
			$LOG = new Logger;
			$log_message = "Member rankings successfully updated (Social Module).";
			$LOG->log_action($log_message);
			
		}
	
	}
	
	private function _member_search_results($pagination = true) {
		global $DB, $TMPL, $FNS, $LOC, $PREFS;
		
		//switch to GET if required
		if (isset($_GET['encsearch'])) {
			$_POST = unserialize(base64_decode($_GET['encsearch']));
		}
		
		//unset some post data
		unset($_POST['advanced_search']);
		
		//get parameters
		$group_id = $TMPL->fetch_param('group_id');
		$sort = $TMPL->fetch_param('sort');
		$order_by = $TMPL->fetch_param('order_by');		
		$limit = $TMPL->fetch_param('limit');
		$filter = $TMPL->fetch_param('filter');
		
		//pipe delimited group ids
		if ($group_id == '') {
			$group_id = '';
		}
		else {
			$group_id = explode('|', $group_id);
			foreach ($group_id as $val) {
				$group_ids[] = "exp_members.group_id = '".$val."'";
			}
		}
		
		//pagination parameters
		if ($pagination == true) {
			$pagination = $TMPL->fetch_param('pagination');
			$limit = $TMPL->fetch_param('limit');
			$lowerbound = $TMPL->fetch_param('lower_bound');
		}
		else {
			$pagination = false;
			$limit = false;
			$lowerbound = false;
		}
		
		//check pagination parameters
		if ($pagination == '') {
			$pagination = false;
		}
		if ($pagination == true && $limit == '') {
			$limit = 10;
		}
		//sort out lowerbound (replace P)
		$lowerbound = str_replace('P', '', $lowerbound);
		if (!is_numeric($lowerbound) || $lowerbound == '') {
			$lowerbound = 0;
		}
		
		//set up arrays
		$members = array();
		$swap = array();
		$cond = array();
		
		//if order by profile completeness, get custom field id
		if ($order_by == 'profile_completeness') {
			$sql = "SELECT m_field_id FROM exp_member_fields
					WHERE m_field_name = 'profile_completeness'";
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				$profile_completeness_id = $query->result[0]['m_field_id'];
			}
		}
		
		//build searching sql query
		$sql = "SELECT * FROM exp_members, exp_member_data
				WHERE exp_members.member_id = exp_member_data.member_id";
			
		//group ids
		if (isset($group_ids) && is_array($group_ids)) {
			$sql .= " AND (".implode($group_ids, ' OR ').")";
		}
		
		//add keywords
		$keywords = '';
		//if (isset($_POST['keywords']) && $_POST['keywords'] != '') {
		//	$keywords = $_POST['keywords'];
		//	$sql .= " AND (screen_name LIKE '%".$keywords."%'
		//				 	 OR location LIKE '%".$keywords."%')";
		//}
		if (isset($_POST['keywords']) && $_POST['keywords'] != '') {
			$keywords = $_POST['keywords'];
			$sql .= " AND (screen_name LIKE '%".$keywords."%')";
		}
		unset($_POST['keywords']);
		
		// I added support to the search for additional member fields here such as screen_name only, occupation and location
		// This could all probably be tidied into one slightly more inteligent loop/function though
		
		//add screen_name (Nathan)
		$screen_name = '';
		if (isset($_POST['screen_name']) && $_POST['screen_name'] != '') {
			$screen_name = $_POST['screen_name'];
			$sql .= " AND (exp_members.screen_name LIKE '%".$screen_name."%')";
		}
		unset($_POST['screen_name']);
		
		//add occupation (Nathan)
		$occupation = '';
		if (isset($_POST['occupation']) && $_POST['occupation'] != '') {
			$occupation = $_POST['occupation'];
			$sql .= " AND (exp_members.occupation LIKE '%".$occupation."%')";
		}
		unset($_POST['occupation']);
		
		//add location (Nathan)
		$location = '';
		if (isset($_POST['location']) && $_POST['location'] != '') {
			$location = $_POST['location'];
			$sql .= " AND (exp_members.location LIKE '%".$location."%')";
		}
		unset($_POST['location']);
		
		//do custom fields
		if (!empty($_POST)) {
			$args = array();
			foreach ($_POST as $key=>$val) {
				$id = $this->_get_custom_field_id($key);
				$args[] = 'exp_member_data.'.$id." LIKE '%".$val."%'";
			}	
			$sql .= ' AND (';
			$sql .= implode(' AND ', $args);
			$sql .= ')';
		}
		
		//do filter
		if (is_array($filter)) {
			$name = $this->_get_custom_field_id($filter[0]);
			if ($name != false) {
				$sql .= " AND (exp_member_data.".$name." = '".$filter[1]."')";
			}
			else {
				$sql .= " AND (exp_members.".$filter[0]." = '".$filter[1]."')";
			}
		}
		
		//orderby
		if ($order_by != '' && $order_by != false) {
			if ($order_by != 'profile_completeness') {
				$sql .= ' ORDER BY exp_members.'.$order_by;	
			}
			elseif (isset($profile_completeness_id)) {
				$sql .= " ORDER BY exp_member_data.m_field_id_".$profile_completeness_id;
			}
		}
		
		//direction
		if ($sort != '' && $order_by != '' && $sort != false && $order_by != false) {
			$sql .= ' '.$sort;
		}
		
		//get total members for search without limits (for paging)
		$q = $DB->query($sql);
		$membercount = $q->num_rows;
		
		//paging
		if ($pagination == true) {
			$sql .= " LIMIT ".$lowerbound.", ".$limit;
		}
		elseif ($pagination == false && $limit != '') {
			$sql .= " LIMIT ".$limit;
		
		}

		//do search
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			foreach ($query->result as $member) {
				//add to array
				$members[] = $member;
			}
		}
		
		//add members as attribute
		$this->searchmembers = $members;
		
		//return total member count without limits
		return $membercount;
	}
	
	//settings methods
	
	public function settings() {
		global $DB, $FNS, $TMPL, $LANG;
		
		//get member_id
		$member_id = $this->_get_member_id();
		
		//get params
		$return = $TMPL->fetch_param('return');
		$form_name = $TMPL->fetch_param('form_name');
		$form_class = $TMPL->fetch_param('form_class');

		//perform checks
		if ($return == '') {
			$this->return_data = $LANG->line('no_return');
			return $this->return_data;
		}
		if ($form_name == '') {
			$this->return_data = $LANG->line('no_form_name');
			return $this->return_data;
		}
		if ($form_class == '') {
			$this->return_data = $LANG->line('no_form_class');
			return $this->return_data;
		}


		if (isset($_POST['settingsSubmit'])) {
			//do checkbox values
			if (!isset($_POST['accept_messages'])) {
				$_POST['accept_messages'] = 'n';
			}

			//form has been submitted
			$sql = "UPDATE exp_members SET
					accept_messages = '".$_POST['accept_messages']."'
					WHERE member_id = '".$member_id."'";
			$DB->query($sql);
			
			//return
			if ($return != '') {
				$return = $FNS->create_url($return);
				$FNS->redirect($return);
			}
		}
		else {
			//get settings values
			$sql = "SELECT accept_messages FROM exp_members
					WHERE member_id = '".$member_id."'";
			$query = $DB->query($sql);
			$settings = $query->result[0];
		
			//form has not been submitted
			$s = '<form name="'.$form_name.'" class="'.$form_class.'" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
			$s .= $TMPL->tagdata;
						
			$swap['button'] = '<input type="submit" value="Save" name="settingsSubmit" />';
			$s .= '</form>';
			
			$s = $FNS->var_swap($s, $swap);
			$s = $FNS->prep_conditionals($s, $settings); 
			
			return $s;
		}

	}
	
	//private methods

	private function _check_for_input($return, $notification = "false") {
		if (isset($_POST['sentCheck']) && $this->sentcheck == false) {
			if (isset($_POST['subject']) && $_POST['subject'] != '' && isset($_POST['body']) && $_POST['body'] != '') {
				$this->_send($return, $notification);
			}
			else {
				$this->_no_content();
			}
    	}
    	if (isset($_POST['deleteMultiCheck']) && $this->deletemulticheck == false) {
			$this->_delete_multi($return);
    	}
    	if (isset($_POST['deleteSingularCheck']) && $this->deletesingularcheck == false) {
			$this->_delete_singular($return);
    	}
	}

	private function _send($return, $notification = "false") {
		global $FNS, $LANG, $OUT, $DB, $SESS, $REGX, $PREFS;
		
		//switch flag
		$this->sentcheck = true;

		//check for missing values and throw errors
		if (!isset($_POST['to']) || empty($_POST['to'])) {
			return $OUT->show_user_error('submission', $LANG->line('no_recipient'));
		}
		if (!isset($_POST['subject'])) {
			return $OUT->show_user_error('submission', $LANG->line('no_subject'));
		}
		if (!isset($_POST['body'])) {
			return $OUT->show_user_error('submission', $LANG->line('no_body'));
		}
		
		//check for mutual relationship
		if ($this->_check_if_mutual($_POST['to'], $this->_get_member_id()) == false) {
			return $OUT->show_user_error('submission', $LANG->line('no_mutual_relationship'));
		}

		//insert into exp_message_data
		$insertarray = array();
		$insertarray['sender_id'] = $this->_get_member_id();
		$insertarray['message_date'] = date('U');
		$insertarray['message_subject'] = $REGX->xss_clean($_POST['subject']);
		$insertarray['message_body'] = $REGX->xss_clean($_POST['body']);
		$insertarray['message_tracking'] = 'n';
		$insertarray['message_attachments'] = 'n';
		$insertarray['message_recipients'] = '1';
		$insertarray['message_cc'] = '';
		$insertarray['message_hide_cc'] = 'n';
		$insertarray['message_sent_copy'] = 'n';
		$insertarray['total_recipients'] = '1';
		$insertarray['message_status'] = 'sent';
		$sql = $DB->insert_string('exp_message_data', $insertarray);
		$DB->query($sql);

		//insert into exp_message_copies
		$insertarray = array();
		$insertarray['message_id'] = $DB->insert_id;
		$insertarray['sender_id'] = $this->_get_member_id();
		$insertarray['recipient_id'] = $_POST['to'];
		$insertarray['message_received'] = 'y';
		$insertarray['message_read'] = 'n';
		$insertarray['message_time_read'] = '0';
		$insertarray['attachment_downloaded'] = 'n';
		$insertarray['message_folder'] = '1';
		$insertarray['message_authcode'] = $this->_generate_random_alphastring(10);
		$insertarray['message_deleted'] = 'n';
		$insertarray['message_status'] = '';
		$sql = $DB->insert_string('exp_message_copies', $insertarray);
		$DB->query($sql);

		//add sent message
		$insertarray['message_read'] = 'y';
		$insertarray['message_folder'] = '2';
		$sql = $DB->insert_string('exp_message_copies', $insertarray);
		$DB->query($sql);

		//mark potential previous message as replied
		if ($_POST['mark_me_replied'] != '') {
			$message_id = $_POST['mark_me_replied'];
			$data['message_status'] = 'replied';
			$sql = $DB->update_string('exp_message_copies', $data, "message_id='".$message_id."'");
			$DB->query($sql);
		}

		//increment pm count
		$DB->query("UPDATE exp_members SET private_messages = private_messages + 1
					WHERE member_id ='".$DB->escape_str($this->_get_member_id())."'");
		
		//do notifications
		if ($notification == "true") {
			$query = $DB->query("SELECT member_id, screen_name, email FROM exp_members
								 WHERE member_id = '".$_POST['to']."'
								 AND notify_of_pm = 'y'");

			if ($query->num_rows > 0)
			{

				if ( ! class_exists('Typography'))
				{
					require PATH_CORE.'core.typography'.EXT;
				}

				$TYPE = new Typography(0);
 				$TYPE->smileys = FALSE;
				$TYPE->highlight_code = TRUE;

				$body = $TYPE->parse_type(stripslashes($REGX->xss_clean($_POST['body'])),
													   array('text_format'   => 'none',
													   		 'html_format'   => 'none',
													   		 'auto_links'    => 'n',
													   		 'allow_img_url' => 'n'
													   		 ));

				if ( ! class_exists('EEmail'))
				{
					require PATH_CORE.'core.email'.EXT;
				}

				// Because we're using non standard screen names we need to get the real member name now
				$sender_id = $this->_get_member_id();
				$sender_name = ($this->_get_forename($sender_id)." ".$this->_get_surname($sender_id));

				$email = new EEmail;
				$email->wordwrap = true;

				$swap = array(
							  //'sender_name'			=> $SESS->userdata('screen_name'),
							  'sender_name'			=> $sender_name,
							  'message_subject'		=> $REGX->xss_clean($_POST['subject']),
							  'message_content'		=> $body,
							  'site_name'			=> stripslashes($PREFS->ini('site_name')),
							  'site_url'			=> $PREFS->ini('site_url')
							  );

				$template = $FNS->fetch_email_template('private_message_notification');
				$email_tit = $FNS->var_swap($template['title'], $swap);
				$email_tit = str_replace("\'", "'", $email_tit);
				$email_msg = $FNS->var_swap($template['data'], $swap);

				foreach($query->result as $row)
				{
					// We also need to trump the screen name for the recipient
					$recipient_name = ($this->_get_forename($row['member_id'])." ".$this->_get_surname($row['member_id']));
					
					$email->initialize();
					$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));
					$email->to($row['email']);
					$email->subject($email_tit);
					$email->message($REGX->entities_to_ascii($FNS->var_swap($email_msg, array('recipient_name' => $recipient_name))));
					$email->Send();
				}
			}
		}

		//redirect
		$return = $FNS->create_url($return);
		$FNS->redirect($return);
	}
	
	private function _cc_private_messaging($sender_id=NULL, $recipient_id, $subject, $body, $add_to_sent=FALSE) {
		global $DB, $REGX;
	
		if ($sender_id==NULL) {
			$sender_id = 0;
		}
	
		//insert into exp_message_data
		$insertarray = array();
		$insertarray['sender_id'] = $sender_id;
		$insertarray['message_date'] = date('U');
		$insertarray['message_subject'] = $REGX->xss_clean($subject);
		$insertarray['message_body'] = $REGX->xss_clean($body);
		$insertarray['message_tracking'] = 'n';
		$insertarray['message_attachments'] = 'n';
		$insertarray['message_recipients'] = '1';
		$insertarray['message_cc'] = '';
		$insertarray['message_hide_cc'] = 'n';
		$insertarray['message_sent_copy'] = 'n';
		$insertarray['total_recipients'] = '1';
		$insertarray['message_status'] = 'sent';
		$sql = $DB->insert_string('exp_message_data', $insertarray);
		$DB->query($sql);

		//insert into exp_message_copies
		$insertarray = array();
		$insertarray['message_id'] = $DB->insert_id;
		$insertarray['sender_id'] = $sender_id;
		$insertarray['recipient_id'] = $recipient_id;
		$insertarray['message_received'] = 'y';
		$insertarray['message_read'] = 'n';
		$insertarray['message_time_read'] = '0';
		$insertarray['attachment_downloaded'] = 'n';
		$insertarray['message_folder'] = '1';
		$insertarray['message_authcode'] = $this->_generate_random_alphastring(10);
		$insertarray['message_deleted'] = 'n';
		$insertarray['message_status'] = '';
		$sql = $DB->insert_string('exp_message_copies', $insertarray);
		$DB->query($sql);

		if ($add_to_sent) {

			//add sent message
			$insertarray['message_read'] = 'y';
			$insertarray['message_folder'] = '2';
			$sql = $DB->insert_string('exp_message_copies', $insertarray);
			$DB->query($sql);
		
		}

		//increment pm count
		$DB->query("UPDATE exp_members SET private_messages = private_messages + 1
					WHERE member_id ='".$DB->escape_str($recipient_id)."'");
	
	}

	private function _delete_multi($return) {
		global $DB, $FNS;

		//switch flag
		$this->deletemulticheck = true;

		$recipient_id = $_POST['recipient_id'];

		//delete messages
		foreach ($_POST['deleteMultiCheckbox'] as $message) {
			//normal delete
			$data['message_deleted'] = 'y';
			
			//delete from specified folder
			switch ($_POST['deleteType']) {
				case 'inbox':
				default:						
					$sql = $DB->update_string('exp_message_copies', $data, "message_id = '".$message."' AND recipient_id = '".$recipient_id."' AND message_folder='1'");
					break;
				case 'sent':
					$sql = $DB->update_string('exp_message_copies', $data, "message_id = '".$message."' AND recipient_id = '".$recipient_id."' AND message_folder='2'");
					break;
			}
			$DB->query($sql);

			//check for bypass trash
			if ($this->prefs['bypass_trash'] == true) {
				$sql = "DELETE FROM exp_message_copies
						WHERE message_id = '".$message."'
						AND message_deleted = 'y'";
						
				//delete from specified folder
				switch ($_POST['deleteType']) {
					case 'inbox':
					default:						
						$sql .= " AND message_folder = '1'";
						break;
					case 'sent':
						$sql .= " AND message_folder = '2'";
						break;
				}
					
				$DB->query($sql);
			}
			//check for delete from sent
			if ($this->prefs['remove_messages_from_sent'] == true || $_POST['deleteType'] == 'sent') {
				$sql = "UPDATE exp_message_copies
						SET message_deleted = 'y'
						WHERE message_id = '".$message."'
						AND message_folder = '2'";
				$DB->query($sql);
			}
		}

		//redirect
		$return = $FNS->create_url($return);
		$FNS->redirect($return);
	}

	private function _delete_singular($return) {
		global $DB, $FNS;

		//switch flag
		$this->deletesinglecheck = true;

		$recipient_id = $_POST['recipient_id'];
		$message_id = $_POST['message_id'];

		//delete
		$data['message_deleted'] = 'y';
		$sql = $DB->update_string('exp_message_copies', $data, "message_id = '".$message_id."' AND recipient_id = '".$recipient_id."' AND message_folder='1'");
		$DB->query($sql);

		//check for bypass trash
		if ($this->prefs['bypass_trash'] == true) {
			$sql = "DELETE FROM exp_message_copies
					WHERE message_id = '".$message_id."'
					AND message_deleted = 'y'
					AND message_folder = '1'";
			$DB->query($sql);
		}
		//check for delete from sent
		if ($this->prefs['remove_messages_from_sent'] == true) {
			$sql = "UPDATE exp_message_copies
					SET message_deleted = 'y'
					WHERE message_id = '".$message_id."'
					AND message_folder = '2'";
			$DB->query($sql);
		}

		//redirect
		$return = $FNS->create_url($return);
		$FNS->redirect($return);
	}

	private function _get_sender_name($id) {
		global $DB, $LANG;

		$sql = "SELECT screen_name FROM exp_members
				WHERE member_id = '".$DB->escape_str($id)."'";
		$query = $DB->query($sql);

		if ($query->num_rows > 0) {
			$name = $query->result[0]['screen_name'];
			$name = $query->result[0]['screen_name'];
		}
		else {
			$name = $LANG->line('member_unknown');
		}

		return $name;
	}
	
	private function _check_if_following($leader_id) {
		global $DB;
		
		$follower_id = $this->_get_member_id();
		
		$sql = "SELECT leader_id FROM exp_social_followers
				WHERE leader_id = ".$leader_id."
				AND follower_id = ".$follower_id;
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			return true;
		}
		return false;
	}
	
	private function _new_follower_email($leader_id, $follower_id, $template) {
		global $DB, $REGX, $PREFS, $FNS, $LANG;
		
		//get leader information
		$query = $DB->query("SELECT * FROM exp_members, exp_member_data
							 WHERE exp_members.member_id = exp_member_data.member_id
							 AND exp_members.member_id = '".$leader_id."'
							 AND accept_messages = 'y'");
		$leaderInfo = $query->result[0];
		
		//get follower information
		$query = $DB->query("SELECT * FROM exp_members, exp_member_data
							 WHERE exp_members.member_id = exp_member_data.member_id
							 AND exp_members.member_id = '".$follower_id."'");
		$followerInfo = $query->result[0];
		
		//get template information
		list($template_group, $template_name) = split(SLASH, $template);
		$sql = "SELECT g.group_name, t.template_name, t.template_data 
				FROM exp_templates t 
				LEFT JOIN exp_template_groups g ON t.group_id = g.group_id 
				WHERE t.template_name='". $template_name ."' 
				AND g.group_name='". $template_group ."'
				LIMIT 1";				
		$query = $DB->query($sql);
		$templateInfo = $query->result[0];
		
		//get template
		$TMPL = new Template();
		$TMPL->run_template_engine($query->row['group_name'], $query->row['template_name']);
		$messagebody = $TMPL->final_template;
		
		//get subject
		$bodyArray = split("\n", $messagebody);
		$subject = $LANG->line('followers_subject');
		if (substr($bodyArray[0], 0, 9) == "Subject: ") {
			$subject = substr($bodyArray[0], 9) ;
			unset($bodyArray[0]);
		}
		$messagebody = join("\n", $bodyArray);
		
		//get swap data
		$swap['leader_name'] = $leaderInfo['m_field_id_37'].' '.$leaderInfo['m_field_id_38'];
		$swap['follower_name'] = $followerInfo['m_field_id_37'].' '.$followerInfo['m_field_id_38'];
		$swap['follower_id'] = $followerInfo['member_id'];
		$swap['leader_id'] = $leaderInfo['member_id'];
			
		//get conditionals
		$cond['following_and_followed'] = $this->_check_if_mutual($leaderInfo['member_id'], $this->_get_member_id());
		
		//perform swaps
		$messagebody = $FNS->var_swap($messagebody, $swap);
		$messagebody = $FNS->prep_conditionals($messagebody, $cond);
		$subject = $FNS->var_swap($subject, $swap);
		$subject = $FNS->prep_conditionals($subject, $cond);
		
		if (!empty($leaderInfo)) {
			if (!class_exists('EEmail')) {
				require PATH_CORE.'core.email'.EXT;
			}
			$email = new EEmail();
			
			if ( ! class_exists('Typography')) {
				require PATH_CORE.'core.typography'.EXT;
			}
			$TYPE = new Typography(0);

			$messagebody = $TYPE->parse_type(stripslashes($REGX->xss_clean($messagebody)),
												   				array('text_format'   => 'none',
												   		 			  'html_format'   => 'none',
																	  'auto_links'    => 'n',
												   		 			  'allow_img_url' => 'n'
												   		 		)
												   		 	);

			foreach ($query->result as $row) {
				$email->initialize();
				$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));
				$email->to($leaderInfo['email']);
				$email->subject($subject);
				$email->message($REGX->entities_to_ascii($messagebody));
				$email->Send();
				
				// cc to private messaging table
				$this->_cc_private_messaging(NULL, $leaderInfo['member_id'], $subject, $messagebody);
				//
			}
		}
	}
	
	private function _get_follower_count($leader_id) {
		global $DB;
		
		$sql = "SELECT * FROM exp_social_followers
				WHERE leader_id = '".$leader_id."'";
		$query = $DB->query($sql);
		return $query->num_rows;
	}

	private function _no_content() {
		global $OUT, $LANG;

		if (!isset($_POST['subject']) || $_POST['subject'] == '') {
			$errormsg[] = $LANG->line('no_message_subject');
		}
		if (!isset($_POST['body']) || $_POST['body'] == '') {
			$errormsg[] = $LANG->line('no_message_content');
		}
		
		return $OUT->show_user_error('submission', $errormsg);
	}

	private function _get_member_id() {
    	global $TMPL, $SESS;
	   	$member_id = $TMPL->fetch_param('member_id');
	   	if ($member_id == FALSE || $member_id == '{member_id}' || $member_id == '{logged_in_member_id}') {
	    	$member_id = $SESS->userdata['member_id'];
	   	}
	    if (is_numeric($member_id)) {
	    	return $member_id;
	    }
	    else {
	    	return "";
	    }
	}
	
	private function _get_entries_count($member_id, $weblog_id, $status) {
		global $DB;
		
		$sql = "SELECT * FROM exp_weblog_titles
				WHERE author_id=".$member_id." AND weblog_id=".$weblog_id." AND status='".$status."'";
		$query = $DB->query($sql);
		return $query->num_rows;
	}
	
	private function _get_custom_field_id($name) {
		global $DB;

		$sql = "SELECT m_field_id FROM exp_member_fields
				WHERE m_field_name = '".$name."'";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			return 'm_field_id_'.$query->result[0]['m_field_id'];
		}
		return false;
	}
	
	private function _get_custom_field_name($id) {
		global $DB;

		//remove m_field_id
		$id = str_replace('m_field_id_', '', $id);
		
		$sql = "SELECT m_field_name FROM exp_member_fields
				WHERE m_field_id = '".$id."'";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			return $query->result[0]['m_field_name'];
		}
		return false;
	}
	
	private function _get_custom_field_value($field_id, $member_id) {
		global $DB;
		
		$sql = "SELECT m_field_id_".$field_id." FROM exp_member_data
				WHERE member_id = '".$member_id."'";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			return $query->result[0]['m_field_id_'.$field_id];
		}
		return false;
	}
	
	private function _get_date_formats_array() {
		global $TMPL;
		
		$dateformats = array();
		foreach ($TMPL->var_single as $key => $val) {
			if (strpos($key, 'format') > 0) {
		    	$d = explode(' ', $key);		    	
		    	//get tag
		    	$tag = $d[0];
		    	
		    	//get format
		    	$format = '';
				foreach ($d as $k=>$part) {
					if ($k != 0) {
						$format .= $part;
					}
					if ($part != end($d) && $k != 0) {
						$format .= " ";
					}
				}
				
				$format = str_replace('format=', '', $format);
		    	$format = str_replace('"', '', $format);
		    	
		    	//add to array
		    	$dateformats[$tag]['format'] = $format;
		    	$dateformats[$tag]['key'] = $key;
			}		    
		}
		
		return $dateformats;
	}
	
	private function _get_custom_fields_array() {
		global $DB;
		
		$custom_fields = array();
		$sql = "SELECT m_field_name, m_field_id FROM exp_member_fields";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			foreach ($query->result as $result) {
				$custom_fields[$result['m_field_name']] = $result['m_field_id'];
			}			
		}
		return $custom_fields;
	}
	
	private function _get_accept_message_val() {
		global $DB;
		
		$member_id = $this->_get_member_id();
		
		$sql = "SELECT accept_messages FROM exp_members
				WHERE member_id = '".$member_id."'";
		$query = $DB->query($sql);

		return $query->result[0]['accept_messages'];
	}
	
	private function _get_forename($member_id) {
		global $DB;
		
		//attempt to get custom profile field id
		$sql = "SELECT m_field_id FROM exp_member_fields
				WHERE m_field_name = 'forename'";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			$id = $query->result[0]['m_field_id'];
			
			//get data
			$sql = "SELECT m_field_id_".$id." FROM exp_member_data
					WHERE member_id = '".$member_id."'";
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				return $query->result[0]['m_field_id_'.$id];
			}
			return '';					
		}
		return '';
	}
	
	private function _get_surname($member_id) {
		global $DB;
		
		//attempt to get custom profile field id
		$sql = "SELECT m_field_id FROM exp_member_fields
				WHERE m_field_name = 'surname'";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			$id = $query->result[0]['m_field_id'];
			
			//get data
			$sql = "SELECT m_field_id_".$id." FROM exp_member_data
					WHERE member_id = '".$member_id."'";
			$query = $DB->query($sql);
			if ($query->num_rows > 0) {
				return $query->result[0]['m_field_id_'.$id];
			}
			return '';					
		}
		return '';
	}
	
	private function _check_if_mutual($member_id_1, $member_id_2) {
		global $DB;
		
		$check1 = false;
		$check2 = false;
		
		//check first relationship
		$sql = "SELECT * FROM exp_social_followers
				WHERE leader_id = ".$member_id_1."
				AND follower_id = ".$member_id_2;
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			$check1 = true;
		}
		
		//check second relationship
		$sql = "SELECT * FROM exp_social_followers
				WHERE leader_id = ".$member_id_2."
				AND follower_id = ".$member_id_1;
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			$check2 = true;
		}
		
		//return
		if ($check1 == true && $check2 == true) {
			return 'true';
		}
		return 'false';
	}
	
	private function _get_member_rank($m_id) {
		global $DB;
		
		//get all member
		$sql = "SELECT * FROM exp_social_ranks
				ORDER BY weighting DESC, member_id ASC";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
		
			//sort out array just in case
			$members = array();
			foreach ($query->result as $result) {
				$members[] = $result['member_id'];
			}
		
			foreach ($members as $key=>$id) {
				if ($id == $m_id) {
					$rank = $key + 1;
					return $rank;
				}
			}
		}
		return 'Unknown';
	}
	
	private function _get_member_weighting($m_id) {
		global $DB;
		
		//get all member
		$sql = "SELECT * FROM exp_social_ranks
				WHERE member_id = '".$m_id."'";
		$query = $DB->query($sql);
		if ($query->num_rows > 0) {
			return $query->result[0]['weighting'];		
		}
		return 0;
	}

	private function _generate_random_alphastring($length=10) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$code = "";
		while (strlen($code) < $length) {
			$code .= $chars[mt_rand(0,strlen($chars)-1)];
		}
		return $code;
	}
	
	private function _notify_admin($email, $subject, $body) {
	
		if ( ! class_exists('EEmail'))
		{
			require PATH_CORE.'core.email'.EXT;
		}

		$email = new EEmail;
		$email->wordwrap = true;

		$email->initialize();
		$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));
		$email->to($email);
		$email->subject($subject);
		$email->message($REGX->entities_to_ascii($body));
		$email->Send();
	
	}

	private function _debug($array) {
		echo '<pre>';
		print_r($array);
		echo '</pre>';
	}
}

?>