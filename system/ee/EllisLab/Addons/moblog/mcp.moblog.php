<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use EllisLab\ExpressionEngine\Library\CP\Table;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2015, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Moblog Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Moblog_mcp {

	var $channel_array		= array();
	var $status_array 		= array();
	var $field_array  		= array();
	var $author_array 		= array();
	var $image_dim_array	= array();
	var $upload_loc_array	= array();

	var $default_template 	= '';
	var $default_channel_cat	= '';


	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function __construct()
	{
		$this->default_template = <<<EOT
{text}

{images}
<img src="{file}" width="{width}" height="{height}" alt="pic" />
{/images}

{files match="audio|files|movie"}
<a href="{file}">Download File</a>
{/files}
EOT;

	}

	// --------------------------------------------------------------------

	/**
	 * Moblog Homepage
	 *
	 * @access	public
	 * @return	string
	 */
	function index()
	{
		$table = ee('CP/Table', array('autosort' => TRUE));
		$table->setColumns(array(
			'col_id',
			'moblog',
			'manage' => array(
				'type'	=> Table::COL_TOOLBAR
			),
			array(
				'type'	=> Table::COL_CHECKBOX
			)
		));

		$table->setNoResultsText('no_moblogs', 'create_moblog', ee('CP/URL', 'addons/settings/moblog/create'));

		$sort_map = array(
			'col_id' => 'moblog_id',
			'moblog' => 'moblog_full_name',
		);

		$moblogs = ee()->db->select('moblog_id, moblog_full_name')
			->order_by($sort_map[$table->sort_col], $table->sort_dir)
			->get('moblogs')
			->result_array();

		$data = array();
		foreach ($moblogs as $moblog)
		{
			$columns = array(
				$moblog['moblog_id'],
				$moblog['moblog_full_name'],
				array('toolbar_items' => array(
					'edit' => array(
						'href' => ee('CP/URL', 'addons/settings/moblog/edit/'.$moblog['moblog_id']),
						'title' => lang('edit')
					),
					'txt-only' => array(
						'href' => ee('CP/URL', 'addons/settings/moblog/check/'.$moblog['moblog_id']),
						'title' => (lang('check_now')),
						'content' => strtolower(lang('check_now'))
					)
				)),
				array(
					'name' => 'moblogs[]',
					'value' => $moblog['moblog_id'],
					'data'	=> array(
						'confirm' => lang('moblog') . ': <b>' . htmlentities($moblog['moblog_full_name'], ENT_QUOTES) . '</b>'
					)
				)
			);

			$attrs = array();
			if (ee()->session->flashdata('highlight_id') == $moblog['moblog_id'])
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs' => $attrs,
				'columns' => $columns
			);
		}

		$table->setData($data);

		$vars['base_url'] = ee('CP/URL', 'addons/settings/moblog');
		$vars['table'] = $table->viewData($vars['base_url']);

		$vars['pagination'] = ee('CP/Pagination', count($moblogs))
			->perPage($vars['table']['limit'])
			->currentPage($vars['table']['page'])
			->render($vars['table']['base_url']);

		ee()->javascript->set_global('lang.remove_confirm', lang('moblogs') . ': <b>### ' . lang('moblogs') . '</b>');
		ee()->cp->add_js_script(array(
			'file' => array('cp/v3/confirm_remove'),
		));

		return ee('View')->make('moblog:index')->render($vars);
	}

	/**
	 * Remove moblogs handler
	 */
	public function remove()
	{
		$moblog_ids = ee()->input->post('moblogs');

		if ( ! empty($moblog_ids) && ee()->input->post('bulk_action') == 'remove')
		{
			// Filter out junk
			$moblog_ids = array_filter($moblog_ids, 'is_numeric');

			if ( ! empty($moblog_ids))
			{
				ee('Model')->get('moblog:Moblog', $moblog_ids)->delete();

				ee('Alert')->makeInline('moblogs-table')
					->asSuccess()
					->withTitle(lang('moblogs_removed'))
					->addToBody(sprintf(lang('moblogs_removed_desc'), count($moblog_ids)))
					->defer();
			}
		}
		else
		{
			show_error(lang('unauthorized_access'));
		}

		ee()->functions->redirect(ee('CP/URL', 'addons/settings/moblog', ee()->cp->get_url_state()));
	}

	/**
	 * New moblog form
	 */
	public function create()
	{
		return $this->form();
	}

	/**
	 * Edit moblog form
	 */
	public function edit($moblog_id)
	{
		return $this->form($moblog_id);
	}

	/**
	 * Moblog creation/edit form
	 *
	 * @param	int	$moblog_id	ID of moblog to edit
	 */
	private function form($moblog_id = NULL)
	{
		$vars = array();
		if (is_null($moblog_id))
		{
			ee()->cp->add_js_script('plugin', 'ee_url_title');
			ee()->javascript->output('
				$("input[name=moblog_full_name]").bind("keyup keydown", function() {
					$(this).ee_url_title("input[name=moblog_short_name]");
				});
			');

			$alert_key = 'created';
			$vars['cp_page_title'] = lang('create_moblog');
			$vars['base_url'] = ee('CP/URL', 'addons/settings/moblog/create');
			$moblog = ee('Model')->make('moblog:Moblog');
		}
		else
		{
			$moblog = ee('Model')->get('moblog:Moblog', $moblog_id)->first();

			if ( ! $moblog)
			{
				show_error(lang('unauthorized_access'));
			}

			$alert_key = 'updated';
			$vars['cp_page_title'] = lang('edit_moblog');
			$vars['base_url'] = ee('CP/URL', 'addons/settings/moblog/edit/'.$moblog_id);
		}

		if ( ! empty($_POST))
		{
			$moblog->set($_POST);

			// Need to convert this field from its presentation serialization
			$moblog->moblog_valid_from = explode(',', trim(preg_replace("/[\s,|]+/", ',', $_POST['moblog_valid_from']), ','));

			$result = $moblog->validate();

			if ($result->isValid())
			{
				$moblog = $moblog->save();

				if (is_null($moblog_id))
				{
					ee()->session->set_flashdata('highlight_id', $moblog->getId());
				}

				ee('Alert')->makeInline('moblogs-table')
					->asSuccess()
					->withTitle(lang('moblog_'.$alert_key))
					->addToBody(sprintf(lang('moblog_'.$alert_key.'_desc'), $moblog->moblog_full_name))
					->defer();

				ee()->functions->redirect(ee('CP/URL', 'addons/settings/moblog'));
			}
			else
			{
				$vars['errors'] = $result;
				ee('Alert')->makeInline('moblogs-table')
					->asIssue()
					->withTitle(lang('moblog_not_'.$alert_key))
					->addToBody(lang('moblog_not_'.$alert_key.'_desc'))
					->now();
			}
		}

		$channels = ee('Model')->get('Channel')->with('Site');

		if (ee()->config->item('multiple_sites_enabled') !== 'y')
		{
			$channels = $channels->filter('site_id', 1);
		}

		$channels = $channels->all();

		$channels_options = array();
		foreach ($channels as $channel)
		{
			$channels_options[$channel->channel_id] = (ee()->config->item('multiple_sites_enabled') === 'y')
				? $channel->Site->site_label.NBS.'-'.NBS.$channel->channel_title : $channel->channel_title;
		}

		$vars['sections'] = array(
			array(
				array(
					'title' => 'moblog_name',
					'fields' => array(
						'moblog_full_name' => array(
							'type' => 'text',
							'value' => $moblog->moblog_full_name,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_short_name',
					'desc' => 'moblog_short_name_desc',
					'fields' => array(
						'moblog_short_name' => array(
							'type' => 'text',
							'value' => $moblog->moblog_short_name,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_check_interval',
					'desc' => 'moblog_check_interval_desc',
					'fields' => array(
						'moblog_time_interval' => array(
							'type' => 'text',
							'value' => $moblog->moblog_time_interval,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_enabled',
					'fields' => array(
						'moblog_enabled' => array(
							'type' => 'yes_no',
							'value' => is_null($moblog->moblog_enabled) ? TRUE : $moblog->moblog_enabled
						)
					)
				),
				array(
					'title' => 'file_archive_mode',
					'desc' => 'file_archive_mode_desc',
					'fields' => array(
						'moblog_file_archive' => array(
							'type' => 'yes_no',
							'value' => $moblog->moblog_file_archive
						)
					)
				)
			),
			'channel_entry_settings' => array(
				array(
					'title' => 'channel',
					'desc' => 'moblog_channel_desc',
					'fields' => array(
						'moblog_channel_id' => array(
							'type' => 'select',
							'choices' => $channels_options,
							'value' => $moblog->moblog_channel_id
						)
					)
				),
				array(
					'title' => 'cat_id',
					'fields' => array(
						'moblog_categories' => array(
							'type' => 'checkbox',
							'choices' => ee('Model')->get('Category')->all()->getDictionary('cat_id', 'cat_name'),
							'value' => $moblog->moblog_categories
						)
					)
				),
				array(
					'title' => 'field_id',
					'fields' => array(
						'moblog_field_id' => array(
							'type' => 'select',
							'choices' => ee('Model')->get('ChannelField')->all()->getDictionary('field_id', 'field_label'),
							'value' => $moblog->moblog_field_id
						)
					)
				),
				array(
					'title' => 'default_status',
					'fields' => array(
						'moblog_status' => array(
							'type' => 'select',
							'choices' => ee('Model')->get('Status')->all()->getDictionary('status', 'status'),
							'value' => $moblog->moblog_status
						)
					)
				),
				array(
					'title' => 'author_id',
					'fields' => array(
						'moblog_author_id' => array(
							'type' => 'select',
							'choices' => ee('Model')->get('Member')->all()->getDictionary('member_id', 'screen_name'),
							'value' => $moblog->moblog_author_id
						)
					)
				),
				array(
					'title' => 'moblog_sticky_entry',
					'fields' => array(
						'moblog_sticky_entry' => array(
							'type' => 'yes_no',
							'value' => $moblog->moblog_sticky_entry
						)
					)
				),
				array(
					'title' => 'moblog_allow_overrides',
					'desc' => 'moblog_allow_overrides_subtext',
					'fields' => array(
						'moblog_allow_overrides' => array(
							'type' => 'yes_no',
							'value' => $moblog->moblog_allow_overrides
						)
					)
				),
				array(
					'title' => 'moblog_template',
					'fields' => array(
						'moblog_template' => array(
							'type' => 'textarea',
							'value' => $moblog->moblog_template ?: $this->default_template
						)
					)
				)
			),
			'moblog_email_settings' => array(
				array(
					'title' => 'moblog_email_type',
					'fields' => array(
						'moblog_email_type' => array(
							'type' => 'select',
							'choices' => array('pop3' => lang('pop3')),
							'value' => $moblog->moblog_email_type
						)
					)
				),
				array(
					'title' => 'moblog_email_address',
					'fields' => array(
						'moblog_email_address' => array(
							'type' => 'text',
							'value' => $moblog->moblog_email_address,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_email_server',
					'desc' => 'server_example',
					'fields' => array(
						'moblog_email_server' => array(
							'type' => 'text',
							'value' => $moblog->moblog_email_server,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_email_login',
					'desc' => 'data_encrypted',
					'fields' => array(
						'moblog_email_login' => array(
							'type' => 'text',
							'value' => $moblog->moblog_email_login,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_email_password',
					'desc' => 'data_encrypted',
					'fields' => array(
						'moblog_email_password' => array(
							'type' => 'text',
							'value' => $moblog->moblog_email_password,
							'required' => TRUE
						)
					)
				),
				array(
					'title' => 'moblog_subject_prefix',
					'desc' => 'moblog_subject_subtext',
					'fields' => array(
						'moblog_subject_prefix' => array(
							'type' => 'text',
							'value' => $moblog->moblog_subject_prefix
						)
					)
				),
				array(
					'title' => 'moblog_auth_required',
					'desc' => 'moblog_auth_subtext',
					'fields' => array(
						'moblog_auth_required' => array(
							'type' => 'yes_no',
							'value' => $moblog->moblog_auth_required
						)
					)
				),
				array(
					'title' => 'moblog_auth_delete',
					'desc' => 'moblog_auth_delete_subtext',
					'fields' => array(
						'moblog_auth_delete' => array(
							'type' => 'yes_no',
							'value' => $moblog->moblog_auth_delete
						)
					)
				),
				array(
					'title' => 'moblog_valid_from',
					'desc' => 'valid_from_subtext',
					'fields' => array(
						'moblog_valid_from' => array(
							'type' => 'textarea',
							'value' => implode("\n", $moblog->moblog_valid_from)
						)
					)
				),
				array(
					'title' => 'moblog_ignore_text',
					'desc' => 'ignore_text_subtext',
					'fields' => array(
						'moblog_ignore_text' => array(
							'type' => 'textarea',
							'value' => $moblog->moblog_ignore_text
						)
					)
				)
			),
			'moblog_file_settings' => array(
				array(
					'title' => 'moblog_upload_directory',
					'fields' => array(
						'moblog_upload_directory' => array(
							'type' => 'select',
							'choices' => ee('Model')->get('UploadDestination')
								->filter('site_id', ee()->config->item('site_id'))
								->all()
								->getDictionary('id', 'name'),
							'value' => $moblog->moblog_upload_directory
						)
					)
				),
				array(
					'title' => 'moblog_image_size',
					'fields' => array(
						'moblog_image_size' => array(
							'type' => 'select',
							'choices' => array('0'=> lang('none')),
							'value' => $moblog->moblog_image_size
						)
					)
				),
				array(
					'title' => 'moblog_thumb_size',
					'fields' => array(
						'moblog_thumb_size' => array(
							'type' => 'select',
							'choices' => array('0'=> lang('none')),
							'value' => $moblog->moblog_thumb_size
						)
					)
				)
			)
		);

		$this->_filtering_menus('moblog_create');
		ee()->javascript->compile();

		$vars['save_btn_text'] = 'save_moblog';
		$vars['save_btn_text_working'] = 'btn_saving';

		return array(
			'heading'    => $vars['cp_page_title'],
			'breadcrumb' => array(ee('CP/URL', 'addons/settings/moblog')->compile() => lang('moblog') . ' ' . lang('configuration')),
			'body'       => ee('View')->make('moblog:create')->render($vars)
		);
	}

	/**
	 * Create Moblog
	 *
	 * @access	public
	 * @return	string
	 */
	function create_modify()
	{
		ee()->load->helper('form');
		ee()->load->library('form_validation');
		ee()->load->library('api');
		ee()->legacy_api->instantiate('channel_categories');

		$id		= ( ! ee()->input->get_post('id')) ? '' : ee()->input->get_post('id');
		$basis	= ( ! ee()->input->post('basis'))  ? '' : ee()->input->post('basis');

		$count = ee()->db->count_all('moblogs');

		$vars['hidden_fields'] = array('id' => $id, 'basis' => $basis);
		ee()->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog', lang('moblog'));

		if ($id != '')
		{
			$vars['cp_page_title'] = lang('edit_moblog');
		}
		else
		{
			$vars['cp_page_title'] = lang('create_moblog');
		}

		// Base new moblog on existing one?

		if ($basis == '' && $count > 0 && $id == '')
		{
			ee()->db->select('moblog_id, moblog_full_name');
			$query = ee()->db->get('moblogs');

			$options['none'] = lang('none');

			foreach($query->result_array() as $row)
			{
				$options[$row['moblog_id']] = $row['moblog_full_name'];
			}

			return ee()->load->view('choose', array('options' => $options), TRUE);
		}


		// Fetch Channels

		$channel_array = array();

		ee()->db->select('channel_id, channel_title, site_label');
		ee()->db->from(array('channels', 'sites'));
		ee()->db->where('channels.site_id = '.ee()->db->dbprefix('sites.site_id'));

		if (ee()->config->item('multiple_sites_enabled') !== 'y')
		{
			ee()->db->where('channels.site_id', '1');
		}

		$channel_array['null'] = lang('channel_id');

		$result = ee()->db->get();

		if ($result->num_rows() > 0)
		{
			foreach ($result->result_array() as $rez)
			{
				$channel_array[$rez['channel_id']] = (ee()->config->item('multiple_sites_enabled') === 'y') ? $rez['site_label'].NBS.'-'.NBS.$rez['channel_title'] : $rez['channel_title'];
			}
		}

		// Fetch Upload Directories
		$this->upload_loc_array = array('0' => lang('none'));
		$this->image_dim_array = array('0' => lang('none'));

		$upload_array = array('0' => lang('none'));

		ee()->load->model(array('file_model', 'file_upload_preferences_model'));

		$upload_prefs = ee()->file_upload_preferences_model->get_file_upload_preferences(ee()->session->userdata['group_id']);

		$sizes_q = ee()->file_model->get_dimensions_by_dir_id(1);
		$sizes = array();

		foreach ($upload_prefs as $row)
		{
			$sizes[$row['id']] = array('0' => '----');
			$upload_array[$row['id']] = $row['name'];
		}

		foreach ($sizes_q->result() as $size)
		{
			$sizes[$size->upload_location_id][$size->id] = $size->title;
		}

		// Options Matrix - Whoa.

		$form_data = array(
						'moblog_full_name'			=> '',
						'moblog_short_name'			=> '',
						'moblog_time_interval'		=> '15',
						'moblog_enabled'			=> 'y',
						'moblog_file_archive'		=> 'n',

						// moblog_entry_settings
						'channel_id'				=> array($channel_array, 0),
						'cat_id[]'					=> array(array('none'=> lang('none')), array('none' => lang('none'), '' => '-----')),
						'field_id'					=> array(array('none'=> lang('none')), 'none'),
						'status'					=> array(array('none'=> lang('none'), 'open' => lang('open'), 'closed' => lang('closed')), 'none'),
						'author_id'					=> array(array('none'=> lang('none'),
																ee()->session->userdata['member_id'] => (ee()->session->userdata['screen_name'] == '') ? ee()->session->userdata['username'] : ee()->session->userdata['screen_name']),
																'none'),
						'moblog_sticky_entry'		=> 'n',
						'moblog_allow_overrides'	=> 'y',
						'moblog_template'			=> $this->default_template,	// textarea

						// moblog_email_settings
						'moblog_email_type'			=> array(array('pop3' => lang('pop3')),'pop3'),
						'moblog_email_address'		=> '',
						'moblog_email_server'		=> '',
						'moblog_email_login'		=> '',
						'moblog_email_password'		=> '',
						'moblog_subject_prefix'		=> 'moblog:',
						'moblog_auth_required'		=> 'n',
						'moblog_auth_delete'		=> 'n',
						'moblog_valid_from'			=> '',	// textarea
						'moblog_ignore_text'		=> '',	// textarea

						// moblog_image_settings
						'moblog_upload_directory'	=> array(array('0'=> lang('none')), '0'),
						'moblog_image_size'			=> array(array('0'=> lang('none')), '0'),
						'moblog_thumb_size'			=> array(array('0'=> lang('none')), '0')
						);


		// Filtering Javascript

		$this->_filtering_menus('moblog_create');
		ee()->javascript->compile();


		// Validation Rules

		ee()->form_validation->set_rules('moblog_full_name',			'lang:moblog_full_name',		'required|callback__check_duplicate[moblog_full_name]');
		ee()->form_validation->set_rules('moblog_short_name',			'lang:moblog_short_name',		'required|alpha_dash|callback__check_duplicate');
		ee()->form_validation->set_rules('moblog_auth_required',		'lang:moblog_auth_required',	'required|enum[y,n]');
		ee()->form_validation->set_rules('moblog_auth_delete',			'lang:moblog_auth_delete',		'required|enum[y,n]');
		ee()->form_validation->set_rules('moblog_email_type',			'lang:moblog_email_type',		'required');
		ee()->form_validation->set_rules('moblog_email_address',		'lang:moblog_email_address',	'required');
		ee()->form_validation->set_rules('moblog_email_server',		'lang:moblog_email_server',		'required');
		ee()->form_validation->set_rules('moblog_email_login',			'lang:moblog_email_login',		'required');
		ee()->form_validation->set_rules('moblog_email_password',		'lang:moblog_email_password',	'required');
		ee()->form_validation->set_rules('moblog_time_interval',		'lang:moblog_time_interval',	'required');
		ee()->form_validation->set_rules('moblog_enabled',				'lang:moblog_enabled',			'required|enum[y,n]');

		// All the non-required fields...sighs
		ee()->form_validation->set_rules('moblog_valid_from',			'lang:moblog_valid_from',		'prep_list[,]|valid_emails');

		ee()->form_validation->set_rules('channel_id',					'lang:channel_id',				'');
		ee()->form_validation->set_rules('cat_id[]',					'lang:cat_id',					'');
		ee()->form_validation->set_rules('field_id',					'lang:field_id',				'');
		ee()->form_validation->set_rules('status',						'lang:status',					'');
		ee()->form_validation->set_rules('author_id',					'lang:author_id',				'');

		ee()->form_validation->set_rules('moblog_subject_prefix',		'lang:moblog_subject_prefix',	'');
		ee()->form_validation->set_rules('moblog_ignore_text',			'lang:moblog_ignore_text',		'');
		ee()->form_validation->set_rules('moblog_template',			'lang:moblog_template',			'');
		ee()->form_validation->set_rules('moblog_allow_overrides',		'lang:moblog_allow_overrides',	'enum[y,n]');
		ee()->form_validation->set_rules('moblog_sticky_entry',		'lang:moblog_sticky_entry',		'enum[y,n]');

		ee()->form_validation->set_rules('moblog_upload_directory',	'lang:moblog_upload_directory',	'required');
		ee()->form_validation->set_rules('moblog_image_size',			'lang:moblog_image_size',		'is_natural');
		ee()->form_validation->set_rules('moblog_thumb_size',			'lang:moblog_thumb_size',		'is_natural');

		ee()->form_validation->set_error_delimiters('<p class="notice">', '</p>');

		if ($edit_id = ee()->input->post('id'))
		{
			ee()->form_validation->set_old_value('id', $edit_id);
		}

		// Data

		$data = array('author_id' => ee()->session->userdata['member_id']);

		$form_data['moblog_upload_directory'] = array($upload_array, '');

		if (($basis != '' && $basis != 'none') OR ($id != '' && is_numeric($id)))
		{
			$moblog_id = ($basis != '') ? $basis : $id;

			$query = ee()->db->get_where('moblogs', array('moblog_id' => $moblog_id));

			// Fetch a single row

			$row = $query->row_array();

			// Upload Directory Double-Check

			if ( ! isset($upload_array[$row['moblog_upload_directory']]))
			{
				$upload_prefs = ee()->file_upload_preferences_model->get_file_upload_preferences(1, $row['moblog_upload_directory']);

				if (count($upload_prefs) > 0)
				{
					$upload_array[$row['moblog_upload_directory']] = $upload_prefs['name'];
					$form_data['moblog_upload_directory'] = array($upload_array, $row['moblog_upload_directory']);
				}
			}
			else
			{
				$form_data['moblog_upload_directory'] = array($upload_array, $row['moblog_upload_directory']);
			}

			$data = array(
						'moblog_short_name'			=> ($basis != '') ? $row['moblog_short_name'] .'_copy' : $row['moblog_short_name'] ,
						'moblog_full_name'			=> ($basis != '') ? $row['moblog_full_name'] .' - copy' : $row['moblog_full_name'] ,
						'channel_id'				=> $row['moblog_channel_id'] ,
						'cat_id[]'					=> explode('|',$row['moblog_categories'] ),
						'field_id'					=> $row['moblog_field_id'] ,
						'status'					=> $row['moblog_status'] ,
						'author_id'					=> $row['moblog_author_id'] ,
						'moblog_auth_required'		=> $row['moblog_auth_required'] ,
						'moblog_auth_delete'		=> $row['moblog_auth_delete'] ,
						'moblog_upload_directory'	=> $row['moblog_upload_directory'] ,

						'moblog_image_size'			=> $row['moblog_image_size'],
						'moblog_thumb_size'			=> $row['moblog_thumb_size'],

						'moblog_email_type'			=> $row['moblog_email_type'] ,
						'moblog_email_address'		=> base64_decode($row['moblog_email_address'] ),
						'moblog_email_server'		=> $row['moblog_email_server'] ,
						'moblog_email_login'		=> base64_decode($row['moblog_email_login'] ),
						'moblog_email_password'		=> base64_decode($row['moblog_email_password'] ),
						'moblog_subject_prefix'		=> $row['moblog_subject_prefix'] ,
						'moblog_valid_from'			=> str_replace('|',"\n",$row['moblog_valid_from'] ),
						'moblog_ignore_text'		=> $row['moblog_ignore_text'] ,
						'moblog_template'			=> $row['moblog_template'] ,
						'moblog_time_interval'		=> $row['moblog_time_interval'] ,
						'moblog_enabled'			=> $row['moblog_enabled'] ,
						'moblog_file_archive'		=> $row['moblog_file_archive'] ,

						'moblog_allow_overrides'	=> ( ! isset($row['moblog_allow_overrides'] ) OR $row['moblog_allow_overrides']  == '') ? 'y' : $row['moblog_allow_overrides'] ,
						'moblog_sticky_entry'		=> ( ! isset($row['moblog_sticky_entry'] ) OR $row['moblog_sticky_entry']  == '') ? 'n' : $row['moblog_sticky_entry']
						);

			/** ------------------------------
			/**  Modify Form Creation Data
			/** ------------------------------*/

			if ($row['moblog_channel_id'] != 0 && array_key_exists($row['moblog_channel_id'], $this->channel_array))
			{
				// Upload Locations
				if ( ! isset($this->upload_loc_array[$data['moblog_upload_directory']]))
				{
					$data['moblog_upload_directory'] = '0';
				}

				$form_data['moblog_upload_directory'] = array($this->upload_loc_array, $data['moblog_upload_directory']);

				// Image Dimensions
				$size_options = $this->image_dim_array[$data['moblog_upload_directory']];

				if ( ! isset($size_options[$data['moblog_image_size']]))
				{
					$data['moblog_image_size'] = 0;
				}
				if ( ! isset($size_options[$data['moblog_thumb_size']]))
				{
					$data['moblog_thumb_size'] = 0;
				}

				$form_data['moblog_image_size'] = array(
					$size_options,
					$data['moblog_image_size']
				);

				$form_data['moblog_thumb_size'] = array(
					$size_options,
					$data['moblog_thumb_size']
				);


				$form_data['channel_id'][1] = $row['moblog_channel_id'];

				$new_array = array('none'=> lang('none'));

				foreach(ee()->api_channel_categories->cat_array as $key => $val)
				{
					if (is_array($val) && ! in_array($val['0'], explode('|', $this->channel_array[$query->row('moblog_channel_id')]['1'])))
					{
						unset(ee()->api_channel_categories->cat_array[$key]);
					}
				}

				if (count(ee()->api_channel_categories->cat_array > 0))
				{
					$new_array = array('all'=> lang('all'));
				}

				$new_array = array('none'=> lang('none'));
				$i=0;

				foreach (ee()->api_channel_categories->cat_array as $ckey => $cat)
				{
					if ($ckey-1 < 0 OR ! isset(ee()->api_channel_categories->cat_array[$ckey-1]))
					{
						$new_array['NULL_'.$i] = '-------';
					}

					$new_array[$cat['1']] = (str_replace("!-!","&nbsp;",$cat['2']));

					if (isset(ee()->api_channel_categories->cat_array[$ckey+1]) && ee()->api_channel_categories->cat_array[$ckey+1]['0'] != $cat['0'])
					{
						$new_array['NULL_'.$i] = '-------';
					}

					$i++;
				}

				$form_data['cat_id[]'] = array($new_array, $data['cat_id[]']);

				$new_array = array('none'=> lang('none'), 'open' => lang('open'), 'closed' => lang('closed'));

				foreach($this->status_array as $val)
				{
					if (is_array($val) && $val['0'] == $this->channel_array[$row['moblog_channel_id']]['2'])
					{
						if ($val['1'] == 'open' OR $val['1'] == 'closed')
						{
							$new_array[$val['1']] = lang($val['1']);
						}
						else
						{
							$new_array[$val['1']] = $val['1'];
						}
					}
				}

				if ( ! in_array($row['moblog_status'], $new_array))
				{
					$new_array[$row['moblog_status'] ] = $row['moblog_status'] ;
				}

				$form_data['status'] = array($new_array, $data['status']);
				$new_array = array('none'=> lang('none'));

				foreach($this->field_array as $val)
				{
					if (is_array($val) && $val['0'] == $this->channel_array[$row['moblog_channel_id']]['3'])
					{
						$new_array[$val['1']] = $val['2'];
					}
				}

				$form_data['field_id'] = array($new_array, $data['field_id']);
				$new_array = array('none'=> lang('none'));

				foreach($this->author_array as $val)
				{
					if (is_array($val) && $val['0'] == $row['moblog_channel_id'] )
					{
						$new_array[$val['1']] = $val['2'];
					}
				}

				$form_data['author_id'] = array($new_array, $data['author_id']);
			}
		}

		/** -----------------------------
		/**  Create the form
		/** -----------------------------*/

		$vars['submit_text'] = ($id != '' && is_numeric($id)) ? 'update' : 'submit';

		// Set the default types
		foreach($form_data as $key => $var)
		{
			if (isset($data[$key]) && ! is_array($var))
			{
				$form_data[$key] = $data[$key];
			}
		}

		$vars['values'] = $form_data;

		if (ee()->form_validation->run() === FALSE)
		{
			// If the "basis_flag" $_POST is set, it means they have come from the form
			// that asks if they want to build this moblog based on another one. We need
			// to unset the form validation error messages.
			if (ee()->input->post('basis_flag'))
			{
				unset(ee()->form_validation->_field_data);
			}

			return ee()->load->view('update', $vars, TRUE);
		}

		$this->update_moblog();
	}

	// --------------------------------------------------------------------

	/**
	 * JavaScript filtering code
	 *
	 * Creates some javascript functions that are used to switch
	 * various pull-down menus
	 *
	 * @access	public
	 * @return	void
	 */
	function _filtering_menus($form_name)
	{
		// In order to build our filtering options we need to gather
		// all the channels, categories and custom statuses

		/** -----------------------------
		/**  Allowed Channels
		/** -----------------------------*/

		$allowed_channels = ee()->functions->fetch_assigned_channels(TRUE);

		if (count($allowed_channels) > 0)
		{
			// Fetch channel titles
			ee()->db->select('channel_title, channel_id, cat_group, status_group, field_group');

			if ( ! ee()->cp->allowed_group('can_edit_other_entries'))
			{
				ee()->db->where_in('channel_id', $allowed_channels);
			}

			ee()->db->order_by('channel_title');
			$query = ee()->db->get('channels');

			foreach ($query->result_array() as $row)
			{
				$this->channel_array[$row['channel_id']] = array(str_replace('"','',$row['channel_title']), $row['cat_group'], $row['status_group'], $row['field_group']);
			}
		}

		ee()->legacy_api->instantiate('channel_categories');

		//  Category Tree
		$cat_array = ee()->api_channel_categories->category_form_tree('y', FALSE, 'all');

		/** -----------------------------
		/**  Entry Statuses
		/** -----------------------------*/

		ee()->db->select('group_id, status');
		ee()->db->order_by('status_order');
		$query = ee()->db->get('statuses');

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$this->status_array[]  = array($row['group_id'], $row['status']);
			}
		}

		/** -----------------------------
		/**  Custom Channel Fields
		/** -----------------------------*/

		/* -------------------------------------
		/*  Hidden Configuration Variable
		/*  - moblog_allow_nontextareas => Removes the textarea only restriction
		/*	for custom fields in the moblog module (y/n)
		/* -------------------------------------*/

		ee()->db->select('group_id, field_label, field_id');
		ee()->db->order_by('field_label');

		if (ee()->config->item('moblog_allow_nontextareas') != 'y')
		{
			ee()->db->where('channel_fields.field_type', 'textarea');
		}

		$query = ee()->db->get('channel_fields');

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$this->field_array[]  = array($row['group_id'], $row['field_id'], str_replace('"','',$row['field_label']));
			}
		}

		/** -----------------------------
		/**  SuperAdmins
		/** -----------------------------*/

		ee()->db->select('member_id, username, screen_name');
		ee()->db->where('group_id', '1');
		$query = ee()->db->get('members');

		foreach ($query->result_array() as $row)
			{
				$author = ($row['screen_name'] == '') ? $row['username'] : $row['screen_name'];

				foreach($this->channel_array as $key => $value)
				{
					$this->author_array[]  = array($key, $row['member_id'], str_replace('"','',$author));
				}
			}

		/** -----------------------------
		/**  Assignable Channel Authors
		/** -----------------------------*/
		$dbp = ee()->db->dbprefix;

		ee()->db->select('channels.channel_id, members.member_id, members.group_id, members.username, members.screen_name');
		ee()->db->from(array('channels', 'members', 'channel_member_groups'));
		ee()->db->where("({$dbp}channel_member_groups.channel_id = {$dbp}channels.channel_id OR {$dbp}channel_member_groups.channel_id IS NULL)");
		ee()->db->where("{$dbp}members.group_id", "{$dbp}channel_member_groups.group_id", FALSE);

		$query = ee()->db->get();

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$author = ($row['screen_name'] == '') ? $row['username'] : $row['screen_name'];

				$this->author_array[]  = array($row['channel_id'], $row['member_id'], str_replace('"','',$author));
			}
		}

		// Create JSON Reference

		// Mixing php with output buffering was ugly, so we'll build out a js objects with
		// all the information we need and then manipulate that in javascript

		$channel_info = array();

		foreach ($this->channel_array as $key => $val)
		{
			$any = 0;
			$cats = array();

			if (count($cat_array) > 0)
			{
				$last_group = 0;

				foreach ($cat_array as $k => $v)
				{
					if (in_array($v['0'], explode('|', $val['1'])))
					{
						if ( ! isset($set))
						{
							$cats[] = array('', lang('all'));

							$set = 'y';
						}

						if ($last_group == 0 OR $last_group != $v['0'])
						{
							$last_group = $v['0'];
						}

						$cats[] = array($v['1'], $v['2']);
					}
				}

				if ( ! isset($set))
		        {
					$cats[] = array('none', lang('none'));
		        }
				unset($set);
			}

			$channel_info[$key]['moblog_categories'] = $cats;

			$statuses = array();

			$statuses[] = array('none', lang('none'));

			if (count($this->status_array) > 0)
			{
				foreach ($this->status_array as $k => $v)
				{
					if ($v['0'] == $val['2'])
					{
						$status_name = ($v['1'] == 'closed' OR $v['1'] == 'open') ?  lang($v['1']) : $v['1'];
						$statuses[] = array($v['1'], $status_name);
					}
				}
			}
			else
			{
				$statuses[] = array($v['1'], lang('open'));
				$statuses[] = array($v['1'], lang('closed'));
			}

			$channel_info[$key]['moblog_status'] = $statuses;

			$fields = array();

			$fields[] = array('none', lang('none'));


			if (count($this->field_array) > 0)
			{
				foreach ($this->field_array as $k => $v)
				{
					if ($v['0'] == $val['3'])
					{
						$fields[] = array($v['1'], $v['2']);
					}
				}
			}

			$channel_info[$key]['moblog_field_id'] = $fields;

			$authors = array();

			$authors[] = array('0', lang('none'));

			if (count($this->author_array) > 0)
			{
				$inserted_authors = array();

				foreach ($this->author_array as $k => $v)
				{
					if ($v['0'] == $key && ! in_array($v['1'],$inserted_authors))
					{
						$inserted_authors[] = $v['1'];
						$authors[] = array($v['1'], $v['2']);
					}
				}
			}

			$channel_info[$key]['moblog_author_id'] = $authors;
		}

		$channel_info = json_encode($channel_info);
		$none_text = lang('none');

		$javascript = <<<MAGIC

// An object to represent our channels
var channel_map = $channel_info;

var empty_select =  '<option value="0">$none_text</option>';
var spaceString = new RegExp('!-!', "g");

// We prep the magic array as soon as we can, basically
// converting everything into option elements
(function() {
	jQuery.each(channel_map, function(key, details) {

		// Go through each of the individual settings and build a proper dom element
		jQuery.each(details, function(group, values) {
			var html = new String();

			if (group == 'moblog_categories') {
				var checkbox_values = [];
				// Categories are checkboxes
				$('input[name="moblog_categories[]"]').each(function() {
					checkbox_values.push(this.value);
				});
				jQuery.each(values, function(a, b) {
					var checked = '',
						chosen = '';
					if ($.inArray(b[0], checkbox_values) > -1) {
						checked = ' checked';
						chosen = ' chosen';
					}
					html += '<label class="choice block'+chosen+'"><input type="checkbox" name="moblog_categories[]" value ="' + b[0] + '"'+checked+'>' + b[1].replace(spaceString, String.fromCharCode(160)) + "</label>";
				});
			} else {
				var value = $('select[name="'+group+'"]').val();
				// Add the new option fields
				jQuery.each(values, function(a, b) {
					var selected = (value == b[0]) ? ' selected' : '';console.log(value + ' ' + b[0]);
					html += '<option value="' + b[0] + '"'+selected+'>' + b[1].replace(spaceString, String.fromCharCode(160)) + "</option>";
					//console.log(html);
				});
			}

			channel_map[key][group] = html;
		});
	});
})();

// Change the submenus
// Gets passed the channel id
function changemenu(index)
{
	var channels = 'null';

	if (channel_map[index] === undefined) {
		$('select[name=moblog_field_id], select[name="moblog_categories"], select[name=moblog_status], select[name=moblog_author_id]').empty().append(empty_select);
	}
	else {
		jQuery.each(channel_map[index], function(key, val) {
			switch(key) {
				case 'moblog_field_id':		$('select[name=moblog_field_id]').empty().append(val);
					break;
				case 'moblog_categories':	$('input[name="moblog_categories[]"]').parents('.setting-field').empty().append(val);
					break;
				case 'moblog_status':	$('select[name=moblog_status]').empty().append(val);
					break;
				case 'moblog_author_id':		$('select[name=moblog_author_id]').empty().append(val);
					break;
			}
		});
	}
}

$('select[name=moblog_channel_id]').change(function() {
	changemenu(this.value);
}).change();

MAGIC;

		// And same idea for file upload dirs and dimensions
		$this->upload_loc_array = array('0' => lang('none'));
		$this->image_dim_array = array('0' => $this->upload_loc_array);

		// Fetch Upload Directories
		ee()->load->model(array('file_model', 'file_upload_preferences_model'));

		$sizes_q = ee()->file_model->get_dimensions_by_dir_id();
		$sizes_array = array();

		foreach ($sizes_q->result_array() as $row)
		{
			$sizes_array[$row['upload_location_id']][$row['id']] = $row['title'];
		}

		$upload_q = ee()->file_upload_preferences_model->get_file_upload_preferences(ee()->session->userdata['group_id']);

		foreach ($upload_q as $row)
		{
			$this->image_dim_array[$row['id']] = array('0' => lang('none'));
			$this->upload_loc_array[$row['id']] = $row['name'];

			// Get sizes
			if (isset($sizes_array[$row['id']]))
			{
				foreach ($sizes_array[$row['id']] as $id => $title)
				{
					$this->image_dim_array[$row['id']][$id] = $title;
				}
			}
		}

		$upload_info = json_encode($this->image_dim_array);

		$javascript .= <<<MAGIC

// An object to represent our channels
var upload_info = $upload_info;

var empty_select =  '<option value="0">$none_text</option>';
var spaceString = new RegExp('!-!', "g");

// We prep the magic array as soon as we can, basically
// converting everything into option elements
(function(undefined) {
	jQuery.each(upload_info, function(key, options) {

		var html = '';

		// add option fields
		jQuery.each(options, function(k, v) {

			html += '<option value="' + k + '">' + v.replace(spaceString, String.fromCharCode(160)) + "</option>";
		});

		if (html) {
			upload_info[key] = html;
		}
	});
})();

// Change the submenus
// Gets passed the channel id
function upload_changemenu(index)
{
	$('select[name=moblog_image_size]').empty().append(upload_info[index]);
	$('select[name=moblog_thumb_size]').empty().append(upload_info[index]);
}

$('select[name=moblog_upload_directory]').change(function() {
	upload_changemenu(this.value);
}).change();

MAGIC;



		ee()->javascript->output($javascript);
	}

	// --------------------------------------------------------------------

	/**
	 * Form validation duplicate name callback
	 *
	 * @access	public
	 * @return	bool
	 */
	function _check_duplicate($str, $which = 'moblog_short_name')
	{
		if ($edit_id = ee()->form_validation->old_value('id'))
		{
			ee()->db->start_cache();
			ee()->db->where('moblog_id !=', $edit_id);
			ee()->db->stop_cache();
		}

		if ($which == 'moblog_short_name')
		{
			// Short Name Check - Zzzzz...

			ee()->db->where('moblog_short_name', $str);
			$count = ee()->db->count_all_results('moblogs');

			if ($count > 0)
			{
				ee()->form_validation->set_message('_check_duplicate', lang('moblog_taken_short_name'));
				return FALSE;
			}
		}
		elseif ($which = 'moblog_full_name')
		{
			// Full Name Check

			ee()->db->where('moblog_full_name', $str);
			$count = ee()->db->count_all_results('moblogs');

			ee()->db->flush_cache();

			if ($count > 0)
			{
				ee()->form_validation->set_message('_check_duplicate', lang('moblog_taken_name'));
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Update Moblog
	 *
	 * @access	public
	 * @return	void
	 */
	function update_moblog()
	{
		// In case the select none/all and any others.
		if (isset($_POST['cat_id']) && count($_POST['cat_id']) > 1 && (in_array('all',$_POST['cat_id']) OR in_array('none',$_POST['cat_id'])))
		{
			if (in_array('all', $_POST['cat_id']))
			{
				$_POST['cat_id'] = array('all');
			}
			else
			{
				$_POST['cat_id'] = array('none');
			}
		}


		// Format from emails

		$from_values = '';

		if (isset($_POST['moblog_valid_from']))
		{
			$from_emails = explode(",", ee()->input->post('moblog_valid_from'));

			if (count($from_emails) > 0)
			{
				$from_values = implode('|', $from_emails);
			}
		}

		$post_data = array(
						'moblog_full_name'			=> $_POST['moblog_full_name'],
						'moblog_short_name'			=> $_POST['moblog_short_name'],
						'moblog_channel_id'			=> ( ! isset($_POST['channel_id']) OR $_POST['channel_id'] == 'null') ? 'none' : $_POST['channel_id'],
						'moblog_categories'			=> ( ! isset($_POST['cat_id'])) ? 'none' : implode('|',$_POST['cat_id']),
						'moblog_field_id'			=> ( ! isset($_POST['field_id'])) ? 'none' : $_POST['field_id'],
						'moblog_status'				=> ( ! isset($_POST['status'])) ? 'none' : $_POST['status'],
						'moblog_author_id'			=> ( ! isset($_POST['author_id'])) ? 'none' : $_POST['author_id'],
						'moblog_auth_required'		=> $_POST['moblog_auth_required'],
						'moblog_auth_delete'		=> $_POST['moblog_auth_delete'],
						'moblog_upload_directory'	=> $_POST['moblog_upload_directory'],

						'moblog_image_size'			=> $_POST['moblog_image_size'],
						'moblog_thumb_size'			=> $_POST['moblog_thumb_size'],

						'moblog_email_type'			=> $_POST['moblog_email_type'],
						'moblog_email_address'		=> base64_encode($_POST['moblog_email_address']),
						'moblog_email_server'		=> $_POST['moblog_email_server'],
						'moblog_email_login'		=> base64_encode($_POST['moblog_email_login']),
						'moblog_email_password'		=> base64_encode($_POST['moblog_email_password']),
						'moblog_subject_prefix'		=> ( ! isset($_POST['moblog_subject_prefix'])) ? '' : $_POST['moblog_subject_prefix'],
						'moblog_valid_from'			=> $from_values,
						'moblog_ignore_text'		=> ( ! isset($_POST['moblog_ignore_text'])) ? '' : $_POST['moblog_ignore_text'],
						'moblog_template'			=> ( ! isset($_POST['moblog_template'])) ? '' : $_POST['moblog_template'],
						'moblog_time_interval'		=> $_POST['moblog_time_interval'],
						'moblog_enabled'			=> $_POST['moblog_enabled'],
						'moblog_file_archive'		=> $_POST['moblog_file_archive'],

						'moblog_allow_overrides'	=> ( ! isset($_POST['moblog_allow_overrides'])) ? 'y' : $_POST['moblog_allow_overrides'],
						'moblog_sticky_entry'		=> ( ! isset($_POST['moblog_sticky_entry'])) ? 'n' : $_POST['moblog_sticky_entry']
						);

		// In 1.6 this module wasn't strict mode compatible and just inserted 'none'
		// into integer fields. This is a quick hack to simply unset those. As well
		// as a check to make sure that we have a usable id

		if (isset($_POST['id']) && ! is_numeric($_POST['id']))
		{
			unset($_POST['id']);
		}

		$int_fields = array('moblog_id', 'moblog_channel_id', 'moblog_time_interval', 'moblog_author_id', 'moblog_upload_directory', 'moblog_image_width',
							'moblog_image_height', 'moblog_resize_width', 'moblog_resize_height', 'moblog_thumbnail_width', 'moblog_thumbnail_height'
							);

		foreach($int_fields as $field)
		{
			if (isset($post_data[$field]) && ( ! is_numeric($post_data[$field])))
			{
				unset($post_data[$field]);
			}
		}

		if ( ! isset($_POST['id']))
		{
			$sql = ee()->db->insert_string('exp_moblogs', $post_data);
			ee()->db->query($sql);
			$message = lang('moblog_created');
		}
		else
		{
			$sql = ee()->db->update_string('exp_moblogs', $post_data, "moblog_id = '".ee()->db->escape_str($_POST['id'])."'");
			ee()->db->query($sql);
			$message = lang('moblog_updated');
		}

		ee()->session->set_flashdata('message_success', $message);
		ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog');
	}


	/** -------------------------------------------
	/**  Delete Confirm
	/** -------------------------------------------*/
	function delete_confirm()
	{
		if ( ! ee()->input->post('toggle'))
		{
			ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog');
		}

		ee()->load->helper('form');

		$vars['cp_page_title'] = lang('moblog_delete_confirm_title');

		ee()->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog', lang('moblog'));
		$vars['form_action'] = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog'.AMP.'method=delete_moblogs';

		foreach ($_POST['toggle'] as $val)
		{
			$vars['damned'][] = $val;
		}

		return ee()->load->view('delete_confirm', $vars, TRUE);
	}

	/** -------------------------------------------
	/**  Delete Moblogs
	/** -------------------------------------------*/
	function delete_moblogs()
	{
		if ( ! ee()->input->post('delete'))
		{
			ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog');
		}

		foreach ($_POST['delete'] as $key => $val)
		{
			ee()->db->or_where('moblog_id', $val);
		}

		ee()->db->delete('moblogs');

		$message = (count($_POST['delete']) == 1) ? lang('moblog_deleted') : lang('moblogs_deleted');

		ee()->session->set_flashdata('message_success', $message);
		ee()->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=moblog');
	}


	/** -------------------------
	/**  Check Moblog
	/** -------------------------*/

	function check($moblog_id)
	{
		$where = array(
			'moblog_enabled'	=> 'y',
			'moblog_id'			=> $moblog_id
		);

		$query = ee()->db->get_where('moblogs', $where);

		if ($query->num_rows() == 0)
		{
			return ee()->output->show_user_error('submission', array(lang('invalid_moblog')));
		}

		if ( ! class_exists('Moblog'))
		{
			require PATH_ADDONS.'moblog/mod.moblog.php';
		}

		$MP = new Moblog();
		$MP->moblog_array = $query->row_array();

		$error = FALSE;

		if ($MP->moblog_array['moblog_email_type'] == 'imap')
		{
			$this->_moblog_check_return($MP->check_imap_moblog(), $MP);
		}
		else
		{
			$this->_moblog_check_return($MP->check_pop_moblog(), $MP);
		}
	}

	/** -------------------------
	/**  Moblog Check Return
	/** -------------------------*/

	function _moblog_check_return($response, $MP)
	{
		if ( ! $response)
		{
			ee('Alert')->makeInline('moblogs-table')
				->asIssue()
				->withTitle(lang('moblog_check_failure'))
				->addToBody($MP->errors())
				->defer();
		}
		else
		{
			ee('Alert')->makeInline('moblogs-table')
				->asSuccess()
				->withTitle(lang('moblog_check_success'))
				->addToBody(lang('emails_done').NBS.$MP->emails_done)
				->addToBody(lang('entries_added').NBS.$MP->entries_added)
				->addToBody(lang('attachments_uploaded').NBS.$MP->uploads)
				->defer();
		}

		ee()->functions->redirect(ee('CP/URL', 'addons/settings/moblog', ee()->cp->get_url_state()));
	}

}
// END CLASS

/* End of file mcp.moblog.php */
/* Location: ./system/expressionengine/modules/moblog/mcp.moblog.php */
