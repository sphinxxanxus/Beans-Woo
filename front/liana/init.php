<?php

namespace BeansWoo\Front\Liana;

use BeansWoo\Helper;

include_once('observer.php');
include_once('observer-product.php');
include_once('block.php');

define( 'BEANS_LIANA_COUPON_UID', 'redeem_points' );


class Main {

    public static function init() {

        $display = Helper::getBeansObject('liana', 'display');
        if ( empty( $display ) || ! $display['is_active'] || !Helper::isSetupApp('liana')) {
            return;
        }

        Observer::init($display);
        ProductObserver::init($display);
        Block::init($display);
    }
}
