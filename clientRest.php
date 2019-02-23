<?php
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
?>
