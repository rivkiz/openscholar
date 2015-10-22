<?php

/**
 * @file
 * Contains \RestfulDataProviderDbQuery
 */

class OSRestfulCPMenu extends \RestfulBase implements \RestfulDataProviderInterface {

  /**
   * Overrides \RestfulBase::controllersInfo().
   */
  public static function controllersInfo() {
    return array(
      '' => array(
        // If they don't pass a menu-id then display nothing.
        \RestfulInterface::GET => 'index',
        \RestfulInterface::HEAD => 'index',
        // POST
        \RestfulInterface::POST => 'create',
      ),
      // We don't know what the ID looks like, assume that everything is the ID.
      '^.*$' => array(
        \RestfulInterface::GET => 'getMenu',
        \RestfulInterface::HEAD => 'getMenu',
        \RestfulInterface::PUT => 'replace',
        \RestfulInterface::PATCH => 'update',
        \RestfulInterface::DELETE => 'remove',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function publicFieldsInfo() {
    return array(
      'type' => array(
        'property' => 'type',
      ),
      'label' => array(
        'property' => 'label',
      ),
      'weight' => array(
        'property' => 'weight',
      ),
      'children' => array(
        'property' => 'children',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    $account = $this->getAccount();
    return user_access('adminsiter site configuration', $account) || $this->checkGroupAccess();;
  }

  /**
   * Verify the user's request has access CRUD in the current group.
   */
  public function checkGroupAccess() {
    $account = $this->getAccount();

    $vsite = null;
    if (!empty($this->request['vsite'])) {
      $vsite = $this->request['vsite'];
    }

    if ($vsite) {
      return user_access('administer spaces', $account) || og_is_member('node', $vsite, 'user', $account);
    } else {
      $this->throwException('The vsite ID is missing.');
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function index() {

    $this->throwException('You must provide the id of the menu.');

    return $return;
  }

  /**
   * View a menu.
   *
   * @param string $name_string
   *  the name of the menu you would like to retrieve.
   */
  public function getMenu($name_string) {

    $output = array();

    $function = "get_$name_string";
    if (method_exists($this, $function)) {
      $output = $this->$function();
      $user = $this->getAccount();

      drupal_alter('os_restful_cp_menu_'.$name_string, $output, $user);
      //Set the menu order, so angular does not re-order
      $output = $this->maintainOrder($output);
      $this->alterURLs($output);
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultiple(array $ids) {
    $this->notImplementedCrudOperation(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function update($id, $full_replace = FALSE) {
    $this->notImplementedCrudOperation(__FUNCTION__);
  }


  /**
   * {@inheritdoc}
   */
  public function create() {
    $this->notImplementedCrudOperation(__FUNCTION__);
  }


  /**
   * {@inheritdoc}
   */
  public function remove($id) {
    $this->notImplementedCrudOperation(__FUNCTION__);
  }

  /**
   * Builds the admin menu as a structured array ready for drupal_render().
   *
   * @return Array of links and settings relating to the admin menu.
   */
  protected function get_admin_panel() {

    $user = $this->getAccount();
    $vsite = $this->request['vsite'];
    $vsite_object = vsite_get_vsite($vsite);

    $bundles = os_app_info_declared_node_bundle();
    $type_info = node_type_get_types();
    $variable_controller = $this->getVariableController($vsite);
    $spaces_features = $variable_controller->get('spaces_features');

    foreach ($bundles as $bundle) {
      if (!og_user_access('node', $vsite, 'create ' . $bundle . ' content')) {
        continue;
      }

      //Check that the feature is enabled, and not hidden from menus.
      $feature = os_get_app_by_bundle($bundle);
      $info = os_app_info($feature);
      if (empty($spaces_features[$feature]) || (!empty($info['hide from toolbar']) && is_array($info['hide from toolbar']) && in_array($bundle, $info['hide from toolbar']))) {
        continue;
      }

      $type_url_str = str_replace('_', '-', $bundle);
      $add_links["{$bundle}"] = array(
        'label' => $type_info[$bundle]->name,
        'type' => 'link',
        'href' => "node/add/{$type_url_str}",
        'alt' => $type_info[$bundle]->description,
      );

      if (os_importer_importable_content($bundle)) {
          $import_links["{$bundle}"] = array(
          'label' => os_importer_importer_title($bundle),
          'type' => 'link',
          'href' => 'cp/os-importer/' . $bundle,
          'alt' => t("One time bulk import of @type content.",array('@type' => $type_info[$bundle]->name)),
        );
      }
    }

    $feature_settings = array();
    if (spaces_access_admin($user, $vsite_object)) {
      foreach (array_keys(array_filter($spaces_features)) as $feature) {
        $item = menu_get_item("cp/build/features/{$feature}");
        if ($item && $item['href'] == "cp/build/features/{$feature}") {
          $feature_settings["feature_{$feature}"] = array(
            'label' => $item['title'],
            'type' => 'link',
            'href' => $item['href'],
          );
        }
      }
    }

    //Order alphabetically
    ksort($add_links);
    ksort($import_links);

    $structure = array(
      'content' => array(
        'label' => 'Site Content',
        'type' => 'heading',
        'default_state' => 'collapsed',
        'children' => array(
          'browse' => array(
            'label' => 'Browse',
            'type' => 'heading',
            'default_state' => 'collapsed',
            'children' => array(
              'content' => array(
                'label' => 'Content',
                'type' => 'link',
                'href' => 'cp/content'
              ),
              'files' => array(
                'label' => 'Files',
                'type' => 'link',
                'href' => 'cp/content/files'
              ),
//              @tbd v2
//              'widgets' => array(
//                'label' => 'Widgets',
//                'type' => 'link',
//                'href' => '/cp/content'
//              ),
              'tagging' => array(
                'label' => 'Tagging',
                'type' => 'link',
                'href' => 'cp/build/taxonomy'
              ),
            ),
          ),
          'add' => array(
            'label' => 'Add',
            'type' => 'heading',
            'default_state' => 'collapsed',
            'children' => $add_links,
          ),
          'import' => array(
            'label' => 'Import',
            'type' => 'heading',
            'default_state' => 'collapsed',
            'children' => $import_links,
          ),
        ),
      ),
      'menus' => array(
        'label' => 'Menus',
        'type' => 'link',
        'href' => 'cp/build/menu'
      ),
      'appearance' => array(
        'label' => 'Appearance',
        'type' => 'heading',
        'default_state' => 'collapsed',
        'children' => array(
            'themes' => array(
              'label' => 'Themes',
              'type' => 'link',
              'href' => 'cp/appearance'
            ),
            'layout' => array(
              'label' => 'Layout',
              'type' => 'link',
              'href' => 'cp/build/layout'
            ),
//            'theme_settings' => array(
//              'label' => 'Theme Settings',
//              'type' => 'link',
//              'href' => 'dev/null'
//            ),
          ),
      ),
      'settings' => array(
        'label' => 'Settings',
        'type' => 'heading',
        'default_state' => 'collapsed',
        'children' => array(
          'app' => array(
            'label' => 'Apps',
            'type' => 'link',
            'href' => 'cp/apps'
          )
        ) + $feature_settings +
        array(
          'advanced' => array(
            'label' => 'Advanced',
            'type' => 'link',
            'href' => 'cp/settings'
          )
        ),
      ),
      'users_roles' => array(
        'label' => 'Users & Roles',
        'type' => 'link',
        'href' => 'cp/users'
      ),
      'help' => array(
        'label' => 'Help',
        'type' => 'heading',
        'default_state' => 'collapsed',
        'children' => array(
          'support' => array(
            'label' => 'Support',
            'type' => 'link',
            'href' => 'cp/support'
          ),
          'documentation' => array(
            'label' => 'Documentation',
            'type' => 'link',
            'href' => 'cp/welcome'
          ),
        ),
      ),
    );

    //Should we show this user the admin links?
    if (user_access('access toolbar',$user)) {
      $admin_menu = menu_tree_all_data('management', NULL, 2);
      $admin_menu = current($admin_menu);
      $admin_links = array();

      foreach ($admin_menu['below'] as $mi) {
        $link = $mi['link'];
        if ($link['hidden'] != 0) continue;
        $key = str_replace(" ", "_", strtolower($link['title']));
        $admin_links[$key] = array(
          'label' => $link['title'],
          'type' => 'link',
          'href' => "/{$link['href']}",
        );
      }

      $structure['admin'] = array(
        'label' => 'Admin',
        'type' => 'heading',
        'default_state' => 'collapsed',
        'children' => $admin_links,
      );
    }

    return $structure;
  }

  protected function getVariableController($vsite) {

    $controller = FALSE;
    ctools_include('plugins');

    $plugin = ctools_get_plugins('spaces', 'plugins', 'spaces_controller_variable');
    if ($plugin && $class = ctools_plugin_get_class($plugin, 'handler')) {
      $controller = new $class('variable', 'og', $vsite);
    }

    return $controller;
  }

  /**
   * Change the array keys so that angular does not re-order them. It automatically re-orders
   *   keys so that they are alphabetical.
   * @param $menu
   */
  protected function maintainOrder($menu) {
    $final = array();
    $i = 0;
    foreach ($menu as $key => $value) {
      if (is_array($value)) {
        $value = $this->maintainOrder($value);
      }

      if(is_array($value) && !empty($value['type'])) {
    	  $final["{$i}_{$key}"] = $value;
    	  $i++;
      } else {
        $final["{$key}"] = $value;
      }
    }

    return $final;
  }

  /**
   * Alter the URL's so that they are vsite specific.
   * @param $menu
   */
  protected function alterURLs(&$menu) {

    $vsite = $this->request['vsite'];
    $vsite_object = vsite_get_vsite($vsite);

    foreach ($menu as $key => $value) {
      if (!empty($value['children'])) {
        $this->alterURLs($menu[$key]['children']);
      }

      if (!empty($value['href']) && $value['href'] != '#') {
        $menu[$key]['href'] = $vsite_object->get_absolute_url($value['href']);
      }
    }
  }
}