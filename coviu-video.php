<?php
/*
 * Plugin Name: Coviu Video
 * Plugin URI: http://wordpress.org/extend/plugins/coviu-video/
 * Description: Add Coviu video calling to your Website. 
 * Author: Silvia Pfeiffer
 * Version: 0.1
 * Author URI: http://www.coviu.com/
 * License: GPL2
 */

/*
  Copyright 2015  Silvia Pfeiffer  (email : silviapfeiffer1@gmail.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

  @package    coviu-video
  @author     Silvia Pfeiffer <silviapfeiffer1@gmail.com>
  @copyright  Copyright 2015 Silvia Pfeiffer
  @license    http://www.gnu.org/licenses/gpl.txt GPL 2.0
  @version    0.1
  @link       http://wordpress.org/extend/plugins/coviu-video/

*/


/// ***   Admin Settings Page   *** ///
add_action( 'admin_menu', 'cvu_admin_menu' );

function cvu_admin_menu() {
    add_options_page(__('Coviu Video Settings', 'coviu-video'), __('Coviu Video', 'coviu-video'), 'edit_posts', __FILE__, 'cvu_settings_page');
}

function cvu_settings_page() {
    ?>
        <div class="wrap">
            <h2><?php _e('Coviu Video Settings', 'coviu-video'); ?></h2>
        </div>
    <?php
}