<?php
/**
 * DokuWiki Action Plugin SidebarNG
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Michael Klier <chi@chimeric.de>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');
if(!defined('DOKU_LF')) define('DOKU_LF', "\n");

require_once(DOKU_PLUGIN.'action.php');

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class action_plugin_sidebarng extends DokuWiki_Action_Plugin {

    // register hook
    function register(&$controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, '_before');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'AFTER', $this, '_after');
    }

    function _before(&$event, $param) {
        $pos = $this->getConf('pos');

        ob_start();
        $this->p_sidebar($pos);
        $this->sidebar = ob_get_contents();
        ob_end_clean();

        if(empty($this->sidebar) && !$this->getConf('main_always')) {
            print '<div class="page">' . DOKU_LF;
        } else {
#            if($pos == 'left') {
#                    print '<div class="' . $pos . '_sidebar">' . DOKU_LF;
#                    print $this->sidebar;
#                    print '</div>' . DOKU_LF;
#                    print '<div class="page_right">' . DOKU_LF;
#            } else {
                print '<div class="page_left">' . DOKU_LF;
#            }
        }
    }

    function _after(&$event, $param) {
        $pos = $this->getConf('pos');
        if(empty($this->sidebar) && !$this->getConf('main_always')) {
            print '</div>' . DOKU_LF;
        } else {
            if($pos == 'left') {
            print '</div>' . DOKU_LF; 
            } else {
                print '</div>' . DOKU_LF;
                print '<div class="sidebar ' . $pos . '_sidebar">' . DOKU_LF;
                print $this->sidebar;
                print '</div>'. DOKU_LF;
            }
        }
    }

    /**
     * Displays the sidebar
     *
     * Michael Klier <chi@chimeric.de>
     */
    function p_sidebar($pos) {
        $sb_order   = explode(',', $this->getConf('order'));
        $sb_content = explode(',', $this->getConf('content'));
        $notoc      = (in_array('toc', $sb_content)) ? true : false;

        // process contents by given order
        foreach($sb_order as $sb) {
            if(in_array($sb,$sb_content)) {
                $key = array_search($sb,$sb_content);
                unset($sb_content[$key]);
                $this->_sidebar_dispatch($sb,$pos);
            }
        }

        // check for left content not specified by order
        if(is_array($sb_content) && !empty($sb_content) > 0) {
            foreach($sb_content as $sb) {
                $this->_sidebar_dispatch($sb,$pos);
            }
        }
    }

    /**
     * Prints given sidebar box
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _sidebar_dispatch($sb, $pos) {
        global $lang;
        global $conf;
        global $ID;
        global $REV;
        global $INFO;

        $svID  = $ID;   // save current ID
        $svREV = $REV;  // save current REV 

        $pname = $this->getConf('pagename');

        switch($sb) {

            case 'main':
                $main_sb = $pname;
                if(@page_exists($main_sb)) {
                    if(auth_quickaclcheck($main_sb) >= AUTH_READ) {
                        $always = $this->getConf('main_always');
                        if($always or (!$always && !getNS($ID))) {
                            print '<div class="main_sidebar sidebar_box">' . DOKU_LF;
                            print $this->p_sidebar_xhtml($main_sb,$pos) . DOKU_LF;
                            print '</div>' . DOKU_LF;
                        }
                    }
                } else {
                    $out = $this->locale_xhtml('nosidebar');
                    $link = '<a href="' . wl($pname) . '" class="wikilink2">' . $pname . '</a>' . DOKU_LF;
                    print '<div class="main_sidebar sidebar_box">' . DOKU_LF;
                    print str_replace('LINK', $link, $out);
                    print '</div>' . DOKU_LF;
                }
                break;

            case 'namespace':
                $user_ns  = $this->getConf('user_ns');
                $group_ns = $this->getConf('group_ns');
                if(!preg_match("/^".$user_ns.":.*?$|^".$group_ns.":.*?$/", $svID)) { // skip group/user sidebars and current ID
                    $ns_sb = $this->_getNsSb($svID);
                    if($ns_sb && auth_quickaclcheck($ns_sb) >= AUTH_READ) {
                        print '<div class="namespace_sidebar sidebar_box">' . DOKU_LF;
                        print $this->p_sidebar_xhtml($ns_sb,$pos) . DOKU_LF;
                        print '</div>' . DOKU_LF;
                    }
                }
                break;

            case 'user':
                $user_ns = $this->getConf('user_ns');
                if(isset($INFO['userinfo']['name'])) {
                    $user = $_SERVER['REMOTE_USER'];
                    $user_sb = $user_ns . ':' . $user . ':' . $pname;
                    if(@page_exists($user_sb)) {
                        $subst = array('pattern' => array('/@USER@/'), 'replace' => array($user));
                        print '<div class="user_sidebar sidebar_box">' . DOKU_LF;
                        print $this->p_sidebar_xhtml($user_sb,$pos,$subst) . DOKU_LF;
                        print '</div>';
                    }
                    // check for namespace sidebars in user namespace too
                    if(preg_match('/'.$user_ns.':'.$user.':.*/', $svID)) {
                        $ns_sb = $this->_getNsSb($svID); 
                        if($ns_sb && $ns_sb != $user_sb && auth_quickaclcheck($ns_sb) >= AUTH_READ) {
                            print '<div class="namespace_sidebar sidebar_box">' . DOKU_LF;
                            print $this->p_sidebar_xhtml($ns_sb,$pos) . DOKU_LF;
                            print '</div>' . DOKU_LF;
                        }
                    }

                }
                break;

            case 'group':
                $group_ns = $this->getConf('group_ns');
                if(isset($INFO['userinfo']['grps'])) {
                    foreach($INFO['userinfo']['grps'] as $grp) {
                        $group_sb = $group_ns.':'.$grp.':'.$pname;
                        if(@page_exists($group_sb) && auth_quickaclcheck(cleanID($group_sb)) >= AUTH_READ) {
                            $subst = array('pattern' => array('/@GROUP@/'), 'replace' => array($grp));
                            print '<div class="group_sidebar sidebar_box">' . DOKU_LF;
                            print $this->p_sidebar_xhtml($group_sb,$pos,$subst) . DOKU_LF;
                            print '</div>' . DOKU_LF;
                        }
                    }
                } else {
                    $group_sb = $group_ns.':all:'.$pname;
                    if(@page_exists($group_sb) && auth_quickaclcheck(cleanID($group_sb)) >= AUTH_READ) {
                        print '<div class="group_sidebar sidebar_box">' . DOKU_LF;
                        print $this->p_sidebar_xhtml($group_sb,$pos,$subst) . DOKU_LF;
                        print '</div>' . DOKU_LF;
                    }
                }
                break;

        case 'toc':
            if($this->getConf('closedwiki') && !isset($_SERVER['REMOTE_USER'])) return;
            if(auth_quickaclcheck($svID) >= AUTH_READ) {
                $toc = tpl_toc(true);
                // replace ids to keep XHTML compliance
                if(!empty($toc)) {
                    $toc = preg_replace('/id="(.*?)"/', 'id="sb__' . $pos . '__\1"', $toc);
                    print '<div class="toc_sidebar">' . DOKU_LF;
                    print ($toc);
                    print '</div>' . DOKU_LF;
                }
            }
            $INFO['prependTOC'] = false;
            break;

        case 'index':
            if($this->getConf('closedwiki') && !isset($_SERVER['REMOTE_USER'])) return;
            print '<div class="index_sidebar sidebar_box">' . DOKU_LF;
            print '  ' . p_index_xhtml($svID,$pos) . DOKU_LF;
            print '</div>' . DOKU_LF;
            break;

        
        case 'toolbox':

            if($this->getConf('hideactions') && !isset($_SERVER['REMOTE_USER'])) return;

            if($this->getConf('closedwiki') && !isset($_SERVER['REMOTE_USER'])) {
                print '<div class="toolbox_sidebar sidebar_box">' . DOKU_LF;
                print '  <div class="level1">' . DOKU_LF;
                print '    <ul>' . DOKU_LF;
                print '      <li><div class="li">';
                tpl_actionlink('login');
                print '      </div></li>' . DOKU_LF;
                print '    </ul>' . DOKU_LF;
                print '  </div>' . DOKU_LF;
                print '</div>' . DOKU_LF;
            } else {
                $actions = array('admin', 
                                 'revert', 
                                 'edit', 
                                 'history', 
                                 'recent', 
                                 'backlink', 
                                 'media', 
                                 'subscription', 
                                 'index', 
                                 'login', 
                                 'profile',
                                 'top');

                print '<div class="toolbox_sidebar sidebar_box">' . DOKU_LF;
                print '  <div class="level1">' . DOKU_LF;
                print '    <ul>' . DOKU_LF;

                foreach($actions as $action) {
                    if(!actionOK($action)) continue;
                    // start output buffering
                    if($action == 'edit') {
                        // check if new page button plugin is available
                        if(!plugin_isdisabled('npd') && ($npd =& plugin_load('helper', 'npd'))) {
                            $npb = $npd->html_new_page_button(true);
                            if($npb) {
                                print '    <li><div class="li">';
                                print $npb;
                                print '</div></li>' . DOKU_LF;
                            }
                        }
                    }
                    ob_start();
                    print '   <li><div class="li">';
                    if(tpl_actionlink($action)) {
                        print '</div></li>' . DOKU_LF;
                        ob_end_flush();
                    } else {
                        ob_end_clean();
                    }
                }

                print '  </ul>' . DOKU_LF;
                print '  </div>' . DOKU_LF;
                print '</div>' . DOKU_LF;
            }

            break;

        case 'trace':
            if($this->getConf('closedwiki') && !isset($_SERVER['REMOTE_USER'])) return;
            print '<div class="trace_sidebar sidebar_box">' . DOKU_LF;
            print '  <div class="sb_label">'.$lang['breadcrumb'].'</div>' . DOKU_LF;
            print '  <div class="breadcrumbs">' . DOKU_LF;
            ($conf['youarehere'] != 1) ? tpl_breadcrumbs() : tpl_youarehere();
            print '  </div>' . DOKU_LF;
            print '</div>' . DOKU_LF;
            break;

        case 'extra':
            if($this->getConf('closedwiki') && !isset($_SERVER['REMOTE_USER'])) return;
            print '<div class="extra_sidebar sidebar_box">' . DOKU_LF;
            @include(dirname(__FILE__).'/sidebar.html');
            print '</div>' . DOKU_LF;
            break;

        default:
            if($this->getConf('closedwiki') && !isset($_SERVER['REMOTE_USER'])) return;

            if(@file_exists(DOKU_PLUGIN.'sidebarng/sidebars/'.$sb.'/sidebar.php')) {
                print '<div class="'.$sb.'_sidebar sidebar_box">' . DOKU_LF;
                @require_once(DOKU_PLUGIN.'sidebarng/sidebars/'.$sb.'/sidebar.php');
                print '</div>' . DOKU_LF;
            }
            break;
        }

        // restore ID and REV
        $ID  = $svID;
        $REV = $svREV;
        $TOC = $svTOC;
    }

    /**
     * Removes the TOC of the sidebar pages and 
     * shows a edit button if the user has enough rights
 *
 * TODO sidebar caching
 * 
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function p_sidebar_xhtml($sb,$pos,$subst=array()) {
        $data = p_wiki_xhtml($sb,'',false);
        if(!empty($subst)) {
            $data = preg_replace($subst['pattern'], $subst['replace'], $data);
        }
        if(auth_quickaclcheck($sb) >= AUTH_EDIT) {
            $data .= '<div class="secedit">'.html_btn('secedit',$sb,'',array('do'=>'edit','rev'=>'','post')).'</div>';
        }
        // strip TOC
        $data = preg_replace('/<div class="toc">.*?(<\/div>\n<\/div>)/s', '', $data);
        // replace headline ids for XHTML compliance
        $data = preg_replace('/(<h.*?><a.*?name=")(.*?)(".*?id=")(.*?)(">.*?<\/a><\/h.*?>)/','\1sb_'.$pos.'_\2\3sb_'.$pos.'_\4\5', $data);
        return ($data);
    }
/**
 * Renders the Index
 *
 * copy of html_index located in /inc/html.php
 *
 * TODO update to new AJAX index possible?
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 */
function p_index_xhtml($ns,$pos) {
  require_once(DOKU_INC.'inc/search.php');
  global $conf;
  global $ID;
  $dir = $conf['datadir'];
  $ns  = cleanID($ns);
  #fixme use appropriate function
  if(empty($ns)){
    $ns = dirname(str_replace(':','/',$ID));
    if($ns == '.') $ns ='';
  }
  $ns  = utf8_encodeFN(str_replace(':','/',$ns));

  // extract only the headline
  preg_match('/<h1>.*?<\/h1>/', p_locale_xhtml('index'), $match);
  print preg_replace('#<h1(.*?id=")(.*?)(".*?)h1>#', '<h1\1sidebar_'.$pos.'_\2\3h1>', $match[0]);

  $data = array();
  search($data,$conf['datadir'],'search_index',array('ns' => $ns));

  print '<div id="' . $pos . '__index__tree">' . DOKU_LF;
  print html_buildlist($data,'idx','html_list_index','html_li_index');
  print '</div>' . DOKU_LF;
}

/**
 * Searches for namespace sidebars
 *
 * @author Michael Klier <chi@chimeric.de>
 */
function _getNsSb($id) {
     $pname = $this->getConf('pagename');
     $ns_sb = '';
     $path  = explode(':', $id);
     $found = false;

     while(count($path) > 0) {
         $ns_sb = implode(':', $path).':'.$pname;
         if(@page_exists($ns_sb)) return $ns_sb;
         array_pop($path);
     }
     
     // nothing found
     return false;
 }
  /**
   * Checks wether the sidebar should be hidden or not
   *
   * @author Michael Klier <chi@chimeric.de>
   */
  function tpl_sidebar_hide() {
    global $ACT;
    $act_hide = array( 'edit', 'preview', 'admin', 'conflict', 'draft', 'recover', 'media' );
    if(in_array($ACT, $act_hide)) {
        return true;
    } else {
        return false;
    }
  }
}
