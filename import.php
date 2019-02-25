<?php

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;


/*Configuración para la nueva integración de la API REST WooCommerce de  Wordpress:*/
function getWoocommerceConfig(){
    $woocommerce = new Client(
        'https://gigacomputers.com.ec/', 
        'ck_de42de41d657f45dcaded014e5f55bc01fa15a7b', 
        'cs_30338af0cbab351ff84d5de9e1439e197bf844f0',
        [
            'version' => 'wc/v3',
        ]
    );
    return $woocommerce;
}
    
    
try {
    $woocommerce = getWoocommerceConfig();
    /*GENERO EL ARCHIVO DE PRODUCTOS QUE ME BRINDA EL SERVICIO WEB (API REST) DE GIGACOMPUTERS*/
    //get_products_from_API_REST();  
    $json = parse_json('results.json');
	$data = get_products_from_json($json);
	$product_data = merge_products_and_variations($data['products']);
	
    echo('<p>IMPORTACION DE PROUCTOS CON WEB SERVICE DE GIGA (SPRING BOOT).<br><br></p>');
    
    
    /*IMPORTAR PRODUCTOS DEL FICHERO*/
	foreach ( $product_data as $k => $product ) :
        
        /*SE VERIFICA QUE EL PRODUCTO NO EXISTA EN LA BD DE LA PAGINA USANDO EL SKU (IDENTIFICADOR UNICO -> EN GIGA: CODIGO BARRAS)*/
        $productExist = checkProductBySku($product['sku']);
        if (!$productExist['exist']) {
            /*SI EL PRODUCTO NI EXISTE EN LA PAGINA, SE AGREGA CON EL METODO POST*/
            $wc_product = $woocommerce->post( 'products', $product );
            if ( $wc_product ) :
                /*SI SE AGREGA CORRECTAMENTE SE ENVIA UN MENSAJE DE OCNFIRMACION*/
                echo('<p>PRODUCTO AGREGADO. ID: <b>'. $wc_product->id . '</b> SKU. : <b>'. $wc_product->sku .'</b></p>');
            endif;
        }else{
            /*EN CASO DE QUE EL PRODUCTO CON SKU EXISTA, NO SE AGREGA A LA PAGINA*/
            echo('<p>PRODUCTO CON SKU (CODIGO DE BARRAS) : <b>'. $product['sku'] . '</b> YA EXISTE EN LA BD DE LA PAGINA!!</p>');
        }
    
	endforeach;
    /*ELIMINO EL ARCHIVO GENERADO PARA LA PROXIMA OCASION*/
    //unlink('results.json');
	
} catch ( HttpClientException $e ) {
    echo $e->getMessage(); // MENSAJE DE ERROR DE LA API REST WOOCOMMERCE
}

/*Convertir array de productos en array necesario para API REST Woocommerce*/
function merge_products_and_variations( $product_data = array()) {
	foreach ( $product_data as $k => $product ) :
		/*NO NECESITAMOS EL ID, WOOCOMMERCE LO ASIGNA AUTOMATICAMENTE*/
		unset($product_data[$k]['_product_id']);
	endforeach;

	return $product_data;
}

/*Obtener productos de JSON y prepararlos para importar según las propiedades de la API de WooCommerce*/
function get_products_from_json( $json ) {

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
    
			/*PARA EL STOCK*/
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

	return $data;
}

/*Convertir la cadena JSON a la matriz de PHP*/
function parse_json( $file ) {
	$json = json_decode( file_get_contents( $file ), true );
	if ( is_array( $json ) && !empty( $json ) ) :
		return $json;	
	else :
		die( 'An error occurred while parsing ' . $file . ' file.' );

	endif;
}

/*Usar la API REST de Woocommerce para verificar si un producto existe en la pagina*/
function checkProductBySku($skuCode){
    $woocommerce = getWoocommerceConfig();
    $products = $woocommerce->get('products');
    foreach ($products as $product) {
        $currentSku = strtolower($product->sku);
        $skuCode = strtolower($skuCode);
        if ($currentSku === $skuCode) {
            return ['exist' => true, 'idProduct' => $product->id];
        }
    }
    return ['exist' => false, 'idProduct' => null];
}

/*COnsumir el servicio web de Gigacomputers para obtener los productos de la base de datos*/
function get_products_from_API_REST() {
    //$url="http://localhost:8086/RestaurantServer/api/productos/getAll";
    /*URL DEL SERVICIO WEB DE SPRING BOOT (EL SERVICIO DEBE ESTAR DISPONIBLE)*/
    $url="http://localhost:8086/RestaurantServer/api/productos/getByCategoria?idCategoria=5";
    $client=curl_init($url);
    curl_setopt($client,CURLOPT_RETURNTRANSFER,1);
    $response=curl_exec($client);
    /*SE CONVIERTE EL RESULTADO DE LA API REST DE SPRING EN FORMATO JSON PARA PHP*/
    $json = json_decode($response,true);
    /*SE ALMACENA EN UN ARCHIVO .JSON*/
    $fp = fopen('results.json', 'w');
    fwrite($fp, json_encode($json));
    fclose($fp);
}
