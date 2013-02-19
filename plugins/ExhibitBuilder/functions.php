<?php
/**
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2012
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package ExhibitBuilder
 */

/**
 * Add EB's translations directory for all requests.
 */
function exhibit_builder_initialize()
{
    add_translation_source(dirname(__FILE__) . '/languages');
}

/**
 * Install the plugin, creating the tables in the database.
 */
function exhibit_builder_install()
{
    $db = get_db();
    $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}exhibits` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `title` varchar(255) collate utf8_unicode_ci default NULL,
      `description` text collate utf8_unicode_ci,
      `credits` text collate utf8_unicode_ci,
      `featured` tinyint(1) default '0',
      `public` tinyint(1) default '0',
      `theme` varchar(30) collate utf8_unicode_ci default NULL,
      `theme_options` text collate utf8_unicode_ci default NULL,
      `slug` varchar(30) collate utf8_unicode_ci default NULL,
      `added` timestamp NOT NULL default '0000-00-00 00:00:00',
      `modified` timestamp NOT NULL default '0000-00-00 00:00:00',
      `owner_id` int unsigned default NULL,
      PRIMARY KEY  (`id`),
      UNIQUE KEY `slug` (`slug`),
      KEY `public` (`public`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}exhibit_page_entries` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `item_id` int(10) unsigned default NULL,
      `file_id` int(10) unsigned default NULL,
      `page_id` int(10) unsigned NOT NULL,
      `text` text collate utf8_unicode_ci,
      `caption` text collate utf8_unicode_ci,
      `order` tinyint(3) unsigned NOT NULL,
      PRIMARY KEY  (`id`),
      KEY `page_id_order` (`page_id`, `order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->query("CREATE TABLE IF NOT EXISTS `{$db->prefix}exhibit_pages` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `exhibit_id` int(10) unsigned NOT NULL,
      `parent_id` int(10) unsigned,
      `title` varchar(255) collate utf8_unicode_ci NOT NULL,
      `slug` varchar(30) collate utf8_unicode_ci NOT NULL,
      `layout` varchar(255) collate utf8_unicode_ci default NULL,
      `order` tinyint(3) unsigned NOT NULL,
      PRIMARY KEY  (`id`),
      KEY `exhibit_id_order` (`exhibit_id`, `order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
}

/**
 * Uninstall the plugin.
 */
function exhibit_builder_uninstall()
{
    // drop the tables
    $db = get_db();
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}exhibits`";
    $db->query($sql);
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}exhibit_page_entries`";
    $db->query($sql);
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}exhibit_pages`";
    $db->query($sql);

    // delete plugin options
    delete_option('exhibit_builder_sort_browse');
}

/**
 * Upgrades ExhibitBuilder's tables to be compatible with a new version.
 *
 * @param array $args expected keys:
 *  'old_version' => Previous plugin version
 *  'new_version' => Current version; to be upgraded to
 */
function exhibit_builder_upgrade($args)
{
    $oldVersion = $args['old_version'];
    $newVersion = $args['new_version'];

    $db = get_db();
    
    // Transition to upgrade model for EB
    if (version_compare($oldVersion, '0.6', '<') )
    {
        $sql = "ALTER TABLE `{$db->prefix}exhibits` ADD COLUMN `theme_options` text collate utf8_unicode_ci default NULL AFTER `theme`";
        $db->query($sql);
    }

    if (version_compare($oldVersion, '0.6', '<=') )
    {
        $sql = "ALTER TABLE `{$db->prefix}items_section_pages` ADD COLUMN `caption` text collate utf8_unicode_ci default NULL AFTER `text`";
        $db->query($sql);
    }

    if(version_compare($oldVersion, '2.0-dev', '<')) {
        $sql = "RENAME TABLE `{$db->prefix}items_section_pages` TO `{$db->prefix}exhibit_page_entries` ";
        $db->query($sql);

        //alter the section_pages table into revised exhibit_pages table
        $sql = "ALTER TABLE `{$db->prefix}section_pages` ADD COLUMN `parent_id` INT UNSIGNED NULL AFTER `id` ";
        $db->query($sql);

        $sql = "ALTER TABLE `{$db->prefix}section_pages` ADD COLUMN `exhibit_id` INT UNSIGNED NOT NULL AFTER `parent_id` ";
        $db->query($sql);

        $sql = "RENAME TABLE `{$db->prefix}section_pages` TO `{$db->prefix}exhibit_pages` ";
        $db->query($sql);

        //dig up all the data about sections so I can turn them into ExhibitPages
        $sql = "SELECT * FROM `{$db->prefix}sections` ";
        $result = $db->query($sql);
        $sectionData = $result->fetchAll();

        $sectionIdMap = array();
        foreach($sectionData as $section) {
            $sectionToPage = new ExhibitPage();
            $sectionToPage->title = $section['title'];
            $sectionToPage->parent_id = null;
            $sectionToPage->exhibit_id = $section['exhibit_id'];
            $sectionToPage->layout = 'text';
            $sectionToPage->slug = $section['slug'];
            $sectionToPage->order = $section['order'];
            $sectionToPage->save();
            $sectionIdMap[$section['id']] = array('pageId' =>$sectionToPage->id, 'exhibitId'=>$section['exhibit_id']);

            //slap the section's description into a text entry for the page
            $entry = new ExhibitPageEntry();
            $entry->page_id = $sectionToPage->id;
            $entry->order = 1;
            $entry->text = $section['description'];
            $entry->save();
        }


        //map the old section ids to the new page ids, and slap in the correct exhibit id.
        foreach($sectionIdMap as $sectionId=>$data) {
            $pageId = $data['pageId'];
            $exhibitId = $data['exhibitId'];
            //probably a more sophisticated way to do the updates, but my SQL skills aren't up to it
            $sql = "UPDATE `{$db->prefix}exhibit_pages` SET parent_id = $pageId, exhibit_id = $exhibitId WHERE section_id = $sectionId ";
            $db->query($sql);
        }

        $sql = "ALTER TABLE `{$db->prefix}exhibit_pages` DROP `section_id` ";

        $db->query($sql);

        //finally kill the sections for good.
        $sql = "DROP TABLE `{$db->prefix}sections`";

        $db->query($sql);
    }

    if(version_compare($oldVersion, '2.0-dev2', '<')) {
        $sql = "ALTER TABLE `{$db->prefix}exhibit_page_entries` ADD `file_id` INT UNSIGNED DEFAULT NULL AFTER `item_id`";
        $db->query($sql);

        $sql = "ALTER TABLE `{$db->prefix}exhibit_page_entries` ADD INDEX `page_id_order` (`page_id`, `order`)";
        $db->query($sql);

        $sql = "ALTER TABLE `{$db->prefix}exhibit_pages` ADD INDEX `exhibit_id_order` (`exhibit_id`, `order`)";
        $db->query($sql);
        
        delete_option('exhibit_builder_use_browse_exhibits_for_homepage');
    }
}

/**
 * Display the configuration form.
 */
function exhibit_builder_config_form()
{
    include 'config_form.php';
}

/**
 * Process the configuration form.
 */
function exhibit_builder_config()
{
    set_option('exhibit_builder_sort_browse', $_POST['exhibit_builder_sort_browse']);
}

/**
 * Modify the ACL to include an 'ExhibitBuilder_Exhibits' resource.
 *
 * Requires the module name as part of the ACL resource in order to avoid naming
 * conflicts with pre-existing controllers, e.g. an ExhibitBuilder_ItemsController
 * would not rely on the existing Items ACL resource.
 *
 * @param array $args Zend_Acl in the 'acl' key
 */
function exhibit_builder_define_acl($args)
{
    $acl = $args['acl'];

    /*
     * NOTE: unless explicitly denied, super users and admins have access to all
     * of the defined resources and privileges.  Other user levels will not by default.
     * That means that admin and super users can both manipulate exhibits completely,
     * but researcher/contributor cannot.
     */
    $acl->addResource('ExhibitBuilder_Exhibits');

    $acl->allow(null, 'ExhibitBuilder_Exhibits',
        array('show', 'summary', 'showitem', 'browse', 'tags'));

    // Allow contributors everything but editAll and deleteAll.
    $acl->allow('contributor', 'ExhibitBuilder_Exhibits',
        array('add', 'add-page', 'delete-page', 'edit-page-content',
            'edit-page-metadata', 'item-container', 'theme-config',
            'editSelf', 'deleteSelf'));

    $acl->allow(null, 'ExhibitBuilder_Exhibits', array('edit', 'delete'),
        new Omeka_Acl_Assert_Ownership);
}

/**
 * Add the routes from routes.ini in this plugin folder.
 *
 * @param array $args Router object in 'router' key
 */
function exhibit_builder_define_routes($args)
{
    $router = $args['router'];
    $router->addConfig(new Zend_Config_Ini(EXHIBIT_PLUGIN_DIR .
        DIRECTORY_SEPARATOR . 'routes.ini', 'routes'));
}

/**
 * Display the CSS layout for the exhibit in the public head
 */
function exhibit_builder_public_head()
{
    queue_css_file('exhibits');
    if ($layoutCssHref = exhibit_builder_layout_css()) {
        queue_css_url($layoutCssHref);
    }
}

/**
 * Display the CSS style and javascript for the exhibit in the admin head
 */
function exhibit_builder_admin_head()
{
    $request = Zend_Controller_Front::getInstance()->getRequest();
    $module = $request->getModuleName();
    $controller = $request->getControllerName();

    // Check if using Exhibits controller, and add the stylesheet for general display of exhibits
    if ($module == 'exhibit-builder' && $controller == 'exhibits') {
        queue_css_file('exhibits', 'screen');
        queue_js_file(array('vendor/tiny_mce/tiny_mce', 'exhibits'));
    }
}

/**
 * Append an Exhibits section to admin dashboard
 * 
 * @param array $stats Array of "statistics" displayed on dashboard
 * @return array
 */
function exhibit_builder_dashboard_stats($stats)
{
    if (is_allowed('ExhibitBuilder_Exhibits', 'browse')) {
        $stats[] = array(link_to('exhibits', array(), total_records('Exhibits')), __('exhibits'));
    }
    return $stats;
}

/**
 * Adds the Browse Exhibits link to the public main navigation
 *
 * @param array $navArray The array of navigation links
 * @return array
 */
function exhibit_builder_public_main_nav($navArray)
{
    $navArray[] = array(
        'label' => __('Browse Exhibits'),
        'uri' => url('exhibits'),
        'visible' => true
    );
    return $navArray;
}

/**
 * Adds the Exhibits link to the admin navigation
 *
 * @param array $navArray The array of admin navigation links
 * @return array
 */
function exhibit_builder_admin_nav($navArray)
{
    $navArray[] = array(
        'label' => __('Exhibits'),
        'uri' => url('exhibits'),
        'resource' => 'ExhibitBuilder_Exhibits',
        'privilege' => 'browse'
    );
    return $navArray;
}

/**
 * Intercept get_theme_option calls to allow theme settings on a per-Exhibit basis.
 *
 * @param string $themeOptions Serialized array of theme options
 * @param string $args Unused here
 */
function exhibit_builder_theme_options($themeOptions, $args)
{
    if (Zend_Controller_Front::getInstance()->getRequest()->getModuleName() == 'exhibit-builder') {
        try {
            if ($exhibit = get_current_record('exhibit', false)) {
                $exhibitThemeOptions = $exhibit->getThemeOptions();
                if (!empty($exhibitThemeOptions)) {
                    return serialize($exhibitThemeOptions);
                }
            }
        } catch (Zend_Exception $e) {
            // no view available
        }
    }
    return $themeOptions;
}

/**
 * Filter for changing the public theme between exhibits.
 *
 * @param string $themeName "Normal" current theme.
 * @return string Theme that will actually be used.
 */
function exhibit_builder_public_theme_name($themeName)
{
    static $exhibitTheme;

    if ($exhibitTheme) {
        return $exhibitTheme;
    }

    $request = Zend_Controller_Front::getInstance()->getRequest();

    if ($request->getModuleName() == 'exhibit-builder') {
        $slug = $request->getParam('slug');
        $exhibit = get_db()->getTable('Exhibit')->findBySlug($slug);
        if ($exhibit && ($exhibitTheme = $exhibit->theme)) {
            return $exhibitTheme;
        }
    }
    return $themeName;
}

/**
 * Custom hook from the HtmlPurifier plugin that will only fire when that plugin is
 * enabled.
 *
 * @param $args: 'purifier' => HTMLPurifier The purifier object.
 */
function exhibit_builder_purify_html($args)
{
    $request = Zend_Controller_Front::getInstance()->getRequest();
    $purifier = $args['purifier'];
    // Make sure that we only bother with the Exhibits controller in the ExhibitBuilder module.
    if ($request->getControllerName() != 'exhibits' or $request->getModuleName() != 'exhibit-builder') {
        return;
    }

    $post = $request->getPost();

    switch ($request->getActionName()) {
        // exhibit-metadata-form
        case 'add':
        case 'edit':

        case 'add-page':
        case 'edit-page-metadata':
            // Skip the page-metadata-form.
            break;

        case 'edit-page-content':
            // page-content-form
            if (isset($post['Text']) && is_array($post['Text'])) {
                // All of the 'Text' entries are HTML.
                foreach ($post['Text'] as $key => $text) {
                    $post['Text'][$key] = $purifier->purify($text);
                }
            }
            if (isset($post['Caption']) && is_array($post['Caption'])) {
                foreach ($post['Caption'] as $key => $text) {
                    $post['Caption'][$key] = $purifier->purify($text);
                }
            }
            break;

        default:
            // Don't process anything by default.
            break;
    }

    $request->setPost($post);
}

/**
 * Hooks into item_browse_sql to return items in a particular exhibit. The
 * passed exhibit can either be an Exhibit object or a specific exhibit ID.
 *
 * @return Omeka_Db_Select
 */
function exhibit_builder_items_browse_sql($args)
{
    $select = $args['select'];
    $params = $args['params'];
    $db = get_db();

    $exhibit = isset($params['exhibit']) ? $params['exhibit'] : null;

    if ($exhibit) {
        $select->joinInner(
            array('epe' => $db->ExhibitPageEntry),
            'epe.item_id = items.id',
            array()
            );

        $select->joinInner(
            array('ep' => $db->ExhibitPage),
            'ep.id = epe.page_id',
            array()
            );

        $select->joinInner(
            array('e' => $db->Exhibit),
            'e.id = ep.exhibit_id',
            array()
            );

        if ($exhibit instanceof Exhibit) {
            $select->where('e.id = ?', $exhibit->id);
        } elseif (is_numeric($exhibit)) {
            $select->where('e.id = ?', $exhibit);
        }
    }

    return $select;
}

/**
 * Form element for advanced search.
 */
function exhibit_builder_items_search()
{
    $view = get_view();
    $html = '<div class="field"><div class="two columns alpha">'
          . $view->formLabel('exhibit', __('Search by Exhibit'))
          . '</div><div class="five columns omega inputs">'
          . $view->formSelect('exhibit', @$_GET['exhibit'], array(), get_table_options('Exhibit'))
          . '</div></div>';
    echo $html;
}

function exhibit_builder_search_record_types($recordTypes)
{
    $recordTypes['Exhibit'] = __('Exhibit');
    $recordTypes['ExhibitPage'] = __('Exhibit Page');
    return $recordTypes;
}
