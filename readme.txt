=== PayHub Plugin For WooCommerce ===
Contributors: EJ Costiniano, Lon Sun
Website: http://payhub.com
Tags: woocommerce, payment, gateway, credit card, visa, mastercard
Requires at least: 3.5
Tested up to: 3.8.1
Stable tag: 1.0.9 
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

PayHub gateway plugin for the WooCommerce system.  It allows you to accept credit cards directly from the WooCommerce checkout page. 

== Description ==
This plugin works specifically with the WooCommerce ecommerce plugin.  It allows you to accept credit card payments through WooCommerce, using PayHub as the payment gateway.  Please note, a PayHub account is required to process transactions.  Contact us using the info below to setup an account.

== Changelog ==

= 1.0.8 =
* Initial Release

= 1.0.9 =
* Released on February 20, 2014
* Fixed issue on WooCommerce 2.1.x where the results page was not displaying correctly.
* We now require SSL peer verification when sending a transaction request.
* Added more helpful information to the read me, such as better configuration instructions and notes on security.

== Upgrade Notice ==

= 1.9 =
This version improves security and fixes a critical issue for users on WooCommerce 2.1.x.  You should upgrade immediately.

== Installation ==
* Search for the "WooCommerce PayHub Gateway Plugin" via the WordPress Plugins page.
* Click on the Install Now option and follow the instructions that are presented to you

==Configuration ==
Once the PayHub plugin is installed, in WordPress Admin:

* Click on WooCommerce Settings, either through the option on the navigation bar on the left, or through the plugin list.
* For WooCommerce 2.0.x, click on the Payment tab.  For WooCommerce 2.1.x, click on the Checkout Tab.
* Select PayHub as the default payment processor and save the changes.
* Click on PayHub Settings button.
* Enter in your PayHub API credentials n the fields provided.

== How to find your API credentials ==
* Log into the PayHub VirtualHub site (go to http://payhub.com and click on Login in the top left)
* Once logged in, click on the Admin navigation link at the top right.
* Under the General heading, click on the 3rd Party API link.
* Copy down your Username, Password, and Terminal ID.  Please note the username and password is case sensitive.

== Notes on Security ==
This plugin requires validation of the host SSL certificate for PayHub servers.  This is important as it greatly reduces the chance of a successful "man in the middle" attack.

If you go through the installation and everything works fine, then you don't have to worry about the rest of this section.  If you are experiencing a problem where you receive a blank error when trying to process cards and the transaction never actually processes then read on...

Since our plugin uses cURL (http://curl.haxx.se/) to send transaction requests, you need to make sure that cURL knows where to find the CA certificate with which to validate our API SSL certs.  This is generally not a problem with hosted setups, but if you have built out your own server then you may find that this is a problem because newer versions of cURL don't include a CA bundle by default.  In this case, if you are using PHP 5.3.7 or greater you can:

*download http://curl.haxx.se/ca/cacert.pem and save it somewhere on your server.
*update php.ini -- add curl.cainfo = "PATH_TO/cacert.pem"

This solutions was shamelessly borrowed from the Stack Overflow post: http://stackoverflow.com/questions/6400300/php-curl-https-causing-exception-ssl-certificate-problem-verify-that-the-ca-cer.  Gotta love Stack Overflow ;^).

Alternatively, you can dig into the PayHub plugin itself and add the following key/value pair to the $c_opts array: CURLOPT_CAINFO => "payth/to/ca-bundle.pem".  See http://us2.php.net/manual/en/book.curl.php for more info.

== How to get support ==
If you have any questions you can contact PayHub at:
(415) 306-9476 from 8AM - 5PM PST M-F
or email us at wecare@payhub.com
