<?php


namespace BeansWoo\Admin\Connector;

defined('ABSPATH') or die;

use BeansWoo\Helper;

abstract class AbstractConnector {

	static public $errors = array();
	static public $messages = array();

    static public $app_name;
    static public $app_info;

    static public $has_install_asset = false;

	abstract public static function init();

    protected static function _installAssets($app_name = null) {
        if (!in_array($app_name, ['liana', 'bamboo'])){
            return false;
        }
        $name = $app_name;
        // Install Page
        $page_infos = Helper::getPages()[$name];

        if ( ! get_post( Helper::getConfig( $name . '_page' ) ) ) {
            require_once( WP_PLUGIN_DIR . '/woocommerce/includes/admin/wc-admin-functions.php' );
            $page_id = wc_create_page(
                    $page_infos['slug'],
                    $page_infos['option'],
                    $page_infos['page_name'],
                    $page_infos['shortcode'], 0
            );
            Helper::setConfig( $name . '_page', $page_id );
        }
    }

	abstract protected static function _uninstallAssets();

	public static function render_settings_page() {
        self::$app_info = Helper::getApps()[static::$app_name];

		if ( isset( $_GET['card'] ) && isset( $_GET['token'] ) ) {
			if ( static::_processSetup() ) {
				return wp_redirect( admin_url( static::$app_info['link'] ) );
			}
		}

		if ( isset( $_GET['reset_beans'] ) ) {
			if ( Helper::resetSetup(static::$app_name) ) {
			    if (static::$has_install_asset || static::$app_name == 'ultimate'){
                    static::_uninstallAssets();
                }
			    # todo remove after the next release
			    if ($_GET['force']){
                    delete_option(Helper::CONFIG_NAME);
                    delete_option(Helper::BEANS_ULTIMATE_DISMISSED);
                }
				return wp_redirect( admin_url( static::$app_info['link'] ) );
			}
		}

		self::_render_notices();

		if (Helper::isSetup()){
		    if (static::$app_name == 'liana'){
		        $page = Helper::getConfig('page');
     		        if ( ! is_null($page)){
     		            Helper::setConfig('page', null);
     		            return wp_redirect(admin_url( static::$app_info['link']. "&reset_beans=1" ));
		        }
            }
        }

		if ( Helper::isSetup() && Helper::isSetupApp(static::$app_name) ) {
		    if ( static::$app_name == 'ultimate' ){
		        static::updateInstalledApp();
            }
			return include( dirname( __FILE__ , 2) . '/views/html-info.php' );
		}

		return include( dirname( __FILE__,2) . '/views/html-connect.php' );
	}


	protected static function _processSetup() {
        if (static::$has_install_asset){

	        static::_installAssets();
        }

		Helper::log( print_r( $_GET, true ) );

		$card  = $_GET['card'];
		$token = $_GET['token'];

		Helper::$key = $card;

		try {
			$integration_key = Helper::API()->get( '/core/auth/integration_key/' . $token );
		} catch ( \Beans\Error\BaseError  $e ) {
			self::$errors[] = 'Connecting to Beans failed with message: ' . $e->getMessage();
			Helper::log( 'Connecting failed: ' . $e->getMessage() );

			return null;
		}

		Helper::setConfig( 'key', $integration_key['id'] );
		Helper::setConfig( 'card', $integration_key['card']['id'] );
		Helper::setConfig( 'secret', $integration_key['secret'] );
		Helper::setAppInstalled(static::$app_name);
		return true;
	}

	public static function _render_notices() {
		if ( static::$errors || static::$messages ) {
			?>
			<div class="<?php echo empty(static::$errors)? "updated" : "error"; ?> ">
				<?php if ( static::$errors ) : ?>
					<ul>
						<?php foreach ( static::$errors as $error ) {
							echo "<li>$error</li>";
						} ?>
					</ul>
				<?php else : ?>
					<ul>
						<?php foreach ( static::$messages as $message ) {
							echo "<li>$message</li>";
						} ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php
		}
	}

    public static function admin_notice() {
        static::$app_info = Helper::getApps()[static::$app_name];

        $user_id = get_current_user_id();
        if ( get_user_meta( $user_id,   'beans_'. static::$app_name . '_notice_dismissed' ) ){
            return;
        }

        if (! Helper::isSetup() || ! Helper::isSetupApp(static::$app_name)) {
            echo '<div class="notice notice-error " style="margin-left: auto"><div style="margin: 10px auto;"> Beans: ' .
                __(  static::$app_info['name'] ." is not properly setup.", 'beans-woo' ) .
                ' <a href="' . admin_url(static::$app_info['link'] ) . '">' .
                __( 'Set up', 'beans-woo' ) .
                '</a><a style="float:right; text-decoration: none;" href="?beans_'. static::$app_name .'_notice_dismissed">x</a>' .
                '</div></div>';
        }
    }

    public static function notice_dismissed() {
        $user_id = get_current_user_id();
        if ( isset( $_GET['beans_'. static::$app_name .'_notice_dismissed'] ) ){
            add_user_meta( $user_id, 'beans_'. static::$app_name .'_notice_dismissed', 'true', true );
            $location = $_SERVER['HTTP_REFERER'];
            wp_safe_redirect($location);
        }
    }
}
