<?php

global $cgi;

loader_import ('cms.Workflow.Lock');

lock_init ();

if (lock_exists ($cgi->_collection, $cgi->_key)) {
	page_title (intl_get ('Item Locked by Another User'));
	echo '<p><a href="javascript: history.go (-1)">' . intl_get ('Back') . '</a></p>';
	echo template_simple (LOCK_INFO_TEMPLATE, lock_info ($cgi->_collection, $cgi->_key));
	return;
} else {
	lock_add ($cgi->_collection, $cgi->_key);
}

class NewsEditForm extends MailForm {
	function NewsEditForm () {
		parent::MailForm ();

		$this->autosave = true;

		$this->parseSettings ('inc/app/news/forms/edit/settings.php');

		global $page, $cgi;

		page_title (intl_get ('Editing News Story') . ': ' . $cgi->_key);

		loader_import ('ext.phpsniff');

		$sniffer = new phpSniff;
		$this->_browser = $sniffer->property ('browser');

		// include formhelp, edit panel init, and cancel handler
		page_add_script (site_prefix () . '/js/formhelp-compressed.js');
		page_add_script (CMS_JS_FORMHELP_INIT);
		page_add_script ('
			function cms_cancel_unlock (f, collection, key) {
				onbeforeunload_form_submitted = true;
				if (arguments.length == 0) {
					window.location.href = "' . site_prefix () . '/index/cms-unlock-action?collection=" + collection + "&key=" + key + "&return=' . site_prefix () . '/index/cms-app";
				} else {
					if (f.elements[\'_return\'] && f.elements[\'_return\'].value.length > 0) {
						window.location.href = "' . site_prefix () . '/index/cms-unlock-action?collection=" + collection + "&key=" + key + "&return=" + EncodeURIComponent (f.elements[\'_return\'].value);
					} else {
						window.location.href = "' . site_prefix () . '/index/cms-unlock-action?collection=" + collection + "&key=" + key + "&return=' . site_prefix () . '/index/news-app";
					}
				}
				return false;
			}
		');
		if (session_pref ('form_help') == 'off') {
			page_add_script ('
				formhelp_disable = true;
			');
		}

		// add cancel handler
		$this->widgets['submit_button']->buttons[0]->extra = 'onclick="onbeforeunload_form_submitted = true;"';
		$this->widgets['submit_button']->buttons[1]->extra = 'onclick="onbeforeunload_form_submitted = true;"';
		$this->widgets['submit_button']->buttons[2]->extra = 'onclick="return cms_cancel_unlock (this.form, \'' . $cgi->_collection . '\', \'' . $cgi->_key . '\')"';

		// get copy from repository
		loader_import ('cms.Versioning.Rex');
		$rex = new Rex ($cgi->_collection);
		$_document = $rex->getCurrent ($cgi->_key);

		// set values from repository entry
		foreach (get_object_vars ($_document) as $k => $v) {
			if (is_object ($this->widgets[$k])) {
				$this->widgets[$k]->setValue ($v);
			}
		}
	}

	function onSubmit ($vals) {
		loader_import ('cms.Versioning.Rex');

		$collection = $vals['_collection'];
		unset ($vals['_collection']);
		if (empty ($collection)) {
			$collection = 'sitellite_page';
		}

		$key = $vals['_key'];
		unset ($vals['_key']);

		$return = $vals['_return'];
		unset ($vals['_return']);

		$changelog = $vals['changelog'];
		unset ($vals['changelog']);

		$rex = new Rex ($collection);

                $continue = ($vals['submit_button'] == intl_get ('Save and continue'));
		unset ($vals['submit_button']);
		unset ($vals['tab1']);
		unset ($vals['tab2']);
		unset ($vals['tab3']);
		unset ($vals['tab-end']);

		$method = $rex->determineAction ($key, $vals['sitellite_status']);
		if (! $method) {
			die ($rex->error);
		}
		$res = $rex->{$method} ($key, $vals, $changelog);

		// remove lock when editing is finished
		lock_remove ($collection, $key);

		if (! $res) {
			if (! empty ($return)) {
				$return = site_prefix () . '/index/cms-browse-action?collection=sitellite_news';
			}
			echo loader_box ('cms/error', array (
				'message' => $rex->error,
				'collection' => $collection,
				'key' => $key,
				'action' => $method,
				'data' => $vals,
				'changelog' => $changelog,
				'return' => $return,
			));
		} else {
			loader_import ('cms.Workflow');
			echo Workflow::trigger (
				'edit',
				array (
					'collection' => $collection,
					'key' => $key,
					'action' => $method,
					'data' => $vals,
					'changelog' => $changelog,
					'message' => 'Collection: ' . $collection . ', Item: ' . $key . ' (' . $vals['title'] . ')',
				)
			);

			session_set ('sitellite_alert', intl_get ('Your item has been saved.'));

                        if ($continue) {
                                header ('Location: ' . site_prefix () . '/cms-edit-form?_collection=' . $collection . '&_key=' . $key . '&_return=' . $return);
                                exit;
                        }

			if (! empty ($return)) {
				header ('Location: ' . $return);
				exit;
			}
			header ('Location: ' . site_prefix () . '/index/news-app/story.' . $key);
			exit;
		}
	}
}

?>
