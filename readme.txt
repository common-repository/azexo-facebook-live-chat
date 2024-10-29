=== Live Chat Marketing Automation ===
Contributors: azexo
Tags: chat, facebook, facebook chat, live chat, live support, message, messages, messenger, messenger chat, messenger customer chat, messenger live chat, facebook customer chat, support
Requires at least: 4.4
Tested up to: 4.9
Stable tag: 1.27
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add Facebook Customer Chat to your site. Collect subscribers and send news or marketing offers.


== Description ==

Customer live chat for support and leads conversion. Professional solution for Facebook Messenger Marketing.

== Contact customer anytime again after he contacting your support ==

**[Plugin Demo](http://azexo.com/automation/)**

This is add-on for **[Marketing Automation by AZEXO](https://wordpress.org/plugins/marketing-automation-by-azexo/)**.


= Main Features =

* Collect subscribers via Facebook Live Chat
* Link site visitor behavior (and other info) with chat participant
* Automation for news or marketing offers sending (event notifications, time scheduling or other logic)
* Any dynamic logic for live chat greeting message (behavior based, personal marketing offers or something other) - to stimulate chat starting and get visitor Facebook contact **in [PRO version](https://codecanyon.net/item/facebook-live-chat-marketing-automation/21599897)**
* Supported all features of **[Marketing Automation by AZEXO](https://wordpress.org/plugins/marketing-automation-by-azexo/)**


== Installation ==

1. Plugin settings: "Automation > Facebook Chat settings"
2. Switch "Facebook live chat mode" to "Live chat" if you do not need messages bulk sending then "Save settings"
3. Create an Facebook Application https://www.facebook.com/business/help/444614902378217 in Facebook Developer Account
4. Create Facebook Page and "Facebook Page"->"Settings"->"Messenger Platform" tab and at "Whitelisted Domains" add domain name of your site with this plugin.
5. Enter "Application ID"  and "Facebook Page ID" in plugin settings
6. If you need messages bulk sending - switch "Facebook live chat mode" to "Live chat and collecting subscribers" then "Save settings"
7. Enable HTTPS for your site - this in mandatory for collecting subscribers
8. Enter "Application ID"  and "Application Secret" in plugin settings then "Save settings"
9. Go to your "Facebook Application Panel > Products > Facebook login", insert the "OAuth redirect URL" (from plugin settings page) to "Valid OAuth redirect URIs" then "Save settings"
10. Click "Connect to Facebook". After you return to plugin settings choose Facebook Page which will be connected to chat then "Save settings"
11. Set up webhook in "Facebook Application Panel > Products > Messenger" - use "Webhook URL" and "Webhook verify token" which provided in plugin settings https://developers.facebook.com/docs/messenger-platform/getting-started/app-setup
12. Subscribe webhook to events "standby,messages,messaging_postbacks,messaging_referrals,message_deliveries,message_reads" in "Facebook Application Panel > Products > Messenger"
13. Request access to "pages_messaging" in "Facebook Application Panel > Products > Messenger"


== Frequently Asked Questions ==

= Why are there no FAQs besides this one? =

Because you haven't asked one yet.


== Changelog ==

= 1.27 =
* Initial Release
