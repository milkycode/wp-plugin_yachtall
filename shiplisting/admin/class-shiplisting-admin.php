<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.milkycode.com
 * @since      1.0.0
 *
 * @package    Shiplisting
 * @subpackage Shiplisting/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Shiplisting
 * @subpackage Shiplisting/admin
 * @author     Stefan Meyer <milkycode GmbH> <stefan@milkycode.com>
 */
class Shiplisting_Admin
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.1.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    private $transientStr = 'yachtall_shiplisting-updater';
    private $pluginSlug = 'shiplisting';
    private $pluginVersion = SHIPLISTING_VERSION;
    private $updateUrl = 'https://update.yachtino.com/wordpress/info.json';
    public $api;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Shiplisting_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Shiplisting_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/shiplisting-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Shiplisting_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Shiplisting_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/shiplisting-admin.js', array('jquery'),
            $this->version, false);

        wp_localize_script('wp_ajax_nopriv_shiplisting_get_translation', 'wp_ajax_nopriv_shiplisting_get_translation',
            array('ajax_url' => admin_url('admin-ajax.php')));
        wp_localize_script('wp_ajax_shiplisting_get_translation', 'wp_ajax_shiplisting_get_translation',
            array('ajax_url' => admin_url('admin-ajax.php')));
    }

    function shiplisting_adminmenu()
    {
        add_menu_page(__('yachtino Shiplisting', 'shiplisting'), __('Yachtino', 'shiplisting'), 'manage_options', 'shiplisting-admin', array($this, 'shiplisting_configuration_page'), 'dashicons-superhero-alt');
        add_submenu_page('shiplisting-admin', __('yachtino Shiplisting - Filter', 'shiplisting'), __('Filter', 'shiplisting'), 'manage_options', 'shiplisting-admin-filter', array($this, 'shiplisting_filter_page'));
        add_submenu_page('shiplisting-admin', __('yachtino Shiplisting - Pages', 'shiplisting'), __('Pages', 'shiplisting'), 'manage_options', 'shiplisting-admin-routes', array($this, 'shiplisting_routes_page'));
        add_submenu_page('shiplisting-admin', __('yachtino Shiplisting - Generator', 'shiplisting'), __('Generator', 'shiplisting'), 'manage_options', 'shiplisting-admin-generator', array($this, 'shiplisting_generator_page'));
    }

    function shiplisting_configuration_page()
    {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $shiplisting_active = $_POST['shiplisting_active'];
            $shiplisting_caching = $_POST['shiplisting_caching'];

            $shiplisting_api_key = $_POST['shiplisting_api_key'];
            $shiplisting_api_site_id = $_POST['shiplisting_api_site_id'];

            $shiplisting_filtering_standard = $_POST['shiplisting_filtering_standard'];
            $shiplisting_filtering_advanced = $_POST['shiplisting_filtering_advanced'];
            $shiplisting_contact_form = $_POST['shiplisting_contact_form'];

            if (!empty($shiplisting_api_key)) {
                update_option('shiplisting_api_key', $shiplisting_api_key);
            }
            if (!empty($shiplisting_api_site_id)) {
                update_option('shiplisting_api_siteid', $shiplisting_api_site_id);
            }

            $wpdb->update(
                'wp_shiplisting_settings',
                array(
                    'active' => $shiplisting_active,
                    'cache_active' => $shiplisting_caching,
                    'api_key' => $shiplisting_api_key,
                    'api_site_id' => $shiplisting_api_site_id,
                    'filtering_standard' => $shiplisting_filtering_standard,
                    'filtering_advanced' => $shiplisting_filtering_advanced,
                    'contact_form' => $shiplisting_contact_form,
                ),
                array(
                    'id' => 1
                )
            );
        }

        $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/admin/js/templates/shiplisting-admin-configuration.html');
        if (!$template) {
            return;
        }

        $data = [];
        $result = $wpdb->get_results('SELECT * FROM wp_shiplisting_settings');
        if ($result) {
            $data['active'] = ($result[0]->{'active'} == "1") ? ' selected="selected"' : '';
            $data['cache'] = ($result[0]->{'cache_active'} == "1") ? ' selected="selected"' : '';
            $data['api_key'] = $result[0]->{'api_key'};
            $data['api_site_id'] = $result[0]->{'api_site_id'};
            $data['filtering_standard'] = ($result[0]->{'filtering_standard'} == "1") ? ' selected="selected"' : '';
            $data['filtering_advanced'] = ($result[0]->{'filtering_advanced'} == "1") ? ' selected="selected"' : '';
            $data['contact_form'] = ($result[0]->{'contact_form'} == "1") ? ' selected="selected"' : '';
        }

        $template = preg_replace_callback("/\{(.*?)\}/i", function ($result) use ($data) {
            $placeholder = $result[1];

            if (strpos($placeholder, 'data_') > -1) {
                $placeholder = substr($placeholder, 5);
                $isData = true;
            }

            if ($isData && !empty($placeholder)) {
                return $data[$placeholder];
            }
        }, $template);

        echo $template;
    }

    function shiplisting_filter_page()
    {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $action = $_POST['action'];
            switch ($action) {
//                case 'filter_add':
//                    $filterName = $_POST['filterName'];
//                    $filtersfields = json_encode($_POST['filters']);
//
//                    $wpdb->insert(
//                        'wp_shiplisting_filter',
//                        array(
//                            'name' => $filterName,
//                            'fields_to_filter' => $filtersfields
//                        )
//                    );
//                    break;
                case 'filter_remove':

                    $filterId = $_POST['filterId'];
                    $wpdb->delete('wp_shiplisting_filter', array('id' => $filterId));

                    break;
            }
        }

        echo '
        <div class="shiplisting-admin-wrapper">
            <div class="shiplisting-admin-header">
                <h1>Filter - Overview</h1>
            </div>
            <div class="shiplisting-admin-configuration-page-wrapper">
                <div class="shiplisting-admin-configuration-page">
                    <div class="shiplisting-filter-wrapper">
                        <div class="shiplisting-filter-heading">         
                            <div class="shiplisting-filter-header id">
                                ID
                            </div>         
                            <div class="shiplisting-filter-header name">
                                Name
                            </div>         
                            <div class="shiplisting-filter-header filter-fields">
                                Filter Fields
                            </div>         
                            <div class="shiplisting-filter-header added">
                                Added
                            </div>
                            <div class="shiplisting-filter-header options">
                                Options
                            </div>
                        </div>
                        <div class="shiplisting-filter-content">';

                        $result = $wpdb->get_results("SELECT * FROM wp_shiplisting_filter");
                        foreach ($result as $filterObj) {
                            if (!empty($filterObj->{'fields_to_filter'}) && $filterObj->{'fields_to_filter'} != "null") {
                                $filterFieldsData = json_decode($filterObj->{'fields_to_filter'}, true);
                                $filterFields = '';
                                foreach ($filterFieldsData as $field) {
                                    foreach ($field as $key => $fieldObj) {
                                        $filterFields .= $key . ' => ' . $fieldObj . '<br>';
                                    }
                                }
                            } else {
                                $filterFields = '(keine)';
                            }

                            echo '
                            <div class="shiplisting-filter-row">
                                <div class="shiplisting-filter id">
                                    ' . $filterObj->{'id'} . '
                                </div>
                                <div class="shiplisting-filter name">
                                    ' . $filterObj->{'name'} . '
                                </div>
                                <div class="shiplisting-filter filter-fields">
                                    ' . $filterFields . '
                                </div>
                                <div class="shiplisting-filter added">
                                    ' . $filterObj->{'added'} . '
                                </div>
                                <div class="shiplisting-filter options">
                                    <a class="shiplisting-filter-delete" href="javascript:void(0);">Remove</a>
                                </div>
                            </div>
                            ';
                        }

        echo '
                    </div>
                </div>
            </div>
        </div>
<!--
            <div class="shiplisting-filter-add-wrapper">
                <h2>Filter - Add</h2>
                <div class="shiplisting-filter-add-cell">
                    <div class="shiplisting-filter-add-label">
                        Name:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <input type="text" id="filter_name" name="filter_name" />
                    </div>
                </div>
                <div class="shiplisting-filter-add-cell">
                    <div class="shiplisting-filter-add-label">
                        Filter Felder:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <div class="shiplisting-filter-add-filter-fields">
                            <div class="shiplisting-filter-creator">
                                <a class="creator-cell-add" href="javascript:void(0);">Add Filter Field</a>
                            </div>
                        </div>
                    </div>
                </div>
                <a class="shiplisting-filter-add-button" href="javascript:void(0);">Add Filter</a>
            </div>
-->
        </div>
        <script type="text/javascript">
            jQuery("a.creator-cell-add").on("click", function(e){
                e.preventDefault();
                var template = "<div class=\"creator-cell\"><div class=\"creator-cell-name\"><input type=\"text\" name=\"filterfields_key\" id=\"filterfields_key\" /></div><div class=\"creator-cell-value\"><input type=\"text\" name=\"filterfields_value\" id=\"filterfields_value\" /></div><div class=\"creator-cell-options\"><a class=\"creator-cell-delete\" href=\"javascript:void(0);\">Entfernen</a></div></div>";
                
                if (jQuery("div.shiplisting-filter-creator .creator-cell").length > 0) {
                    jQuery("div.shiplisting-filter-creator .creator-cell:last").after(template);
                } else {
                    jQuery("a.creator-cell-add").before(template);
                }
            });
            
            jQuery(".shiplisting-filter-creator").on("click", ".creator-cell-delete", function(e){
                var confirmResult = confirm("Filter wirklich entfernen?");
                if (confirmResult == true) {
                    jQuery(this).parent().parent().remove();
                }
            });
            
            jQuery(".shiplisting-filter-add-button").on("click", function(e){
                var filterName = jQuery("#filter_name").val();
                if (filterName.length <= 0) {
                    alert("Please type in filter name.");
                    return;
                }
                
                var filters = [];
                jQuery(".creator-cell").each(function(filterObj){
                    if (jQuery(this).find("#filterfields_key").val().length > 0) {
                        var key = jQuery(this).find("#filterfields_key").val();
                        var val = jQuery(this).find("#filterfields_value").val();
                        
                        filters.push({ [key]: val });
                    }
                });
                
                jQuery.post(window.location, {
                    \'action\' : \'filter_add\',
                    \'filterName\': filterName,
                    \'filters\': filters
                }, function(data) {
                  window.location.reload();
                });
            });
            
            jQuery(".shiplisting-filter-delete").on("click", function(e){
                e.preventDefault();
                
                var confirmResult = confirm("Are you sure to remove?");
                if (confirmResult == true) {
                    var currentId = jQuery(this).parent().parent().find(".shiplisting-filter.id").text();
                    currentId = currentId.replace(/\s/g, \'\');
                    
                    jQuery.post(window.location, {
                        \'action\' : \'filter_remove\',
                        \'filterId\': currentId
                    }, function(data) {
                      window.location.reload();
                    });
                }
            });
        </script>
        ';
    }

    function shiplisting_routes_page()
    {
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $action = $_POST['action'];
            switch ($action) {
//                case 'route_add':
//                    $routeName = $_POST['routeName'];
//                    $routeTitle = $_POST['routeTitle'];
//                    $routePath = $_POST['routePath'];
//                    $routeFilter = $_POST['routeFilter'];
//                    $routeType = $_POST['routeType'];
//
//                    $routeCallback = '';
//                    $variables = [];
//                    $arguments = [];
//                    if ($routeType == 0) {
//                        $routeCallback = 'display_boats_details';
//                        array_push($variables, [ 'boat_id' => 1 ]);
//                        array_push($variables, [ 'template' => 'template-shiplisting-getboat.html' ]);
//
//                        $arguments[] = 'boat_id';
//                        $arguments[] = 'template';
//                    } else if ($routeType == 1) {
//                        $routeCallback = 'display_boats';
//                        array_push($variables, [ 'template' => 'template-shiplisting-get-all-boats.html' ]);
//                        array_push($variables, [ 'boatTemplate' => 'template-shiplisting-boat-obj.html' ]);
//                        array_push($variables, [ 'filter' => $routeFilter ]);
//
//                        $arguments[] = 'template';
//                        $arguments[] = 'boatTemplate';
//                        $arguments[] = 'filter';
//                    }
//
//                    $wpdb->insert(
//                        'wp_shiplisting_routes',
//                        array(
//                            'name' => $routeName,
//                            'title' => $routeTitle,
//                            'path' => $routePath,
//                            'callback' => $routeCallback,
//                            'vars' => json_encode($variables),
//                            'arguments' => json_encode($arguments),
//                        )
//                    );
//                    break;
                case 'route_remove':

                    $routeId = $_POST['routeId'];
                    $wpdb->delete('wp_shiplisting_routes', array('id' => $routeId));

                    break;
            }
        }

        $filterRes = $wpdb->get_results("SELECT id, name FROM wp_shiplisting_filter");
        $filterStr = '';
        foreach ($filterRes as $filterObj) {
            $filterStr .= '<option value="' . $filterObj->{'id'} . '">' . $filterObj->{'name'} . '</option>';
        }

        echo '
        <div class="shiplisting-admin-wrapper">
            <div class="shiplisting-admin-header">
                <h1>Pages - Overview</h1>
                <div class="shiplisting-admin-header-desc"></div>
            </div>
            <div class="shiplisting-admin-configuration-page-wrapper">
                <div class="shiplisting-admin-configuration-page">
                    <div class="shiplisting-filter-wrapper">
                        <div class="shiplisting-filter-heading">         
                            <div class="shiplisting-filter-header id">
                                ID
                            </div>         
                            <div class="shiplisting-filter-header name">
                                Name
                            </div>    
                            <div class="shiplisting-filter-header title">
                                Title
                            </div>     
                            <div class="shiplisting-filter-header path">
                                Path
                            </div>         
                            <div class="shiplisting-filter-header variables">
                                Filter
                            </div>
                            <div class="shiplisting-filter-header added">
                                Added
                            </div>
                            <div class="shiplisting-filter-header options">
                                Options
                            </div>
                        </div>
                        <div class="shiplisting-filter-content">';

                        $result = $wpdb->get_results("SELECT * FROM wp_shiplisting_routes");
                        foreach ($result as $routesObj) {
                            $vars = json_decode($routesObj->{'vars'}, true);
                            $filterId = 0;
                            if (isset($vars[2]['filter'])) {
                                $filterId = $vars[2]['filter'];
                            }

                            if ($filterId > 0) {
                                $filter = $wpdb->get_results("SELECT id, name FROM wp_shiplisting_filter WHERE id = $filterId");
                                $filter = $filter[0]->{'name'};
                            } else {
                                $filter = '(none)';
                            }

                            $path = $routesObj->{'path'};
                            $uri = '';
                            if (stripos($path, '/(.*?)$') > 0) {
                                $uri = substr($path, 1);
                                $uri = substr($uri, 0, stripos($uri, '/(.*?)')) . '/57/';
                            } else {
                                $uri = $path;
                            }

                            echo '
                            <div class="shiplisting-filter-row">
                                <div class="shiplisting-filter id">
                                    ' . $routesObj->{'id'} . '
                                </div>
                                <div class="shiplisting-filter name">
                                    ' . $routesObj->{'name'} . '
                                </div>
                                <div class="shiplisting-filter title">
                                    ' . $routesObj->{'title'} . '
                                </div>
                                <div class="shiplisting-filter path">
                                    <a target="_blank" href="/' . $uri . '">' . $routesObj->{'path'} . '</a>
                                </div>
                                <div class="shiplisting-filter variables">
                                    ' . $filter . '
                                </div>
                                <div class="shiplisting-filter added">
                                    ' . $routesObj->{'added'} . '
                                </div>
                                <div class="shiplisting-filter options">
                                    <a class="shiplisting-filter-delete" href="javascript:void(0);">Remove</a>
                                </div>
                            </div>
                            ';
                        }

        echo '
                    </div>
                </div>
            </div>
        </div>
            <!--
            <div class="shiplisting-filter-add-wrapper">
                <h2>Pages - Add</h2>
                <div class="shiplisting-filter-add-cell">
                    <div class="shiplisting-filter-add-label">
                        Name:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <input type="text" id="route_name" name="route_name" />
                    </div>
                </div>
                <div class="shiplisting-filter-add-cell">
                    <div class="shiplisting-filter-add-label">
                        Title:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <input type="text" id="route_title" name="route_title" />
                    </div>
                </div>
                <div class="shiplisting-filter-add-cell">
                    <div class="shiplisting-filter-add-label">
                        Path:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <input type="text" id="route_path" name="route_path" />
                    </div>
                </div>
                <div class="shiplisting-filter-add-cell">
                    <div class="shiplisting-filter-add-label">
                        Type:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <select id="route_type" name="route_type">
                            <option value="0">Detailview</option>
                            <option value="1">Listview</option>
                        </select>
                    </div>
                </div>
                <div class="shiplisting-filter-add-cell filter">
                    <div class="shiplisting-filter-add-label">
                        Filter:
                    </div>
                    <div class="shiplisting-filter-add-input">
                        <select name="route_filter" id="route_filter"><option value="-1" selected>(None)</option>' . $filterStr . '</select>
                    </div>
                </div>
                <a class="shiplisting-filter-add-button" href="javascript:void(0);">Add Route</a>
            </div>
        </div>-->
        <script type="text/javascript">
            jQuery("#variables a.creator-cell-add").on("click", function(e){
                e.preventDefault();
                var template = "<div class=\"creator-cell\"><div class=\"creator-cell-name\"><input type=\"text\" name=\"filterfields_key\" id=\"filterfields_key\" /></div><div class=\"creator-cell-value\"><input type=\"text\" name=\"filterfields_value\" id=\"filterfields_value\" /></div><div class=\"creator-cell-options\"><a class=\"creator-cell-delete\" href=\"javascript:void(0);\">Remove</a></div></div>";
                
                if (jQuery("#variables div.shiplisting-filter-creator .creator-cell").length > 0) {
                    jQuery("#variables div.shiplisting-filter-creator .creator-cell:last").after(template);
                } else {
                    jQuery("#variables a.creator-cell-add").before(template);
                }
            });
            
            jQuery("#arguments a.creator-cell-add").on("click", function(e){
                e.preventDefault();
                var template = "<div class=\"creator-cell\"><div class=\"creator-cell-name\"><input type=\"text\" name=\"filterfields_key\" id=\"filterfields_key\" /></div><div class=\"creator-cell-options\"><a class=\"creator-cell-delete\" href=\"javascript:void(0);\">Remove</a></div></div>";
                
                if (jQuery("#arguments div.shiplisting-filter-creator .creator-cell").length > 0) {
                    jQuery("#arguments div.shiplisting-filter-creator .creator-cell:last").after(template);
                } else {
                    jQuery("#arguments a.creator-cell-add").before(template);
                }
            });
            
            if (jQuery("#route_type option:selected").val() == 0) {
                jQuery(".shiplisting-filter-add-cell.filter").hide();
            } else {
                jQuery(".shiplisting-filter-add-cell.filter").show();
            }
            
            jQuery("#route_type").on("change", function(e){
                if (jQuery(this).find("option:selected").val() == 0) {
                    jQuery(".shiplisting-filter-add-cell.filter").hide();
                } else {
                    jQuery(".shiplisting-filter-add-cell.filter").show();
                }
            });
            
            
            jQuery(".shiplisting-filter-creator").on("click", ".creator-cell-delete", function(e){
                var confirmResult = confirm("Are you sure to remove?");
                if (confirmResult == true) {
                    jQuery(this).parent().parent().remove();
                }
            });
            
            jQuery(".shiplisting-filter-add-button").on("click", function(e){
                var routeName = jQuery("#route_name").val();
                if (routeName.length <= 0) {
                    alert("Please type in route name.");
                    return;
                }
                
                var routeTitle = jQuery("#route_title").val();
                if (routeTitle.length <= 0) {
                    alert("Please type in route title.");
                    return;
                }
                
                var routePath = jQuery("#route_path").val();
                if (routePath.length <= 0) {
                    alert("Please type in route path.");
                    return;
                }
                
                var routeFilter = jQuery("#route_filter option:selected").val();
                if (routeFilter.length <= 0) {
                    alert("Please select route filter.");
                    return;
                }
                
                var routeType = jQuery("#route_type option:selected").val();
                if (routeType.length <= 0) {
                    alert("Please select route type.");
                    return;
                } 
                
                jQuery.post(window.location, {
                    \'action\' : \'route_add\',
                    \'routeName\': routeName,
                    \'routeTitle\': routeTitle,
                    \'routePath\': routePath,
                    \'routeFilter\': routeFilter,
                    \'routeType\': routeType
                }, function(data) {
                  window.location.reload();
                });
            });
            
            jQuery(".shiplisting-filter-delete").on("click", function(e){
                e.preventDefault();
                
                var confirmResult = confirm("Are you sure to remove this route?");
                if (confirmResult == true) {
                    var currentId = jQuery(this).parent().parent().find(".shiplisting-filter.id").text();
                    currentId = currentId.replace(/\s/g, \'\');
                    
                    jQuery.post(window.location, {
                        \'action\' : \'route_remove\',
                        \'routeId\': currentId
                    }, function(data) {
                      window.location.reload();
                    });
                }
            });
        </script>
        ';
    }

    function shiplisting_generator_page()
    {
        global $wpdb;

        if (is_admin() && isset($_GET['action']) && $_GET['action'] == hash('sha512', "dropitlikeitshot")) {
            $wpdb->get_results('DROP TABLE IF EXISTS `wp_shiplisting_routes`');
            $wpdb->get_results('DROP TABLE IF EXISTS `wp_shiplisting_filter`');
            $wpdb->get_results('DROP TABLE IF EXISTS `wp_shiplisting_settings`');
            $wpdb->get_results('DROP TABLE IF EXISTS `wp_shiplisting_caching`');
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (is_array($_POST['formData'][0])) {
                $routeCallback = '';
                $routeName = '';
                $routeTitle = '';
                $routePath = '';
                $routeSource = 0;
                $routeLinkedDetailView = 0;

                $variables = [];
                $arguments = [];
                $routeCallback = '';
                $routeAdvancedFiltering = '';
                $routeLanguage = '';

                if ($_POST['formType'] == 0) {
                    $routeCallback = 'display_boat_details';
                    $routeName = $_POST['formData'][0]['detail_name'] . '';
                    $routeTitle = $_POST['formData'][0]['detail_title'];
                    $routePath = '^' . $_POST['formData'][0]['detail_path'] . '/(.*?)$';
                    $templateDetail = '';
                    $routeLanguage = $_POST['formData'][0]['detail_language'];

                    $routeSource = $_POST['formSource'];
                    if ($routeSource == "0") {
                        $templateDetail = 'template-shiplisting-getboat.html';
                    } elseif ($routeSource == "1") {
                        $templateDetail = 'template-shiplisting-happycharter-getboat.html';
                    }

                    array_push($variables, ['boat_id' => 1]);
                    array_push($variables, ['template' => $templateDetail]);
                    array_push($variables, ['source' => $_POST['formSource']]);

                    $arguments[] = 'boat_id';
                    $arguments[] = 'template';
                    $arguments[] = 'source';
                } elseif ($_POST['formType'] == 1) {
                    $filterNameStr = $_POST['formData'][0]['list_name'] . '_filter';
                    $filters = [];
                    foreach ($_POST['formFilters'] as $filterName => $filterVal) {
                        $filters[$filterName] = $filterVal;
                    }

                    $wpdb->insert(
                        'wp_shiplisting_filter',
                        array(
                            'name' => $filterNameStr,
                            'fields_to_filter' => json_encode($filters)
                        )
                    );
                    $filterId = $wpdb->insert_id;

                    $routeName = $_POST['formData'][0]['list_name'];
                    $routeTitle = $_POST['formData'][0]['list_title'];
                    $routePath = $_POST['formData'][0]['list_path'];
                    $routeCallback = 'display_boats';
                    $advancedFiltering = $_POST['formData'][0]['list_advanced_filtering'];
                    if ($advancedFiltering) {
                        //$routeAdvancedFiltering = json_encode($_POST['formData'][0]['list_advanced_filtering_options']);
                        if (is_array($_POST['formData'][0]['list_advanced_filtering_options'])) {
                            $tmp_routeAdvancedFiltering = [];
                            foreach ($_POST['formData'][0]['list_advanced_filtering_options'] as $option) {
                                $tmp_routeAdvancedFiltering[] = str_replace('adv_filter_', '', $option);
                            }
                            $routeAdvancedFiltering = json_encode($tmp_routeAdvancedFiltering);
                        }
                    }
                    $routeLinkedDetailView = $_POST['formData'][0]['list_linked_detail_view'];
                    $routeLanguage = $_POST['formData'][0]['list_language'];

                    array_push($variables, ['template' => 'template-shiplisting-get-all-boats.html']);
                    array_push($variables, ['boatTemplate' => 'template-shiplisting-boat-obj.html']);
                    array_push($variables, ['filter' => '' . $filterId . '']);
                    array_push($variables, ['hitsByPage' => $_POST['formData'][0]['list_hitsbypage']]);
                    array_push($variables, ['source' => $_POST['formSource']]);

                    $arguments[] = 'template';
                    $arguments[] = 'boatTemplate';
                    $arguments[] = 'filter';
                    $arguments[] = 'hitsByPage';
                    $arguments[] = 'source';
                }

                try {

                    $wpdb->insert(
                        'wp_shiplisting_routes',
                        array(
                            'name' => $routeName,
                            'title' => $routeTitle,
                            'path' => $routePath,
                            'callback' => $routeCallback,
                            'adv_filter' => $routeAdvancedFiltering,
                            'vars' => json_encode($variables),
                            'arguments' => json_encode($arguments),
                            'linked_detail_view' => $routeLinkedDetailView,
                            'language' => $routeLanguage
                        )
                    );
                } catch (Exception $e) {
                    print_r($e);
                }
            }
        }

        $template = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/shiplisting/admin/js/templates/shiplisting-admin-generator.html');
        if (!$template) {
            return;
        }

        if (!Shiplisting_Shortcodes::$api) {
            Shiplisting_Shortcodes::$api = $GLOBALS['shiplisting'];
        }

        echo preg_replace_callback("/\{{(.*?)\}}/i", function ($result) {
            $placeholder = $result[1];
            if ($placeholder == "domain_name") {
                return get_site_url();
            }
        }, $template);
    }

    function shiplisting_pushupdate($transient)
    {
        try {
            if (empty($transient->checked)) {
                return $transient;
            }

            delete_transient($this->transientStr);
            if (false == $remote = get_transient($this->transientStr)) {
                $remote = wp_remote_get($this->updateUrl, array(
                        'timeout' => 10,
                        'headers' => array(
                            'Accept' => 'application/json'
                        )
                    )
                );

                if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {
                    set_transient($this->transientStr, $remote, 43200); // 12 hours cache

                    $body = json_decode($remote['body']);
                    if ($body && !empty($body->version) && version_compare($this->pluginVersion, $body->version, '<')
                        && version_compare($body->requires, get_bloginfo('version'), '<')) {
                        $res = new stdClass();
                        $res->slug = $this->pluginSlug;
                        $res->plugin = 'shiplisting/shiplisting.php';
                        $res->new_version = $body->version;
                        $res->tested = $body->tested;
                        $res->package = $body->download_url;
                        $res->compatibility = new stdClass();
                        $transient->response[$res->plugin] = $res;
                        $transient->checked[$res->plugin] = $body->version;
                    }
                }
            }
        } catch (Exception $e) {
        }

        return $transient;
    }

    function shiplisting_plugininfo($res, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return false;
        }

        if ($this->pluginSlug !== $args->slug) {
            return false;
        }

        delete_transient($this->transientStr);
        if (false == $remote = get_transient($this->transientStr)) {
            $remote = wp_remote_get($this->updateUrl, array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/json'
                    )
                )
            );

            if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200 && !empty($remote['body'])) {
                set_transient($this->transientStr, $remote, 43200);
            }

        }

        if ($remote) {
            $remote = json_decode($remote['body']);
            $res = new stdClass();
            $res->name = $remote->name;
            $res->slug = $this->pluginSlug;
            $res->version = $remote->version;
            $res->tested = $remote->tested;
            $res->requires = $remote->requires;
            $res->author = '<a href="https://www.milkycode.com">Christian Hinz (christian@milkycode.com) | milkycode GmbH</a>';
            $res->author_profile = '';
            $res->download_link = $remote->download_url;
            $res->trunk = $remote->download_url;
            $res->last_updated = $remote->last_updated;
            $res->sections = array(
                //'description' => $remote->sections->description,
                //'installation' => $remote->sections->installation,
                'changelog' => $remote->sections->changelog
                // you can add your custom sections (tabs) here
            );
            if (!empty($remote->sections->screenshots)) {
                $res->sections['screenshots'] = $remote->sections->screenshots;
            }

            //$res->banners = array(
            //	'low' => 'https://YOUR_WEBSITE/banner-772x250.jpg',
            //  'high' => 'https://YOUR_WEBSITE/banner-1544x500.jpg'
            //);
            return $res;
        }

        return false;
    }

    function shiplisting_afterupdate($upgrader_object)
    {
        delete_transient($this->transientStr);
    }
}