# Mageto-2.X
Magento 2.X compatible
# <h3>How To Generate Access token and API Secret :</h3>
You can find your Merchant Id in Paykun Dashboard.

You can generate Or Regenerate Access token and API Secret from login into your paykun admin panel, Then Go To : Settings -> Security -> API Keys. There you will find the generate button if you have not generated api key before.

If you have generated api key before then you will see the date of the api key generate, since you will not be able to retrieve the old api key (For security reasons) we have provided the re-generate option, so you can re-generate api key in case you have lost the old one.

Note : Once you re-generate api key your old api key will stop working immediately. So be cautious while using this option.

# <h3>Prerequisite</h3>
    Merchant Id (Please read 'How To Generate Access token and API Secret :')
    Access Token (Please read 'How To Generate Access token and API Secret :')
    Encryption Key (Please read 'How To Generate Access token and API Secret :')
    Wordpress 4.x compatible Woo-Commerce version must be installed and other payment method working properly.

# <h3>Installation</h3>
Note: Please backup your running source code and database first.
   1. Download the zip and extract it to the some temporary location.
   2. Now Copy the app directory from extracted zip and override app directory in your Magento2 store.
   3. Fire required command like php bin/magento setup:upgrade.
   4. Login to Magento 2 admin and go to the Store > Configuration > Sales > Payment Methods
   5. Find Paykun and enter required credentials provided by Paykun.
   6. Save the below configuration.
   
         * Enable                  - Yes
         * Title                   - Paykun
         * Merchant Id             - Staging/Production Merchant Id provided by Paykun
         * Access Token            - Staging/Production Access Token provided by Paykun
         * Encryption Key          - Staging/Production Encryption Key provided by Paykun
         * Payment Action          - Authorize
         * Debug                   - For trouble shooting, also enable magento log from the Configuration > Advanced > Log Settings > Set Enabled to Yes

#<h3> In case of any query, please contact to support@paykun.com.</h3>