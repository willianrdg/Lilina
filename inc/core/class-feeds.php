<?php
/**
 * Feed management class
 *
 * @package Lilina
 * @subpackage Administration
 */

/**
 * Feed management class
 *
 * @package Lilina
 * @subpackage Administration
 */
class Feeds {
	protected $feeds;

	public function __construct() {
		if(!class_exists('SimplePie'))
			require_once(LILINA_INCPATH . '/contrib/simplepie/simplepie.inc');

		$this->file = new DataHandler(LILINA_CONTENT_DIR . '/system/config/');
		$this->load();
	}

	/**
	 * Add a new feed to the database
	 *
	 * Adds the specified feed name and URL to the database. If no name is set
	 * by the user, it fetches one from the feed. If the URL specified is a HTML
	 * page and not a feed, it lets SimplePie do autodiscovery and uses the XML
	 * url returned.
	 *
	 * @since 1.0
	 *
	 * @param string $url URL to feed or website (if autodiscovering)
	 * @param string $name Title/Name of feed
	 * @param string $cat Category to add feed to
	 * @return bool True if succeeded, false if failed
	 */
	public function add($url, $name = '', $cat = 'default') {
		if(empty($url)) {
			throw new Exception(_r("Couldn't add feed: No feed URL supplied"), Errors::get_code('admin.feeds.no_url'));
		}

		if(!preg_match('#https|http|feed#', $url)) {
			if(strpos($url, '://')) {
				throw new Exception(_r('Unsupported URL protocol'), Errors::get_code('admin.feeds.protocol_error'));
			}

			$url = 'http://' . $url;
		}
		require_once(LILINA_INCPATH . '/contrib/simplepie/simplepie.inc');
		$feed_info = new SimplePie();
		$feed_info->set_useragent(LILINA_USERAGENT . ' SimplePie/' . SIMPLEPIE_BUILD);
		$feed_info->set_stupidly_fast(true);
		$feed_info->enable_cache(false);
		$feed_info->set_feed_url($url);
		$feed_info->init();
		$feed_error = $feed_info->error();
		$feed_url = $feed_info->subscribe_url();

		if(!empty($feed_error)) {
			throw new Exception(
				sprintf(_r( "Couldn't add feed: %s is not a valid URL or the server could not be accessed. Additionally, no feeds could be found by autodiscovery." ), $url ),
				Errors::get_code('admin.feeds.invalid_url')
			);
		}

		if(empty($name)) {
			//Get it from the feed
			$name = $feed_info->get_title();
		}

		$id = sha1($feed_url);

		$this->feeds[$id] = array(
			'feed'	=> $feed_url,
			'url'	=> $feed_info->get_link(),
			'id'	=> $id,
			'name'	=> $name,
			'cat'	=> $cat,
		);

		$this->feeds[$id] = apply_filters('feed-create', $this->feeds[$id], $url);
		$this->save();
		return sprintf( _r('Added feed "%1$s"'), $name );
	}

	public function get($id) {
		$feed = false;
		if(!empty($this->feeds[$id]))
			$feed = $this->feeds[$id];
		return apply_filters('feeds-get', $feed, $id);
	}

	public function getAll() {
		return apply_filters('feeds-get_all', $this->feeds);
	}

	/**
	 * Change a feed's properties
	 *
	 * @param string $id ID of the feed to change
	 * @return bool
	 */
	public function update($id, $data = array()) {
		if(empty($data)) {
			throw new Exception(_r('No change specified'), Errors::get_code('admin.feeds.no_id_or_url'));
			return false;
		}

		if(empty($this->feeds[$id])) {
			throw new Exception(_r('Feed does not exist'), Errors::get_code('admin.feeds.invalid_id'));
		}

		$old = $this->feeds[$id];
		$this->feeds[$id] = array_merge($this->feeds[$id], $data);
		$this->feeds[$id] = apply_filters('feed-update', $this->feeds[$id], $data, $old);
		$this->save();
		return sprintf(_r('Changed "%s" (#%d)'), $this->feeds[$id]['name'], $id);
	}

	/**
	 * Remove a feed
	 *
	 * @param string $id ID of the feed to remove
	 * @return bool
	 */
	public function delete($id) {
		if(empty($this->feeds[$id])) {
			throw new Exception(_r('Feed does not exist'), Errors::get_code('admin.feeds.invalid_id'));
		}

		//Make a copy for later.
		$removed = $this->feeds[$id];
		unset($this->feeds[$id]);

		$removed = apply_filters('feed-delete', $removed);
		$this->save();
		return sprintf(
			_r('Removed feed &mdash; <a href="%s">Undo</a>?'),
			'feeds.php?action=add&amp;add_name=' . urlencode($removed['name']) . '&amp;add_url=' . urlencode($removed['feed']) . '&amp;id=' . urlencode($removed['id'])
		);
	}

	/**
	 * Load feeds from database
	 *
	 * @return array
	 */
	protected function load() {
		$data = $this->file->load('feeds.json');
		if($data !== null)
			$data = json_decode($data, true);
		else
			$data = array();

		$this->feeds = $data;
	}

	/**
	 * Save feeds to database
	 *
	 * @return bool True if feeds were successfully saved, false otherwise
	 */
	protected function save() {
		return $this->file->save('feeds.json', json_encode($this->feeds));
	}
}