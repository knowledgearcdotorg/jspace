<?php
/**
 * @package     JSpace
 * @subpackage  Table
 *
 * @copyright   Copyright (C) 2014 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
 
defined('_JEXEC') or die;
 
/**
 * Represents a JSpace asset.
 *
 * @package     JSpace
 * @subpackage  Table
 */
class JSpaceTableAsset extends JTable
{
	/**
	 * Constructor
	 *
	 * @param  JDatabaseDriver  $db  Database connector object.
	 */
	public function __construct(&$db)
	{
		parent::__construct('#__jspace_assets', 'id', $db);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see JTable::bind()
	 */
	public function bind($array, $ignore = '')
	{
		// set the metadata as a json string.
		if (isset($array['metadata']) && is_array($array['metadata']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string) $registry;
		}
		
		return parent::bind($array, $ignore);
	}
}