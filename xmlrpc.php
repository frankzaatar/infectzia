<?php
/**
 * XML-RPC protocol support for WordPress
 *
 * @license GPL v2 <./license.txt>
 * @package WordPress
 */

/**
 * Whether this is a XMLRPC Request
 *
 * @var bool
 */
define('XMLRPC_REQUEST', true);

// Some browser-embedded clients send cookies. We don't want them.
$_COOKIE = array();

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

// fix for mozBlog and other cases where '<?xml' isn't on the very first line
if ( isset($HTTP_RAW_POST_DATA) )
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);

/** Include the bootstrap for setting up WordPress environment */
include('./wp-load.php');

if ( isset( $_GET['rsd'] ) ) { // http://archipelago.phrasewise.com/rsd
header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
?>
<?php echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
  <service>
    <engineName>WordPress</engineName>
    <engineLink>http://wordpress.org/</engineLink>
    <homePageLink><?php bloginfo_rss('url') ?></homePageLink>
    <apis>
      <api name="WordPress" blogID="1" preferred="true" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Movable Type" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="MetaWeblog" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Blogger" blogID="1" preferred="false" apiLink="<?php echo site_url('xmlrpc.php') ?>" />
      <api name="Atom" blogID="" preferred="false" apiLink="<?php echo apply_filters('atom_service_url', site_url('wp-app.php/service') ) ?>" />
    </apis>
  </service>
</rsd>
<?php
exit;
}

include_once(ABSPATH . 'wp-admin/includes/admin.php');
include_once(ABSPATH . WPINC . '/class-IXR.php');

// Turn off all warnings and errors.
// error_reporting(0);

/**
 * Posts submitted via the xmlrpc interface get that title
 * @name post_default_title
 * @var string
 */
$post_default_title = "";

/**
 * Whether to enable XMLRPC Logging.
 *
 * @name xmlrpc_logging
 * @var int|bool
 */
$xmlrpc_logging = 0;

/**
 * logIO() - Writes logging info to a file.
 *
 * @uses $xmlrpc_logging
 * @package WordPress
 * @subpackage Logging
 *
 * @param string $io Whether input or output
 * @param string $msg Information describing logging reason.
 * @return bool Always return true
 */
function logIO($io,$msg) {
	global $xmlrpc_logging;
	if ($xmlrpc_logging) {
		$fp = fopen("../xmlrpc.log","a+");
		$date = gmdate("Y-m-d H:i:s ");
		$iot = ($io == "I") ? " Input: " : " Output: ";
		fwrite($fp, "\n\n".$date.$iot.$msg);
		fclose($fp);
	}
	return true;
}

if ( isset($HTTP_RAW_POST_DATA) )
	logIO("I", $HTTP_RAW_POST_DATA);

/**
 * WordPress XMLRPC server implementation.
 *
 * Implements compatability for Blogger API, MetaWeblog API, MovableType, and
 * pingback. Additional WordPress API for managing comments, pages, posts,
 * options, etc.
 *
 * Since WordPress 2.6.0, WordPress XMLRPC server can be disabled in the
 * administration panels.
 *
 * @package WordPress
 * @subpackage Publishing
 * @since 1.5.0
 */
class wp_xmlrpc_server extends IXR_Server {

	/**
	 * Register all of the XMLRPC methods that XMLRPC server understands.
	 *
	 * PHP4 constructor and sets up server and method property. Passes XMLRPC
	 * methods through the 'xmlrpc_methods' filter to allow plugins to extend
	 * or replace XMLRPC methods.
	 *
	 * @since 1.5.0
	 *
	 * @return wp_xmlrpc_server
	 */
	function wp_xmlrpc_server() {
		$this->methods = array(
			// WordPress API
			'wp.getUsersBlogs'		=> 'this:wp_getUsersBlogs',
			'wp.getPage'			=> 'this:wp_getPage',
			'wp.getPages'			=> 'this:wp_getPages',
			'wp.newPage'			=> 'this:wp_newPage',
			'wp.deletePage'			=> 'this:wp_deletePage',
			'wp.editPage'			=> 'this:wp_editPage',
			'wp.getPageList'		=> 'this:wp_getPageList',
			'wp.getAuthors'			=> 'this:wp_getAuthors',
			'wp.getCategories'		=> 'this:mw_getCategories',		// Alias
			'wp.getTags'			=> 'this:wp_getTags',
			'wp.newCategory'		=> 'this:wp_newCategory',
			'wp.deleteCategory'		=> 'this:wp_deleteCategory',
			'wp.suggestCategories'	=> 'this:wp_suggestCategories',
			'wp.uploadFile'			=> 'this:mw_newMediaObject',	// Alias
			'wp.getCommentCount'	=> 'this:wp_getCommentCount',
			'wp.getPostStatusList'	=> 'this:wp_getPostStatusList',
			'wp.getPageStatusList'	=> 'this:wp_getPageStatusList',
			'wp.getPageTemplates'	=> 'this:wp_getPageTemplates',
			'wp.getOptions'			=> 'this:wp_getOptions',
			'wp.setOptions'			=> 'this:wp_setOptions',
			'wp.getComment'			=> 'this:wp_getComment',
			'wp.getComments'		=> 'this:wp_getComments',
			'wp.deleteComment'		=> 'this:wp_deleteComment',
			'wp.editComment'		=> 'this:wp_editComment',
			'wp.newComment'			=> 'this:wp_newComment',
			'wp.getCommentStatusList' => 'this:wp_getCommentStatusList',

			// Blogger API
			'blogger.getUsersBlogs' => 'this:blogger_getUsersBlogs',
			'blogger.getUserInfo' => 'this:blogger_getUserInfo',
			'blogger.getPost' => 'this:blogger_getPost',
			'blogger.getRecentPosts' => 'this:blogger_getRecentPosts',
			'blogger.getTemplate' => 'this:blogger_getTemplate',
			'blogger.setTemplate' => 'this:blogger_setTemplate',
			'blogger.newPost' => 'this:blogger_newPost',
			'blogger.editPost' => 'this:blogger_editPost',
			'blogger.deletePost' => 'this:blogger_deletePost',

			// MetaWeblog API (with MT extensions to structs)
			'metaWeblog.newPost' => 'this:mw_newPost',
			'metaWeblog.editPost' => 'this:mw_editPost',
			'metaWeblog.getPost' => 'this:mw_getPost',
			'metaWeblog.getRecentPosts' => 'this:mw_getRecentPosts',
			'metaWeblog.getCategories' => 'this:mw_getCategories',
			'metaWeblog.newMediaObject' => 'this:mw_newMediaObject',

			// MetaWeblog API aliases for Blogger API
			// see http://www.xmlrpc.com/stories/storyReader$2460
			'metaWeblog.deletePost' => 'this:blogger_deletePost',
			'metaWeblog.getTemplate' => 'this:blogger_getTemplate',
			'metaWeblog.setTemplate' => 'this:blogger_setTemplate',
			'metaWeblog.getUsersBlogs' => 'this:blogger_getUsersBlogs',

			// MovableType API
			'mt.getCategoryList' => 'this:mt_getCategoryList',
			'mt.getRecentPostTitles' => 'this:mt_getRecentPostTitles',
			'mt.getPostCategories' => 'this:mt_getPostCategories',
			'mt.setPostCategories' => 'this:mt_setPostCategories',
			'mt.supportedMethods' => 'this:mt_supportedMethods',
			'mt.supportedTextFilters' => 'this:mt_supportedTextFilters',
			'mt.getTrackbackPings' => 'this:mt_getTrackbackPings',
			'mt.publishPost' => 'this:mt_publishPost',

			// PingBack
			'pingback.ping' => 'this:pingback_ping',
			'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks',

			'demo.sayHello' => 'this:sayHello',
			'demo.addTwoNumbers' => 'this:addTwoNumbers'
		);

		$this->initialise_blog_option_info( );
		$this->methods = apply_filters('xmlrpc_methods', $this->methods);
		$this->IXR_Server($this->methods);
	}

	/**
	 * Test XMLRPC API by saying, "Hello!" to client.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method Parameters.
	 * @return string
	 */
	function sayHello($args) {
		return 'Hello!';
	}

	/**
	 * Test XMLRPC API by adding two numbers for client.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Method Parameters.
	 * @return int
	 */
	function addTwoNumbers($args) {
		$number1 = $args[0];
		$number2 = $args[1];
		return $number1 + $number2;
	}

	/**
	 * Check user's credentials.
	 *
	 * @since 1.5.0
	 *
	 * @param string $user_login User's username.
	 * @param string $user_pass User's password.
	 * @return bool Whether authentication passed.
	 */
	function login_pass_ok($user_login, $user_pass) {
		if ( !get_option( 'enable_xmlrpc' ) ) {
			$this->error = new IXR_Error( 405, sprintf( __( 'XML-RPC services are disabled on this blog.  An admin user can enable them at %s'),  admin_url('options-writing.php') ) );
			return false;
		}

		if (!user_pass_ok($user_login, $user_pass)) {
			$this->error = new IXR_Error(403, __('Bad login/pass combination.'));
			return false;
		}
		return true;
	}

	/**
	 * Sanitize string or array of strings for database.
	 *
	 * @since 1.5.2
	 *
	 * @param string|array $array Sanitize single string or array of strings.
	 * @return string|array Type matches $array and sanitized for the database.
	 */
	function escape(&$array) {
		global $wpdb;

		if(!is_array($array)) {
			return($wpdb->escape($array));
		}
		else {
			foreach ( (array) $array as $k => $v ) {
				if (is_array($v)) {
					$this->escape($array[$k]);
				} else if (is_object($v)) {
					//