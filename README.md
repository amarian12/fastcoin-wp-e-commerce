Fastcoin for WP e-Commerce
=========================

A Fastcoin payment method for the
[WP e-Commerce][WP e-Commerce] shopping cart for [WordPress][WordPress].

Version: fastcoin-wp-e-commerce-version 0.3

Features
--------

* Generates a new fastcoin address for every order
* Provides payment address to customer on site at checkout, plus in a
  subsequent email
* Configurable timeout after which unpaid transactions will be canceled
  automatically
* Configurable number of Fastcoin network confirmations after which an order
  is considered paid
* HTTP or HTTPS access to fastcoind

Requirements
------------

### Base requirements
* WP e-Commerce 3.7.7 or greater
* WordPress 3.0 or greater (may work on 2.8+, untested)

### PHP requirements:
* PHP5
* cURL support
* SSL support (if you're using HTTPS to talk to terracoind)

Limitations
-----------

* It is assumed that Fastcoin is the *only* currency accepted.
* All prices are assumed to be in Fastcoins, and no currency conversions are
  performed.
* Checks for payment receipt are performed via WordPress cron, at least until
  fastcoind allows attaching a JSON-RPC callback to an address.
* No notification is sent to the customer or shop administrator if a
  transaction expires without payment.
* Expired transactions are marked with a status code of "5" in the database,
  which doesn't correspond to a human-readable status code provided by
  WP e-Commerce.
* No localization support.

Installation
------------

* Install WordPress <http://codex.wordpress.org/Installing_WordPress>.
* Log into your WordPress installation as an administrator.
* Install WP e-Commerce via Plugins->Add New in the WordPress dashboard.
* Transfer the contents of the distribution archive to the
  `wp-content/plugins/wp-e-commerce` directory of your WordPress installation.

Configuration
-------------

* Navigate to Store->Settings->Payment Options.
* Under "General Settings", check "Fastcoin" and uncheck everything else.
* Click "Update"
* At right, Select the Fastcoin payment gateway.
* Configure your fastcoind server information.
* If you are using HTTPS to talk to fastcoind and would like to validate
  the connection using fastcoind's own SSL certificate, enter the
  absolute path to the certificate file (server.cert) you've uploaded
  to the server.
* Configure your payment timeout and number of transaction confirmations
  required.
* Adjust the checkout message template as required.
* Click "Update".
* Click "General" at the top and set the currency type to "Terracoin".
* Set the remaining parameters as you wish and click "Update".


Modified for Fastcoin 
-------

* [Jon Marshall (m0gliE)](http://github.com/m0gliE) -

Orignal Authors
-------

* [Mike Gogulski](http://github.com/mikegogulski) -
  <http://www.nostate.com/> <http://www.gogulski.com/>

Credits
-------

Terracoin for WP e-Commerce incorporates code from:

* [XML-RPC for PHP][XML-RPC-PHP] by Edd Dumbill (for JSON-RPC support)
* [bitcoin-php][bitcoin-php] by Mike Gogulski (Bitcoin support library)

License
-------

Terracoin for WP e-Commerce is free and unencumbered public domain software. For more
information, see <http://unlicense.org/> or the accompanying UNLICENSE file.


[Terracoin]:			http://www.terracoin.org/
[WP e-Commerce]:	http://www.getshopped.org/
[WordPress]:		http://www.wordpress.org/
[XML-RPC-PHP]:		http://phpxmlrpc.sourceforge.net/
[terracoin-php]:		http://github.com/FuzzyBearBTC/terracoin-php
