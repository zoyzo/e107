<?php
/*
 * e107 website system
 *
 * Copyright (C) 2001-2008 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * e107 Preference Handler
 *
 * $Source: /cvs_backup/e107_0.8/e107_handlers/pref_class.php,v $
 * $Revision: 1.18 $
 * $Date: 2009-09-04 15:27:28 $
 * $Author: secretr $
*/

if (!defined('e107_INIT')) { exit; }
require_once(e_HANDLER.'model_class.php');

/**
 * Base preference object - shouldn't be used direct,
 * used internal by {@link e_plugin_pref} and {@link e_core_pref classes}
 *
 * @package e107
 * @category e107_handlers
 * @version 1.0
 * @author SecretR
 * @copyright Copyright (c) 2009, e107 Inc.
 */
class e_pref extends e_model 
{
	/**
	 * Preference ID - DB row value
	 *
	 * @var string
	 */
	protected $prefid;
	
	/**
	 * Preference ID alias e.g. 'core' is an alias of prefid 'SitePrefs'
	 * Used in e.g. server cache file name
	 * 
	 * @var string
	 */
	protected $alias;
	
	/**
	 * Runtime cache, set on first data load
	 *
	 * @var string
	 */
	protected $pref_cache = '';
	
	/**
	 * Backward compatibility - serialized preferences
	 * Note: serialized preference storage is deprecated
	 *
	 * @var boolean
	 */
	protected $serial_bc = false;
	
	/**
	 * If true, $prefid.'_Backup' row will be created/updated
	 * on every {@link save()} call
	 *
	 * @var boolean
	 */
	protected $set_backup = false;
	
	/**
	 * Constructor
	 *
	 * @param string $prefid
	 * @param string $alias
	 * @param array $data
	 * @param boolean $sanitize_data
	 */
	function __construct($prefid, $alias = '', $data = array(), $sanitize_data = true)
	{
		require_once(e_HANDLER.'cache_handler.php');
		
		$this->prefid = preg_replace('/[^\w\-]/', '', $prefid);
		if(empty($alias))
		{
			$alias = $prefid;
		}
		$this->alias = preg_replace('/[^\w\-]/', '', $alias);
		
		$this->loadData($data, $sanitize_data);
	}
	
	/**
	 * Advanced getter - $pref_name is parsed (multidimensional arrays support), alias of {@link e_model::getData()}
	 * If $pref_name is empty, all data array will be returned
	 *
	 * @param string $pref_name
	 * @param mixed $default
	 * @param integer $index
	 * @return mixed
	 */
	public function getPref($pref_name = '', $default = null, $index = null)
	{
		return $this->getData($pref_name, $default, $index);
	}
	
	/**
	 * Simple getter - $pref_name is not parsed (no multidimensional arrays support), alias of {@link e_model::get()}
	 *
	 * @param string $pref_name
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($pref_name, $default = null)
	{
		return parent::get((string) $pref_name, $default);
	}
	
	/**
	 * Advanced setter - $pref_name is parsed (multidimensional arrays support)
	 * Object data reseting is not allowed, adding new pref is allowed
	 * @param string|array $pref_name
	 * @param mixed $value
	 * @return e_pref
	 */
	public function setPref($pref_name, $value = null)
	{
		global $pref;
		//object reset not allowed, adding new pref is allowed
		if(empty($pref_name))
		{
			return $this; 
		}

		//Merge only allowed
		if(is_array($pref_name))
		{
			$this->mergeData($pref_name, false, false, false);
			return $this;
		}
		
		parent::setData($pref_name, $value, false);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Advanced setter - $pref_name is parsed (multidimensional arrays support)
	 * Object data reseting is not allowed, adding new pref is controlled by $strict parameter
	 * @param string|array $pref_name
	 * @param mixed $value
	 * @param boolean $strict true - update only, false - same as setPref()
	 * @return e_pref
	 */
	public function updatePref($pref_name, $value = null, $strict = false)
	{
		global $pref;
		//object reset not allowed, adding new pref is not allowed
		if(empty($pref_name))
		{
			return $this; 
		}
		
		//Merge only allowed
		if(is_array($pref_name))
		{
			$this->mergeData($pref_name, $strict, false, false);
			return $this;
		}
		
		parent::setData($pref_name, $value, $strict);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Simple setter - $pref_name is  not parsed (no multidimensional arrays support)
	 * Adding new pref is allowed
	 * 
	 * @param string $pref_name
	 * @param mixed $value
	 * @return e_pref
	 */
	public function set($pref_name, $value)
	{
		global $pref;
		if(empty($pref_name))
		{
			return $this;
		}
		parent::set((string) $pref_name, $value, false);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Simple setter - $pref_name is  not parsed (no multidimensional arrays support)
	 * Non existing setting will be not created
	 * 
	 * @param string $pref_name
	 * @param mixed $value
	 * @return e_pref
	 */
	public function update($pref_name, $value)
	{
		global $pref;
		if(empty($pref_name))
		{
			return $this;
		}
		parent::set((string) $pref_name, $value, true);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Add new (single) preference (ONLY if doesn't exist)
	 * 
	 * @see addData()
	 * @param string $pref_name
	 * @param mixed $value
	 * @return e_pref
	 */
	public function add($pref_name, $value)
	{	
		if(empty($pref_name))
		{
			return $this;
		}
		$this->addData((string) $pref_name, $value);
		return $this;	
	}
	
	/**
	 * Add new preference or preference array (ONLY if it/they doesn't exist)
	 * 
	 * @see addData()
	 * @param string|array $pref_name
	 * @param mixed $value
	 * @return e_pref
	 */
	public function addPref($pref_name, $value = null)
	{	
		$this->addData($pref_name, $value);
		return $this;
	}
	
	/**
	 * Remove single preference
	 * 
	 * @see e_model::remove()
	 * @param string $pref_name
	 * @return e_pref
	 */
	public function remove($pref_name)
	{
		global $pref;
		parent::remove((string) $pref_name);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Remove single preference (parse $pref_name)
	 * 
	 * @see removeData()
	 * @param string $pref_name
	 * @return e_pref
	 */
	public function removePref($pref_name)
	{
		$this->removeData($pref_name);		
		return $this;
	}
	
	/**
	 * Disallow public use of addData()
	 * Disallow preference override
	 *
	 * @param string|array $pref_name
	 * @param mixed value
	 * @param boolean $strict
	 */
	final public function addData($pref_name, $value = null)
	{
		global $pref;
		parent::addData($pref_name, $value, false);
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Disallow public use of setData()
	 * Update only possible
	 * 
	 * @param string|array $pref_name
	 * @param mixed $value
	 * @return e_pref
	 */
	final public function setData($pref_name, $value = null)
	{
		global $pref;
		parent::setData($pref_name, $value, true);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Disallow public use of removeData()
	 * Object data reseting is not allowed
	 *
	 * @param string $pref_name
	 * @return e_pref
	 */
	final public function removeData($pref_name)
	{
		global $pref;
		parent::removeData((string) $pref_name);
		
		//BC
		if($this->alias === 'core')
		{
			$pref = $this->getData();
		}
		return $this;
	}
	
	/**
	 * Reset object data
	 *
	 * @param array $data
	 * @param boolean $sanitize
	 * @return e_pref
	 */
	public function loadData(array $data, $sanitize = true)
	{
		global $pref;
		if(!empty($data))
		{
			if($sanitize)
			{
				$data = e107::getParser()->toDB($data);
			}
			parent::setData($data, null, false);
			$this->pref_cache = e107::getArrayStorage()->WriteArray($data, false); //runtime cache
			//BC
			if($this->alias === 'core')
			{
				$pref = $this->getData();
			}
		}
		return $this;
	}
	
	/**
	 * Load object data - public
	 *
	 * @see _load()
	 * @param boolean $force
	 * @return e_pref
	 */
	public function load($force = false)
	{
		global $pref;
		if($force || !$this->hasData())
		{
			$this->data_has_changed = false;
			$this->_load($force);
			//BC
			if($this->alias === 'core')
			{
				$pref = $this->getData();
			}
		}
		
		return $this;
	}
	
	/**
	 * Load object data
	 *
	 * @param boolean $force
	 * @return e_pref
	 */
	protected function _load($force = false)
	{
		$id = $this->prefid;
		$data = $force ? false : $this->getPrefCache(true); 
		
		if($data !== false)
		{
			$this->pref_cache = e107::getArrayStorage()->WriteArray($data, false); //runtime cache
			$this->loadData($data, false);
			return $this;
		}
		
		if (e107::getDb()->db_Select('core', 'e107_value', "e107_name='{$id}'"))
		{
			$row = e107::getDb()->db_Fetch();
			
			if($this->serial_bc)
			{
				$data = unserialize($row['e107_value']); 
				$row['e107_value'] = e107::getArrayStorage()->WriteArray($data, false);
			}
			else 
			{
				$data = e107::getArrayStorage()->ReadArray($row['e107_value']);
			}
			
			$this->pref_cache = $row['e107_value']; //runtime cache
			$this->setPrefCache($row['e107_value'], true);
		}

		if(empty($data))
			$data = array();
		
		$this->loadData($data, false);
		return $this;
	}
	
	/**
	 * Save object data to DB
	 *
	 * @param boolean $from_post merge post data
	 * @param boolean $force
	 * @param boolean $session_messages use session messages
	 * @return boolean|integer 0 - no change, true - saved, false - error
	 */
	public function save($from_post = true, $force = false, $session_messages = false)
	{
		global $pref;
		if(!$this->prefid)
		{
			return false;
		}
		
		if($from_post)
		{
			$this->mergePostedData(); //all posted data is sanitized and filtered vs structure array
		}
		
		//TODO - LAN
		require_once(e_HANDLER.'message_handler.php');
		$emessage = eMessage::getInstance();
		
		if(!$this->data_has_changed && !$force)
		{
			$emessage->add('Settings not saved as no changes were made.', E_MESSAGE_INFO, $session_messages);
			return 0;
		}
		
		//Save to DB
		if(!$this->isError())
		{
			if($this->serial_bc)
			{
				$dbdata = serialize($this->getPref()); 
			}
			else 
			{
				$dbdata = $this->toString(false);
			}
			
			if(e107::getDb()->db_Select_gen("REPLACE INTO `#core` (e107_name,e107_value) values ('{$this->prefid}', '".addslashes($dbdata)."') "))
			{
				$this->data_has_changed = false; //reset status
				
				if($this->set_backup === true)
				{
					if($this->serial_bc)
					{
						$dbdata = serialize(e107::getArrayStorage()->ReadArray($this->pref_cache)); 
					}
					else 
					{
						$dbdata = $this->pref_cache;
					}
					if(e107::getDb()->db_Select_gen("REPLACE INTO `#core` (e107_name,e107_value) values ('".$this->prefid."_Backup', '".addslashes($dbdata)."') "))
					{
						$emessage->add('Backup successfully created.', E_MESSAGE_SUCCESS, $session_messages);
						ecache::clear_sys('Config_'.$this->alias.'_backup');
					}
				}
				$this->setPrefCache($this->toString(false), true); //reset pref cache - runtime & file
				
				$emessage->add('Settings successfully saved.', E_MESSAGE_SUCCESS, $session_messages);
				//BC
				if($this->alias === 'core')
				{
					$pref = $this->getData();
				}
				return true;
			}
			elseif(e107::getDb()->mySQLlastErrNum)
			{
				$emessage->add('mySQL error #'.e107::getDb()->$mySQLlastErrNum.': '.e107::getDb()->mySQLlastErrText, E_MESSAGE_ERROR, $session_messages);
				$emessage->add('Settings not saved.', E_MESSAGE_ERROR, $session_messages);
				return false;
			}
		}
		
		if($this->isError())
		{
			$this->setErrors(true, $session_messages); //add errors to the eMessage stack
			$emessage->add('Settings not saved.', E_MESSAGE_ERROR, $session_messages);
			return false;
		}
		else 
		{
			$emessage->add('Settings not saved as no changes were made.', E_MESSAGE_INFO, $session_messages);
			return 0;
		}
	}
	
	/**
	 * Get cached data from server cache file
	 *
	 * @param boolean $toArray convert to array
	 * @return string|array|false
	 */
	protected function getPrefCache($toArray = true)
	{
		if(!$this->pref_cache)
		{
			$this->pref_cache = ecache::retrieve_sys('Config_'.$this->alias, 24 * 60, true);
		}
		
		return ($toArray && $this->pref_cache ? e107::getArrayStorage()->ReadArray($this->pref_cache) : $this->pref_cache);
	}
	
	/**
	 * Store data to a server cache file
	 * If $cache_string is an array, it'll be converted to a string
	 * If $save is string, it'll be used for building the cache filename
	 *
	 * @param string|array $cache_string
	 * @param string|boolean $save write to a file
	 * @return e_pref
	 */
	protected function setPrefCache($cache_string, $save = false)
	{
		if(is_array($cache_string))
		{
			$cache_string = e107::getArrayStorage()->WriteArray($cache_string, false);
		}
		if(is_bool($save))
		{
			$this->pref_cache = $cache_string;
		}
		if($save)
		{
			ecache::set_sys('Config_'.($save !== true ? $save : $this->alias), $cache_string, true);
		}
		return $this;
	}
	
	/**
	 * Set $set_backup option
	 *
	 * @param boolean $optval
	 * @return e_pref
	 * 
	 */
	public function setOptionBackup($optval)
	{
		$this->set_backup = $optval;
		return $this;
	}
	
	/**
	 * Set $serial_bc option
	 *
	 * @param boolean $optval
	 * @return e_pref
	 * 
	 */
	public function setOptionSerialize($optval)
	{
		$this->serial_bc = $optval;
		return $this;
	}
	
}

/**
 * Handle core preferences
 * 
 * @package e107
 * @category e107_handlers
 * @version 1.0
 * @author SecretR
 * @copyright Copyright (c) 2009, e107 Inc.
 */
final class e_core_pref extends e_pref 
{	
	/**
	 * Allowed core id array
	 *
	 * @var array
	 */
	public $aliases = array(
		'core' 			=> 'SitePrefs', 
		'core_backup' 	=> 'SitePrefs_Backup', 
		'core_old' 		=> 'pref',
		'emote' 		=> 'emote_default', //TODO include other emote packs of the user. 
		'menu' 			=> 'menu_pref', 
		'search' 		=> 'search_prefs', 
		'notify' 		=> 'notify_prefs',
		'ipool'			=> 'IconPool'
	);
	
	/**
	 * Backward compatibility - list of prefid's which operate wit serialized data
	 *
	 * @var array
	 */
	// protected $serial_bc_array = array('core_old', 'emote', 'menu', 'search');
	protected $serial_bc_array = array('core_old');

	/**
	 * Constructor
	 *
	 * @param string $alias
	 * @param boolean $load load DB data on startup
	 */
	function __construct($alias, $load = true)
	{
		$pref_alias = $alias;
		$pref_id = $this->getConfigId($alias);
		
		if(!$pref_id) 
		{
			$pref_id = $pref_alias = '';
			trigger_error('Core config ID '.$alias.' not found!', E_USER_WARNING);
			return;
		}
		
		if(in_array($pref_alias, $this->serial_bc_array))
		{
			$this->setOptionSerialize(true);
		}
		
		if('core' === $pref_alias)
		{
			$this->setOptionBackup(true);
		}
		
		parent::__construct($pref_id, $pref_alias);
		if($load && $pref_id)
		{
			$this->load();
		}
	}
	
	/**
	 * Get config ID
	 * Allowed values: key or value from $alias array
	 * If id not found this method returns false
	 *
	 * @param string $alias
	 * @return string
	 */
	public function getConfigId($alias)
	{
		$alias = trim($alias);
		if(isset($this->aliases[$alias]))
		{
			return $this->aliases[$alias];
		}
		return false;
	}
	
	/**
	 * Get config ID
	 * Allowed values: key or value from $alias array
	 * If id not found this method returns false
	 *
	 * @param string $prefid
	 * @return string
	 */
	public function getAlias($prefid)
	{
		$prefid = trim($prefid);
		return array_search($prefid, $this->aliases);
	}
}

/**
 * Handle plugin preferences
 * 
 * @package e107
 * @category e107_handlers
 * @version 1.0
 * @author SecretR
 * @copyright Copyright (c) 2009, e107 Inc.
 */
class e_plugin_pref extends e_pref 
{
	/**
	 * Unique plugin name
	 *
	 * @var string
	 */
	protected $plugin_id;
	
	/**
	 * Constructor
	 * Note: object data will be loaded only if the plugin is installed (no matter of the passed
	 * $load value)
	 *
	 * @param string $plugin_id unique plugin name
	 * @param string $multi_row additional field identifier appended to the $prefid
	 * @param boolean $load load on startup
	 */
	function __construct($plugin_id, $multi_row = '', $load = true)
	{
		$this->plugin_id = $plugin_id;
		if($multi_row)
		{
			$plugin_id = $plugin_id.'_'.$multi_row;
		}
		parent::__construct($plugin_id, 'plugin_'.$plugin_id);
		if($load && e107::findPref('plug_installed/'.$this->plugin_id))
		{
			$this->load();
		}
	}
	
	/**
	 * Retrive unique plugin name
	 *
	 * @return string
	 */
	public function getPluginId()
	{
		return $this->plugin_id;
	}
}

/**
 * DEPRECATED - see e107::getConfig(), e_core_pref and e_plugin_pref
 *
 */
//
// Simple functionality:
// Grab all prefs once, in one DB query. Reuse them throughout the session.
//
// get/set methods serve/consume strings (with slashes taken care of)
// getArray/setArray methods serve/consume entire arrays (since most prefs are such!)
//
// NOTE: Use of this class is VALUABLE (efficient) yet not NECESSARY (i.e. the system
//       will not break if it is ignored)... AS LONG AS there is no path consisting of:
//             - modify pref value(s) IGNORING this class
//  - retrieve pref value(s) USING this class
//       (while processing a single web page)
//  Just to be safe I have changed a number of menu_pref edits to use setArray().
//

class prefs 
{
	var $prefVals;
	var $prefArrays;

	// Default prefs to load
	var $DefaultRows = "e107_name='e107' OR e107_name='menu_pref' OR e107_name='notify_prefs'";

	// Read prefs from DB - get as many rows as are required with a single query.
	// $RowList is an array of pref entries to retrieve.
	// If $use_default is TRUE, $RowList entries are added to the default array. Otherwise only $RowList is used.
	// Returns TRUE on success (measured as getting at least one row of data); false on error.
	// Any data read is buffered (in serialised form) here - retrieve using get()
	function ExtractPrefs($RowList = "", $use_default = FALSE) 
	{
		global $sql;
		$Args = '';
		if($use_default)
		{
			$Args = $this->DefaultRows;
		}
		if(is_array($RowList))
		{
			foreach($RowList as $v)
			{
				$Args .= ($Args ? " OR e107_name='{$v}'" : "e107_name='{$v}'");
			}
		}
		if (!$sql->db_Select('core', '*', $Args, 'default'))
		{
			return FALSE;
		}
		while ($row = $sql->db_Fetch())
		{
			$this->prefVals['core'][$row['e107_name']] = $row['e107_value'];
		}
		return TRUE;
	}


	/**
	* Return current pref string $name from $table (only core for now)
	*
	* - @param  string $name -- name of pref row
	* - @param  string $table -- "core"
	* - @return  string pref value, slashes already stripped. FALSE on error
	* - @access  public
	*/
	function get($Name) 
	{
		if(isset($this->prefVals['core'][$Name]))
		{
			if($this->prefVals['core'][$Name] != '### ROW CACHE FALSE ###')
			{
				return $this->prefVals['core'][$Name];		// Dava from cache
			} 
			else 
			{
				return false;
			}
		}

		// Data not in cache - retrieve from DB
		$get_sql = new db; // required so sql loops don't break using $tp->toHTML(). 
		if($get_sql->db_Select('core', '*', "`e107_name` = '{$Name}'", 'default')) 
		{
			$row = $get_sql->db_Fetch();
			$this->prefVals['core'][$Name] = $row['e107_value'];
			return $this->prefVals['core'][$Name];
		} 
		else 
		{	// Data not in DB - put a 'doesn't exist' entry in cache to save another DB access
			$this->prefVals['core'][$Name] = '### ROW CACHE FALSE ###';
			return false;
		}
	}

	/**
	* Return current array from pref string $name in $table (core only for now)
	*
	* - @param:  string $name -- name of pref row
	* - @param  string $table -- "core" only now
	* - @return  array pref values
	* - @access     public
	*/
	// retrieve prefs as an array of values
	function getArray($name)
	{
		return e107::getArrayStorage()->ReadArray($this->get($name));
		// return unserialize($this->get($name));
	}


	/**
	* Update pref set and cache
	*
	* @param  string val -- pre-serialized string
	* @param  string $name -- name of pref row
	* @param  string $table -- "core" or "user"
	* @global  $$name
	* @access  public
	*
	* set("val")    == 'core', 'pref'
	* set("val","rowname")   == 'core', rowname
	* set("val","","user")   == 'user', 'user_pref' for current user
	* set("val","","user",uid)   == 'user', 'user_pref' for user uid
	* set("val","fieldname","user")  == 'user', fieldname
	*
	*/
	function set($val, $name = "", $table = "core", $uid = USERID) {
		global $sql;
		if (!strlen($name)) {
			switch ($table) {
				case 'core':
				$name = "pref";
				break;
				case 'user':
				$name = "user_pref";
				break;
			}
		}
		$val = addslashes($val);

		switch ($table ) {
			case 'core':
			if(!$sql->db_Update($table, "e107_value='$val' WHERE e107_name='$name'"))
			{
				$sql->db_Insert($table, "'{$name}', '{$val}'");
			}
			$this->prefVals[$table][$name] = $val;
			unset($this->prefArrays[$table][$name]);
			break;
			case 'user':
			$sql->db_Update($table, "user_prefs='$val' WHERE user_id=$uid");
			break;
		}
	}


	/**
	* Update pref set and cache
	*
	* - @param  string $name -- name of pref row
	* - @param  string $table -- "core" or "user"
	* - @global  $$name
	* - @access  public
	*
	* set()    == core, pref
	* set("rowname")   == core, rowname
	* set("","user")   == user, user_pref for current user
	* set("","user",uid)   == user, user_pref for user uid
	* set("fieldname","user")  == user, fieldname
	*
	* all pref sets other than menu_pref get toDB()
	*/
	function setArray($name = "", $table = "core", $uid = USERID) {
		global $tp;

		if (!strlen($name)) {
			switch ($table) {
				case 'core':
				$name = "pref";
				break;
				case 'user':
				$name = "user_pref";
				break;
			}
		}

		global $$name;
		if ($name != "menu_pref") {
			foreach($$name as $key => $prefvalue) {
				$$name[$key] = $tp->toDB($prefvalue);
			}
		}
		$tmp = e107::getArrayStorage()->WriteArray($$name);
	//	$tmp = serialize($$name);
		$this->set($tmp, $name, $table, $uid);
	}
}
?>