<?php
/**
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2018, EllisLab, Inc. (https://ellislab.com)
 * @license   https://expressionengine.com/license
 */

namespace EllisLab\ExpressionEngine\Service\EntryListing;

use Serializable;
use BadMethodCallException;
use InvalidArgumentException;
use EllisLab\ExpressionEngine\Service\View\View;

/**
 * CP Entry Listing Service
 */
class EntryListing {

	/**
	 * @var Filter $channel_filter Channel Filter object
	 */
	public $channel_filter;

	/**
	 * @var Filter $category_filter Category Filter object
	 */
	public $category_filter;

	/**
	 * @var Filter $status_filter Status Filter object
	 */
	public $status_filter;

	/**
	 * @var int $site_id Current site ID
	 */
	protected $site_id;

	/**
	 * @var boolean $is_admin Whether or not a Super Admin is making this
	 * request, skips $allowed_channels check
	 */
	protected $is_admin;

	/**
	 * @var array $allowed_channels IDs of channels this user is allowed to access
	 */
	protected $allowed_channels;

	/**
	 * @var int $now Timestamp of current time, used to filter entries by date
	 */
	protected $now;

	/**
	 * @var string $search_value Search critera to filter entries by
	 */
	protected $search_value;

	/**
	 * @var Query\Builder $entries Builder object for the channel entries
	 */
	protected $entries;

	/**
	 * @var FilterFactory $filters FilterFactory object
	 */
	protected $filters;

	/**
	 * @var Collection of channel models
	 */
	protected $channels;

	/**
	 * Constructor
	 * @param int $site_id Current site ID
	 * @param boolean $is_admin Whether or not a Super Admin is making this
	 * request, skips $allowed_channels check
	 * @param array $allowed_channels IDs of channels this user is allowed to access
	 * @param int $now Timestamp of current time, used to filter entries by date
	 * @param string $search_value Search critera to filter entries by
	 */
	public function __construct($site_id, $is_admin, $allowed_channels = array(), $now = NULL, $search_value = NULL)
	{
		$this->site_id = $site_id;
		$this->is_admin = $is_admin;
		$this->allowed_channels = $allowed_channels;
		$this->now = $now;
		$this->search_value = $search_value;

		$this->setupFilters();
		$this->setupEntries();
	}

	/**
	 * Getter for channel entries Query\Builder object
	 *
	 * @return Query\Builder
	 */
	public function getEntries()
	{
		return $this->entries;
	}

	/**
	 * Getter for channel entries Query\Builder object
	 *
	 * @return FilterFactory
	 */
	public function getFilters()
	{
		$count = $this->getEntryCount();

		// Add this last to get the right $count
		$this->filters->add('Perpage', $count, 'all_entries');

		return $this->filters;
	}

	public function getEntryCount()
	{
		static $count;

		if (is_null($count))
		{
			$count = $this->getEntries()->count();
		}

		return $count;
	}

	public function getChannelModelFromFilter()
	{
		static $channel = NULL;

		if (is_null($channel)
			&& $this->channel_filter
			&& $this->channel_filter->value())
		{
			$channel = ee('Model')->get('Channel', $this->channel_filter->value())
				->first();
		}

		return $channel;
	}

	/**
	 * Sets up our various filters for showing an entry listing and
	 * creates the FilterFactory object
	 */
	private function setupFilters()
	{
		$this->channel_filter = $this->createChannelFilter();

		$channel = $this->getChannelModelFromFilter();

		$this->category_filter = $this->createCategoryFilter($channel);
		$this->status_filter = $this->createStatusFilter($channel);

		$this->filters = ee('CP/Filter')
			->add($this->channel_filter)
			->add($this->category_filter)
			->add($this->status_filter)
			->add('Date')
			->add('Keyword');
	}

	/**
	 * Given various filters and permissions, sets up an entries Query\Builder
	 * to be passed on to the caller to optionally add more filtering to
	 */
	protected function setupEntries()
	{
		$entries = ee('Model')->get('ChannelEntry')
			->with('Channel', 'Author')
			->fields('entry_id', 'title', 'Author.screen_name', 'Author.username', 'Channel.channel_title', 'Channel.live_look_template', 'Channel.status_group', 'author_id', 'comment_total', 'entry_date', 'status')
			->filter('site_id', $this->site_id);

		// We need to filter by Channel first (if necissary) as that will
		// impact the entry count for the perpage filter
		$channel_id = $this->channel_filter->value();

		// If we have a selected channel filter, and we are not an admin, we
		// first need to ensure it is in the list of assigned channels. If it
		// is we will filter by that id. If not we throw an error.
		$channel = NULL;
		if ($channel_id)
		{
			if ($this->is_admin || in_array($channel_id, $this->allowed_channels))
			{
				$entries->filter('channel_id', $channel_id);
				$channel = $this->getChannelModelFromFilter();

				$channel_name = $channel->channel_title;
			}
			else
			{
				show_error(lang('unauthorized_access'), 403);
			}
		}
		// If we have no selected channel filter, and we are not an admin, we
		// need to filter via WHERE IN
		else
		{
			if ( ! $this->is_admin)
			{
				if (empty($this->allowed_channels))
				{
					show_error(lang('no_channels'));
				}

				$entries->filter('channel_id', 'IN', $this->allowed_channels);
			}
		}

		if ($this->category_filter->value())
		{
			$entries->with('Categories')
				->filter('Categories.cat_id', $this->category_filter->value());
		}

		if ($this->status_filter->value())
		{
			$entries->filter('status', $this->status_filter->value());
		}

		if ( ! empty($this->search_value))
		{
			$search_fields = array('title');

			if (isset($channel))
			{
				$custom_fields = $channel->getAllCustomFields();
			}
			else
			{
				$custom_fields = array();

				foreach ($this->getChannels() as $channel)
				{
					$custom_fields = array_merge($custom_fields, $channel->getAllCustomFields()->asArray());
				}
			}

			foreach ($custom_fields as $cf)
			{
				$search_fields[] = 'field_id_'.$cf->getId();
			}

			$entries->search($search_fields, $this->search_value);
		}

		$filter_values = $this->filters->values();

		if ( ! empty($filter_values['filter_by_date']))
		{
			if (is_array($filter_values['filter_by_date']))
			{
				$entries->filter('entry_date', '>=', $filter_values['filter_by_date'][0]);
				$entries->filter('entry_date', '<', $filter_values['filter_by_date'][1]);
			}
			else
			{
				$entries->filter('entry_date', '>=', $this->now - $filter_values['filter_by_date']);
			}
		}

		$entries->with('Autosaves', 'Author', 'Channel');

		$this->entries = $entries;
	}

	/**
	 * Creates a channel fllter
	 */
	public function createChannelFilter()
	{
		$channels = $this->getChannels();
		$channel_filter_options = $channels->getDictionary('channel_id', 'channel_title');

		$channel_filter = ee('CP/Filter')->make('filter_by_channel', 'filter_by_channel', $channel_filter_options);
		$channel_filter->setPlaceholder(lang('filter_channels'));
		$channel_filter->useListFilter(); // disables custom values
		return $channel_filter;
	}

	/**
	 * Get the allowed channels
	 */
	protected function getChannels()
	{
		if ( ! isset($this->channels))
		{
			$allowed_channel_ids = ($this->is_admin) ? NULL : $this->allowed_channels;
			$this->channels = ee('Model')->get('Channel', $allowed_channel_ids)
				->fields('channel_id', 'channel_title')
				->filter('site_id', ee()->config->item('site_id'))
				->order('channel_title', 'asc')
				->all();
		}

		return $this->channels;
	}

	/**
	 * Creates a category fllter
	 */
	private function createCategoryFilter($channel = NULL)
	{
		$cat_id = ($channel) ? explode('|', $channel->cat_group) : NULL;

		$category_groups = ee('Model')->get('CategoryGroup', $cat_id)
			->with('Categories')
			->filter('site_id', ee()->config->item('site_id'))
			->filter('exclude_group', '!=', 1)
			->all();

		$category_options = array();
		foreach ($category_groups as $group)
		{
			$sort_column = ($group->sort_order == 'a') ? 'cat_name' : 'cat_order';
			foreach ($group->Categories->sortBy($sort_column) as $category)
			{
				$category_options[$category->cat_id] = $category->cat_name;
			}
		}

		$categories = ee('CP/Filter')->make('filter_by_category', 'filter_by_category', $category_options);
		$categories->setPlaceholder(lang('filter_categories'));
		$categories->useListFilter(); // disables custom values
		return $categories;
	}

	/**
	 * Creates a category fllter
	 */
	private function createStatusFilter($channel = NULL)
	{
		if ($channel)
		{
			$statuses = $channel->Statuses;
		}
		else
		{
			$statuses = ee('Model')->get('Status')->all();
		}

		$status_options = array();

		foreach ($statuses as $status)
		{
			$status_name = ($status->status == 'closed' OR $status->status == 'open') ?  lang($status->status) : $status->status;
			$status_options[$status->status] = $status_name;
		}

		$status = ee('CP/Filter')->make('filter_by_status', 'filter_by_status', $status_options);
		$status->disableCustomValue();
		return $status;
	}
}

// EOF
