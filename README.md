# Contents of this File
* Introduction
* Requirements
* Installation
* Configuration
* How It Works
* Troubleshooting
* Maintainers

# Introduction
This project provides Drupal Commerce integration with the [BlueSnap Payment Platform](https://developers.bluesnap.com/).
* For a full description of the module, visit the project page:
  https://www.drupal.org/project/commerce_bluesnap
* To submit bug reports and feature suggestions, or to track changes:
  https://www.drupal.org/project/issues/commerce_bluesnap


# Requirements
This module requires the following:
* Submodules of Drupal Commerce package (https://drupal.org/project/commerce)
  - Commerce Core
  - Commerce Payment (and its dependencies)
* BlueSnap PHP SDK (https://github.com/shabananavas/php-bluesnap-sdk)
* BlueSnap Merchant account (https://home.bluesnap.com/merchant-account)


# Installation
* Install the module via [Composer](https://www.drupal.org/docs/8/extending-drupal-8/installing-modules-composer-dependencies), which will download
the required libraries:

  ```composer require "drupal/commerce_bluesnap"```

* Or manually include the following in your application's composer.json ```repositories``` array:

  ```
  {
    "name": "drupal/commerce_bluesnap",
    "type": "vcs",
    "url": "https://github.com/shabananavas/drupal8-commerce-bluesnap"
  }
  ```

* Enable the module as you would like any Drupal 8 module:
  - You can do this through the UI by going to Administration > Extend
  or by running the drush command `drush en commerce_bluesnap`

# Configuration
Creating payment gateways for accepting Credit Card and ACH/ECP payments
are pretty much identical with exception to some minor changes.

## Accepting Credit Card Payments
* Create a new payment gateway by going to:
  Administration > Commerce > Configuration > Payment gateways > Add payment gateway
* Add a name to specify which type of checkout this is. ie. Credit Card
* Select ```BlueSnap (Hosted Payment Fields)``` from the list of 'Plugin' types
* Select the gateway mode
* Add the BlueSnap-specific settings:
  - Username
  - Password

Use the credentials provided by your BlueSnap merchant account.

Note: It is recommended to enter credentials from a sandbox account when testing and then override these with live credentials in settings.php on the production site. This way, live credentials will not be stored in the db.


## Accepting ACH/ECP Payments
* Create a new payment gateway by going to:
  Administration > Commerce > Configuration > Payment gateways > Add payment gateway
* Add a name to specify which type of checkout this is. ie. Check
* Select ```BlueSnap (ACH/ECP)``` from the list of 'Plugin' types
* Select the gateway mode
* Add the BlueSnap-specific settings:
  - Username
  - Password

Use the credentials provided by your BlueSnap merchant account.

Note: It is recommended to enter credentials from a sandbox account when testing
and then override these with live credentials in settings.php on the production
site. This way, live credentials will not be stored in the db.


# How it works

* General considerations:
  - The store owner must have a BlueSnap merchant account.
    Sign up here:
    https://home.bluesnap.com/merchant-account
  - Customers should have a valid credit card for processing card payments.
    - BlueSnap provides several dummy credit card numbers for testing:
      https://developers.bluesnap.com/docs/test-credit-cards
  - ACH/ECP payments require the customer's account number,
     routing number, and account type.
    - For testing ACH/ECP payments, test bank credentials can be found here:
      https://support.bluesnap.com/docs/ecp

* Credit Card checkout workflow:

  - During checkout, the customer should select the BlueSnap Credit Card
  payment method and should enter his/her credit card data
  or select one of the existing credit cards saved with BlueSnap
  from a previous order.
  - As seen here: https://www.drupal.org/files/bluesnap_credit_card_checkout.png

* ACH/ECP checkout workflow:
  - During checkout, the customer should select the BlueSnap ACH/ECP
  payment method and enter his/her bank details or select one of the
  existing ACH/ECP payments saved with BlueSnap from a previous order.
  - As seen here: https://www.drupal.org/files/bluesnap_check_checkout.png

* Payment Terminal
  - The store owner can Void, Capture and Refund the BlueSnap payments.


# Troubleshooting
* No troubleshooting pending for now.


# Maintainers
Current maintainers:
* Shabana Navas (shabana.navas) - https://www.drupal.org/u/shabananavas
* Dimitris Bozelos (krystalcode) - https://www.drupal.org/u/krystalcode

This project has been developed by:
* Acro Media Inc - Visit https://www.acromedia.com for more information.
