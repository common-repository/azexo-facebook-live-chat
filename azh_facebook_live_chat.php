<?php
/*
  Plugin Name: Facebook Live Chat Marketing Automation
  Description: Facebook Live Chat Marketing Automation
  Author: azexo
  Author URI: http://azexo.com
  Version: 1.27.4
  Text Domain: azm
 */

add_action('plugins_loaded', 'azm_fb_plugins_loaded');

function azm_fb_plugins_loaded() {
    load_plugin_textdomain('azm', FALSE, basename(dirname(__FILE__)) . '/languages/');
}

add_action('admin_notices', 'azm_fb_admin_notices');

function azm_fb_admin_notices() {
    if (!defined('AZM_VERSION')) {
        $plugin_data = get_plugin_data(__FILE__);
        print '<div class="updated notice error is-dismissible"><p>' . $plugin_data['Name'] . ': ' . __('please install <a href="https://codecanyon.net/item/marketing-automation-by-azexo/21402648">Marketing Automation by AZEXO</a> plugin.', 'azm') . '</p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'azm') . '</span></button></div>';
    }
}

add_action('wp_footer', 'azm_fb_footer');

function azm_fb_footer() {
    $settings = get_option('azh-fb-settings', array());
    if (isset($settings['mode']) && $settings['mode'] != 'disabled') {
        $page_id = $settings['page-id'];
        if ($settings['mode'] == 'webhook') {
            $page_id = azm_fb_get_subscribed_page_id();
        }
        if ($settings['app-id'] && $page_id) {
            $settings = apply_filters('azm_fb_chat_greeting', $settings);
            ?>
            <script>
                window.fbAsyncInit = function () {
                    FB.init({
                        appId: '<?php print $settings['app-id']; ?>',
                        autoLogAppEvents: true,
                        xfbml: true,
                        version: 'v2.12'
                    });
                };
                (function (d, s, id) {
                    var js, fjs = d.getElementsByTagName(s)[0];
                    if (d.getElementById(id)) {
                        return;
                    }
                    js = d.createElement(s);
                    js.id = id;
                    js.src = "https://connect.facebook.net/<?php print get_locale(); ?>/sdk.js";
                    fjs.parentNode.insertBefore(js, fjs);
                }(document, 'script', 'facebook-jssdk'));
            </script>
            <div class="fb-customerchat"
                 page_id="<?php print $page_id; ?>"
                 ref="<?php print azr_get_current_visitor(); ?>"
                 minimized="<?php print (empty($settings['minimized']) ? 'false' : 'true'); ?>"
                 logged_in_greeting="<?php print $settings['logged-in-greeting']; ?>"
                 logged_out_greeting="<?php print $settings['logged-out-greeting']; ?>"
                 theme_color="<?php print $settings['theme-color']; ?>">
            </div>    
            <?php
        }
    }
}

function azm_fb_get_page_access_token($page_id) {
    $pages = get_option('azm-fb-pages');
    if ($pages && is_array($pages)) {
        foreach ($pages as $page) {
            if ($page['id'] == $page_id) {
                return $page['access_token'];
            }
        }
    }
}

function azm_fb_get_subscribed_page_id() {
    $settings = get_option('azh-fb-settings', array());
    if (isset($settings['subscriptions']) && is_array($settings['subscriptions'])) {
        foreach ($settings['subscriptions'] as $id => $status) {
            return $id;
        }
    }
}

function azm_fb_event($event) {
    global $wpdb;
    if (isset($event['message']['text']) && isset($event['sender']['id']) && isset($event['recipient']['id']) && isset($event['timestamp'])) {
        $wpdb->query("INSERT INTO {$wpdb->prefix}azr_fb_history (sender, recipient, timestamp, message) VALUES (" . $event['sender']['id'] . ", " . $event['recipient']['id'] . ", " . $event['timestamp'] . ", '" . $event['message']['text'] . "')");
    }
    if (isset($event['referral']['ref'])) {
        if (isset($event['sender']['id'])) {
            //$event['referral']['referer_uri']
            $user_info = azm_fb_get_user_info(azm_fb_get_page_access_token($event['recipient']['id']), $event['sender']['id']);
            $wpdb->query("REPLACE INTO {$wpdb->prefix}azr_fb_visitors (visitor_id, psid, hash, first_name, last_name, profile_pic, locale, timezone, gender) VALUES ('" .
                    $event['referral']['ref'] . "', " .
                    $event['sender']['id'] . ", '" .
                    uniqid() . "', '" .
                    (isset($user_info['first_name']) ? $user_info['first_name'] : '') . "', '" .
                    (isset($user_info['last_name']) ? $user_info['last_name'] : '') . "', '" .
                    (isset($user_info['profile_pic']) ? $user_info['profile_pic'] : '') . "', '" .
                    (isset($user_info['locale']) ? $user_info['locale'] : '') . "', " .
                    (isset($user_info['timezone']) ? $user_info['timezone'] : '0') . ", '" .
                    (isset($user_info['gender']) ? $user_info['gender'] : '') . "')");
        }
    }
    if (isset($event['read'])) {
        //$event['read']['watermark']        
    }
}

function azm_fb_get_user_info($page_access_token, $psid) {
    $url = 'https://graph.facebook.com/v2.6/' . $psid . '?access_token=' . $page_access_token;
    $result = file_get_contents($url);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}

add_action('wp_ajax_azm_fb_webhook', 'azm_fb_webhook');
add_action('wp_ajax_nopriv_azm_fb_webhook', 'azm_fb_webhook');

function azm_fb_webhook() {
    $settings = get_option('azh-fb-settings', array());
    if (isset($settings['app-id'])) {
        $input = file_get_contents('php://input');

        $mode = $_REQUEST['hub_mode'];
        $token = $_REQUEST['hub_verify_token'];
        $challenge = $_REQUEST['hub_challenge'];

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === get_option('azm-fb-verify-token')) {
                print $challenge;
            }
        }
        if ($input) {
            $body = json_decode($input, true);
            if ($body) {
                azm_log(print_r($body, true));
                if (isset($body['object']) && $body['object'] === 'page') {
                    foreach ($body['entry'] as $entry) {
                        if (isset($entry['messaging'])) {
                            foreach ($entry['messaging'] as $event) {
                                azm_fb_event($event);
                            }
                            if (isset($event['recipient']['id'])) {
                                $page_access_token = azm_fb_get_page_access_token($event['recipient']['id']);
                                if ($page_access_token) {
                                    $secondary_receivers = azm_fb_secondary_receivers($page_access_token);
                                    if (is_array($secondary_receivers)) {
                                        foreach ($secondary_receivers as $secondary_receiver) {
                                            if (isset($secondary_receiver['id']) && $secondary_receiver['id'] != $settings['app-id']) {
                                                azm_fb_pass_thread_control($page_access_token, $event['sender']['id'], $secondary_receiver['id']);
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if (isset($entry['standby'])) {
                            foreach ($entry['standby'] as $event) {
                                azm_fb_event($event);
                            }
                        }
                    }
                }
            }
        }
    }

    wp_die();
}

function azm_fb_secondary_receivers($page_access_token) {
    $url = 'https://graph.facebook.com/v2.6/me/secondary_receivers?fields=id,name&access_token=' . $page_access_token;
    $result = file_get_contents($url);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    if (isset($result['data'])) {
        return $result['data'];
    }
}

function azm_fb_pass_thread_control($page_access_token, $psid, $app_id) {
    $url = 'https://graph.facebook.com/v2.6/me/pass_thread_control?access_token=' . $page_access_token;

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "recipient" => array("id" => $psid),
        "target_app_id" => $app_id,
    );


    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}

function azm_fb_message_send($page_access_token, $psid, $message, $tag) {
    $url = 'https://graph.facebook.com/v2.6/me/messages?access_token=' . $page_access_token;

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        'messaging_type' => 'MESSAGE_TAG',
        "recipient" => array("id" => $psid),
        "message" => array("text" => $message),
        "tag" => $tag,
    );


    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return ((isset($result['error'])) ? false : true);
}

function azm_fb_broadcast_message_create($page_access_token, $message) {
    $url = 'https://graph.facebook.com/v2.11/me/message_creatives?access_token=' . $page_access_token;

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "messages" => array($message),
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    if (isset($result['message_creative_id'])) {
        return $result['message_creative_id'];
    }
}

function azm_fb_broadcast_message_send($page_access_token, $message_id, $label_id) {
    $url = 'https://graph.facebook.com/v2.11/me/broadcast_messages?access_token=' . $page_access_token;

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "message_creative_id" => $message_id,
        "custom_label_id" => $label_id,
        "notification_type" => 'REGULAR', // REGULAR | SILENT_PUSH | NO_PUSH
            //"tag" => 'NON_PROMOTIONAL_SUBSCRIPTION',
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}

function azm_fb_broadcast_label_create($page_access_token, $label) {
    $url = 'https://graph.facebook.com/v2.11/me/custom_labels?access_token=' . $page_access_token;

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "name" => $label,
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    if (isset($result['id'])) {
        return $result['id'];
    }
}

function azm_fb_broadcast_label_psid_add($page_access_token, $label_id, $psid) {
    $url = 'https://graph.facebook.com/v2.11/' . $label_id . '/label?access_token=' . $page_access_token;

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "user" => $psid,
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    if (isset($result['id'])) {
        return $result['id'];
    }
}

register_activation_hook(__FILE__, 'azr_fb_activate');

function azr_fb_activate() {
    global $wpdb;
    $collate = '';

    if ($wpdb->has_cap('collation')) {
        $collate = $wpdb->get_charset_collate();
    }

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}azr_fb_visitors (
                visitor_id varchar(32) NOT NULL,
                psid bigint(30) NOT NULL,
                hash varchar(20) NOT NULL,
                first_name varchar(50),
                last_name varchar(50),
                profile_pic varchar(300),
                locale varchar(5),
                timezone int(2),
                gender varchar(10),
                UNIQUE KEY visitor (visitor_id)
    ) $collate;");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}azr_fb_history (
                sender bigint(30) NOT NULL,
                recipient bigint(30) NOT NULL,
                timestamp int(11) unsigned NOT NULL,
                message text,
                UNIQUE KEY sender (sender),
                UNIQUE KEY recipient (recipient)
    ) $collate;");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}azr_fb_campaigns (
                visitor_id varchar(32) NOT NULL,
                campaign_id bigint(20) NOT NULL,
                status varchar(10),
                sent_timestamp int(11) unsigned,
                read_timestamp int(11) unsigned,
                click_timestamp int(11) unsigned,
                UNIQUE KEY campaign (campaign_id, visitor_id),
                KEY status (status)
    ) $collate;");
}

add_action('admin_menu', 'azm_fb_admin_menu', 14);

function azm_fb_admin_menu() {
    add_submenu_page('edit.php?post_type=azr_rule', __('Facebook Chat settings', 'azm'), __('Facebook Chat settings', 'azm'), 'edit_pages', 'azh-fb-settings', 'azm_fb_page');
}

function azm_fb_page() {
    do_action('azm_fb_page');
    ?>

    <div class="wrap">
        <?php screen_icon(); ?>
        <h2><?php _e('Facebook Live Chat settings', 'azm'); ?></h2>

        <form method="post" action="options.php" class="azh-form">
            <?php
            settings_errors();
            settings_fields('azh-fb-settings');
            do_settings_sections('azh-fb-settings');
            submit_button(__('Save Settings', 'azm'));
            ?>
        </form>
    </div>

    <?php
}

add_action('admin_init', 'azm_fb_options');

function azm_fb_options() {
    register_setting('azh-fb-settings', 'azh-fb-settings', array('sanitize_callback' => 'azh_settings_sanitize_callback'));
    $settings = get_option('azh-fb-settings', array());

    add_settings_field(
            'mode', // Field ID
            esc_html__('Facebook live chat mode', 'azm'), // Label to the left
            'azh_radio', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'mode',
        'default' => 'disabled',
        'options' => array(
            'disabled' => __('Disabled', 'azm'),
            'chat' => __('Live chat only', 'azm'),
            'webhook' => __('Live chat and collecting subscribers', 'azm'),
        )
            )
    );
    add_settings_section(
            'azh_fb_section', // Section ID
            esc_html__('Facebook live chat settings', 'azm'), // Title above settings section
            'azm_fb_options_callback', // Name of function that renders a description of the settings section
            'azh-fb-settings'                     // Page to show on
    );
    add_settings_field(
            'app-id', // Field ID
            esc_html__('Application ID', 'azm'), // Label to the left
            'azh_textfield', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'app-id',
            )
    );
    if ($settings['mode'] != 'webhook') {
        add_settings_field(
                'page-id', // Field ID
                esc_html__('Facebook Page ID', 'azm'), // Label to the left
                'azh_textfield', // Name of function that renders options on the page
                'azh-fb-settings', // Page to show on
                'azh_fb_section', // Associate with which settings section?
                array(
            'id' => 'page-id',
                )
        );
    }
    if ($settings['mode'] == 'webhook') {
        add_settings_field(
                'app-secret', // Field ID
                esc_html__('Application Secret', 'azm'), // Label to the left
                'azh_textfield', // Name of function that renders options on the page
                'azh-fb-settings', // Page to show on
                'azh_fb_section', // Associate with which settings section?
                array(
            'id' => 'app-secret',
                )
        );
    }
    add_settings_field(
            'theme-color', // Field ID
            esc_html__('Theme color', 'azm'), // Label to the left
            'azh_textfield', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'theme-color',
        'type' => 'color',
        'default' => '#FF0000',
            )
    );
    add_settings_field(
            'minimized', // Field ID
            esc_html__('Greeting text should be minimized', 'azm'), // Label to the left
            'azh_checkbox', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'minimized',
        'options' => array(
            'yes' => __('Yes', 'azm'),
        )
            )
    );
    add_settings_field(
            'logged-in-greeting', // Field ID
            esc_html__('Default logged in greeting', 'azm'), // Label to the left
            'azh_textfield', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'logged-in-greeting',
        'default' => esc_html__('Logged in greeting', 'azm'),
            )
    );
    add_settings_field(
            'logged-out-greeting', // Field ID
            esc_html__('Default logged out greeting', 'azm'), // Label to the left
            'azh_textfield', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'logged-out-greeting',
        'default' => esc_html__('Logged out greeting', 'azm'),
            )
    );
    add_settings_field(
            'google-api-key', // Field ID
            esc_html__('Google API Key', 'azm'), // Label to the left
            'azh_textfield', // Name of function that renders options on the page
            'azh-fb-settings', // Page to show on
            'azh_fb_section', // Associate with which settings section?
            array(
        'id' => 'google-api-key',
        'desc' => esc_html__('For URL shortener', 'azm'),
            )
    );
}

add_filter('azr_settings', 'azm_fb_settings');

function azm_fb_settings($azr) {
    global $wpdb;
    $gmt_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $azr['conditions']['fb_messenger_sender'] = array(
        'name' => __('Facebook messenger sender', 'azm'),
        'group' => __('Facebook', 'azm'),
        'query_where' => true,
        'required_context' => array('visitors'),
        'where_clause' => "v.visitor_id IN (SELECT fbv.visitor_id FROM {$wpdb->prefix}azr_fb_visitors as fbv WHERE {{fbv.visitor_id IN ({visitor_id}) AND}} 1=1)",
        'helpers' => '<div class="azr-tokens"><label>' . __('Available tokens for template:', 'azm') . '</label><input type="text" value="{first_name}"/><input type="text" value="{last_name}"/></div>',
    );
    $campaigns = get_posts(array(
        'post_type' => 'azr_rule',
        'post_status' => 'publish',
        'ignore_sticky_posts' => 1,
        'no_found_rows' => 1,
        'posts_per_page' => -1,
        'numberposts' => -1,
    ));
    $campaign_options = array();
    if (!empty($campaigns)) {
        foreach ($campaigns as $campaign) {
            $campaign_options[$campaign->ID] = $campaign->post_title;
        }
    }
    $azr['conditions']['fb_campaign_status'] = array(
        'name' => __('Facebook campaign status', 'azm'),
        'group' => __('Facebook', 'azm'),
        'query_where' => true,
        'required_context' => array('visitors'),
        'parameters' => array(
            'campaign' => array(
                'type' => 'dropdown',
                'label' => __('Campaign name', 'azm'),
                'required' => true,
                'no_options' => sprintf(__('Please <a href="%s">create a campaign</a>', 'azm'), admin_url('post-new.php?post_type=azr_rule')),
                'options' => $campaign_options,
            ),
            'status' => array(
                'type' => 'dropdown',
                'label' => __('Status', 'azm'),
                'required' => true,
                'options' => array(
                    'clicked' => __('Clicked', 'azm'),
                    'did_not_clicked' => __('Did not clicked', 'azm'),
                    'was_sent' => __('Was sent', 'azm'),
                    'was_not_sent' => __('Was not sent', 'azm'),
                ),
                'where_clauses' => array(
                    'clicked' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND click_timestamp IS NOT NULL)",
                    'did_not_clicked' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND click_timestamp IS NULL)",
                    'was_sent' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND sent_timestamp IS NOT NULL)",
                    'was_not_sent' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND sent_timestamp IS NULL)",
                ),
                'default' => 'opened',
            ),
        ),
    );
    $azr['conditions']['fb_campaign_sent_date'] = array(
        'name' => __('Facebook campaign sent date', 'azm'),
        'group' => __('Facebook', 'azm'),
        'query_where' => true,
        'required_context' => array('visitors'),
        'parameters' => array(
            'campaign' => array(
                'type' => 'dropdown',
                'label' => __('Campaign name', 'azm'),
                'required' => true,
                'no_options' => sprintf(__('Please <a href="%s">create a campaign</a>', 'azm'), admin_url('post-new.php?post_type=azr_rule')),
                'options' => $campaign_options,
            ),
            'sent' => array(
                'type' => 'dropdown',
                'label' => __('Sent date', 'azm'),
                'required' => true,
                'options' => array(
                    'is_after' => __('Is after', 'azm'),
                    'is_before' => __('Is before', 'azm'),
                    'is' => __('Is', 'azm'),
                    'is_within' => __('Is within', 'azm'),
                    'is_not_within' => __('Is not within', 'azm'),
                ),
                'where_clauses' => array(
                    'is_after' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND DATE(FROM_UNIXTIME(sent_timestamp + $gmt_offset)) < DATE(STR_TO_DATE('{date}','%Y-%m-%d')))",
                    'is_before' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND DATE(FROM_UNIXTIME(sent_timestamp + $gmt_offset)) > DATE(STR_TO_DATE('{date}','%Y-%m-%d')))",
                    'is' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND DATE(FROM_UNIXTIME(sent_timestamp + $gmt_offset)) = DATE(STR_TO_DATE('{date}','%Y-%m-%d')))",
                    'is_within' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND CAST(sent_timestamp AS DECIMAL(10, 2)) >= UNIX_TIMESTAMP(NOW() - INTERVAL {days} DAY))",
                    'is_not_within' => "v.visitor_id IN (SELECT visitor_id FROM {$wpdb->prefix}azr_fb_campaigns WHERE {{visitor_id IN ({visitor_id}) AND}} campaign_id={campaign} AND CAST(sent_timestamp AS DECIMAL(10, 2)) < UNIX_TIMESTAMP(NOW() - INTERVAL {days} DAY))",
                ),
                'default' => 'is_before',
            ),
            'date' => array(
                'type' => 'date',
                'label' => __('Date', 'azm'),
                'required' => true,
                'dependencies' => array(
                    'sent' => array('is_after', 'is_before', 'is'),
                ),
            ),
            'days' => array(
                'type' => 'number',
                'label' => __('Days', 'azm'),
                'required' => true,
                'dependencies' => array(
                    'sent' => array('is_within', 'is_not_within'),
                ),
            ),
        ),
    );
    $azr['actions']['fb_chat_greeting'] = array(
        'name' => __('Alter Facebook chat greeting', 'azm'),
        'group' => __('Facebook', 'azm'),
        'required_context' => array('visitors', 'visitor_id'),
        'event_dependency' => array('visit'),
        'where_clause' => "v.visitor_id = '{visitor_id}'",
        'parameters' => array(
            'logged_in_greeting' => array(
                'type' => 'textarea',
                'label' => __('Logged in greeting', 'azm'),
                'required' => true,
            ),
            'logged_out_greeting' => array(
                'type' => 'textarea',
                'label' => __('Logged out greeting', 'azm'),
                'required' => true,
            ),
        ),
    );

    $azr['actions']['send_fb_message'] = array(
        'name' => __('Send Facebook Message', 'azm'),
        'group' => __('Facebook', 'azm'),
        'required_context' => array('visitors'),
        'condition_dependency' => array('fb_messenger_sender'),
        'parameters' => array(
            'message_template' => array(
                'type' => 'textarea',
                'label' => __('Message template (shortcodes supported)', 'azm'),
                'required' => true,
            ),
            'message_tag' => array(
                'type' => 'dropdown',
                'label' => __('Message Tag', 'azm'),
                'required' => true,
                'default' => 'NON_PROMOTIONAL_SUBSCRIPTION',
                'options' => array(
                    'NON_PROMOTIONAL_SUBSCRIPTION' => 'NON_PROMOTIONAL_SUBSCRIPTION',
                    'ISSUE_RESOLUTION' => 'ISSUE_RESOLUTION',
                    'COMMUNITY_ALERT' => 'COMMUNITY_ALERT',
                    'CONFIRMED_EVENT_REMINDER' => 'CONFIRMED_EVENT_REMINDER',
                    'PAIRING_UPDATE' => 'PAIRING_UPDATE',
                    'APPLICATION_UPDATE' => 'APPLICATION_UPDATE',
                    'ACCOUNT_UPDATE' => 'ACCOUNT_UPDATE',
                    'PAYMENT_UPDATE' => 'PAYMENT_UPDATE',
                    'PERSONAL_FINANCE_UPDATE' => 'PERSONAL_FINANCE_UPDATE',
                    'SHIPPING_UPDATE' => 'SHIPPING_UPDATE',
                    'RESERVATION_UPDATE' => 'RESERVATION_UPDATE',
                    'APPOINTMENT_UPDATE' => 'APPOINTMENT_UPDATE',
                    'GAME_EVENT' => 'GAME_EVENT',
                    'TRANSPORTATION_UPDATE' => 'TRANSPORTATION_UPDATE',
                    'FEATURE_FUNCTIONALITY_UPDATE' => 'FEATURE_FUNCTIONALITY_UPDATE',
                    'TICKET_UPDATE' => 'TICKET_UPDATE',
                ),
            ),
            'batch_size' => array(
                'type' => 'number',
                'label' => __('Messages number per batch', 'azm'),
                'required' => true,
                'default' => '5',
                'event_dependency' => array('scheduler', 'new_post'),
            ),
            'batch_delay' => array(
                'type' => 'number',
                'label' => __('Delay between batches (seconds)', 'azm'),
                'required' => true,
                'default' => '60',
                'event_dependency' => array('scheduler', 'new_post'),
            ),
            'batch_sleep' => array(
                'type' => 'number',
                'label' => __('Delay between every Message send (microseconds)', 'azm'),
                'required' => true,
                'default' => '1000',
                'event_dependency' => array('scheduler', 'new_post'),
            ),
        ),
    );

    return $azr;
}

add_filter('azr_process_action', 'azm_fb_process_action', 10, 2);

function azm_fb_process_action($context, $action) {
    switch ($action['type']) {
        case 'fb_chat_greeting':
            if (isset($context['visitors'])) {
                global $wpdb;
                $db_query = azr_get_db_query($context['visitors']);
                $visitors = $wpdb->get_results($db_query, ARRAY_A);
                $visitors = array_map(function($value) {
                    return $value['visitor_id'];
                }, $visitors);
                if (!empty($visitors)) {
                    add_filter('azm_fb_chat_greeting', function($settings) use($action, $context) {
                        if ($action['logged_in_greeting']) {
                            $settings['logged_in_greeting'] = base64_decode($action['logged_in_greeting'], ENT_QUOTES);
                        }
                        if ($action['logged_out_greeting']) {
                            $settings['logged_out_greeting'] = base64_decode($action['logged_out_greeting'], ENT_QUOTES);
                        }
                        return $settings;
                    });
                }
                azr_action_executed($context['rule']);
                azr_visitors_prcessed($context['rule'], count($visitors));
            }
            break;
        case 'send_fb_message':
            if (isset($context['visitors'])) {
                global $wpdb;
                if (!empty($context['visitor_id'])) {
                    update_post_meta($context['rule'], '_fb_receivers', __('Unknown', 'azm'));

                    $db_query = azr_get_db_query($context['visitors']);
                    $visitors = $wpdb->get_results($db_query, ARRAY_A);
                    $visitors = array_map(function($value) {
                        return $value['visitor_id'];
                    }, $visitors);
                    azm_send_fb_campaign($visitors, $action, $context);
                    azr_visitors_prcessed($context['rule'], count($visitors));
                } else {
                    $num_rows = $wpdb->get_var(azr_get_count_db_query($context['visitors']));
                    update_post_meta($context['rule'], '_fb_receivers', $num_rows);

                    $batches_number = floor($num_rows / $action['batch_size']);
                    if ($num_rows % $action['batch_size']) {
                        $batches_number = $batches_number + 1;
                    }
                    for ($i = 0; $i < $batches_number; $i++) {
                        wp_schedule_single_event(time() + $i * $action['batch_delay'], 'azm_send_fb_process', array(
                            'action' => $action,
                            'context' => $context,
                            'offset' => $i * $action['batch_size'],
                        ));
                    }
                    azr_visitors_prcessed($context['rule'], $num_rows);
                }
                azr_action_executed($context['rule']);
            }
            break;
    }
    return $context;
}

add_action('azm_send_fb_process', 'azm_send_fb_process', 10, 3);

function azm_send_fb_process($action, $context, $offset) {
    global $wpdb;
    $db_query = azr_get_db_query($context['visitors']);
    $db_query = $db_query . ' LIMIT ' . $offset . ',' . $action['batch_size'];
    $visitors = $wpdb->get_results($db_query, ARRAY_A);
    $visitors = array_map(function($value) {
        return $value['visitor_id'];
    }, $visitors);
    azm_send_fb_campaign($visitors, $action, $context);
}

function azm_fb_contact_nonce($action, $visitor_id) {
    if ($visitor_id) {
        global $wpdb;
        $contact = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}azr_fb_visitors WHERE visitor_id='$visitor_id'", ARRAY_A);
        return substr(wp_hash($action . '|' . $id . '|' . $contact['hash'], 'nonce'), -12, 10);
    }
    return '';
}

function azm_send_fb_campaign($visitors, $action, $context = array()) {
    $settings = get_option('azh-fb-settings', array());
    foreach ($visitors as $visitor_id) {
        global $wpdb;
        $contact = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}azr_fb_visitors WHERE visitor_id='$visitor_id'", ARRAY_A);
        if ($contact) {
            $wpdb->query("REPLACE INTO {$wpdb->prefix}azr_fb_campaigns (visitor_id, campaign_id) VALUES ('$visitor_id', {$context['rule']})");

            $message = base64_decode($action['message_template'], ENT_QUOTES);
            if (!$message) {
                return false;
            }

            $url_params = array(
                'campaign' => $context['rule'],
                'visitor' => $visitor_id,
                'click' => azm_fb_contact_nonce('azm-fb-click', $visitor_id),
            );
            $url_params = apply_filters('azr_action_url_params', $url_params, $action, $context);
            $regex = '"\b(https?://\S+)"';
            $message = preg_replace_callback($regex, function( $url ) use($url_params, $settings) {
                $new_url = add_query_arg($url_params, $url[0]);
                if (!empty($settings['google-api-key'])) {
                    $new_url = azm_url_shorten($new_url, $settings['google-api-key']);
                }
                return $new_url;
            }, $message);

            foreach ($contact as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }
            if (!empty($context)) {
                $message = azm_tokens($message, $context);
            }
            $message = azr_visitor_tokens($message, $visitor_id);
            $message = do_shortcode($message);

            $result = azm_fb_message_send(azm_fb_get_page_access_token(azm_fb_get_subscribed_page_id()), $contact['psid'], $message, $action['message_tag']);
            if (!$result) {
                $wpdb->query("UPDATE {$wpdb->prefix}azr_fb_campaigns SET status='failed' WHERE campaign_id={$context['rule']} AND visitor_id='$visitor_id'");
            } else {
                $wpdb->query("UPDATE {$wpdb->prefix}azr_fb_campaigns SET status='sent', sent_timestamp = " . time() . " WHERE campaign_id={$context['rule']} AND visitor_id='$visitor_id'");
                azr_counter_increment($context['rule'], '_fb_sent');
            }
        }
    }
}

add_action('init', 'azm_fb_click');

function azm_fb_click() {
    if (isset($_GET['click']) && isset($_GET['campaign']) && is_numeric($_GET['campaign'])) {
        if (isset($_GET['visitor'])) {
            $visitor_id = sanitize_text_field($_GET['visitor']);
            $campaign_id = (int) $_GET['campaign'];
            if ($_GET['click'] == azm_fb_contact_nonce('azm-fb-click', $visitor_id)) {
                azr_check_visitor_id($visitor_id);
                global $wpdb;
                $click_timestamp = $wpdb->get_var("SELECT click_timestamp FROM {$wpdb->prefix}azr_fb_campaigns WHERE campaign_id=$campaign_id AND visitor_id='$visitor_id'");
                if (!$click_timestamp) {
                    $wpdb->query("UPDATE {$wpdb->prefix}azr_fb_campaigns SET click_timestamp = " . time() . " WHERE campaign_id=$campaign_id AND visitor_id='$visitor_id'");
                    azr_counter_increment($campaign_id, '_fb_clicks');
                    if (isset($_GET['redirect'])) {
                        exit(wp_redirect(esc_url($_GET['redirect'])));
                    }
                }
            }
        }
    }
}

add_filter('azr_get_action_results', 'azm_fb_results', 10, 3);

function azm_fb_results($results, $action, $rule_id) {
    switch ($action['type']) {
        case 'send_fb_message':
            $results .= '<div>' . __('Facebook receivers', 'azm') . ': ' . (int) get_post_meta($rule_id, '_fb_receivers', true) . '</div>';
            $results .= '<div>' . __('Facebook messages sent', 'azm') . ': ' . (int) get_post_meta($rule_id, '_fb_sent', true) . '</div>';
            $results .= '<div>' . __('Facebook clicks', 'azm') . ': ' . (int) get_post_meta($rule_id, '_fb_clicks', true) . '</div>';
            break;
    }
    return $results;
}

add_action('wp_insert_post', 'azm_fb_campaign_insert_post', 10, 3);

function azm_fb_campaign_insert_post($post_id, $post, $update) {
    if ($post->post_type == 'azr_rule') {
        $rule = get_post_meta($post->ID, '_rule', true);
        $rule = json_decode($rule, true);

        $context = azr_prepare_context_by_event($rule);
        if ($context['visitors']) {
            $context = azr_process_conditions($rule, $context);
            if (empty($context['visitor_id'])) {
                global $wpdb;
                $db_query = azr_get_count_db_query($context['visitors']);
                $num_rows = $wpdb->get_var($db_query);
                update_post_meta($post->ID, '_fb_receivers', $num_rows);
            } else {
                update_post_meta($post->ID, '_fb_receivers', __('Unknown', 'azm'));
            }
            global $wpdb;
            $sent = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}azr_fb_campaigns WHERE campaign_id={$post->ID} AND status='sent'");
            update_post_meta($post->ID, '_fb_sent', $sent);
            $clicks = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}azr_fb_campaigns WHERE campaign_id={$post->ID} AND click_timestamp IS NOT NULL");
            update_post_meta($post->ID, '_fb_clicks', $clicks);
        }
    }
}

function azm_fb_options_callback() {
    $settings = get_option('azh-fb-settings', array());
    if (isset($settings['mode']) && $settings['mode'] == 'webhook') {
        if (!empty($settings['app-id']) && !empty($settings['app-secret'])) {
            $access_token = get_option('azm-fb-access-token');
            if (empty($access_token)) {
                ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th>                        
                                <?php esc_html_e('OAuth redirect URL', 'azm'); ?>
                            </th>                        
                            <td>                        
                                <?php print azm_fb_get_redirect_uri(); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <a href="<?php print azm_fb_login_url() ?>" class="button button-primary button-hero"><?php esc_html_e('Connect to Facebook', 'azm'); ?></a>
                <?php
            } else {
                $pages = get_option('azm-fb-pages');
                if (!empty($pages)) {
                    ?>
                    <p><?php esc_html_e('Subscribe Webhook to Facebook Pages', 'azm'); ?></p>
                    <?php
                    if ($pages && is_array($pages)) {
                        foreach ($pages as $page) {
                            ?>
                            <label>
                                <input type="checkbox" name="azh-fb-settings[subscriptions][<?php print $page['id'] ?>]" <?php print (empty($settings['subscriptions'][$page['id']]) ? '' : 'checked') ?>>
                                <?php print $page['name'] ?>
                            </label>
                            <?php
                        }
                    }
                }
                ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th>                        
                                <?php esc_html_e('Webhook URL', 'azm'); ?>
                            </th>                        
                            <td>                        
                                <?php print str_replace('http://', 'https://', admin_url('admin-ajax.php')) . '?action=azm_fb_webhook'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>                        
                                <?php esc_html_e('Webhook verify token', 'azm'); ?>
                            </th>                        
                            <td>                        
                                <?php print get_option('azm-fb-verify-token'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php
            }
        }
    } else {
        delete_option('azm-fb-access-token');
    }
}

add_action('update_option_azh-fb-settings', 'azm_fb_options_update', 10, 2);

function azm_fb_options_update($oldvalue, $newvalue) {
    if (!isset($oldvalue['subscriptions']) || !is_array($oldvalue['subscriptions'])) {
        $oldvalue['subscriptions'] = array();
    }
    if (!isset($newvalue['subscriptions']) || !is_array($newvalue['subscriptions'])) {
        $newvalue['subscriptions'] = array();
    }
    foreach ($newvalue['subscriptions'] as $id => $status) {
        if (!isset($oldvalue['subscriptions'][$id]) || $oldvalue['subscriptions'][$id] != 'on') {
            $pages = get_option('azm-fb-pages');
            if (is_array($pages)) {
                foreach ($pages as $page) {
                    if ($page['id'] == $id) {
                        azm_fb_subscribe_app2page($page['access_token']);
                    }
                }
            }
        }
    }
    foreach ($oldvalue['subscriptions'] as $id => $status) {
        if (!isset($newvalue['subscriptions'][$id]) || $newvalue['subscriptions'][$id] != 'on') {
            $pages = get_option('azm-fb-pages');
            if (is_array($pages)) {
                foreach ($pages as $page) {
                    if ($page['id'] == $id) {
                        azm_fb_unsubscribe_app2page($id, $page['access_token']);
                    }
                }
            }
        }
    }
}

function azm_fb_login_url() {
    $settings = get_option('azh-fb-settings', array());
    $redirect_uri = azm_fb_get_redirect_uri();
    $scope = 'manage_pages,public_profile,email';
    $state = uniqid();
    return "https://www.facebook.com/v2.8/dialog/oauth?client_id={$settings['app-id']}&redirect_uri={$redirect_uri}&state={$state}&scope={$scope}&response_type=code&v=5.68";
}

function azm_fb_code2token($code) {
    $settings = get_option('azh-fb-settings', array());
    $redirect_uri = azm_fb_get_redirect_uri();
    //https://oauth.facebook.com/access_token
    $url = sprintf('https://graph.facebook.com/v2.8/oauth/access_token?client_id=%1$s&redirect_uri=%2$s&client_secret=%3$s&code=%4$s', $settings['app-id'], $redirect_uri, $settings['app-secret'], $code);
    $result = file_get_contents($url);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return ((isset($result['access_token'])) ? $result['access_token'] : $result);
}

function azm_fb_get_redirect_uri() {
    return str_replace('http://', 'https://', add_query_arg(array('page' => 'azh-fb-settings'), admin_url('admin.php')));
}

add_action('azm_fb_page', 'azm_fb_login_callback');

function azm_fb_login_callback() {
    if (isset($_GET['code'])) {
        $access_token = azm_fb_code2token(sanitize_text_field($_GET['code']));
        if ($access_token) {
            update_option('azm-fb-access-token', $access_token);
            update_option('azm-fb-pages', azm_fb_get_all_pages($access_token));
            update_option('azm-fb-verify-token', uniqid());
            azm_fb_set_webhook();
            wp_redirect(add_query_arg(array('page' => 'azh-fb-settings'), admin_url('admin.php')));
        }
    }
}

function azm_fb_get_all_pages($access_token) {
    $result = file_get_contents('https://graph.facebook.com/v2.8/me/accounts?access_token=' . $access_token . '&limit=10000');
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    if (isset($result['data'])) {
        return $result['data'];
    }
}

function azm_fb_get_app_access_token() {
    $settings = get_option('azh-fb-settings', array());
    $result = file_get_contents("https://graph.facebook.com/oauth/access_token?client_id=" . $settings['app-id'] . "&client_secret=" . $settings['app-secret'] . "&grant_type=client_credentials");
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return ((isset($result['access_token'])) ? $result['access_token'] : $result);
}

function azm_fb_subscribe_app2page($page_token) {
    $url = "https://graph.facebook.com/v2.8/me/subscribed_apps";

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "access_token" => $page_token,
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}

function azm_fb_unsubscribe_app2page($page_id, $page_token) {
    $url = "https://graph.facebook.com/v2.8/" . $page_id . "/subscribed_apps";

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "access_token" => $page_token,
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'DELETE',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}

function azm_fb_set_webhook() {
    $settings = get_option('azh-fb-settings', array());

    $url = "https://graph.facebook.com/v2.8/" . $settings['app-id'] . "/subscriptions";

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "access_token" => azm_fb_get_app_access_token(),
        "object" => 'page',
        'callback_url' => str_replace('http://', 'https://', admin_url('admin-ajax.php')) . '?action=azm_fb_webhook',
        'fields' => 'standby,messages,messaging_postbacks,messaging_referrals,message_deliveries,message_reads',
        'verify_token' => get_option('azm-fb-verify-token'),
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}

function azm_fb_unset_webhook() {
    $settings = get_option('azh-fb-settings', array());

    $url = "https://graph.facebook.com/v2.8/" . $settings['app-id'] . "/subscriptions";

    $headers = array(
        "Content-type: application/x-www-form-urlencoded",
    );
    $params = array(
        "access_token" => azm_fb_get_app_access_token(),
        "object" => 'page',
    );

    $data = http_build_query($params);
    $streamOptions = stream_context_create(array(
        'http' => array(
            'method' => 'DELETE',
            'header' => $headers,
            'content' => $data
        ),
    ));
    $result = file_get_contents($url, false, $streamOptions);
    if($http_response_header && isset($http_response_header[0]) && strpos($http_response_header[0], '200') === false) {
        azm_log(print_r($http_response_header, true));
    }    
    $result = json_decode($result, true);
    return $result;
}
