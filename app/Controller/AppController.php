<?php // http://www.phpencode.org
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

define('TIMESTAMP_DEBUT', microtime(true));
App::uses('Controller', 'Controller');
require ROOT . '/config/function.php';


/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package        app.Controller
 * @link        http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{

    public $components = ['Util', 'Module', 'Session', 'Cookie', 'Security', 'EyPlugin', 'Lang', 'Theme', 'History', 'Statistics', 'Permissions', 'Update', 'Server'];
    public $helpers = ['Session'];

    public $view = 'Theme';

    protected $isConnected = false;
    protected $isBanned = false;

    public function beforeFilter()
    {

        // lowercase to avoid errors when the controller is called with uppercase
        $this->params['controller'] = strtolower($this->params['controller']);
        $this->params['action'] = strtolower($this->params['action']);

        $LoginCondition = $this->here != "/login" || !$this->EyPlugin->isInstalled('phpierre.signinup');

        $this->loadModel("Maintenance");
        if ($this->params['controller'] != "user" and $this->params['controller'] != "maintenance" and !$this->Permissions->can("BYPASS_MAINTENANCE") and $this->Maintenance->checkMaintenance($this->here) and $LoginCondition) {
            $this->redirect([
                'controller' => 'maintenance',
                'action' => 'index',
                'plugin' => false,
                'admin' => false,
                explode("/", $this->here)[1]
            ]);
        }

        // Plugin disabled
        if ($this->request->params['plugin']) {
            $plugin = $this->EyPlugin->findPlugin('slugLower', $this->request->params['plugin']);
            if (!empty($plugin) && !$plugin->loaded) {
                $this->redirect('/');
                exit;
            }
        }

        // Global configuration
        $this->__initConfiguration();

        // CSRF / Security
        $this->__initSecurity();

        // User
        $this->__initUser();
        $this->__initWebsiteInfos();

        // Navbar
        if ($this->params['prefix'] == "admin" && !$this->request->is('ajax'))
            $this->__initAdminNavbar();
        else if (!$this->request->is('ajax'))
            $this->__initNavbar();

        // Server
        if ($this->params['prefix'] !== "admin" && !$this->request->is('ajax'))
            $this->__initServerInfos();

        // Plugins events
        $this->EyPlugin->initEventsListeners($this);

        $event = new CakeEvent('requestPage', $this, $this->request->data);
        $this->getEventManager()->dispatch($event);
        if ($event->isStopped())
            return $event->result;

        if ($this->request->is('post')) {
            $event = new CakeEvent('onPostRequest', $this, $this->request->data);
            $this->getEventManager()->dispatch($event);
            if ($event->isStopped())
                return $event->result;
        }


    }

    public function __initConfiguration()
    {
        // configuration générale
        $this->loadModel('Configuration');
        $this->set('Configuration', $this->Configuration);

        $website_name = $this->Configuration->getKey('name');
        list($theme_name, $theme_config) = $this->Theme->getCurrentTheme();
        Configure::write('theme', $theme_name);
        $this->__setTheme();


        // Session
        $session_type = $this->Configuration->getKey('session_type');
        if ($session_type) {
            Configure::write('Session', [
                'defaults' => $session_type
            ]);
        }

        // partie sociale
        $facebook_link = $this->Configuration->getKey('facebook');
        $skype_link = $this->Configuration->getKey('skype');
        $youtube_link = $this->Configuration->getKey('youtube');
        $twitter_link = $this->Configuration->getKey('twitter');

        // Variables
        $google_analytics = $this->Configuration->getKey('google_analytics');
        $configuration_end_code = $this->Configuration->getKey('end_layout_code');
        $condition = $this->Configuration->getKey('condition');

        $this->loadModel('SocialButton');
        $findSocialButtons = $this->SocialButton->find('all');
        $type = "";
        switch ($this->Configuration->getKey('captcha_type')) {
            case "1":
                $type = "default";
                break;
            case "2":
                $type = "google";
                break;
            case "3":
                $type = "hcaptcha";
                break;
        }

        $captcha['type'] = $type;
        $captcha['siteKey'] = $this->Configuration->getKey('captcha_sitekey');
        $reCaptcha = $captcha;
        $this->set(compact(
            'reCaptcha',
            'captcha',
            'condition',
            'website_name',
            'theme_config',
            'facebook_link',
            'skype_link',
            'youtube_link',
            'twitter_link',
            'findSocialButtons',
            'google_analytics',
            'configuration_end_code'
        ));
    }

    protected function __setTheme()
    {
        if (!isset($this->params['prefix']) or $this->params['prefix'] !== "admin" or (isset($this->params['prefix']) and $this->params['prefix'] === "admin" and $this->response->statusCode() >= 400))
            $this->theme = Configure::read('theme');
    }

    protected function __initSecurity()
    {
        $this->Security->blackHoleCallback = 'blackhole';
        $this->Security->validatePost = false;
        $this->Security->csrfUseOnce = false;

        $csrfToken = $this->Session->read('_Token')['key'];
        if (empty($csrfToken)) {
            $this->Security->generateToken($this->request);
            $csrfToken = $this->Session->read('_Token')['key'];
        }
        $this->set(compact('csrfToken'));
    }

    private function __initUser()
    {
        $this->loadModel('User');

        if (!$this->User->isConnected() && ($cookie = $this->Cookie->read('remember_me')) && isset($cookie['pseudo']) && isset($cookie['password'])) {
            $user = $this->User->find('first', ['conditions' => ['pseudo' => $cookie['pseudo']]]);

            if (!empty($user) && $user['User']['password'] == $cookie['password'])
                $this->Session->write('user', $user['User']['id']);
        }

        $this->isConnected = $this->User->isConnected();
        $this->set('isConnected', $this->isConnected);

        if ($this->isConnected) {
            $LoginCondition = ($this->here != "/login") || !$this->EyPlugin->isInstalled('phpierre.signinup');
            if ($this->params['controller'] != "user" and $this->params['controller'] != "ban" and $this->User->isBanned() != false and $LoginCondition) {
                $this->isBanned = $this->User->isBanned();

                $this->redirect([
                    'controller' => 'ban',
                    'action' => 'index',
                    'plugin' => false,
                    'admin' => false
                ]);
            }
        }
        $user = ($this->isConnected) ? $this->User->getAllFromCurrentUser() : [];
        if (!empty($user))
            $user['isAdmin'] = $this->User->isAdmin();

        $this->set(compact('user'));
    }

    public function __initWebsiteInfos()
    {
        $this->loadModel('User');
        $this->loadModel('Visit');
        $users_count = $this->User->find('count');
        $users_last = $this->User->find('first', ['order' => 'created DESC']);
        $users_last = $users_last['User'];
        $users_count_today = $this->User->find('count', ['conditions' => ['created LIKE' => date('Y-m-d') . '%']]);
        $visits_count = $this->Visit->getVisitsCount();
        $visits_count_today = $this->Visit->getVisitsByDay(date('Y-m-d'))['count'];
        $admin_dark_mode = $this->Cookie->read('use_admin_dark_mode');
        $this->set(compact('users_count', 'users_last', 'users_count_today', 'visits_count', 'visits_count_today', 'admin_dark_mode'));

    }

    public function __initAdminNavbar()
    {
        $nav = [
            'Dashboard' => [
                'icon' => 'fas fa-tachometer-alt',
                'route' => ['controller' => 'admin', 'action' => 'index', 'admin' => true, 'plugin' => false]
            ],
            'GLOBAL__ADMIN_GENERAL' => [
                'icon' => 'cogs',
                'menu' => [
                    'USER__MEMBERS_REGISTERED' => [
                        'icon' => 'users',
                        'permission' => 'MANAGE_USERS',
                        'route' => ['controller' => 'user', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'BAN__MEMBERS' => [
                        'icon' => 'ban',
                        'permission' => 'MANAGE_BAN',
                        'route' => ['controller' => 'ban', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'PERMISSIONS__LABEL' => [
                        'icon' => 'user',
                        'permission' => 'MANAGE_PERMISSIONS',
                        'route' => ['controller' => 'permissions', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'CONFIG__GENERAL_PREFERENCES' => [
                        'icon' => 'cog',
                        'permission' => 'MANAGE_CONFIGURATION',
                        'route' => ['controller' => 'configuration', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'STATS__TITLE' => [
                        'icon' => 'far fa-chart-bar',
                        'permission' => 'VIEW_STATISTICS',
                        'route' => ['controller' => 'statistics', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'MAINTENANCE__TITLE' => [
                        'icon' => 'fas fa-hand-paper',
                        'permission' => 'MANAGE_MAINTENANCE',
                        'route' => ['controller' => 'maintenance', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                ]
            ],
            'GLOBAL__CUSTOMIZE' => [
                'icon' => 'fas fa-copy',
                'menu' => [
                    'NEWS__TITLE' => [
                        'icon' => 'fas fa-pencil-ruler',
                        'permission' => 'MANAGE_NEWS',
                        'route' => ['controller' => 'news', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'PAGE__TITLE' => [
                        'icon' => 'fas fa-file-alt',
                        'permission' => 'MANAGE_PAGE',
                        'route' => ['controller' => 'pages', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'NAVBAR__TITLE' => [
                        'icon' => 'fas fa-bars',
                        'permission' => 'MANAGE_NAV',
                        'route' => ['controller' => 'navbar', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'SEO__TITLE' => [
                        'icon' => 'fab fa-google',
                        'permission' => 'MANAGE_SEO',
                        'route' => ['controller' => 'seo', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'MOTD__TITLE' => [
                        'icon' => 'fas fa-sort-amount-up-alt',
                        'permission' => 'MANAGE_MOTD',
                        'route' => ['controller' => 'motd', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ]
                ]
            ],
            'SERVER__TITLE' => [
                'icon' => 'server',
                'permission' => 'MANAGE_SERVERS',
                'menu' => [
                    'SERVER__LINK' => [
                        'icon' => 'fas fa-arrows-alt-h',
                        'permission' => 'MANAGE_SERVERS',
                        'route' => ['controller' => 'server', 'action' => 'link', 'admin' => true, 'plugin' => false]
                    ],
                    'SERVER__BANLIST' => [
                        'icon' => 'ban',
                        'permission' => 'MANAGE_SERVERS',
                        'route' => ['controller' => 'server', 'action' => 'banlist', 'admin' => true, 'plugin' => false]
                    ],
                    'SERVER__WHITELIST' => [
                        'icon' => 'list',
                        'permission' => 'MANAGE_SERVERS',
                        'route' => ['controller' => 'server', 'action' => 'whitelist', 'admin' => true, 'plugin' => false]
                    ],
                    'SERVER__ONLINE_PLAYERS' => [
                        'icon' => 'list-ul',
                        'permission' => 'MANAGE_SERVERS',
                        'route' => ['controller' => 'server', 'action' => 'online', 'admin' => true, 'plugin' => false]
                    ],
                    'SERVER__CMD' => [
                        'icon' => 'key',
                        'permission' => 'MANAGE_SERVERS',
                        'route' => ['controller' => 'server', 'action' => 'cmd', 'admin' => true, 'plugin' => false]
                    ]
                ]
            ],
            'GLOBAL__ADMIN_PLUGINS' => [
                'icon' => 'puzzle-piece'
            ],
            'GLOBAL__ADMIN_OTHER_TITLE' => [
                'icon' => 'fas fa-folder-open',
                'menu' => [
                    'PLUGIN__TITLE' => [
                        'icon' => 'plus',
                        'permission' => 'MANAGE_PLUGINS',
                        'route' => ['controller' => 'plugin', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'THEME__TITLE' => [
                        'icon' => 'mobile',
                        'permission' => 'MANAGE_THEMES',
                        'route' => ['controller' => 'theme', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'API__LABEL' => [
                        'icon' => 'sitemap',
                        'permission' => 'MANAGE_API',
                        'route' => ['controller' => 'API', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'NOTIFICATION__TITLE' => [
                        'icon' => 'flag',
                        'permission' => 'MANAGE_NOTIFICATIONS',
                        'route' => ['controller' => 'notifications', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ],
                    'HISTORY__VIEW_GLOBAL' => [
                        'icon' => 'table',
                        'permission' => 'VIEW_WEBSITE_HISTORY',
                        'route' => ['controller' => 'history', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ]
                ]
            ],
            'GLOBAL__UPDATE' => [
                'icon' => 'wrench',
                'permission' => 'MANAGE_UPDATE',
                'route' => ['controller' => 'update', 'action' => 'index', 'admin' => true, 'plugin' => false]
            ]
        ];

        // Functions
        if (!function_exists('addToNav')) {
            function addToArrayAt($where, $index, $array)
            {
                return array_slice($where, 0, $index, true) +
                    $array +
                    array_slice($where, $index, count($where) - $index, true);
            }
        }
        if (!function_exists('addToNav')) {
            function addToNav($menus, $nav, $index = 0)
            {
                if (!is_array($menus))
                    return $nav;
                foreach ($menus as $name => $menu) {
                    if (isset($nav[$name])) // Multidimensional
                        $nav[$name] = addToNav($menu, $nav[$name], $index + 1);
                    else { // Add
                        if (!isset($nav['menu']) && $index !== 0) // No others submenu
                            $nav['menu'] = [];
                        if ($index === 0) // Add
                            $nav = addToArrayAt($nav, (isset($menu['index']) ? $menu['index'] : count($nav)), [$name => $menu]);
                        else // Add into submenu
                            $nav['menu'] = addToArrayAt($nav['menu'], (isset($menu['index']) ? $menu['index'] : count($nav['menu'])), [$name => $menu]);
                    }
                }
                return $nav;
            }
        }

        // Add slider if !useless
        $themeConfig = $this->Theme->getConfig(Configure::read('theme'));
        if (isset($themeConfig->slider) && $themeConfig->slider) {
            $nav['GLOBAL__CUSTOMIZE']['menu'] = addToArrayAt($nav['GLOBAL__CUSTOMIZE']['menu'],
                count($nav['GLOBAL__CUSTOMIZE']['menu']), [
                    'SLIDER__TITLE' => [
                        'icon' => 'far fa-image',
                        'permission' => 'MANAGE_SLIDER',
                        'route' => ['controller' => 'slider', 'action' => 'index', 'admin' => true, 'plugin' => false]
                    ]
                ]);
        }

        // Handle plugins
        $plugins = $this->EyPlugin->pluginsLoaded;
        foreach ($plugins as $plugin) {
            if (!isset($plugin->admin_menus) || !$plugin->active)
                continue;
            $menus = json_decode(json_encode($plugin->admin_menus), true);
            $nav = addToNav($menus, $nav);
        }

        $this->set('adminNavbar', $nav);
    }

    public function __initNavbar()
    {
        $this->loadModel('Navbar');
        $nav = $this->Navbar->find('all', ['order' => 'order']);
        if (empty($nav)) {
            $this->set('nav', false);
            return;
        }
        $this->loadModel('Page');
        $pages = $this->Page->find('all', ['fields' => ['id', 'slug']]);
        foreach ($pages as $key => $value)
            $pages_listed[$value['Page']['id']] = $value['Page']['slug'];
        foreach ($nav as $key => $value) {
            if (!isset($value['Navbar']['url']['type']))
                continue;
            if ($value['Navbar']['url']['type'] == "plugin") {
                if (isset($value['Navbar']['url']['route']))
                    $plugin = $this->EyPlugin->findPlugin('slug', $value['Navbar']['url']['id']);
                else
                    $plugin = $this->EyPlugin->findPlugin('DBid', $value['Navbar']['url']['id']);
                if (is_object($plugin))
                    $nav[$key]['Navbar']['url'] = (isset($value['Navbar']['url']['route'])) ? Router::url($value['Navbar']['url']['route']) : Router::url('/' . strtolower($plugin->slug));
                else
                    $nav[$key]['Navbar']['url'] = '#';
            } else if ($value['Navbar']['url']['type'] == "page") {
                if (isset($pages_listed) && isset($pages_listed[$value['Navbar']['url']['id']]))
                    $nav[$key]['Navbar']['url'] = Router::url('/p/' . $pages_listed[$value['Navbar']['url']['id']]);
                else
                    $nav[$key]['Navbar']['url'] = '#';
            } else if ($value['Navbar']['url']['type'] == "custom") {
                $nav[$key]['Navbar']['url'] = $value['Navbar']['url']['url'];
            }
        }
        $this->set(compact('nav'));
    }

    public function __initServerInfos()
    {
        $configuration = $this->Configuration->getKey('banner_server');
        if (empty($configuration) && $this->Server->online())
            $server_infos = $this->Server->banner_infos();
        else if (!empty($configuration))
            $server_infos = $this->Server->banner_infos(unserialize($configuration));
        else {
            $this->set(['banner_server' => false, 'server_infos' => false]);
            return;
        }
        if (!isset($server_infos['GET_MAX_PLAYERS']) || !isset($server_infos['GET_PLAYER_COUNT']) || $server_infos['GET_MAX_PLAYERS'] === 0) {
            $this->set(['banner_server' => false, 'server_infos' => $server_infos]);
            return;
        }

        $this->set([
            'banner_server' => $this->Lang->get('SERVER__STATUS_MESSAGE', [
                '{MOTD}' => @$server_infos['getMOTD'],
                '{VERSION}' => @$server_infos['getVersion'],
                '{ONLINE}' => @$server_infos['GET_PLAYER_COUNT'],
                '{ONLINE_LIMIT}' => @$server_infos['GET_MAX_PLAYERS']
            ]),
            'server_infos' => $server_infos
        ]);

    }

    public function beforeRender()
    {
        $this->__initSeoConfiguration();
        $event = new CakeEvent('onLoadPage', $this, $this->request->data);
        $this->getEventManager()->dispatch($event);
        if ($event->isStopped()) {
            $this->__setTheme();
            return $event->result;
        }

        if ($this->params['prefix'] === "admin") {
            $event = new CakeEvent('onLoadAdminPanel', $this, $this->request->data);
            $this->getEventManager()->dispatch($event);
            if ($event->isStopped()) {
                $this->__setTheme();
                return $event->result;
            }
        }
        $this->__setTheme();
    }

    public function __initSeoConfiguration()
    {
        $this->loadModel('Seo');
        $default = $this->Seo->find('first', ["conditions" => ['page' => null]]);
        $current_url = $this->here;
        $get_page = [];
        $start_url = "/" . explode("/", $current_url)[1];
        $check = $this->Seo->find('first', ["conditions" => ['page LIKE' => $start_url . "%"]]);
        if ($check && ($check['Seo']["page"] == $current_url || $current_url != "/") && strlen($current_url) >= strlen($check['Seo']["page"]))
            $get_page = $check;
        $seo_config['title'] = (!empty($default['Seo']['title']) ? $default['Seo']['title'] : "{TITLE} - {WEBSITE_NAME}");
        $seo_config['title'] = (!empty($get_page['Seo']['title']) ? $get_page['Seo']['title'] : $seo_config['title']);
        $seo_config['description'] = (!empty($get_page['Seo']['description']) ? $get_page['Seo']['description'] : (!empty($default['Seo']['description']) ? $default['Seo']['description'] : ""));
        $seo_config['img_url'] = (!empty($get_page['Seo']['img_url']) ? $get_page['Seo']['img_url'] : (!empty($default['Seo']['img_url']) ? $default['Seo']['img_url'] : ""));
        $seo_config['favicon_url'] = (!empty($get_page['Seo']['favicon_url']) ? $get_page['Seo']['favicon_url'] : (!empty($default['Seo']['favicon_url']) ? $default['Seo']['favicon_url'] : ""));
        $seo_config['favicon_url'] = Router::url($seo_config['favicon_url'], true);
        $seo_config['img_url'] = (empty($seo_config['img_url']) ? $seo_config['favicon_url'] : Router::url($seo_config['img_url'], true));
        $seo_config['title'] = str_replace(["{TITLE}", "{WEBSITE_NAME}"], [(!empty($this->viewVars['title_for_layout']) ? $this->viewVars['title_for_layout'] : $this->Lang->get("GLOBAL__ERROR")), (!empty($this->viewVars['website_name']) ? $this->viewVars['website_name'] : "MineWeb")], $seo_config['title']);
        $seo_config['theme_color'] = (!empty($get_page['Seo']['theme_color']) ? $get_page['Seo']['theme_color'] : (!empty($default['Seo']['theme_color']) ? $default['Seo']['theme_color'] : ""));
        $seo_config['twitter_site'] = (!empty($get_page['Seo']['twitter_site']) ? $get_page['Seo']['twitter_site'] : (!empty($default['Seo']['twitter_site']) ? $default['Seo']['twitter_site'] : ""));
        $this->set(compact('seo_config'));
    }

    public function afterFilter()
    {
        $event = new CakeEvent('beforePageDisplay', $this, $this->request->data);
        $this->getEventManager()->dispatch($event);
        if ($event->isStopped()) {
            $this->__setTheme();
            return $event->result;
        }

    }

    public function blackhole($type)
    {
        if ($type == "csrf") {
            $this->autoRender = false;
            if ($this->request->is('ajax')) {
                $this->response->type('json');
                $this->response->body(json_encode(['statut' => false, 'msg' => $this->Lang->get('ERROR__CSRF')]));
                $this->response->send();
                exit;
            } else {
                $this->Session->setFlash($this->Lang->get('ERROR__CSRF'), 'default.error');
                $this->redirect($this->referer());
            }
        }
    }

    public function sendGetRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: MineWebCMS'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function sendMultipleGetRequests($urls)
    {
        if (!is_array($urls))
            $urls = [$urls];
        $multi = curl_multi_init();
        $channels = [];
        $result = [];

        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: MineWebCMS'
            ]);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_multi_add_handle($multi, $ch);

            $channels[$url] = $ch;
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi) == -1) {
                continue;
            }
            do {
                $mrc = curl_multi_exec($multi, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }

        foreach ($channels as $channel) {
            $result[] = curl_multi_getcontent($channel);
            curl_multi_remove_handle($multi, $channel);
        }

        curl_multi_close($multi);
        return $result;
    }

    public function sendJSON($data)
    {
        $this->response->type('json');
        $this->autoRender = false;
        return $this->response->body(json_encode($data));
    }
}
