<?php
// Display custom fields in the "General" tab of the product edit page
add_action('woocommerce_product_options_general_product_data', 'add_custom_product_fields');
function add_custom_product_fields()
{
    global $product_object;
    echo '<div class="product_custom_fields">';
    // Text field
    woocommerce_wp_text_input(
        array(
            'id' => '_rpc_code',
			'wrapper_class' => 'show_if_simple',
            'label' => __('RPC Code', 'woocommerce'),
            'placeholder' => '',
            'desc_tip' => 'true',
            'description' => __('Enter RPC Code.', 'woocommerce')
        )
    );
    // Select field
    woocommerce_wp_select(
        array(
            'id' => '_redmart_sync',
			'wrapper_class' => 'show_if_simple',
            'label' => __('Redmart Synch', 'woocommerce'),
            'options' => array(
                'No' => __('No', 'woocommerce'),
                'Yes' => __('Yes', 'woocommerce')
            ),
            'desc_tip' => 'true',
            'description' => __('Redmart Synch needed?', 'woocommerce')
        )
    );
    echo '</div>';
}
// Save custom fields when the product is saved
add_action('woocommerce_process_product_meta', 'save_custom_product_fields');

function save_custom_product_fields($product_id)
{
    // Save text field
    $custom_field_text = isset($_POST['_rpc_code']) ? sanitize_text_field($_POST['_rpc_code']) : '';
    update_post_meta($product_id, '_rpc_code', $custom_field_text);
    // Save select field
    $custom_field_select = isset($_POST['_redmart_sync']) ? sanitize_text_field($_POST['_redmart_sync']) : '';
    update_post_meta($product_id, '_redmart_sync', $custom_field_select);
}
add_action( 'woocommerce_order_status_cancelled', 'change_stock', 10, 1 );
add_action( 'woocommerce_order_status_processing', 'order_extracode' );
function order_extracode( $order_id) {
    $filename = dirname(__FILE__) ."/redmart_token.txt";
    $handle = fopen($filename, "r");
    $access_token = fread($handle, filesize($filename));
    fclose($handle);   

    if($access_token != ""){

        //echo $order_id;
        $order = wc_get_order( $order_id );
        $order_items = $order->get_items(); 

        // Iterating through each "line" items in the order      
        foreach ($order_items as $item_id => $item_values){
            $product_id = $item_values->get_product_id();            
            $redmart_sync = $room = get_post_meta( $product_id, '_redmart_sync', true );
            if($redmart_sync == "Yes"){               
            }else{continue;}

            $product=wc_get_product($product_id);
            if( $product->is_type( 'simple' ) ){
                // Step 1 - get one product to have locationsID
                $product_code = get_post_meta( $product_id, '_rpc_code', true );
                $locationID = getLocationID($product_code, $access_token);

                if($locationID>0){
                    // get stock lots from location and productID
                    $filename = dirname(__FILE__) ."/redmart_token.txt";
                    $handle = fopen($filename, "r");
                    $access_token = fread($handle, filesize($filename));
                    fclose($handle);

                    //echo "<br/><br/>Checking Stock</br>";
                    $stock = getStock($product_code, $locationID, $access_token); 
                    $quantityAtPickupLocation = $stock['quantityAtPickupLocation'];
                    $quantityScheduledForPickup = $stock['quantityScheduledForPickup'];
                    $quantityAvailableForSale = $stock['quantityAvailableForSale'];
                    $etag = $stock['etag'];
                    if($quantityAvailableForSale>0){
                        // update stock on woo and redmart by quantity ordered
                        $item_quantity  = $item_values->get_quantity();
                        $new_quantity = $quantityAvailableForSale-$item_quantity;
                        $updatedStock = updateStock($product_id, $product_code, $locationID, $access_token, $etag, $new_quantity);
                    }         
                }
            }else if( $product->is_type( 'variable' ) ){
                // Step 1 - get one product to have locationsID
                $variations = $product->get_available_variations();
                foreach ( $variations as $key => $value ) {                    
                    // check if variation have rpcCode
                    $_redmart_sync_variation = get_post_meta( $value[ 'variation_id' ], '_redmart_sync_variation', true );
                    if($value['_rpc_code_variation'] !="" && $_redmart_sync_variation="yes"){
                        $product_code = $value['_rpc_code_variation'];
                        $locationID = getLocationID($product_code, $access_token);
                        if($locationID>0){
                            $filename = dirname(__FILE__) ."/redmart_token.txt";
                            $handle = fopen($filename, "r");
                            $access_token = fread($handle, filesize($filename));
                            fclose($handle);
                            $stock = getStock($product_code, $locationID, $access_token); 
                            $quantityAtPickupLocation = $stock['quantityAtPickupLocation'];
                            $quantityScheduledForPickup = $stock['quantityScheduledForPickup'];
                            $quantityAvailableForSale = $stock['quantityAvailableForSale'];
                            $etag = $stock['etag'];
                            if($quantityAvailableForSale>0){
                                // update stock on woo and redmart by quantity ordered
                                $item_quantity  = $item_values->get_quantity();
                                $new_quantity = $quantityAvailableForSale-$item_quantity;
                                $updatedStock = updateStock($product_id, $product_code, $locationID, $access_token, $etag, $new_quantity);
                            }else{

                            }  
                        }
                    }
                }
            }
        }        
    }
}
function change_stock( $order_id) {
    $filename = dirname(__FILE__) ."/redmart_token.txt";
    $handle = fopen($filename, "r");
    $access_token = fread($handle, filesize($filename));
    fclose($handle);   

    if($access_token != ""){

        //echo $order_id;
        $order = wc_get_order( $order_id );
        $order_items = $order->get_items(); 

        // Iterating through each "line" items in the order      
        foreach ($order_items as $item_id => $item_values){
            $product_id = $item_values->get_product_id(); 
            $item_quantity  = $item_values->get_quantity();           
            $redmart_sync = get_post_meta( $product_id, '_redmart_sync', true );
            if($redmart_sync == "Yes"){               
            }else{continue;}

            $product=wc_get_product($product_id);
            if( $product->is_type( 'simple' ) ){
                // Step 1 - get one product to have locationsID
                $product_code = get_post_meta( $product_id, '_rpc_code', true );
                $locationID = getLocationID($product_code, $access_token);

                if($locationID>0){
                    // get stock lots from location and productID
                    $filename = dirname(__FILE__) ."/redmart_token.txt";
                    $handle = fopen($filename, "r");
                    $access_token = fread($handle, filesize($filename));
                    fclose($handle);

                    //echo "<br/><br/>Checking Stock</br>";
                    $stock = getStock($product_code, $locationID, $access_token); 
                    $quantityAtPickupLocation = $stock['quantityAtPickupLocation'];
                    $quantityScheduledForPickup = $stock['quantityScheduledForPickup'];
                    $quantityAvailableForSale = $stock['quantityAvailableForSale'];
                    $etag = $stock['etag'];
                    if($quantityAvailableForSale>0){
                        // update stock on woo and redmart by quantity ordered
                        $new_quantity = $quantityAvailableForSale+$item_quantity;
                        $updatedStock = updateStock($product_id, $product_code, $locationID, $access_token, $etag, $new_quantity);
                    }         
                }
            }else if( $product->is_type( 'variable' ) ){
                // Step 1 - get one product to have locationsID
                $variations = $product->get_available_variations();
                foreach ( $variations as $key => $value ) {                    
                    // check if variation have rpcCode
                    $_redmart_sync_variation = get_post_meta( $value[ 'variation_id' ], '_redmart_sync_variation', true );
                    if($_redmart_sync_variation=="yes"){
                        $product_code = $value['_rpc_code_variation'];
                        $locationID = getLocationID($product_code, $access_token);
                        if($locationID>0){
                            $filename = dirname(__FILE__) ."/redmart_token.txt";
                            $handle = fopen($filename, "r");
                            $access_token = fread($handle, filesize($filename));
                            fclose($handle);
                            $stock = getStock($product_code, $locationID, $access_token); 
                            $quantityAtPickupLocation = $stock['quantityAtPickupLocation'];
                            $quantityScheduledForPickup = $stock['quantityScheduledForPickup'];
                            $quantityAvailableForSale = $stock['quantityAvailableForSale'];
                            $etag = $stock['etag'];
                            if($quantityAvailableForSale>0){
                                // update stock on woo and redmart by quantity ordered                                
                                $new_quantity = $quantityAvailableForSale+$item_quantity;
                                $updatedStock = updateStock($product_id, $product_code, $locationID, $access_token, $etag, $new_quantity);
                            }  else{
                            }
                        }
                    }
                }
            }
        }         
    }
}
function getLocationID($product_code, $access_token){
    $url = 'https://partners-api.redmart.com/v1/products/'.$product_code;   
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $headers = array(
       "Accept: application/json",
       "Authorization: Bearer $access_token",
    );

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //for debug only!
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($curl);
    curl_close($curl);
    //var_dump($resp);
    $json_data = json_decode($resp,true); 
    //print_r($json_data);
    if(isset($json_data['error']) && $json_data['error'] == "invalid_token"){
        //echo "Invalid Generating New<br/>";
        $token = generateRedMartToken();
        $headers = array(
           "Accept: application/json",
           "Authorization: Bearer $token",
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        curl_close($curl);
        $json_data = json_decode($resp,true); 
        return $json_data['pickupLocations'][0]['id'];
    }else{
        //echo "<br/>Token exist<br/>";
        if($json_data['pickupLocations'][0]['id']>0)
        return $json_data['pickupLocations'][0]['id'];
    }
}
function updateStock($product_id, $product_code, $locationID, $access_token, $etag, $new_quantity){

    $url = 'https://partners-api.redmart.com/v1/products/'.$product_code.'/pickup-locations/'.$locationID.'/stock-lots/0'; 
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $data = '{ "quantityAtPickupLocation": "'.$new_quantity.'"}';
    $headers = array(
       "Accept: application/json",
       "Content-Type: application/merge-patch+json",
       "Authorization: Bearer $access_token",
       "If-Match: $etag",
    );
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //for debug only!
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($curl);
    curl_close($curl);
    $json_data = json_decode($resp,true);
    return $json_data;
}
function getStock($product_code, $locationID, $access_token){
    $url = 'https://partners-api.redmart.com/v1/products/'.$product_code.'/pickup-locations/'.$locationID.'/stock-lots/0'; 
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $headers = array(
       "Accept: application/json",
       "Authorization: Bearer $access_token",
    );

    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //for debug only!
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($resp, 0, $header_size);
    $headers = array();

    foreach (explode("\r\n", $header) as $i => $line){
        if ($i === 0)
            $headers['http_code'] = $line;
        else
        {
            list ($key, $value) = explode(': ', $line);
            $headers[$key] = $value;
        }
    }

    $body = substr($resp, $header_size);

    curl_close($curl);
    $json_data = json_decode($body,true); 
    $json_data['etag'] = $headers['etag'];
    curl_close($curl);
    return $json_data;
}

function generateRedMartToken(){
    $url = 'https://partners-api.redmart.com/oauth2/token';
    $clientID = "xxxxxxxxxxxxxxxxxx";
    $secretID = "xxxxxxxxxxxxxxxxxxxxxxxxx";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
                "grant_type=client_credentials&client_id=$clientID&client_secret=$secretID&scope=read:product read:pickup-location read:stock-lot write:stock-lot");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec ($ch);
    curl_close ($ch);
    $json_data = json_decode($server_output,true); 
    $access_token = $json_data['access_token'];
    $fp = fopen(dirname(__FILE__) ."/redmart_token.txt", 'w');
    fwrite($fp, $access_token);
    fclose($fp);
    return $access_token;
}

function  getProductsAndUpdateStock(){
    $params = array(
        'post_type' => 'product',
        'posts_per_page' => 1000,
        //'p' => 11963
    );
	//echo "<pre>";
	//print_r($params);
	$count=0;
	
    $wc_query = new WP_Query($params);
	//print_r($wc_query);
	//exit();
    global $post, $product;
    $filename = dirname(__FILE__) ."/redmart_token.txt";
    $handle = fopen($filename, "r");
    $access_token = fread($handle, filesize($filename));
    fclose($handle);
    if( $wc_query->have_posts() ) {
	 
      while( $wc_query->have_posts() ) {
		    $count=	$count+1;
            $wc_query->the_post();
            $product=wc_get_product($post->ID);
		    echo $product->get_type()."==".$post->ID."==";
		  	echo " == Date last synced ";               
		    echo $date_synced = get_post_meta($post->ID, 'red_mart_date_synced',true );
		    $date_now = date('Y-m-d H:i:s');
		    $d1= new DateTime("$date_synced"); // first date
		    $d2= new DateTime("$date_now"); // second date
		    $interval= $d1->diff($d2); // get difference between two datesecho "<br/>";
		    echo "==".$interval->i;
		  	echo " min passed<br/>";	
            echo $redmart_sync = get_post_meta( $post->ID, '_redmart_sync', true );	    
		    if($interval->i >= 0 || $date_synced==""){
			    echo " == Ready to run <br/>";
			    update_post_meta( $post->ID, 'red_mart_date_synced', date("Y-m-d H:i:s"));
				if( $product->is_type( 'simple' ) ){
					//continue;
					echo $redmart_sync = get_post_meta( $post->ID, '_redmart_sync', true );
					if($redmart_sync == "Yes"){               
						echo "<br/>Simple Product ID: $post->ID, product code: ";
						echo $product_code = get_post_meta($post->ID, '_rpc_code',true );
						echo " == Woo Stock ";
						echo $stock = get_post_meta( $post->ID, '_stock', true ) + 0;                
						if($product_code!=""){
							echo $locationID = getLocationID($product_code, $access_token);
							//get Stock RedMart
							// Update Woo Stock
							$filename = dirname(__FILE__) ."/redmart_token.txt";
							$handle = fopen($filename, "r");
							$access_token = fread($handle, filesize($filename));
							fclose($handle);
							$stock = getStock($product_code, $locationID, $access_token);
							$quantityAvailableForSale = $stock['quantityAvailableForSale'];
							if($quantityAvailableForSale>0){
								$woocmmerce_instance = new WC_Product( $post->ID );
								$new_quantity=wc_update_product_stock( $woocmmerce_instance, $quantityAvailableForSale);
								echo "<br/>Current RedMart Stock ".$quantityAvailableForSale."<br/>";
								echo "New Woo Stock updated to ".$new_quantity."<br/>";
							}
						}  
					}
				}else if( $product->is_type( 'variable' ) ){
					echo "<br/>Variable Product ID: $post->ID, product code: ";
					$variations = $product->get_available_variations();
					foreach ( $variations as $key => $value ) {                    
						// check if variation have rpcCode
						//_redmart_sync_variation
						$_redmart_sync_variation = get_post_meta( $value[ 'variation_id' ], '_redmart_sync_variation', true );
						if($_redmart_sync_variation=="yes"){
							echo ": Code and sync set: ";
							echo $product_code = $value['_rpc_code_variation'];
							echo " == Woo Stock ";
							echo $stock = $value['max_qty'] + 0;
							echo " == Date last synced ";
							echo $date_synced = get_post_meta($post->ID, 'red_mart_date_synced',true );
							echo "<br/>";
							$date_now = date('Y-m-d h:i:s');
							$d1= new DateTime("$date_synced"); // first date
							$d2= new DateTime("$date_now"); // second date
							$interval= $d1->diff($d2); // get difference between two datesecho "<br/>";
							if($interval->i > 30 || $date_synced=="" && $product_code!=""){
								$locationID = getLocationID($product_code, $access_token);
								//get Stock RedMart
								// Update Woo Stock
								$filename = dirname(__FILE__) ."/redmart_token.txt";
								$handle = fopen($filename, "r");
								$access_token = fread($handle, filesize($filename));
								fclose($handle);
								$stock = getStock($product_code, $locationID, $access_token);
								$quantityAvailableForSale = $stock['quantityAvailableForSale'];
								if($quantityAvailableForSale>0){
									$woocmmerce_instance = new WC_Product( $post->ID );
									$new_quantity=wc_update_product_stock( $woocmmerce_instance, $quantityAvailableForSale);
									echo "<br/>Current RedMart Stock ".$quantityAvailableForSale."<br/>";
									echo "New Woo Stock updated to ".$new_quantity."<br/>";
								}
							}  
						}
					}
				}             
		   }
		  	
            echo "<br/><br/>";		    
      } // end while
		echo $count." total records";
    } // end if
    else 
    {
        echo "nothing found";
    }
    wp_reset_postdata();
}
add_action( 'woocommerce_variation_options_pricing', 'red_mart_add_custom_field_to_variations', 10, 3 );
 
function red_mart_add_custom_field_to_variations( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input( array(
'id' => '_rpc_code_variation'. $loop ,
'class' => 'short',
'name' => '_rpc_code_variation['. $loop.']' ,
'wrapper_class' => 'form-row form-row-first _rpc_code_variation',
'desc_tip' => 'true',
'description' => __('Enter the RPC Code for API.', 'woocommerce'),
'label' => __( 'RPC Code', 'woocommerce' ),
'value' => get_post_meta( $variation->ID, '_rpc_code_variation', true )
   ) );
}
 
// -----------------------------------------
// 2. Save custom field on product variation save
 
add_action( 'woocommerce_save_product_variation', 'red_mart_save_custom_field_variations', 10, 2 );
 
function red_mart_save_custom_field_variations( $variation_id, $i ) {
   $custom_field = $_POST['_rpc_code_variation'][$i];
   if ( isset( $custom_field ) ) update_post_meta( $variation_id, '_rpc_code_variation', esc_attr( $custom_field ) );
}
 
// -----------------------------------------
// 3. Store custom field value into variation data
 
add_filter( 'woocommerce_available_variation', 'red_mart__add_custom_field_variation_data' );
 
function red_mart__add_custom_field_variation_data( $variations ) {
   $variations['_rpc_code_variation'] = get_post_meta( $variations[ 'variation_id' ], '_rpc_code_variation', true );
   return $variations;
}

function action_woocommerce_variation_options( $loop, $variation_data, $variation ) {
    $is_checked = get_post_meta( $variation->ID, '_redmart_sync_variation', true );

    if ( $is_checked == 'yes' ) {
        $is_checked = 'checked';
    } else {
        $is_checked = '';     
    }

    ?>
    <label class="tips" data-tip="<?php esc_attr_e( 'This is my data tip', 'woocommerce' ); ?>">
        <?php esc_html_e( 'Red Mart Sync:', 'woocommerce' ); ?>
        <input type="checkbox" class="checkbox variable_checkbox" name="_redmart_sync_variation[<?php echo esc_attr( $loop ); ?>]"<?php echo $is_checked; ?>/>
    </label>
    <?php
}
add_action( 'woocommerce_variation_options', 'action_woocommerce_variation_options', 10, 3);

// Save checkbox
function action_woocommerce_save_product_variation( $variation_id, $i ) {
    if ( ! empty( $_POST['_redmart_sync_variation'] ) && ! empty( $_POST['_redmart_sync_variation'][$i] ) ) {
        update_post_meta( $variation_id, '_redmart_sync_variation', 'yes' );
    } else {
        update_post_meta( $variation_id, '_redmart_sync_variation', 'no' ); 
    }       
}
add_action( 'woocommerce_save_product_variation', 'action_woocommerce_save_product_variation', 10, 2 );

add_filter( 'cron_schedules', 'isa_add_every_two_minutes' );
function isa_add_every_two_minutes( $schedules ) {
    $schedules['every_two_minutes'] = array(
            'interval'  => 120,
            'display'   => __( 'Every 2 Minutes', 'textdomain' )
    );
    return $schedules;
}
// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'isa_add_every_two_minutes' ) ) {
    wp_schedule_event( time(), 'every_two_minutes', 'isa_add_every_two_minutes' );
}
// Hook into that action that'll fire every three minutes
add_action( 'isa_add_every_two_minutes', 'getProductsAndUpdateStock' );

function update_redmart_stock($product_id_with_stock, $post_data) {
	if($post_data['post_type'] =="product"){}else{return;}
	$product = wc_get_product( $product_id_with_stock );
    // save the previous product stock quantity as a custom post meta "_old_stock_quantity"
    if( $product->is_type( 'simple' ) ){
		//continue;
		$new_stock = $_POST['_stock'];
		$old_stock = $_POST['_original_stock'];
		$redmart_sync = get_post_meta( $product_id_with_stock, '_redmart_sync', true );
		if($redmart_sync == "Yes"){ 
			$product_code = get_post_meta($product_id_with_stock, '_rpc_code',true );
			$stock = get_post_meta( $product_id_with_stock, '_stock', true ) + 0;
			if($product_code!="" && $new_stock!=$old_stock){
				$locationID = getLocationID($product_code, $access_token);
				$filename = dirname(__FILE__) ."/redmart_token.txt";
				$handle = fopen($filename, "r");
				$access_token = fread($handle, filesize($filename));
				fclose($handle);
				$stock = getStock($product_code, $locationID, $access_token);
				$quantityAvailableForSale = $stock['quantityAvailableForSale'];
				$new_quantity = "";
				$etag = $stock['etag'];
				updateStock($product_id_with_stock, $product_code, $locationID, $access_token, $etag, $new_stock);
				//echo "Current RedMart Stock ".$quantityAvailableForSale."<br/>";
				//echo "New Stock updated in RedMart ".$new_stock."<br/>";
			} 
		}
	}else if( $product->is_type( 'variable' ) ){
		$variations = $product->get_available_variations();
		$i=0;
		foreach ( $variations as $key => $value ) {
			$new_stock = $_POST['variable_stock'][$i];
			$old_stock = $_POST['variable_original_stock'][$i];
			$i++;
			// check if variation have rpcCode
			//_redmart_sync_variation
			$_redmart_sync_variation = get_post_meta( $value[ 'variation_id' ], '_redmart_sync_variation', true );
			if($_redmart_sync_variation=="yes"  && $new_stock!=$old_stock){
				$product_code = $value['_rpc_code_variation'];
				$stock = $value['max_qty'] + 0;
				if($product_code!=""){
					$locationID = getLocationID($product_code, $access_token);
					$filename = dirname(__FILE__) ."/redmart_token.txt";
					$handle = fopen($filename, "r");
					$access_token = fread($handle, filesize($filename));
					fclose($handle);
					$stock = getStock($product_code, $locationID, $access_token);
					$quantityAvailableForSale = $stock['quantityAvailableForSale'];
					$new_quantity = "";
					$etag = $stock['etag'];
					updateStock($product_id_with_stock, $product_code, $locationID, $access_token, $etag, $new_stock);
					//echo "Current RedMart Stock ".$quantityAvailableForSale."<br/>";
					//echo "New Stock updated in RedMart ".$new_stock."<br/>";
				} 
			}
		}
	}
}
add_action('pre_post_update', 'update_redmart_stock', 10, 2);

function test_inventory_stock() {
	$product_id_with_stock = 3715;
    $product = wc_get_product( $product_id_with_stock );
    // save the previous product stock quantity as a custom post meta "_old_stock_quantity"
    if( $product->is_type( 'simple' ) ){
		$redmart_sync = get_post_meta( $product_id_with_stock, '_redmart_sync', true );
		if($redmart_sync == "Yes"){ 
			$product_code = get_post_meta($product_id_with_stock, '_rpc_code',true );
			$stock = get_post_meta( $product_id_with_stock, '_stock', true ) + 0;
			if($product_code!=""){
				$locationID = getLocationID($product_code, $access_token);
				$filename = dirname(__FILE__) ."/redmart_token.txt";
				$handle = fopen($filename, "r");
				$access_token = fread($handle, filesize($filename));
				fclose($handle);
				$stock = getStock($product_code, $locationID, $access_token);
				$quantityAvailableForSale = $stock['quantityAvailableForSale'];
				$new_quantity = "";
				$etag = $stock['etag'];
				echo "Current RedMart Stock ".$quantityAvailableForSale."<br/>";
				echo "New Stock updated in RedMart ".$new_stock."<br/>";
			} 
		}
	}
}

?>
