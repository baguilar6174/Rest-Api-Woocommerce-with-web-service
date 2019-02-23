<?php

define( 'FILE_TO_IMPORT', 'products.json' );

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

if ( ! file_exists( FILE_TO_IMPORT ) ) :
	die( 'Unable to find ' . FILE_TO_IMPORT );
endif;	

$woocommerce = new Client(
    'https://gigacomputers.com.ec/', 
    'ck_de42de41d657f45dcaded014e5f55bc01fa15a7b', 
    'cs_30338af0cbab351ff84d5de9e1439e197bf844f0',
    [
        'version' => 'wc/v3',
    ]
);

try {

    get_products_from_API_REST();  
    $json = parse_json('results.json');
	$data = get_products_and_variations_from_json($json);
	// Merge products and product variations so that we can loop through products, then its variations
	$product_data = merge_products_and_variations( $data['products'], $data['product_variations'] );

	// Import: Products
	foreach ( $product_data as $k => $product ) :

		if ( isset( $product['variations'] ) ) :
			$_product_variations = $product['variations']; // temporary store variations array

			// Unset and make the $product data correct for importing the product.
			unset($product['variations']);
		endif;		

			$wc_product = $woocommerce->post( 'products', $product );

			if ( $wc_product ) :
				status_message( 'Product added. ID: '. $wc_product->id);
			endif;

		if ( isset( $_product_variations ) ) :
			// Import: Product variations

			// Loop through our temporary stored product variations array and add them
			foreach ( $_product_variations as $variation ) :
				$wc_variation = $woocommerce->post( 'products/'. $wc_product->id .'/variations', $variation );

				if ( $wc_variation ) :
					status_message( 'Product variation added. ID: '. $wc_variation->id . ' for product ID: ' . $wc_product->id );
				endif;	
			endforeach;	

			// Don't need it anymore
			unset($_product_variations);
		endif;

	endforeach;
	

} catch ( HttpClientException $e ) {
    echo $e->getMessage(); // Error message
}

/**
 * Merge products and variations together. 
 * Used to loop through products, then loop through product variations.
 *
 * @param  array $product_data
 * @param  array $product_variations_data
 * @return array
*/
function merge_products_and_variations( $product_data = array(), $product_variations_data = array() ) {
	foreach ( $product_data as $k => $product ) :
		foreach ( $product_variations_data as $k2 => $product_variation ) :
			if ( $product_variation['_parent_product_id'] == $product['_product_id'] ) :

				// Unset merge key. Don't need it anymore
				unset($product_variation['_parent_product_id']);

				$product_data[$k]['variations'][] = $product_variation;

			endif;
		endforeach;

		// Unset merge key. Don't need it anymore
		unset($product_data[$k]['_product_id']);
	endforeach;

	return $product_data;
}

/**
 * Get products from JSON and make them ready to import according WooCommerce API properties. 
 *
 * @param  array $json
 * @param  array $added_attributes
 * @return array
*/
function get_products_and_variations_from_json( $json ) {

	$product = array();
	$product_variations = array();

	foreach ( $json as $key => $pre_product ) :

		if ( $pre_product['type'] == 'simple' ) :
			
            $product[$key]['_product_id'] = (string) $pre_product['product_id'];
			$product[$key]['name'] = (string) $pre_product['name'];
			$product[$key]['description'] = (string) $pre_product['description'];
			$product[$key]['regular_price'] = (string) $pre_product['regular_price'];
            $product[$key]['sku'] = (string) $pre_product['sku'];
            
            if ( $pre_product['image'] ) {
                $product[$key]['images'][] = array('src' => (string) $pre_product['image'],'position' => 0);
            }
            if ( $pre_product['idcategory'] ) {
                $product[$key]['categories'][] = array('id' => (int) $pre_product['idcategory'],'position' => 0);
            }
    
    
			// Stock
			if ( $pre_product['stock'] > 0 ) :
				$product[$key]['in_stock'] = (bool) true;
				$product[$key]['stock_quantity'] = (int) $pre_product['stock'];    
            else :
				$product[$key]['in_stock'] = (bool) false;
				$product[$key]['stock_quantity'] = (int) 0;
			endif;	

        endif;		
	endforeach;		

	$data['products'] = $product;
	$data['product_variations'] = $product_variations;

	return $data;
}	

/**
 * Get attributes and terms from JSON.
 * Used to import product attributes.
 *
 * @param  array $json
 * @return array
*/
function get_attributes_from_json( $json ) {
	$product_attributes = array();

	foreach( $json as $key => $pre_product ) :
		if ( !empty( $pre_product['attribute_name'] ) && !empty( $pre_product['attribute_value'] ) ) :
			$product_attributes[$pre_product['attribute_name']]['terms'][] = $pre_product['attribute_value'];
		endif;
	endforeach;		

	return $product_attributes;

}

/**
 * Parse JSON file.
 *
 * @param  string $file
 * @return array
*/
function parse_json( $file ) {
	$json = json_decode( file_get_contents( $file ), true );

	if ( is_array( $json ) && !empty( $json ) ) :
		return $json;	
	else :
		die( 'An error occurred while parsing ' . $file . ' file.' );

	endif;
}

/**
 * Print status message.
 *
 * @param  string $message
 * @return string
*/
function status_message( $message ) {
	echo $message . "\r\n";
}

function get_products_from_API_REST() {
    //$url="http://localhost:8086/RestaurantServer/api/productos/getAll";
    $url="http://localhost:8086/RestaurantServer/api/productos/getByCategoria?idCategoria=5";
    $client=curl_init($url);
    curl_setopt($client,CURLOPT_RETURNTRANSFER,1);
    $response=curl_exec($client);
    //echo $response;
    $json = json_decode($response,true);
    $fp = fopen('results.json', 'w');
    fwrite($fp, json_encode($json));
    fclose($fp);
}
