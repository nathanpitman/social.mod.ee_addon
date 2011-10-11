<?php

class Social_CP {

	//module vars
	private $module_name = 'Social';
	private $module_version = '1.0';
	private $backend_bool = 'y';

	//constructor
	public function Social_CP($switch = true) {
		global $IN, $DB;
		$switchval = $IN->GBL('P');

        if ($switch) {
	        switch($switchval) {
	        	case 'preferences_update':
	        		$this->preferences_update();
	        		break;
	        	default:
					$this->preferences();
	                break;
	        }
        }
	}

	public function preferences($msg = false, $response = false) {
		global $DSP, $LANG, $IN, $DB;

		$query = $DB->query("SELECT * FROM exp_social_prefs");
		if ($query->num_rows > 0) {
       		$prefs = json_decode($query->result[0]['prefsJSON'], true);
		}
		else {
			$prefs = array();
		}

        //HTML Title and Navigation Crumblinks
        $DSP->title = $LANG->line('module_name');
        $DSP->crumb = $DSP->anchor(BASE.AMP.'C=modules'.AMP.'M=social', $LANG->line('module_name'));

		//Print msg if set
		if ($msg != false) {
			if ($response == true) {
				$DSP->body .= $DSP->qdiv('successBox', $DSP->qdiv('success', $msg)).BR;
			}
			else {
				$DSP->body .= $DSP->qdiv('errorBox', $DSP->qdiv('alertHeading', $msg)).BR;
			}
		}

		//Form for preferences
		$DSP->body .= $DSP->qdiv('tableHeading', $LANG->line('preferences'));
        $DSP->body .= $DSP->form_open(array('action' => 'C=modules'.AMP.'M=social'.AMP.'P=preferences_update'));
        $DSP->body .= $DSP->table('tableBorder', '0', '0', '100%');
		$DSP->body .= $DSP->tr();
		$DSP->body .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $LANG->line('bypass_trash')));
		if (isset($prefs['bypass_trash']) && $prefs['bypass_trash'] == 1) {
			$DSP->body .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $DSP->qdiv('itemWrapper', $DSP->input_checkbox('bypass_trash', 1, 1))));
		}
		else {
			$DSP->body .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $DSP->qdiv('itemWrapper', $DSP->input_checkbox('bypass_trash', 1, 0))));
		}
		$DSP->body .= $DSP->tr_c();
		$DSP->body .= $DSP->tr();
		$DSP->body .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $LANG->line('remove_messages_from_sent')));
		if (isset($prefs['remove_messages_from_sent']) && $prefs['remove_messages_from_sent'] == 1) {
			$DSP->body .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $DSP->qdiv('itemWrapper', $DSP->input_checkbox('remove_messages_from_sent', 1, 1))));
		}
		else {
			$DSP->body .= $DSP->table_qcell('tableCellOne', $DSP->qdiv('defaultBold', $DSP->qdiv('itemWrapper', $DSP->input_checkbox('remove_messages_from_sent', 1, 0))));
		}
		$DSP->body .= $DSP->tr_c();
		$DSP->body .= $DSP->table_c();
		$DSP->body .= $DSP->qdiv('itemWrapperTop', $DSP->input_submit($LANG->line('save_preferences')));
        $DSP->body .= $DSP->form_close();
	}

	function preferences_update() {
    	global $DB, $IN;

		//get values
		if (isset($_POST['bypass_trash'])) {
			$data['bypass_trash'] = 1;
		}
		else {
			$data['bypass_trash'] = 0;
		}
		if (isset($_POST['remove_messages_from_sent'])) {
			$data['remove_messages_from_sent'] = 1;
		}
		else {
			$data['remove_messages_from_sent'] = 0;
		}
		$data = json_encode($data);

		//delete old value
	   	$Result = $DB->query("DELETE FROM exp_social_prefs
							  WHERE 1=1");

		//insert new value
    	$DB->query("INSERT INTO exp_social_prefs(prefsJSON)
					VALUES ('".$DB->escape_str($data)."')");

		$this->preferences('Preferences saved', true);
    }

	public function social_module_install() {
		global $DB;

		//create preferences table
		$sql[] = "CREATE TABLE IF NOT EXISTS `exp_social_prefs` (
				 `prefsJSON` TEXT)";

		$data['bypass_trash'] = 0;
		$data['remove_message_from_sent'] = 0;
		$data = json_encode($data);

		//insert starting prefs
		$sql[] = "INSERT INTO exp_social_prefs(prefsJSON)
				  VALUES ('".$DB->escape_str($data)."')";
		
		//create followers tables
		$sql[] = "CREATE TABLE IF NOT EXISTS `exp_social_followers` (
				 `leader_id` int(10),
				 `follower_id` int(10))";

		//create sql query array
        $sql[] = "INSERT INTO exp_modules(module_id, module_name, module_version, has_cp_backend)
				  VALUES ('', '".$this->module_name."', '".$this->module_version."', '".$this->backend_bool."')";

		//perform queries
        foreach ($sql as $query) {
            $DB->query($query);
        }

        return true;
    }

    public function social_module_deinstall() {
        global $DB;

		//delete preferences table
		$sql[] = "DROP TABLE IF EXISTS `exp_social_prefs`;";

        //create sql query array
        $sql[] = "DELETE FROM exp_modules WHERE module_name = '".$this->module_name."'";

		//perform queries
        foreach ($sql as $query) {
            $DB->query($query);
        }

        return true;
    }

}

?>