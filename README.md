# woocomerce_redmart_php_curl_api_stock
Integrate the API for Redmart so that stock levels in WooCommerce are adjusted with sales in Redmart and vice versa,

- The user will make an order on Woocommerce, When a customer pays it goes into order "processing" status.
- Under "processing" status it will call API and will fetch stock in RedMart and reduce it by item quantity.
- Final Reduced stock left in RedMart is updated back in Woocommerce as well. So both values are matched.
- Every day let's say every hour or 30min, a script in the Woocommerce site, will verify RedMart stock, if there is any change it will update the Woocommerce stock.
