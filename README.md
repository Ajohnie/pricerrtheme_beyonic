# pricerrtheme_beyonic
   Adds extra payment gateway - beyonic - for the Pricerr Theme from sitemile. Extension.
# Usage
1. Install pricerr theme from sitemile(http://sitemile.com/p/pricerr)
2. Compress the file into a zip folder
3. Upload it via wordpress plugins page or upload the uncompressed version to the plugins directory
4. Activate the plugin in the plugins page
# Note
1. Make sure name verification checks are disabled in your beyonic organisation settings
2. Account ID can be left blank and the payments will still succeed but will be charged to the default wallet
3. To take advantage of the rest api, add webhooks to your beyonic account under notification endpoints
4. Add 'payment.status.changed' event with url (your site url)/wp_json/beyonic-api/payments'
5. Add 'collectionrequest.status.changed' event with url (your site url)/wp_json/beyonic-api/collections
6. Enable permalinks in your wp settings otherwise the path (your site url)/wp_json/... might not work
7. Make sure your connected to a network with OTT tax paid. access to beyonic will be blocked otherwise

# TODO
1. check the "TODO"'s I left in the comments
2. any improvements are much appreciated


