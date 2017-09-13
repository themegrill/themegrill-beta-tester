<?php

class BetaTesterSettingsPage
{
    public function __construct() {
            add_action( 'admin_menu', array($this, 'beta_tester_plugin_menu' ));
          
    }

    public function beta_tester_plugin_menu() {

         add_submenu_page( 'index.php','Beta Tester Settings', 'Beta Tester Settings', 'manage_options', 'beta-tester-settings-page', array($this, 'betatester_callback' ) );
    }

    public function betatester_callback() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        $WPEVerest=get_option('tgbt-organization-field');
        $ThemeGrill=get_option('tgbt-organization-field');

        if(isset($_POST['submit']))
        {
            $org=isset($_POST['organization'])?$_POST['organization']:null;
            $plugin=isset($_POST['plugin'])?$_POST['plugin']:null;

            update_option('tgbt_organization_field',$org);
            update_option('tgbt_plugin_field',$plugin);

            echo "<div class='notice notice-success is-dismissible'><p>Settings Updated!!</p></div>";
        }
            echo '<h1> Settings Page</h1>';
            ?>
                <form method="post" action=" <?php echo  $_SERVER['REQUEST_URI'] ;?>">
                <table class="form-table">
                <tr>
                    <th scope="row"><label>Github Repository Owner:</label></th>  
                    <td>
                    <select class="category-select" name="organization">
                            <option  <?php if (get_option('tgbt_organization_field')=="WPEVerest") echo "selected='selected'";?> >WPEVerest</option>
                            <option  <?php if (get_option('tgbt_organization_field')=="ThemeGrill") echo "selected='selected'";?> >ThemeGrill</option>
                    </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Theme/Plugin For Beta Test:</label></th>
                    <td>
                    <select class="category-select" name="plugin">
                        <option <?php if (get_option('tgbt_plugin_field')=="user-registration") echo "selected='selected'";?> >user-registration</option>
                        <option <?php if (get_option('tgbt_plugin_field')=="restaurantpress") echo "selected='selected'";?> >restaurantpress</option>
                        <option <?php if (get_option('tgbt_plugin_field')=="flash") echo "selected='selected'";?> >flash</option>
                        <option <?php if (get_option('tgbt_plugin_field')=="spacious") echo "selected='selected'";?> >spacious</option>
                    </select>
                    </td>
                </tr>

                <tr>
                   <td> <input class="button button-primary" type="submit" name="submit"></td>
                </tr>

                </table>
                </form>         
            <?php

            echo '</div>';
    }
}

$settings = new BetaTesterSettingsPage();

?>