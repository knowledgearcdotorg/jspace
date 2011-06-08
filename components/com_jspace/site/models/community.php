<?php 
/**
 * A model that displays information about a single community.
 * 
 * @author		$LastChangedBy$
 * @package		JSpace
 * @copyright	Copyright (C) 2011 Wijiti Pty Ltd. All rights reserved.
 * @license     This file is part of the JSpace component for Joomla!.

   The JSpace component for Joomla! is free software: you can redistribute it 
   and/or modify it under the terms of the GNU General Public License as 
   published by the Free Software Foundation, either version 3 of the License, 
   or (at your option) any later version.

   The JSpace component for Joomla! is distributed in the hope that it will be 
   useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with the JSpace component for Joomla!.  If not, see 
   <http://www.gnu.org/licenses/>.

 * Contributors
 * Please feel free to add your name and email (optional) here if you have 
 * contributed any source code changes.
 * Name							Email
 * Hayden Young					<haydenyoung@wijiti.com> 
 * 
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.model');
jimport("joomla.filesystem.file");
jimport('joomla.error.log');
jimport('joomla.utilities');

require_once(JPATH_COMPONENT_ADMINISTRATOR.DS."helpers".DS."restrequest.php");

class JSpaceModelCommunity extends JModel
{
	var $configPath = null;
	
	var $configuration = null;

	var $id = 0;
	
	var $data = null;
	
	public function __construct()
	{
		$this->configPath = JPATH_ROOT.DS."administrator".DS."components".DS."com_jspace".DS."configuration.php";
		
		require_once($this->configPath);
		
		parent::__construct();
	}

	/**
	 * Gets the configuration file path.
	 * 
	 * @return The configuration file path.
	 */
	public function getConfig()
	{
		if (!$this->configuration) {
			$this->configuration = new JSpaceConfig();	
		}
		
		return $this->configuration;
	}
	
	public function setId($id)
	{
		$this->id = $id;
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	/**
	 * Gets a community.
	 */
	public function getData()
	{
		if (!$this->data) {
			$request = new JSpaceRestRequestHelper($this->getConfig()->rest_url.'/communities/'. $this->getId() .'.json', 'GET');
			$request->execute();

			if (JArrayHelper::getValue($request->getResponseInfo(), "http_code") == 200) {
				$this->data = json_decode($request->getResponseBody());
			} else {
				$this->data = array();
				$log = JLog::getInstance();
				$log->addEntry(array("c-ip"=>JArrayHelper::getValue($request->getResponseInfo(), "http_code", 0), "comment"=>$request->getResponseBody()));
			}
		}
		
		return $this->data;
	}
	
	public function getMetadataElementAsString($metadata, $name, $separator = ";")
	{
		return implode(";", JArrayHelper::getValue($this->getMetadataAsArray($metadata), $name, array()));
	}
	
	/**
	 * Gets a list of meta tags as an array.
	 * 
	 * @param string $metadata Valid meta data as HTML.
	 */
	public function getMetadataAsArray($metadata)
	{
		$array = array();
		
		$document = new DOMDocument();
		$document->loadHTML($metadata);
		
		foreach ($document->getElementsByTagName("meta") as $node) {
			$key = $node->getAttribute("name");
			
			if (!array_key_exists($key, $array)) {
				$array[$key] = array();
			}
			
			$array[$key][] = $node->getAttribute("content");
		}

		return $array;
	}
}