<?php

	function dolibarr_ejecutar($metodo, $url, $data = false){
		$apikey = '[APIKEY]';
		$curl = curl_init();
		$httpheader = ['DOLAPIKEY: '.$apikey];
		$metodo = strtoupper($metodo);
		$url = 'https://[DOMAIN]/api/index.php/'.$url;
		
		if($metodo === 'POST'){
			$httpheader[] = 'Content-Type:application/json';
			
			curl_setopt($curl, CURLOPT_POST, 1);			

            if($data){
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
			}
		}
		else if($metodo === 'PUT'){
			$httpheader[] = 'Content-Type:application/json';
			
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');            

            if($data){
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
			}
		}
		else{
			if($data){
                $url = sprintf("%s?%s", $url, http_build_query($data));
			}
		}   
  
		// Optional Authentication:
		// curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		// curl_setopt($curl, CURLOPT_USERPWD, "username:password");

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);

		$resultado = curl_exec($curl);

		curl_close($curl);

		return $resultado;
	}
	
	function dolibarr_cliente_crear($nombre){
		if($cliente = dolibarr_ejecutar('post', 'thirdparties', array('name' => $nombre, 'client' => '1'))){
			return $cliente;
		}
		else{
			//AUDITORIA
		}
			
		return false;
	}

	function dolibarr_factura_crear($tercero, $vencimiento, $items, $tipo = 'cliente', $referencia = null){
		if($tipo == 'cliente' or $tipo == 'proveedor'){
			if(!empty($tercero) and !empty($vencimiento) and !empty($items) and is_object($items)){
				$procesar = true;
					
				// Estructura de ejemplo
				// $items = (object) array(
				// 	 (object) array('valor' => 'Producto', 'monto' => 20),
				// );
					
				foreach($items as $item){
					if(!isset($item -> valor) or !isset($item -> monto)){
						//AUDITORIA
							
						$procesar = false;
					}
					else{
						if(empty($item -> valor) or empty($item -> monto)){
							//AUDITORIA
								
							$procesar = false;
						}
						else{
							if(!is_numeric($item -> monto)){
								//AUDITORIA
									
								$procesar = false;
							}
						}
					}
				}
					
				if($procesar){
					if($tipo == 'proveedor'){							
						if(!is_null($referencia)){							
							$lineas = array();
								
							foreach($items as $item){
								$lineas[] = array(
									'description' => $item -> valor,
									'pu_ht' => $item -> monto,
									'qty' => '1',
									'product_type' => '1',         //Tipo de linea: Servicio
								);
							}
								
							if(is_array($lineas) and !empty($lineas)){
								if($factura = dolibarr_ejecutar('post', 'supplierinvoices', array(
									'socid' => $tercero,
									'cond_reglement' => 'Por adelantado',
									'cond_reglement_doc' => 'Pago adelantado',
									'cond_reglement_id' => '13',
									'cond_reglement_code' => 'PA',			
									'mode_reglement' => 'Online payment',
									'mode_reglement_id' => '50',
									'mode_reglement_code' => 'VAD',			
									'fk_account' => '2',                                 //Cuenta bancaria ID
									'date_echeance' => strtotime($vencimiento),
									'ref_supplier' => $referencia,
									'lines' => $lineas
								))){									
									if(!dolibarr_ejecutar('post', 'supplierinvoices/'.$factura.'/validate')){
										//AUDITORIA
									}
									else{
										//AUDITORIA
									}
									
									return $factura;
								}
							}
						}							
					}
					else{
						if($factura = dolibarr_ejecutar('post', 'invoices', array(
							'socid' => $tercero,
							'cond_reglement' => 'Por adelantado',
							'cond_reglement_doc' => 'Pago adelantado',
							'cond_reglement_id' => '13',
							'cond_reglement_code' => 'PA',			
							'mode_reglement' => 'Online payment',
							'mode_reglement_id' => '50',
							'mode_reglement_code' => 'VAD',			
							'fk_account' => '2',                                 //Cuenta bancaria ID
							'date_lim_reglement' => strtotime($vencimiento)
						))){			
							foreach($items as $item){
								if(!dolibarr_ejecutar('post', 'invoices/'.$factura.'/lines', array(
									'desc' => $item -> valor,
									'subprice' => $item -> monto,
									'qty' => '1',
									'product_type' => '1',         //Tipo de linea: Servicio
								))){
									//AUDITORIA
								}
							}
							
							if(!dolibarr_ejecutar('post', 'invoices/'.$factura.'/validate')){
								//AUDITORIA
							}
							else{
								//AUDITORIA
							}
							
							return $factura;
						}
					}
				}
			}
		}
		
		return false;
	}

	function dolibarr_factura_asignar_linea($factura, $valor, $monto, $tipo = '1', $cantidad = '1'){
		if(!dolibarr_ejecutar('post', 'invoices/'.$factura.'/lines', array(
			'desc' => $valor,
			'subprice' => $monto,
			'qty' => $cantidad,
			'product_type' => $tipo, //Tipo de linea: Servicio = 1
		))){
			//AUDITORIA
				
			return false;
		}
			
		return true;
	}
	
	function dolibarr_factura_pagar($factura, $monto, $tipo = 'cliente'){		
		if($tipo == 'cliente' or $tipo == 'proveedor'){
			if($tipo == 'proveedor'){
				$sentencia = array(						
					'datepaye' => time(),
					'paiementid' => 50,              //Tipo de pago: Pago en linea
					'closepaidinvoices' => 'yes',
					'accountid' => 2
				);	
					
				if(!dolibarr_ejecutar('post', 'supplierinvoices/'.$factura.'/payments', $sentencia)){
					//AUDITORIA
						
					return false;
				}
			}
			else{
				$sentencia = array(
					'arrayofamounts' => array(
						$factura => $monto
					),
					'datepaye' => time(),
					'paiementid' => 50,              //Tipo de pago: Pago en linea
					'closepaidinvoices' => 'yes',
					'accountid' => 2
				);	
					
				if(!dolibarr_ejecutar('post', 'invoices/paymentsdistributed', $sentencia)){
					//AUDITORIA
					
					return false;
				}
			}
			
			return true;
		}
	}
	
	function dolibarr_factura_reabrir($factura, $tipo = 'cliente'){		
		if($tipo == 'cliente' or $tipo == 'proveedor'){
			if(!dolibarr_ejecutar('post', 'invoices/'.$factura.'/settodraft', array('idwarehouse' => 0))){
				//AUDITORIA
					
				return false;
			}
				
			return true;
		}
	}
	
	function dolibarr_factura_cerrar($factura, $tipo = 'cliente'){		
		if($tipo == 'cliente' or $tipo == 'proveedor'){
			if($invoice = dolibarr_ejecutar('get', 'invoices/'.$factura)){
				$invoice = json_decode($invoice);
				$invoice -> total_ttc = (float) $invoice -> total_ttc;
					
				if($invoice -> total_ttc == 0){
					if(!dolibarr_ejecutar('post', 'invoices/'.$factura.'/settopaid')){
						//AUDITORIA
							
						return false;
					}
				}
				else{
					return false;
				}
			}
				
			return true;
		}
	}
	
	function dolibarr_factura_validar($factura, $tipo = 'cliente'){		
		if($tipo == 'cliente' or $tipo == 'proveedor'){
			if($tipo == 'proveedor'){
				if(!dolibarr_ejecutar('post', 'supplierinvoices/'.$factura.'/validate')){
					//AUDITORIA
						
					return false;
				}
			}
			else{
				if(!dolibarr_ejecutar('post', 'invoices/'.$factura.'/validate')){
					//AUDITORIA
						
					return false;
				}
			}
				
			return true;
		}
	}
	
?>
