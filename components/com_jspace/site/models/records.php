<?php
/**
 * @package     JSpace.Component
 * @subpackage  Model
 * @copyright   Copyright (C) 2014 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
 
defined('_JEXEC') or die;

/**
 * Models the display and management of multiple JSpace records.
 *
 * @package     JSpace.Component
 * @subpackage  Model
 */
class JSpaceModelRecords extends JModelList
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields']))
        {
            $config['filter_fields'] = array(
                'id', 'r.id',
                'title', 'r.title',
                'alias', 'r.alias',
                'checked_out', 'r.checked_out',
                'checked_out_time', 'r.checked_out_time',
                'catid', 'c.catid', 'category_title',
                'published', 'r.published',
                'access', 'r.access', 'access_level',
                'created', 'r.created',
                'created_by', 'r.created_by',
                'ordering', 'r.ordering',
                'language', 'r.language',
                'level', 'r.level',
                'path', 'r.path',
                'lft', 'r.lft',
                'rgt', 'r.rgt',
                'publish_up', 'r.publish_up',
                'publish_down', 'r.publish_down',
                'published', 'r.published',
                'author_id',
                'category_id'
            );
        
            if (JLanguageAssociations::isEnabled())
            {
                $config['filter_fields'][] = 'association';
            }
        }
        
        parent::__construct($config);
    }
    
    protected function populateState($ordering = null, $direction = null)
    {
        $app = JFactory::getApplication();
    
        // Adjust the context to support modal layouts.
        if ($layout = $app->input->get('layout'))
        {
            $this->context .= '.' . $layout;
        }
    
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);
    
        $access = $this->getUserStateFromRequest($this->context . '.filter.access', 'filter_access', 0, 'int');
        $this->setState('filter.access', $access);
    
        $authorId = $app->getUserStateFromRequest($this->context . '.filter.author_id', 'filter_author_id');
        $this->setState('filter.author_id', $authorId);
    
        $published = $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '');
        $this->setState('filter.published', $published);
    
        $categoryId = $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id');
        $this->setState('filter.category_id', $categoryId);
    
        $language = $this->getUserStateFromRequest($this->context . '.filter.language', 'filter_language', '');
        $this->setState('filter.language', $language);

        // List state information.
        parent::populateState('r.lft', 'asc');
    
        // Force a language
        $forcedLanguage = $app->input->get('forcedLanguage');
    
        if (!empty($forcedLanguage))
        {
            $this->setState('filter.language', $forcedLanguage);
            $this->setState('filter.forcedLanguage', $forcedLanguage);
        }
    }
    
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $user = JFactory::getUser();
        $fields = array();
        
        $table = $this->getTable('Record', 'JSpaceTable');
        
        foreach ($table->getFields() as $field)
        {           
            $fields[] = 'r.'.$db->qn($field->Field);
        }

        $query->select($this->getState('list.select', $fields));
        
        $query          
            ->from('#__jspace_records AS r')
            ->where("NOT r.alias = 'root'");
        
        // Get the parent title.
        $query
            ->select('r2.title AS parent_title')
            ->join('LEFT', '#__jspace_records AS r2 ON r.parent_id = r2.id');
        
        // Join over the language
        $query->select('l.title AS language_title')
        ->join('LEFT', $db->quoteName('#__languages') . ' AS l ON l.lang_code = r.language');
        
        if (JLanguageAssociations::isEnabled())
        {
            $query->select('COUNT(asso2.id)>1 as association')
            ->join('LEFT', '#__associations AS asso ON asso.id = r.id AND asso.context='.$db->quote('com_jspace.record'))
            ->join('LEFT', '#__associations AS asso2 ON asso2.key = asso.key');
        }
    
        // Join over the users for the checked out user.
        $query->select('uc.name AS editor')
        ->join('LEFT', '#__users AS uc ON uc.id=r.checked_out');
    
        // Join over the asset groups.
        $query->select('ag.title AS access_level')
        ->join('LEFT', '#__viewlevels AS ag ON ag.id = r.access');
        
        // Join over the users for the author.
        $query->select('ua.name AS author_name')
        ->join('LEFT', '#__users AS ua ON ua.id = r.created_by');
    
        // Join over the categories.
        $query->select('c.title AS category_title, c.path AS category_route, c.access AS category_access, c.alias AS category_alias')
            ->join('LEFT', '#__categories AS c ON c.id = r.catid');
        
        $query
            ->select('parent.title as parent_title, parent.id as parent_cat_id, parent.path as parent_route, parent.alias as parent_alias')
            ->join('LEFT', '#__categories as parent ON parent.id = c.id');

        // Filter by search in title.
        $search = $this->getState('filter.search');

        if (!empty($search))
        {
            if (stripos($search, 'id:') === 0)
            {
                $query->where('r.id = ' . (int) substr($search, 3));
            }
            elseif (stripos($search, 'author:') === 0)
            {
                $search = $db->quote('%' . $db->escape(substr($search, 7), true) . '%');
                $query->where('(ua.name LIKE ' . $search . ' OR ua.username LIKE ' . $search . ')');
            }
            else
            {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(r.title LIKE ' . $search . ' OR r.alias LIKE ' . $search . ')');
            }
        }
        
        // Filter by access level.
        if ($access = $this->getState('filter.access'))
        {
            $query->where('r.access = ' . (int) $access);
        }
        
        // Implement View Level Access
        if (!$user->authorise('core.admin'))
        {
            $groups = implode(',', $user->getAuthorisedViewLevels());
            $query->where('r.access IN (' . $groups . ')');
        }
        
        // Filter by published state
        $published = $this->getState('filter.published');
        
        if (is_numeric($published))
        {
            $query->where('r.published = ' . (int) $published);
        }
        elseif ($published === '')
        {
            $query->where('(r.published = 0 OR r.published = 1)');
        }
        
        // Filter by a single or group of categories.
        $baselevel = 1;
        $categoryId = $this->getState('filter.category_id');

        if (is_numeric($categoryId))
        {
                $cat_tbl = $this->getTable('Category', 'JTable');
                $cat_tbl->load($categoryId);
                $rgt = $cat_tbl->rgt;
                $lft = $cat_tbl->lft;
                $baselevel = (int) $cat_tbl->level;
                $query->where('c.lft >= ' . (int) $lft)
                        ->where('c.rgt <= ' . (int) $rgt);
        }
        elseif (is_array($categoryId))
        {
            JArrayHelper::toInteger($categoryId);
            $categoryId = implode(',', $categoryId);
            $query->where('a.catid IN (' . $categoryId . ')');
        }
        
        $query->order($db->escape($this->getState('list.ordering', 'r.lft')) . ' ' . $db->escape($this->getState('list.direction', 'ASC')));

        return $query;
    }
    
    public function getItems()
    {
        $items = parent::getItems();
        $user = JFactory::getUser();
        $userId = $user->get('id');
        $guest = $user->get('guest');
        $groups = $user->getAuthorisedViewLevels();
        $input = JFactory::getApplication()->input;

        // Get the global params
        $globalParams = JComponentHelper::getParams('com_jspace', true);

        // Convert the parameter fields into objects.
        foreach ($items as &$item)
        {
            $recordParams = new JRegistry;

            // Unpack readmore and layout params
            $item->alternative_readmore = $recordParams->get('alternative_readmore');
            $item->layout = $recordParams->get('layout');

            $item->params = clone $this->getState('params');

            /*For blogs, record params override menu item params only if menu param = 'use_record'
            Otherwise, menu item params control the layout
            If menu item is 'use_record' and there is no record param, use global*/
            if (($input->getString('layout') == 'blog') || ($input->getString('view') == 'featured')
                || ($this->getState('params')->get('layout_type') == 'blog'))
            {
                // Create an array of just the params set to 'use_record'
                $menuParamsArray = $this->getState('params')->toArray();
                $recordArray = array();

                foreach ($menuParamsArray as $key => $value)
                {
                    if ($value === 'use_record')
                    {
                        // If the record has a value, use it
                        if ($recordParams->get($key) != '')
                        {
                            // Get the value from the record
                            $recordArray[$key] = $recordParams->get($key);
                        }
                        else
                        {
                            // Otherwise, use the global value
                            $recordArray[$key] = $globalParams->get($key);
                        }
                    }
                }

                // Merge the selected record params
                if (count($recordArray) > 0)
                {
                    $recordParams = new JRegistry;
                    $recordParams->loadArray($recordArray);
                    $item->params->merge($recordParams);
                }
            }
            else
            {
                // For non-blog layouts, merge all of the record params
                $item->params->merge($recordParams);
            }

            // Get display date
            switch ($item->params->get('list_show_date'))
            {
                case 'modified':
                    $item->displayDate = $item->modified;
                    break;

                case 'published':
                    $item->displayDate = ($item->publish_up == 0) ? $item->created : $item->publish_up;
                    break;

                default:
                case 'created':
                    $item->displayDate = $item->created;
                    break;
            }

            // Compute the asset access permissions.
            // Technically guest could edit an record, but lets not check that to improve performance a little.
            if (!$guest)
            {
                $asset = 'com_jspace.record.' . $item->id;

                // Check general edit permission first.
                if ($user->authorise('core.edit', $asset))
                {
                    $item->params->set('access-edit', true);
                }

                // Now check if edit.own is available.
                elseif (!empty($userId) && $user->authorise('core.edit.own', $asset))
                {
                    // Check for a valid user and that they are the owner.
                    if ($userId == $item->created_by)
                    {
                        $item->params->set('access-edit', true);
                    }
                }
            }

            $access = $this->getState('filter.access');

            if ($access)
            {
                // If the access filter has been set, we already have only the records this user can view.
                $item->params->set('access-view', true);
            }
            else
            {
                // If no access filter is set, the layout takes some responsibility for display of limited information.
                if ($item->catid == 0 || $item->category_access === null)
                {
                    $item->params->set('access-view', in_array($item->access, $groups));
                }
                else
                {
                    $item->params->set('access-view', in_array($item->access, $groups) && in_array($item->category_access, $groups));
                }
            }

            // Get the tags
            $item->tags = new JHelperTags;
            $item->tags->getItemTags('com_jspace.record', $item->id);
        }

        return $items;
    }
}