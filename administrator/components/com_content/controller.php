<?php
/**
 * @version $Id$
 * @package Joomla
 * @subpackage Content
 * @copyright Copyright (C) 2005 - 2006 Open Source Matters. All rights
 * reserved.
 * @license GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant to the
 * GNU General Public License, and as distributed it includes or is derivative
 * of works licensed under the GNU General Public License or other free or open
 * source software licenses. See COPYRIGHT.php for copyright notices and
 * details.
 */

jimport('joomla.application.component.controller');

/**
 * Content Component Controller
 *
 * @package Joomla
 * @subpackage Content
 * @since 1.5
 */
class ContentController extends JController
{
	/**
	 * Articles element
	 */
	function element()
	{
		$model	= &$this->getModel( 'element', 'ContentModel' );
		$view   = &$this->getView( 'element', 'ContentView' );
		$view->setModel( $model, true );
		$view->display();
	}

	/**
	* Compiles a list of installed or defined modules
	* @param database A database connector object
	*/
	function viewContent()
	{
		global $mainframe;

		// Initialize variables
		$db			=& JFactory::getDBO();
		$filter		= null;

		// Get some variables from the request
		$sectionid			= JRequest::getVar( 'sectionid', -1, '', 'int' );
		$redirect			= $sectionid;
		$option				= JRequest::getVar( 'option' );
		$context			= 'com_content.viewcontent';
		$filter_order		= $mainframe->getUserStateFromRequest("$context.filter_order", 'filter_order', '');
		$filter_order_Dir	= $mainframe->getUserStateFromRequest("$context.filter_order_Dir", 'filter_order_Dir', '');
		$filter_state		= $mainframe->getUserStateFromRequest("$context.filter_state", 'filter_state', '*');
		$catid				= $mainframe->getUserStateFromRequest("$context.catid", 'catid', 0);
		$filter_authorid	= $mainframe->getUserStateFromRequest("$context.filter_authorid", 'filter_authorid', 0);
		$filter_sectionid	= $mainframe->getUserStateFromRequest("$context.filter_sectionid", 'filter_sectionid', -1);
		$search				= $mainframe->getUserStateFromRequest("$context.search", 'search', '');

		$limit		= $mainframe->getUserStateFromRequest("$context.limit", 'limit', $mainframe->getCfg('list_limit'), 0);
		$limitstart	= JRequest::getVar('limitstart', 0, '', 'int');

		//$where[] = "c.state >= 0";
		$where[] = 'c.state != -2';

		if (!$filter_order) {
			$filter_order = 'section_name';
		}
		$order = "\n ORDER BY $filter_order $filter_order_Dir, section_name, cc.name, c.ordering";
		$all = 1;

		if ($filter_sectionid >= 0) {
			$filter = "\n WHERE cc.section = $filter_sectionid";
		}
		$section->title = 'All Articles';
		$section->id = 0;

		/*
		 * Add the filter specific information to the where clause
		 */
		// Section filter
		if ($filter_sectionid >= 0) {
			$where[] = 'c.sectionid = ' . (int) $filter_sectionid;
		}
		// Category filter
		if ($catid > 0) {
			$where[] = 'c.catid = ' . (int) $catid;
		}
		// Author filter
		if ($filter_authorid > 0) {
			$where[] = 'c.created_by = ' . (int) $filter_authorid;
		}
		// Content state filter
		if ($filter_state) {
			if ($filter_state == 'P') {
				$where[] = 'c.state = 1';
			} else {
				if ($filter_state == 'U') {
					$where[] = 'c.state = 0';
				} else if ($filter_state == 'A') {
					$where[] = 'c.state = -1';
				} else {
					$where[] = 'c.state != -2';
				}
			}
		}
		// Keyword filter
		if ($search) {
			$where[] = '(LOWER( c.title ) LIKE ' . $db->Quote( "%$search%" ) .
				' OR c.id = ' . (int) $search . ')';
		}

		// Build the where clause of the content record query
		$where = (count($where) ? "\n WHERE ".implode(' AND ', $where) : '');

		// Get the total number of records
		$query = "SELECT COUNT(*)" .
				"\n FROM #__content AS c" .
				"\n LEFT JOIN #__categories AS cc ON cc.id = c.catid" .
				"\n LEFT JOIN #__sections AS s ON s.id = c.sectionid" .
				$where;
		$db->setQuery($query);
		$total = $db->loadResult();

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		// Get the articles
		$query = "SELECT c.*, g.name AS groupname, cc.name, u.name AS editor, f.content_id AS frontpage, s.title AS section_name, v.name AS author" .
				"\n FROM #__content AS c" .
				"\n LEFT JOIN #__categories AS cc ON cc.id = c.catid" .
				"\n LEFT JOIN #__sections AS s ON s.id = c.sectionid" .
				"\n LEFT JOIN #__groups AS g ON g.id = c.access" .
				"\n LEFT JOIN #__users AS u ON u.id = c.checked_out" .
				"\n LEFT JOIN #__users AS v ON v.id = c.created_by" .
				"\n LEFT JOIN #__content_frontpage AS f ON f.content_id = c.id" .
				$where .
				$order;
		$db->setQuery($query, $pagination->limitstart, $pagination->limit);
		$rows = $db->loadObjectList();

		// If there is a database query error, throw a HTTP 500 and exit
		if ($db->getErrorNum()) {
			JError::raiseError( 500, $db->stderr() );
			return false;
		}

		// get list of categories for dropdown filter
		$query = "SELECT cc.id AS value, cc.title AS text, section" .
				"\n FROM #__categories AS cc" .
				"\n INNER JOIN #__sections AS s ON s.id = cc.section ".$filter .
				"\n ORDER BY s.ordering, cc.ordering";
		$lists['catid'] = JContentHelper::filterCategory($query, $catid);

		// get list of sections for dropdown filter
		$javascript = 'onchange="document.adminForm.submit();"';
		$lists['sectionid'] = JAdminMenus::SelectSection('filter_sectionid', $filter_sectionid, $javascript);

		// get list of Authors for dropdown filter
		$query = "SELECT c.created_by, u.name" .
				"\n FROM #__content AS c" .
				"\n INNER JOIN #__sections AS s ON s.id = c.sectionid" .
				"\n LEFT JOIN #__users AS u ON u.id = c.created_by" .
				"\n WHERE c.state <> -1" .
				"\n AND c.state <> -2" .
				"\n GROUP BY u.name" .
				"\n ORDER BY u.name";
		$authors[] = JHTMLSelect::option('0', '- '.JText::_('Select Author').' -', 'created_by', 'name');
		$db->setQuery($query);
		$authors = array_merge($authors, $db->loadObjectList());
		$lists['authorid'] = JHTMLSelect::genericList($authors, 'filter_authorid', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'created_by', 'name', $filter_authorid);

		// state filter
		$lists['state'] = JCommonHTML::selectState($filter_state, 'Published', 'Unpublished', 'Archived');

		// table ordering
		if ($filter_order_Dir == 'DESC') {
			$lists['order_Dir'] = 'ASC';
		} else {
			$lists['order_Dir'] = 'DESC';
		}
		$lists['order'] = $filter_order;

		// search filter
		$lists['search'] = $search;

		ContentView::showContent($rows, $lists, $pagination, $redirect);
	}

	/**
	* Shows a list of archived articles
	* @param int The section id
	*/
	function viewArchive()
	{
		global $mainframe;

		// Initialize variables
		$db						=& JFactory::getDBO();

		$sectionid				= JRequest::getVar( 'sectionid', 0, '', 'int' );
		$option					= JRequest::getVar( 'option' );

		$filter_order			= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.filter_order", 'filter_order', 'sectname');
		$filter_order_Dir		= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.filter_order_Dir", 'filter_order_Dir', '');
		$catid					= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.catid", 'catid', 0);
		$limit					= $mainframe->getUserStateFromRequest('limit', 'limit', $mainframe->getCfg('list_limit'));
		$limitstart				= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.limitstart", 'limitstart', 0);
		$filter_authorid		= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.filter_authorid", 'filter_authorid', 0);
		$filter_sectionid		= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.filter_sectionid", 'filter_sectionid', 0);
		$search					= $mainframe->getUserStateFromRequest("$option.$sectionid.viewarchive.search", 'search', '');
		$search					= $db->getEscaped(JString::strtolower($search));
		$redirect				= $sectionid;

		// A section id of zero means view all articles [all sections]
		if ($sectionid == 0)
		{
			$where = array ("c.state 	= -1", "c.catid	= cc.id", "cc.section = s.id", "s.scope  	= 'content'");
			$filter = "\n , #__sections AS s WHERE s.id = c.section";
			$all = 1;
		}
		else
		{
			 //We are viewing a specific section
			$where = array ("c.state 	= -1", "c.catid	= cc.id", "cc.section	= s.id", "s.scope	= 'content'", "c.sectionid= $sectionid");
			$filter = "\n WHERE section = '$sectionid'";
			$all = NULL;
		}

		// Section filter
		if ($filter_sectionid > 0)
		{
			$where[] = 'c.sectionid = ' . (int) $filter_sectionid;
		}
		// Author filter
		if ($filter_authorid > 0)
		{
			$where[] = 'c.created_by = ' . (int) $filter_authorid;
		}
		// Category filter
		if ($catid > 0)
		{
			$where[] = 'c.catid = ' . (int) $catid;
		}
		// Keyword filter
		if ($search)
		{
			$where[] = "LOWER( c.title ) LIKE '%$search%'";
		}

		// TODO: Sanitise $filter_order
		$filter_order_Dir = ($filter_order_Dir == 'ASC' ? 'ASC' : 'DESC');
		$orderby = "\n ORDER BY $filter_order $filter_order_Dir, sectname, cc.name, c.ordering";
		$where = (count($where) ? "\n WHERE ".implode(' AND ', $where) : '');

		// get the total number of records
		$query = "SELECT COUNT(*)" .
				"\n FROM #__content AS c" .
				"\n LEFT JOIN #__categories AS cc ON cc.id = c.catid" .
				"\n LEFT JOIN #__sections AS s ON s.id = c.sectionid".$where;
		$db->setQuery($query);
		$total = $db->loadResult();

		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		$query = "SELECT c.*, g.name AS groupname, cc.name, v.name AS author, s.name AS sectname" .
				"\n FROM #__content AS c" .
				"\n LEFT JOIN #__categories AS cc ON cc.id = c.catid" .
				"\n LEFT JOIN #__sections AS s ON s.id = c.sectionid" .
				"\n LEFT JOIN #__groups AS g ON g.id = c.access" .
				"\n LEFT JOIN #__users AS v ON v.id = c.created_by".$where.$orderby;
		$db->setQuery($query, $pagination->limitstart, $pagination->limit);
		$rows = $db->loadObjectList();

		// If there is a database query error, throw a HTTP 500 and exit
		if ($db->getErrorNum())
		{
			JError::raiseError( 500, $db->stderr() );
			return false;
		}

		// get list of categories for dropdown filter
		$query = "SELECT c.id AS value, c.title AS text" .
				"\n FROM #__categories AS c".$filter .
				"\n ORDER BY c.ordering";
		$lists['catid'] = JContentHelper::filterCategory($query, $catid);

		// get list of sections for dropdown filter
		$javascript = 'onchange="document.adminForm.submit();"';
		$lists['sectionid'] = JAdminMenus::SelectSection('filter_sectionid', $filter_sectionid, $javascript);

		$section = & JTable::getInstance('section', $db);
		$section->load($sectionid);

		// get list of Authors for dropdown filter
		$query = "SELECT c.created_by, u.name" .
				"\n FROM #__content AS c" .
				"\n INNER JOIN #__sections AS s ON s.id = c.sectionid" .
				"\n LEFT JOIN #__users AS u ON u.id = c.created_by" .
				"\n WHERE c.state = -1" .
				"\n GROUP BY u.name" .
				"\n ORDER BY u.name";
		$db->setQuery($query);
		$authors[] = JHTMLSelect::option('0', '- '.JText::_('Select Author').' -', 'created_by', 'name');
		$authors = array_merge($authors, $db->loadObjectList());
		$lists['authorid'] = JHTMLSelect::genericList($authors, 'filter_authorid', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'created_by', 'name', $filter_authorid);

		// table ordering
		if ($filter_order_Dir == 'DESC')
		{
			$lists['order_Dir'] = 'ASC';
		}
		else
		{
			$lists['order_Dir'] = 'DESC';
		}
		$lists['order'] = $filter_order;

		// search filter
		$lists['search'] = $search;

		ContentView::showArchive($rows, $section, $lists, $pagination, $option, $all, $redirect);
	}

	/**
	* Compiles information to add or edit the record
	*
	* @param database A database connector object
	* @param integer The unique id of the record to edit (0 if new)
	* @param integer The id of the content section
	*/
	function editContent()
	{
		global $mainframe;

		// Initialize variables
		$db				= & JFactory::getDBO();
		$user			= & JFactory::getUser();

		$cid			= JRequest::getVar( 'cid', array(0), '', 'array' );
		$id				= JRequest::getVar( 'id', @$cid[0], '', 'int' );
		$option			= JRequest::getVar( 'option' );
		$nullDate		= $db->getNullDate();
		$contentSection = '';
		$sectionid  	= 0;

		// Create and load the content table row
		$row = & JTable::getInstance('content', $db);
		$row->load($id);

		if ($id) {
			$sectionid = $row->sectionid;
			if ($row->state < 0) {
				$mainframe->redirect('index.php?option=com_content', JText::_('You cannot edit an archived item'));
			}
		}

		// A sectionid of zero means grab from all sections
		if ($sectionid == 0) {
			$where = "\n WHERE section NOT LIKE '%com_%'";
		} else {
			// Grab from the specific section
			$where = "\n WHERE section = ". $db->Quote( $sectionid );
		}

		/*
		 * If the item is checked out we cannot edit it... unless it was checked
		 * out by the current user.
		 */
		if ($row->checked_out && ($row->checked_out != $user->get('id')))
		{
			$msg = sprintf(JText::_('DESCBEINGEDITTED'), JText::_('The module'), $row->title);
			$mainframe->redirect('index.php?option=com_content', $msg);
		}

		if ($id)
		{
			$row->checkout($user->get('id'));

			if (trim($row->images)) {
				$row->images = explode("\n", $row->images);
			} else {
				$row->images = array ();
			}

			$row->created  = _validateDate($row->created);
			$row->modified = _validateDate($row->modified);
			$row->publish_down = _validateDate($row->publish_down);

			$query = "SELECT name" .
					"\n FROM #__users" .
					"\n WHERE id = $row->created_by";
			$db->setQuery($query);
			$row->creator = $db->loadResult();

			// test to reduce unneeded query
			if ($row->created_by == $row->modified_by) {
				$row->modifier = $row->creator;
			} else {
				$query = "SELECT name" .
						"\n FROM #__users" .
						"\n WHERE id = $row->modified_by";
				$db->setQuery($query);
				$row->modifier = $db->loadResult();
			}

			$query = "SELECT COUNT(content_id)" .
					"\n FROM #__content_frontpage" .
					"\n WHERE content_id = $row->id";
			$db->setQuery($query);
			$row->frontpage = $db->loadResult();
			if (!$row->frontpage) {
				$row->frontpage = 0;
			}
		} else {
			if (!$sectionid && @ JRequest::getVar('filter_sectionid')) {
				$sectionid =JRequest::getVar('filter_sectionid');
			}

			if (@JRequest::getVar('catid'))
			{
				$row->catid	 = JRequest::getVar('catid');
				$category 	 = & JTable::getInstance('category', $db);
				$category->load(JRequest::getVar('catid'));
				$sectionid = $category->section;
			} else {
				$row->catid = NULL;
			}
			$row->sectionid = $sectionid;
			$row->version = 0;
			$row->state = 1;
			$row->ordering = 0;
			$row->images = array ();
			$row->publish_up = date('Y-m-d', time() + $mainframe->getCfg('offset') * 60 * 60);
			$row->publish_down = JText::_('Never');
			$row->creator = '';
			$row->modified = $nullDate;
			$row->modifier = '';
			$row->frontpage = 0;
		}

		$javascript = "onchange=\"changeDynaList( 'catid', sectioncategories, document.adminForm.sectionid.options[document.adminForm.sectionid.selectedIndex].value, 0, 0);\"";

		$query = "SELECT s.id, s.title" .
				"\n FROM #__sections AS s" .
				"\n ORDER BY s.ordering";
		$db->setQuery($query);

		$sections[] = JHTMLSelect::option('-1', '- '.JText::_('Select Section').' -', 'id', 'title');
		$sections[] = JHTMLSelect::option('0', JText::_('Uncategorized'), 'id', 'title');
		$sections = array_merge($sections, $db->loadObjectList());
		$lists['sectionid'] = JHTMLSelect::genericList($sections, 'sectionid', 'class="inputbox" size="1" '.$javascript, 'id', 'title', intval($row->sectionid));

		foreach ($sections as $section)
		{
			$section_list[] = $section->id;
			// get the type name - which is a special category
			if ($row->sectionid) {
				if ($section->id == $row->sectionid) {
					$contentSection = $section->title;
				}
			} else {
				if ($section->id == $sectionid) {
					$contentSection = $section->title;
				}
			}
		}

		$sectioncategories = array ();
		$sectioncategories[-1] = array ();
		$sectioncategories[-1][] = JHTMLSelect::option('-1', JText::_( 'Select Category' ), 'id', 'name');
		$section_list = implode('\', \'', $section_list);

		$query = "SELECT id, name, section" .
				"\n FROM #__categories" .
				"\n WHERE section IN ( '$section_list' )" .
				"\n ORDER BY ordering";
		$db->setQuery($query);
		$cat_list = $db->loadObjectList();

		// Uncategorized category mapped to uncategorized section
		$uncat = new stdClass();
		$uncat->id = 0;
		$uncat->name = JText::_('Uncategorized');
		$uncat->section = 0;
		$cat_list[] = $uncat;
		foreach ($sections as $section)
		{
			$sectioncategories[$section->id] = array ();
			$rows2 = array ();
			foreach ($cat_list as $cat)
			{
				if ($cat->section == $section->id) {
					$rows2[] = $cat;
				}
			}
			foreach ($rows2 as $row2) {
				$sectioncategories[$section->id][] = JHTMLSelect::option($row2->id, $row2->name, 'id', 'name');
			}
		}

		foreach ($cat_list as $cat) {
			$categoriesA[] = $cat;
		}

		$categories[] = JHTMLSelect::option('-1', JText::_( 'Select Category' ), 'id', 'name');
		$categories = array_merge($categories, $categoriesA);
		$lists['catid'] = JHTMLSelect::genericList($categories, 'catid', 'class="inputbox" size="1"', 'id', 'name', intval($row->catid));

		// build the html select list for ordering
		$query = "SELECT ordering AS value, title AS text" .
				"\n FROM #__content" .
				"\n WHERE catid = " . (int) $row->catid .
				"\n AND state >= 0" .
				"\n ORDER BY ordering";
		$lists['ordering'] = JAdminMenus::SpecificOrdering($row, $id, $query, 1);

		// build the html radio buttons for frontpage
		$lists['frontpage'] = JHTMLSelect::yesnoList('frontpage', '', $row->frontpage);
		// build the html radio buttons for published
		$lists['state'] = JHTMLSelect::yesnoList('state', '', $row->state);
		// build list of users
		$active = (intval($row->created_by) ? intval($row->created_by) : $user->get('id'));
		$lists['created_by'] = JAdminMenus::UserSelect('created_by', $active);

		// build the html select list for the group access
		$lists['access'] = JAdminMenus::Access($row);

		// get params definitions
		$params = new JParameter($row->attribs, JApplicationHelper::getPath('com_xml', 'com_content'), 'component');

		/*
		 * We need to unify the introtext and fulltext fields and have the
		 * fields separated by the {readmore} tag, so lets do that now.
		 */
		if (JString::strlen($row->fulltext) > 1) {
			$row->text = $row->introtext . "<hr id=\"system-readmore\" />" . $row->fulltext;
		} else {
			$row->text = $row->introtext;
		}

		ContentView::editContent($row, $contentSection, $lists, $sectioncategories, $params, $option);
	}

	/**
	* Saves the article an edit form submit
	* @param database A database connector object
	*/
	function saveContent()
	{
		global $mainframe;

		// Initialize variables
		$db			= & JFactory::getDBO();
		$user		= & JFactory::getUser();

		$option		= JRequest::getVar( 'option' );
		$task		= JRequest::getVar( 'task' );
		$sectionid	= JRequest::getVar( 'sectionid', 0, '', 'int' );
		$redirect	= JRequest::getVar( 'redirect', $sectionid, 'post' );
		$menu		= JRequest::getVar( 'menu', 'mainmenu', 'post' );
		$menuid		= JRequest::getVar( 'menuid', 0, 'post' );
		$nullDate	= $db->getNullDate();

		$row = & JTable::getInstance('content', $db);
		if (!$row->bind(JRequest::get('post'))) {
			JError::raiseError( 500, $db->stderr() );
			return false;
		}

		// sanitise id field
		$row->id = (int) $row->id;

		// Are we saving from an item edit?
		if ($row->id) {
			$row->modified 		= date( 'Y-m-d H:i:s' );
			$row->modified_by 	= $user->get('id');
		}

		$row->created_by 	= $row->created_by ? $row->created_by : $user->get('id');

		if ($row->created && strlen(trim( $row->created )) <= 10) {
			$row->created 	.= ' 00:00:00';
		}

		jimport('joomla.utilities.date');
		$date = new JDate($row->created);
		$date->setOffset( -$mainframe->getCfg('offset'));
		$row->created = $date->toMySQL();

		// Append time if not added to publish date
		if (strlen(trim($row->publish_up)) <= 10) {
			$row->publish_up .= ' 00:00:00';
		}

		$date = new JDate($row->publish_up);
		$date->setOffset( -$mainframe->getCfg('offset'));
		$row->publish_up = $date->toMySQL();

		// Handle never unpublish date
		if (trim($row->publish_down) == JText::_('Never') || trim( $row->publish_down ) == '')
		{
			$row->publish_down = $nullDate;
		}
		else
		{
			if (strlen(trim( $row->publish_down )) <= 10) {
				$row->publish_down .= ' 00:00:00';
			}
			$date = new JDate($row->publish_down);
			$date->setOffset( -$mainframe->getCfg('offset'));
			$row->publish_down = $date->toMySQL();
		}

		// Get a state and parameter variables from the request
		$row->state	= JRequest::getVar( 'state', 0, '', 'int' );
		$params		= JRequest::getVar( 'params', '', 'post' );


		// Build parameter INI string
		if (is_array($params))
		{
			$txt = array ();
			foreach ($params as $k => $v) {
				$txt[] = "$k=$v";
			}
			$row->attribs = implode("\n", $txt);
		}

		// Prepare the content for saving to the database
		JContentHelper::saveContentPrep( $row );

		// Make sure the data is valid
		if (!$row->check()) {
			JError::raiseError( 500, $db->stderr() );
			return false;
		}

		// Increment the content version number
		$row->version++;

		// Store the content to the database
		if (!$row->store()) {
			JError::raiseError( 500, $db->stderr() );
			return false;
		}

		// Check the article and update item order
		$row->checkin();
		$row->reorder("catid = $row->catid AND state >= 0");

		/*
		 * We need to update frontpage status for the article.
		 *
		 * First we include the frontpage table and instantiate an instance of it.
		 */
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_frontpage'.DS.'tables'.DS.'frontpage.php');
		$fp = new TableFrontPage($db);

		// Is the article viewable on the frontpage?
		if (JRequest::getVar( 'frontpage', 0, '', 'int' ))
		{
			// Is the item already viewable on the frontpage?
			if (!$fp->load($row->id))
			{
				// Insert the new entry
				$query = "INSERT INTO #__content_frontpage" .
						"\n VALUES ( $row->id, 1 )";
				$db->setQuery($query);
				if (!$db->query())
				{
					JError::raiseError( 500, $db->stderr() );
					return false;
				}
				$fp->ordering = 1;
			}
		}
		else
		{
			// Delete the item from frontpage if it exists
			if (!$fp->delete($row->id)) {
				$msg .= $fp->stderr();
			}
			$fp->ordering = 0;
		}
		$fp->reorder();

		$cache = & JFactory::getCache('com_content');
		$cache->cleanCache();

		switch ($task)
		{
			case 'go2menu' :
				$mainframe->redirect('index.php?option=com_menus&menutype='.$menu);
				break;

			case 'go2menuitem' :
				$mainframe->redirect('index.php?option=com_menus&menutype='.$menu.'&task=edit&hidemainmenu=1&id='.$menuid);
				break;

			case 'menulink' :
				JContentHelper::menuLink($redirect, $row->id);
				break;

			case 'resethits' :
				JContentHelper::resetHits($redirect, $row->id);
				break;

			case 'apply' :
				$msg = sprintf(JText::_('Successfully Saved changes to Item'), $row->title);
				$mainframe->redirect('index.php?option=com_content&sectionid='.$redirect.'&task=edit&hidemainmenu=1&cid[]='.$row->id, $msg);
				break;

			case 'save' :
			default :
				$msg = sprintf(JText::_('Successfully Saved Item'), $row->title);
				$mainframe->redirect('index.php?option=com_content&sectionid='.$redirect, $msg);
				break;
		}
	}

	/**
	* Changes the state of one or more content pages
	*
	* @param string The name of the category section
	* @param integer A unique category id (passed from an edit form)
	* @param array An array of unique category id numbers
	* @param integer 0 if unpublishing, 1 if publishing
	* @param string The name of the current user
	*/
	function changeContent( $state = 0 )
	{
		global $mainframe;

		// Initialize variables
		$db		= & JFactory::getDBO();
		$user	= & JFactory::getUser();

		$cid	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$option	= JRequest::getVar( 'option' );
		$task	= JRequest::getVar( 'task' );

		if (count($cid) < 1) {
			$action = $state == 1 ? 'publish' : ($state == -1 ? 'archive' : 'unpublish');
			JViewContent::displayError( JText::_('Select an item to') . ' ' . JText::_($action) );
			return false;
		}

		// Get some variables for the query
		$uid	= $user->get('id');
		$total	= count($cid);
		$cids	= implode(',', $cid);

		$query = "UPDATE #__content" .
				"\n SET state = $state" .
				"\n WHERE id IN ( $cids ) AND ( checked_out = 0 OR (checked_out = $uid ) )";
		$db->setQuery($query);
		if (!$db->query()) {
			JError::raiseError( 500, $db->getErrorMsg() );
			return false;
		}

		if (count($cid) == 1) {
			$row = & JTable::getInstance('content', $db);
			$row->checkin($cid[0]);
		}

		switch ($state)
		{
			case -1 :
				$msg = sprintf(JText::_('Item(s) successfully Archived'), $total);
				break;

			case 1 :
				$msg = sprintf(JText::_('Item(s) successfully Published'), $total);
				break;

			case 0 :
			default :
				if ($task == 'unarchive') {
					$msg = sprintf(JText::_('Item(s) successfully Unarchived'), $total);
				} else {
					$msg = sprintf(JText::_('Item(s) successfully Unpublished'), $total);
				}
				break;
		}

		$cache = & JFactory::getCache('com_content');
		$cache->cleanCache();

		// Get some return/redirect information from the request
		$redirect	= JRequest::getVar( 'redirect', $row->sectionid, 'post' );
		$rtask		= JRequest::getVar( 'returntask', '', 'post' );
		if ($rtask) {
			$rtask = '&task='.$rtask;
		} else {
			$rtask = '';
		}

		$mainframe->redirect('index.php?option='.$option.$rtask.'&sectionid='.$redirect.'&josmsg='.$msg);
	}

	/**
	* Changes the frontpage state of one or more articles
	*
	*/
	function toggleFrontPage()
	{
		global $mainframe;

		// Initialize variables
		$db		=& JFactory::getDBO();

		$cid	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$option	= JRequest::getVar( 'option' );
		$msg	= null;

		if (count($cid) < 1) {
			JViewContent::displayError( JText::_('Select an item to toggle') );
			return false;
		}

		/*
		 * We need to update frontpage status for the articles.
		 *
		 * First we include the frontpage table and instantiate an instance of
		 * it.
		 */
		require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_frontpage'.DS.'tables'.DS.'frontpage.php');
		$fp = new TableFrontPage($db);

		foreach ($cid as $id)
		{
			// toggles go to first place
			if ($fp->load($id)) {
				if (!$fp->delete($id)) {
					$msg .= $fp->stderr();
				}
				$fp->ordering = 0;
			} else {
				// new entry
				$query = "INSERT INTO #__content_frontpage" .
						"\n VALUES ( $id, 0 )";
				$db->setQuery($query);
				if (!$db->query()) {
					JError::raiseError( 500, $db->stderr() );
					return false;
				}
				$fp->ordering = 0;
			}
			$fp->reorder();
		}

		$cache = & JFactory::getCache('com_content');
		$cache->cleanCache();

		$mainframe->redirect('index.php?option='.$option, $msg);
	}

	function removeContent()
	{
		global $mainframe;

		// Initialize variables
		$db			= & JFactory::getDBO();

		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$option		= JRequest::getVar( 'option' );
		$return		= JRequest::getVar( 'returntask', '', 'post' );
		$nullDate	= $db->getNullDate();

		if (count($cid) < 1)
		{
			ContentView::displayError( JText::_('Select an item to delete') );
			return false;
		}

		// Removed content gets put in the trash [state = -2] and ordering is always set to 0
		$state		= '-2';
		$ordering	= '0';

		// Get the list of content id numbers to send to trash.
		$cids = implode(',', $cid);

		// Update articles in the database
		$query = "UPDATE #__content" .
				"\n SET state = $state, ordering = $ordering, checked_out = 0, checked_out_time = '$nullDate'"."\n WHERE id IN ( $cids )";
		$db->setQuery($query);
		if (!$db->query())
		{
			JError::raiseError( 500, $db->getErrorMsg() );
			return false;
		}

		$cache = & JFactory::getCache('com_content');
		$cache->cleanCache();

		$msg = sprintf(JText::_('Item(s) sent to the Trash'), count($cid));
		$mainframe->redirect('index.php?option='.$option.'&task='.$return, $msg);
	}

	/**
	* Cancels an edit operation
	*/
	function cancelContent()
	{
		global $mainframe;

		// Initialize variables
		$db	= & JFactory::getDBO();

		// Check the article in if checked out
		$row = & JTable::getInstance('content', $db);
		$row->bind(JRequest::get('post'));
		$row->checkin();

		$mainframe->redirect('index.php?option=com_content');
	}

	/**
	* Moves the order of a record
	* @param integer The increment to reorder by
	*/
	function orderContent($direction)
	{
		global $mainframe;

		// Initialize variables
		$db		= & JFactory::getDBO();

		$cid	= JRequest::getVar( 'cid', array(), 'post', 'array' );

		if (isset( $cid[0] ))
		{
			$row = & JTable::getInstance('content', $db);
			$row->load( (int) $cid[0] );
			$row->move($direction, 'catid = ' . (int) $row->catid . ' AND state >= 0' );

			$cache = & JFactory::getCache('com_content');
			$cache->cleanCache();
		}

		$mainframe->redirect('index.php?option=com_content');
	}

	/**
	* Form for moving item(s) to a different section and category
	*/
	function moveSection()
	{
		// Initialize variables
		$db			=& JFactory::getDBO();

		$cid		= JRequest::getVar( 'cid', array(), 'post', 'array' );
		$sectionid	= JRequest::getVar( 'sectionid', 0, '', 'int' );

		if (count($cid) < 1)
		{
			ContentView::displayError( JText::_('Select an item to move') );
			return false;
		}

		//seperate contentids
		JArrayHelper::toInteger( $cid );
		$cids = implode(',', $cid);
		// Articles query
		$query = "SELECT a.title" .
				"\n FROM #__content AS a" .
				"\n WHERE ( a.id IN ( $cids ) )" .
				"\n ORDER BY a.title";
		$db->setQuery($query);
		$items = $db->loadObjectList();

		$query = "SELECT CONCAT_WS( ', ', s.id, c.id ) AS `value`, CONCAT_WS( '/', s.name, c.name ) AS `text`" .
				"\n FROM #__sections AS s" .
				"\n INNER JOIN #__categories AS c ON c.section = s.id" .
				"\n WHERE s.scope = 'content'" .
				"\n ORDER BY s.name, c.name";
		$db->setQuery($query);
		$rows[] = JHTMLSelect::option("0, 0", 'Static Content');
		$rows = array_merge($rows, $db->loadObjectList());
		// build the html select list
		$sectCatList = JHTMLSelect::genericList($rows, 'sectcat', 'class="inputbox" size="8"', 'value', 'text', null);

		ContentView::moveSection($cid, $sectCatList, 'com_content', $sectionid, $items);
	}

	/**
	* Save the changes to move item(s) to a different section and category
	*/
	function moveSectionSave()
	{
		global $mainframe;

		// Initialize variables
		$db			= & JFactory::getDBO();
		$user		= & JFactory::getUser();

		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$sectionid	= JRequest::getVar( 'sectionid', 0, '', 'int' );
		$option		= JRequest::getVar( 'option' );

		$sectcat = JRequest::getVar( 'sectcat', '', 'post' );
		list ($newsect, $newcat) = explode(',', $sectcat);

		if (!$newsect && !$newcat) {
			$mainframe->redirect("index.php?option=com_content&sectionid=$sectionid&josmsg=".JText::_('An error has occurred'));
		}

		// find section name
		$query = "SELECT a.name" .
				"\n FROM #__sections AS a" .
				"\n WHERE a.id = $newsect";
		$db->setQuery($query);
		$section = $db->loadResult();

		// find category name
		$query = "SELECT  a.name" .
				"\n FROM #__categories AS a" .
				"\n WHERE a.id = $newcat";
		$db->setQuery($query);
		$category = $db->loadResult();

		$total	= count($cid);
		$cids		= implode(',', $cid);
		$uid		= $user->get('id');

		$row = & JTable::getInstance('content', $db);
		// update old orders - put existing items in last place
		foreach ($cid as $id)
		{
			$row->load(intval($id));
			$row->ordering = 0;
			$row->store();
			$row->reorder("catid = $row->catid AND state >= 0");
		}

		$query = "UPDATE #__content SET sectionid = $newsect, catid = $newcat" .
				"\n WHERE id IN ( $cids )" .
				"\n AND ( checked_out = 0 OR ( checked_out = $uid ) )";
		$db->setQuery($query);
		if (!$db->query())
		{
			JError::raiseError( 500, $db->getErrorMsg() );
			return false;
		}

		// update new orders - put items in last place
		foreach ($cid as $id)
		{
			$row->load(intval($id));
			$row->ordering = 0;
			$row->store();
			$row->reorder("catid = $row->catid AND state >= 0");
		}

		if ($section && $category) {
			$msg = sprintf(JText::_('Item(s) successfully moved to Section'), $total, $section, $category);
		} else {
			$msg = JText::_('Item(s) successfully moved to Static Content');
		}

		$mainframe->redirect('index.php?option='.$option.'&sectionid='.$sectionid, $msg);
	}

	/**
	* Form for copying item(s)
	**/
	function copyItem()
	{
		// Initialize variables
		$db			= & JFactory::getDBO();

		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$sectionid	= JRequest::getVar( 'sectionid', 0, '', 'int' );
		$option		= JRequest::getVar( 'option' );

		if (!is_array($cid) || count($cid) < 1) {
			ContentView::displayError( JText::_('Select an item to move') );
			return false;
		}

		//seperate contentids
		$cids = implode(',', $cid);
		## Articles query
		$query = "SELECT a.title" .
				"\n FROM #__content AS a" .
				"\n WHERE ( a.id IN ( $cids ) )" .
				"\n ORDER BY a.title";
		$db->setQuery($query);
		$items = $db->loadObjectList();

		## Section & Category query
		$query = "SELECT CONCAT_WS(',',s.id,c.id) AS `value`, CONCAT_WS(' // ', s.name, c.name) AS `text`" .
				"\n FROM #__sections AS s" .
				"\n INNER JOIN #__categories AS c ON c.section = s.id" .
				"\n WHERE s.scope = 'content'" .
				"\n ORDER BY s.name, c.name";
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		// build the html select list
		$sectCatList = JHTMLSelect::genericList($rows, 'sectcat', 'class="inputbox" size="10"', 'value', 'text', NULL);

		ContentView::copySection($option, $cid, $sectCatList, $sectionid, $items);
	}

	/**
	* saves Copies of items
	**/
	function copyItemSave()
	{
		global $mainframe;

		// Initialize variables
		$db			= & JFactory::getDBO();

		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$sectionid	= JRequest::getVar( 'sectionid', 0, '', 'int' );
		$option		= JRequest::getVar( 'option' );

		$item	= null;
		$sectcat = JRequest::getVar( 'sectcat', '', 'post' );
		//seperate sections and categories from selection
		$sectcat = explode(',', $sectcat);
		list ($newsect, $newcat) = $sectcat;

		if (!$newsect && !$newcat) {
			$mainframe->redirect('index.php?option=com_content&sectionid='.$sectionid.'&josmsg='.JText::_('An error has occurred'));
		}

		// find section name
		$query = "SELECT a.name" .
				"\n FROM #__sections AS a" .
				"\n WHERE a.id = $newsect";
		$db->setQuery($query);
		$section = $db->loadResult();

		// find category name
		$query = "SELECT a.name" .
				"\n FROM #__categories AS a" .
				"\n WHERE a.id = $newcat";
		$db->setQuery($query);
		$category = $db->loadResult();

		$total = count($cid);
		for ($i = 0; $i < $total; $i ++)
		{
			$row = & JTable::getInstance('content', $db);

			// main query
			$query = "SELECT a.*" .
					"\n FROM #__content AS a" .
					"\n WHERE a.id = ".$cid[$i];
			$db->setQuery($query, 0, 1);
			$item = $db->loadObject();

			// values loaded into array set for store
			$row->id						= NULL;
			$row->sectionid					= $newsect;
			$row->catid						= $newcat;
			$row->hits						= '0';
			$row->ordering					= '0';
			$row->title						= $item->title;
			$row->title_alias				= $item->title_alias;
			$row->introtext					= $item->introtext;
			$row->fulltext					= $item->fulltext;
			$row->state						= $item->state;
			$row->mask						= $item->mask;
			$row->created					= $item->created;
			$row->created_by				= $item->created_by;
			$row->created_by_alias			= $item->created_by_alias;
			$row->modified					= $item->modified;
			$row->modified_by				= $item->modified_by;
			$row->checked_out				= $item->checked_out;
			$row->checked_out_time			= $item->checked_out_time;
			$row->publish_up				= $item->publish_up;
			$row->publish_down				= $item->publish_down;
			$row->images					= $item->images;
			$row->attribs					= $item->attribs;
			$row->version					= $item->parentid;
			$row->parentid					= $item->parentid;
			$row->metakey					= $item->metakey;
			$row->metadesc					= $item->metadesc;
			$row->access					= $item->access;

			if (!$row->check()) {
				JError::raiseError( 500, $row->getError() );
				return false;
			}

			if (!$row->store()) {
				JError::raiseError( 500, $row->getError() );
				return false;
			}
			$row->reorder("catid='".$row->catid."' AND state >= 0");
		}

		$msg = sprintf(JText::_('Item(s) successfully copied to Section'), $total, $section, $category);
		$mainframe->redirect('index.php?option='.$option.'&sectionid='.$sectionid.'&josmsg='.$msg);
	}

	/**
	* @param integer The id of the article
	* @param integer The new access level
	* @param string The URL option
	*/
	function accessMenu($access)
	{
		global $mainframe;

		// Initialize variables
		$db		= & JFactory::getDBO();

		$cid	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$option	= JRequest::getVar( 'option' );
		$uid	= $cid[0];

		// Create and load the article table object
		$row = & JTable::getInstance('content', $db);
		$row->load($uid);
		$row->access = $access;

		// Ensure the article object is valid
		if (!$row->check()) {
			JError::raiseError( 500, $row->getError() );
			return false;
		}

		// Store the changes
		if (!$row->store()) {
			JError::raiseError( 500, $row->getError() );
			return false;
		}

		$cache = & JFactory::getCache('com_content');
		$cache->cleanCache();

		$mainframe->redirect('index.php?option='.$option);
	}

	function saveOrder()
	{
		global $mainframe;

		// Initialize variables
		$db			= & JFactory::getDBO();

		$cid		= JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$order		= JRequest::getVar( 'order', array (0), 'post', 'array' );
		$redirect	= JRequest::getVar( 'redirect', 0, 'post' );
		$rettask	= JRequest::getVar( 'returntask', '', 'post' );
		$total		= count($cid);
		$conditions	= array ();

		// Instantiate an article table object
		$row = & JTable::getInstance('content', $db);

		// Update the ordering for items in the cid array
		for ($i = 0; $i < $total; $i ++)
		{
			$row->load( (int) $cid[$i] );
			if ($row->ordering != $order[$i]) {
				$row->ordering = $order[$i];
				if (!$row->store()) {
					JError::raiseError( 500, $db->getErrorMsg() );
					return false;
				}
				// remember to updateOrder this group
				$condition = "catid = $row->catid AND state >= 0";
				$found = false;
				foreach ($conditions as $cond)
					if ($cond[1] == $condition) {
						$found = true;
						break;
					}
				if (!$found)
					$conditions[] = array ($row->id, $condition);
			}
		}

		// execute updateOrder for each group
		foreach ($conditions as $cond)
		{
			$row->load($cond[0]);
			$row->reorder($cond[1]);
		}

		$cache = & JFactory::getCache('com_content');
		$cache->cleanCache();

		$msg = JText::_('New ordering saved');
		switch ($rettask)
		{
			case 'showarchive' :
				$mainframe->redirect('index.php?option=com_content&task=showarchive&sectionid='.$redirect, $msg);
				break;

			default :
				$mainframe->redirect('index.php?option=com_content&sectionid='.$redirect, $msg);
				break;
		}
	}

	function previewContent()
	{
		// Initialize variables
		$document		=& JFactory::getDocument();
		$db 			=& JFactory::getDBO();
		$id				= JRequest::getVar( 'id', 0, '', 'int' );
		$option			= JRequest::getVar( 'option' );

		// Get the current default template
		$query = "SELECT template" .
				"\n FROM #__templates_menu" .
				"\n WHERE client_id = 0" .
				"\n AND menuid = 0";
		$db->setQuery($query);
		$template = $db->loadResult();

		// Set page title
		$document->setTitle(JText::_('Article Preview'));
		$document->addStyleSheet('../templates/'.$template.'/css/editor.css');

		// Render article preview
		ContentView::previewContent();
	}

	function insertPagebreak()
	{
		global $mainframe;

		$mainframe->setPageTitle(JText::_('PGB ARTICLE PAGEBRK'));
		ContentView::insertPagebreak();
	}

	function _validateDate($date) {
		$db = &JFactory::getDBO();

		if ($date == $db->getNullDate() || JHTML::Date($date, '%Y') < date('Y')) {
			$newDate = JText::_('Never');
		} else {
			$newDate =  JHTML::Date($date, '%Y-%m-%d %H:%M:%S');
		}

		return $newDate;
	}
}
?>