<?php
/**
 * Scribendi Wordpress Plugin - order_functions
 * 
 * The various order_functions used by the Scribendi plugin
 */


/**
 * Handles generating a quote, called via ajax
 *
 * @return void
 */
function scribendi_quote() {
	$wordCount = wp_filter_nohtml_kses($_POST['wordcount']);
	if ( !is_numeric($wordCount) || $wordCount < 1 ) {
		echo '<p>'.__('Not enough words to get a quote.', 'scribendi').'</p>';
		exit;
	}
	
	try {
		$oClient = scribendi_prepare_client();
		$oClient->query[Scribendi_Api_Constants::FIELD_WORD_COUNT] = $wordCount;
		$oClient->query[Scribendi_Api_Constants::FIELD_CATEGORY_ID] = SCRIBENDI_SERVICE;
		
		$oResponse = $oClient->useQuoteAdaptor()->getQuotes()->go();
		scribendi_display_quotes($oResponse);
	} catch ( Exception $e ) {
		echo '<p>'.$e->getMessage().'</p>';
	}
	exit;
}

/**
 * Handles generating an order via the API - does not commit
 *
 * @return void
 */
function scribendi_order() {
	$postId = wp_filter_nohtml_kses($_POST['postId']);
	$wordCount = wp_filter_nohtml_kses($_POST['wordcount']);
	$serviceId = wp_filter_nohtml_kses($_POST['serviceId']);
	
	if ( !is_numeric($postId) || $postId < 1 ) {
		echo '<p>'.__('Missing or invalid post ID.', 'scribendi').'</p>';
		exit;
	}
	if ( !is_numeric($wordCount) || $wordCount < 1 ) {
		echo '<p>'.__('Not enough words to create an order.', 'scribendi').'</p>';
		exit;
	}
	if ( !is_numeric($serviceId) || $serviceId < 1 ) {
		echo '<p>'.__('Missing a service to order.', 'scribendi').'</p>';
		exit;
	}
	
	$oPost = get_post($postId);
	
	
	/*
	 * Check word-count of stored post content vs. submitted word-count
	 * and use stored post count over submitted because the submitted
	 * is fetched via a jQuery call and might not be accurate.
	 */
	$phpCalWordCount = scribendi_get_word_count($oPost->post_content);
	if ( $phpCalWordCount !== false && $phpCalWordCount !== 0 && $phpCalWordCount != $wordCount ) {
		$wordCount = $phpCalWordCount;
	}
	
	$title = $oPost->post_title;
	if ( strlen($title) < 1 ) {
		$title = 'Order Request';
	}
	
	try {
		$oClient = scribendi_prepare_client();
		$oClient->query[Scribendi_Api_Constants::FIELD_WORD_COUNT] = $wordCount;
		$oClient->query[Scribendi_Api_Constants::FIELD_SERVICE_ID] = $serviceId;
		$oClient->query[Scribendi_Api_Constants::FIELD_ORDER_DESCRIPTION] = 'Wordpress: '.$title;
		
		/**
		 * @var Scribendi_Api_Client_Response_Order $oResponse
		 */
		$oResponse = $oClient->useOrderAdaptor()->createOrder()->go();
		$oOrder = $oResponse->getOrder();
		
		if ( $oOrder->getOrderId() > 0 ) {
			/*
			 * Check for existing order, and if there, move into previous orders
			 */
			$previousOrders = unserialize(get_post_meta($oPost->ID, SCRIBENDI_OPTION_PREVIOUS_ORDERS, true));
			if ( !is_array($previousOrders) ) {
				$previousOrders = array();
			}
			$previousOrderId = get_post_meta($oPost->ID, SCRIBENDI_OPTION_ORDER_ID, true);
			$previousOrderRev = get_post_meta($oPost->ID, SCRIBENDI_OPTION_ORDER_REVISION, true);
			if ( $previousOrderId && $previousOrderRev && $previousOrderId > 0 ) {
				$previousOrders[$previousOrderRev] = $previousOrderId;
			}
			
			/*
			 * Store basic details, remove download reference
			 */
			delete_post_meta($oPost->ID, SCRIBENDI_OPTION_ORDER_DOWNLOADED);
			update_post_meta($oPost->ID, SCRIBENDI_OPTION_ORDER_ID, $oOrder->getOrderId());
			update_post_meta($oPost->ID, SCRIBENDI_OPTION_ORDER_REVISION, wp_save_post_revision($oPost->ID));
			update_post_meta($oPost->ID, SCRIBENDI_OPTION_PREVIOUS_ORDERS, serialize($previousOrders));
			scribendi_store_order_object($oPost->ID, $oOrder);
			
			/*
			 * Create file attachment for sending by dumping post content
			 * into temporary file and then binding it to the client API.
			 */
			$tmpFileLoc = sys_get_temp_dir().'/wp_scribendi_'.$oOrder->getOrderId().'.html.txt';
			
			$content = $oPost->post_content;
			$content = wptexturize($content);
			$content = wpautop($content);
			$content = shortcode_unautop($content);
			
			file_put_contents($tmpFileLoc, $content, LOCK_EX);
			
			$oResponse = $oClient->useOrderAdaptor()->addFileToOrder($oOrder->getOrderId(), $tmpFileLoc)->go();
			$oOrder = $oResponse->getOrder();
			unlink($tmpFileLoc);
			
			$getPayment = $getPaymentCredit = false;
			try {
				$oResponse = $oClient->useOrderAdaptor()->commitOrder($oOrder->getOrderId())->go();
				$oOrder = $oResponse->getOrder();
			} catch ( Scribendi_Api_Client_Response_Exception $e ) {
				switch ( $e->getCode() ) {
					case 300: $getPayment = true; break;
					case 301: $getPaymentCredit = true; break;
				}
			}
			
			scribendi_store_order_object($oPost->ID, $oOrder);
			scribendi_display_order($oOrder);
			if ( $getPayment ) {
				scribendi_trigger_payment($oOrder);
			}
			if ( $getPaymentCredit ) {
				scribendi_trigger_payment_credit($oOrder);
			}
		} else {
			throw new LogicException(__('API call succeeded, but an order failed to be created', 'scribendi'));
		}
	} catch ( Exception $e ) {
		echo '<p>'.$e->getMessage().'</p>';
	}
	exit;
}

/**
 * Cancels an order or quote
 *
 * @return void
 */
function scribendi_order_cancel() {
	$postId = wp_filter_nohtml_kses($_POST['postId']);
	
	if ( !is_numeric($postId) || $postId < 1 ) {
		echo '<p>'.__('Missing or invalid post ID.', 'scribendi').'</p>';
		exit;
	}
	
	$oPost = get_post($postId);
	$oOrder = scribendi_get_order_from_post($oPost->ID);
	if ( $oOrder->getOrderId() > 0 ) {
		try {
			$oClient = scribendi_prepare_client();
			$oResult = $oClient->useOrderAdaptor()->cancelOrder($oOrder->getOrderId())->go();
			
			$oOrder->setStatus(Scribendi_Api_Model_Order::STATUS_CANCELLED);
			$oOrder->setStatusText('Cancelled');
			
			/*
			 * Replace order information
			 */
			scribendi_store_order_object($oPost->ID, $oOrder);
			
			/*
			 * Send results
			 */
			scribendi_display_order($oOrder);
			
		} catch ( Exception $e ) {
			echo '<p>'.__('There was an error cancelling your order:', 'scribendi').'<br />'.$e->getMessage().'</p>';
		}
	} else {
		echo '<p>'.__('There is no order to cancel on this post.', 'scribendi').'</p>';
	}
	exit;
}



/**
 * Polls the API server for order updates
 *
 * To avoid excessive calls, only query for posts that have
 * an order ID attached, and then at every opportunity avoid
 * an API call.
 * 
 * @return void
 */
function scribendi_order_status_update() {
	global $wpdb;
	$lastRun = get_option(SCRIBENDI_API_STATUS_DATE, false);
	$interval = 60 * get_option(SCRIBENDI_API_STATUS_INTERVAL, 15);
	
	try {
		if ( !$lastRun || (time()-$lastRun) > $interval ) {
			$query = '
				SELECT '.$wpdb->postmeta.'.post_id, '.$wpdb->posts.'.post_title, '.$wpdb->posts.'.post_excerpt, '.$wpdb->posts.'.post_author
				  FROM '.$wpdb->postmeta.' INNER JOIN '.$wpdb->posts.' ON ('.$wpdb->postmeta.'.post_id = '.$wpdb->posts.'.ID)
				 WHERE '.$wpdb->postmeta.'.meta_key = "'.SCRIBENDI_OPTION_ORDER_ID.'"
				   AND '.$wpdb->postmeta.'.meta_value != ""';
			
			$res = $wpdb->get_results($query, 'ARRAY_A');
			if ( count($res) > 0 ) {
				$oClient = scribendi_prepare_client();
				
				foreach ( $res as $row ) {
					$downloaded = get_post_meta($row['post_id'], SCRIBENDI_OPTION_ORDER_DOWNLOADED, true);
					
					if ( $downloaded ) {
						// post downloaded, quit
						continue;
					}
					
					$oOrder = scribendi_get_order_from_post($row['post_id']);
					if ( $oOrder->getStatus() == Scribendi_Api_Model_Order::STATUS_CANCELLED ) {
						// ignore cancelled orders
						continue;
					}
					try {
						/**
						 * @var Scribendi_Api_Client_Response_Order $oResponse
						 */
						$oResponse = $oClient->useOrderAdaptor()->getOrderStatus($oOrder->getOrderId())->go();

						/*
						 * Replace existing order object
						 */
						$oOrder = $oResponse->getOrder();
						
						if ( $oOrder->getOrderId() ) {
							scribendi_store_order_object($row['post_id'], $oOrder);
							
							if ( $oOrder->getStatus() == Scribendi_Api_Model_Order::STATUS_DONE ) {
								$files = $oOrder->getFileSet()->getFileByType(Scribendi_Api_Model_File::FILE_TYPE_PROCESSED);
								if ( count($files) == 1 ) {
									/**
									 * @var Scribendi_Api_Model_File $oFile
									 */
									$oFile = $files[0];

									/*
									 * Only process HTML and text files (.html.txt)
									 */
									if ( stripos($oFile->getFileName(), 'html') !== false ) {
										$fileContents = '';
										$fhndl = @fopen($oFile->getDownloadLocation(), 'rb');
										if ( $fhndl ) {
											while ( !feof($fhndl) ) {
												$fileContents .= fread($fhndl, 8192);
											}
										}
										fclose($fhndl);

										if ( strlen($fileContents) > 0 ) {
											$post = _wp_post_revision_fields(
												array(
													'ID' => $row['post_id'],
													'post_content' => $fileContents,
													'post_title' => $row['post_title'],
													'post_excerpt' => $row['post_excerpt'],
													'post_author' => $row['post_author'],
												)
											);

											$res = wp_insert_post($post);
											if ( is_numeric($res) && $res > 0 ) {
												update_post_meta($row['post_id'], SCRIBENDI_OPTION_ORDER_DOWNLOADED, date('YmdHis'));
												update_post_meta($row['post_id'], SCRIBENDI_OPTION_SCRIBENDI_REVISION, $res);
											}

											if ( get_option(SCRIBENDI_ALWAYS_POST_EDITS, 0) ) {
												$revision = wp_get_post_revision($res);
												if ( $revision ) {
													wp_restore_post_revision($revision->ID);
												}
											}
										}
									}
								}
							}
						}
					} catch ( Exception $e ) {
					}
				}
			}
		}
	} catch ( Exception $e ) {
		// do nothing
	}
	update_option(SCRIBENDI_API_STATUS_DATE, time());
}