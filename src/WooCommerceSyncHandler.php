<?php

/**
 * Class WooCommerceSyncHandler
 *
 */
class WooCommerceSyncHandler {

	public $product;

	/**
	 * WooCommerceHandler constructor.
	 *
	 * @param int $product_id [optional]
	 * @param string $sku [optional]
	 */
	public function __construct( int $product_id = 0, string $sku = '' ) {
		if ( $product_id > 0 ) {
			$this->product = new WC_Product( $product_id );
		} else if ( $sku != '' ) {
			$product_id    = wc_get_product_id_by_sku( $sku );
			$this->product = new WC_Product( $product_id );
		} else {
			$this->product = new WC_Product();
		}
	}

	/**
	 * Update the product price
	 * Give the sale price to provide also a sale price to product
	 * Instead of sale price, you can provide discount in percent 0-100
	 * If sale price is provided and greated than 0, discount will be ignored
	 *
	 * @param float $regular_price
	 * @param float $sale_price
	 * @param float $discount
	 *
	 */
	public function set_product_prices( float $regular_price, float $sale_price = 0.0, float $discount = 0.0 ) {

		$this->product->set_regular_price( floatval( $regular_price ) );
		if ( isset( $sale_price ) && $sale_price > 0 && $sale_price < $regular_price ) {
			$this->product->set_sale_price( floatval( $sale_price ) );
		}
		if ( isset( $discount ) && $discount > 0 && $discount < 100 && $sale_price == 0 ) {
			$this->product->set_sale_price( floatval( $regular_price * ( ( 100 - $discount ) / 100 ) ) );
		}

	}

	/**
	 * This function can manage properly the stock of a product.
	 * If you provide manage stock as yes, you can provide also stock quantity
	 *
	 * @param string $manage_stock no|yes
	 * @param string $stock_status [optional] Available values instock|outofstock|onbackorder
	 * @param int $stock_quantity [optional]
	 * @param string $backorders [optional] Available values yes|no|notify
	 * @param int $low_stock_amount [optional]
	 */
	public function set_stock( string $manage_stock, string $stock_status = '', int $stock_quantity = 0, string $backorders = '', int $low_stock_amount = 0 ) {
		$this->product->set_manage_stock( $manage_stock );
		if ( $manage_stock == 'no' && $stock_status != '' ) {
			$this->product->set_stock_status( $stock_status );
		} else {
			if ( $stock_quantity > 0 ) {
				$this->product->set_stock_quantity( $stock_quantity );
			}
			if ( $backorders != '' ) {
				$this->product->set_backorders( $backorders );
			}
			if ( $low_stock_amount > 0 ) {
				$this->product->set_low_stock_amount( $low_stock_amount );
			}
		}
	}

	/**
	 * Prepare product attributes array to be saved with WooCommerce function set_attributes.
	 * The value element of array or object can be a single value or single column array of values
	 *
	 * @param stdClass|array $attributes <p>You must provide an appropriate array of attributes.
	 * Each item must have values for these elements: name & value.
	 * Optional, you can set values for Visible, Variation and provide the slug of attribute</p>
	 *
	 * @return array
	 */
	public function prepare_attributes_array( $attributes ) {
		$attributes_array = [];

		$counter = 1;
		foreach ( $attributes as $attribute ) {
			if ( is_object( $attribute ) ) {
				$attribute_name      = $attribute->name;
				$attribute_values    = $attribute->value;
				$attribute_visible   = $attribute->visible;
				$attribute_variation = $attribute->variation;
				$attribute_slug      = $attribute->slug;
			} else {
				$attribute_name      = $attribute['name'];
				$attribute_values    = $attribute['value'];
				$attribute_visible   = $attribute['visible'];
				$attribute_variation = $attribute['variation'];
				$attribute_slug      = $attribute['slug'];
			}
			if ( ! $attribute_visible ) {
				$attribute_visible = 0;
			}
			if ( ! $attribute_variation ) {
				$attribute_variation = 0;
			}
			if ( $attribute_name && $attribute_values ) {
				if ( isset( $attribute_slug ) && $attribute_slug != '' ) {
					$slug = $attribute_slug;
				} else {
					$slug = 'pa_' . sanitize_title( $attribute_name );
				}
				$attribute_id = wc_attribute_taxonomy_id_by_name( $slug );
				$wc_attribute = new WC_Product_Attribute();
				if ( $attribute_id ) {
					$wc_attribute->set_id( $attribute_id );
					$wc_attribute->set_name( $slug );
				} else {
					$wc_attribute->set_name( $attribute_name );
				}
				if ( is_array( $attribute_values ) ) {
					$wc_attribute->set_options( $attribute_values );
				} else {
					$wc_attribute->set_options( [ $attribute_values ] );
				}
				$wc_attribute->set_position( $counter );
				$wc_attribute->set_visible( $attribute_visible );
				$wc_attribute->set_variation( $attribute_variation );
				$attributes_array[] = $wc_attribute;
				$counter ++;
			}

		}

		return $attributes_array;
	}

	/**
	 * Trying to match category by name and return its term ID.
	 * If parent (or grandparent) category name provided, it searches also for this matching.
	 * This is used if you have multiple identical names in your categories
	 * Return the term ID if found, else return false
	 *
	 * @param string $category_name
	 * @param string $parent_category_name [optional]
	 * @param string $grand_parent_category_name [optional]
	 *
	 * @return int|false term ID on success, or false on failure.
	 */
	public function match_category_by_name( string $category_name, $parent_category_name = '', $grand_parent_category_name = '' ) {
		$matched_categories = get_terms( [
			'taxonomy'   => 'product_cat',
			'status'     => 'publish',
			'hide_empty' => false,
			'name'       => $category_name
		] );

		foreach ( $matched_categories as $matched_category ) {
			if ( $parent_category_name != '' && $matched_category->parent > 0 ) {
				$parent_category = get_term_by( 'ID', $matched_category->parent, 'product_cat' );
				if ( $parent_category && $parent_category->name == $parent_category_name ) {
					if ( $grand_parent_category_name != '' ) {
						$grand_parent_category = get_term_by( 'ID', $parent_category->parent, 'product_cat' );
						if ( $grand_parent_category->name == $grand_parent_category_name ) {
							return $matched_category->term_id;
						}
					} else {
						return $matched_category->term_id;
					}
				}
			} else {
				return $matched_category->term_id;
			}

		}

		return false;
	}

	/**
	 * Upload the file and attach it to the product ID provided
	 * If this file is image and featured is filled as true, it set it as featured image
	 * You can provide File Title
	 * You can provide Alternative Text (only for images)
	 *
	 * @param int $product_id The ID of product to attach this media
	 * @param string $file The full file URL to get contents
	 * @param string $file_name The final file name e.g. photo.jpg
	 * @param string $folder The folder to save the image
	 *
	 * @param string $file_title [optional] The file title to save it with this title name
	 * @param string $file_alt_text [optional] If you want to save alt text too for your photo
	 * @param bool $featured_image [optional] If this is a featured image
	 *
	 * @return int|bool|WP_Error Attachment ID on success, false or WP_Error on failure
	 */
	public function addFileToProduct( int $product_id, string $file, string $file_name, string $folder, string $file_title = '', string $file_alt_text = '', bool $featured_image = false ) {

		$file_data = file_get_contents( $file ); // Get image data

		$final_filename = $folder . $file_name; // Define file location

		if ( ! $file_data ) {
			return false;
		}

		// Create the image  file on the server
		if ( ! copy( $file, $final_filename ) ) {
			return false;
		}

		$file_type = mime_content_type( $file );
		$alt_text  = '';

		if ( $file_title == '' ) {
			$post_title = sanitize_file_name( $file_name );
		} else {
			$post_title = sanitize_file_name( $file_title );
			$alt_text   = $file_title;
		}

		// Set attachment data
		$attachment = array(
			'post_mime_type' => $file_type,
			'post_title'     => $post_title,
			'post_content'   => '',
			'post_status'    => 'inherit'
		);
		$attach_id  = wp_insert_attachment( $attachment, $final_filename, $product_id );

		// Include image.php
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// Define attachment metadata
		$attach_data = wp_generate_attachment_metadata( $attach_id, $final_filename );

		// Assign metadata to attachment
		wp_update_attachment_metadata( $attach_id, $attach_data );

		if ( strpos( $file_type, 'image' ) !== false ) {
			if ( $file_alt_text != '' ) {
				$alt_text = $file_alt_text;
			}
			if ( $alt_text != '' ) {
				$this->addAltTextToImage( $attach_id, $alt_text );
			}

			if ( $featured_image ) {
				$this->addFeaturedImageToProduct( $product_id, $attach_id );
			}
		}

		return $attach_id;
	}

	/**
	 * Update the alt text of image with the given one
	 *
	 * @param int $photo_id The photo ID
	 * @param string $alt_text The given alt text
	 */
	private function addAltTextToImage( int $photo_id, string $alt_text ) {
		if ( $alt_text != '' ) {
			update_post_meta( $photo_id, '_wp_attachment_image_alt', $alt_text );
		}
	}

	/**
	 * Add featured image to product when provide by their IDs
	 *
	 * @param int $product_id The product ID
	 * @param int $photo_id The attachment ID
	 */
	private function addFeaturedImageToProduct( int $product_id, int $photo_id ) {
		set_post_thumbnail( $product_id, $photo_id );
	}

	/**
	 * Check if file already exists in wordpress database to get this and not create a new one
	 * Return the media id if found, else return false
	 *
	 * @param string $filename
	 *
	 * @return int|false The attchment ID on success, false on failure
	 */
	public function does_file_exists( string $filename ) {
		global $wpdb;
		$media = $wpdb->get_row( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE '%/$filename'" );
		if ( $media && is_file( $this->get_wordpress_uploads_directory_path() . $media->meta_value ) ) {
			return $media->post_id;
		}

		return false;
	}

	/**
	 * Get the absolute path to the WordPress uploads directory,
	 * with a trailing slash.
	 *
	 * @return string The uploads directory path.
	 */
	private function get_wordpress_uploads_directory_path() {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] );
	}

}