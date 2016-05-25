<?php
/**
 * Batch Downloads Import Class
 *
 * This class handles importing download products
 *
 * @package     EDD
 * @subpackage  Admin/Import
 * @copyright   Copyright (c) 2015, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.6
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_Batch_Downloads_Import Class
 *
 * @since 2.6
 */
class EDD_Batch_Downloads_Import extends EDD_Batch_Import {


	public function init() {

		// Set up default field map values
		$this->field_mapping = array(
			'post_title'     => '',
			'post_name'      => '',
			'post_status'    => 'draft',
			'post_author'    => '',
			'post_date'      => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'price'          => '',
			'files'          => '',
			'categories'     => '',
			'tags'           => '',
			'sku'            => '',
			'earnings'       => '',
			'sales'          => '',
			'featured_image' => '',
			'download_limit' => '',
			'notes'          => ''
		);
	}

	/**
	 * Process a step
	 *
	 * @since 2.6
	 * @return bool
	 */
	public function process_step() {

		$more = false;

		if ( ! $this->can_import() ) {
			wp_die( __( 'You do not have permission to import data.', 'easy-digital-downloads' ), __( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
		}

		$i      = 1;
		$offset = $this->step > 1 ? ( $this->per_step * ( $this->step - 1 ) ) : 0;

		if( $offset > $this->total ) {
			$this->done = true;
		}

		if( ! $this->done && $this->csv->data ) {

			$more = true;

			foreach( $this->csv->data as $key => $row ) {

				// Skip all rows until we reach pass our offset
				if( $key + 1 < $offset ) {
					continue;
				}

				// Done with this batch
				if( $i >= $this->per_step ) {
					break;
				}

				// Import Download
				$args = array(
					'post_type'    => 'download',
					'post_title'   => '',
					'post_name'    => '',
					'post_status'  => '',
					'post_author'  => '',
					'post_date'    => '',
					'post_content' => '',
					'post_excerpt' => ''
				);

				foreach( $args as $key => $field ) {
					if( ! empty( $this->field_mapping[ $key ] ) && ! empty( $row[ $this->field_mapping[ $key ] ] ) ) {
						$args[ $key ] = $row[ $this->field_mapping[ $key ] ];
					}
				}

				$download_id = wp_insert_post( $args );

				// setup categories
				if( ! empty( $this->field_mapping['categories'] ) && ! empty( $row[ $this->field_mapping['categories'] ] ) ) {

					$categories = $this->str_to_array( $row[ $this->field_mapping['categories'] ] );

					$this->set_taxonomy_terms( $download_id, $categories, 'download_category' );

				}

				// setup tags
				if( ! empty( $this->field_mapping['tags'] ) && ! empty( $row[ $this->field_mapping['tags'] ] ) ) {

					$tags = $this->str_to_array( $row[ $this->field_mapping['tags'] ] );

					$this->set_taxonomy_terms( $download_id, $tags, 'download_tag' );

				}

				// setup price(s)
				if( ! empty( $this->field_mapping['price'] ) && ! empty( $row[ $this->field_mapping['price'] ] ) ) {

					$price = $row[ $this->field_mapping['price'] ];

					$this->set_price( $download_id, $price );

				}

				// setup files
				if( ! empty( $this->field_mapping['files'] ) && ! empty( $row[ $this->field_mapping['files'] ] ) ) {

					$files = $this->str_to_array( $row[ $this->field_mapping['files'] ] );

					$this->set_files( $download_id, $files );

				}

				// Product Image
				if( ! empty( $this->field_mapping['featured_image'] ) && ! empty( $row[ $this->field_mapping['featured_image'] ] ) ) {

					$image = sanitize_text_field( $row[ $this->field_mapping['featured_image'] ] );

					$this->set_image( $download_id, $image, $args['post_author'] );

				}

				// File download limit
				if( ! empty( $this->field_mapping['download_limit'] ) && ! empty( $row[ $this->field_mapping['download_limit'] ] ) ) {

					update_post_meta( $download_id, '_edd_download_limit', absint( $row[ $this->field_mapping['download_limit'] ] ) );
				}

				// Sale count
				if( ! empty( $this->field_mapping['sales'] ) && ! empty( $row[ $this->field_mapping['sales'] ] ) ) {

					update_post_meta( $download_id, '_edd_download_sales', absint( $row[ $this->field_mapping['sales'] ] ) );
				}

				// Earnings
				if( ! empty( $this->field_mapping['earnings'] ) && ! empty( $row[ $this->field_mapping['earnings'] ] ) ) {

					update_post_meta( $download_id, '_edd_download_earnings', edd_sanitize_amount( $row[ $this->field_mapping['earnings'] ] ) );
				}

				// Notes
				if( ! empty( $this->field_mapping['notes'] ) && ! empty( $row[ $this->field_mapping['notes'] ] ) ) {

					update_post_meta( $download_id, 'edd_product_notes', sanitize_text_field( $row[ $this->field_mapping['notes'] ] ) );
				}

				// SKU
				if( ! empty( $this->field_mapping[ 'sku' ] ) && ! empty( $row[ $this->field_mapping[ 'sku' ] ] ) ) {

					update_post_meta( $download_id, 'edd_sku', sanitize_text_field( $row[ $this->field_mapping['sku'] ] ) );
				}

				// Custom fields


				$i++;
			}

		}

		return $more;
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since 2.6
	 * @return int
	 */
	public function get_percentage_complete() {

		if( $this->total > 0 ) {
			$percentage = ( $this->step / $this->total ) * 100;
		}

		if( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	private function str_to_array( $str = '' ) {

		// Look for standard delimiters
		if( false !== strpos( $str, '|' ) ) {

			$delimiter = '|';

		} elseif( false !== strpos( $str, ',' ) ) {

			$delimiter = ',';

		} elseif( false !== strpos( $str, ';' ) ) {

			$delimiter = ';';

		}

		if( ! empty( $delimiter ) ) {

			$array = (array) explode( $delimiter, $str );

			return array_map( 'trim', $array );

		}

		return array();

	}

	private function set_price( $download_id = 0, $price = '' ) {

		if( is_numeric( $price ) ) {

			update_post_meta( $download_id, 'edd_price', edd_sanitize_amount( $price ) );

		} else {

			$prices = $this->str_to_array( $price );

			if( ! empty( $prices ) ) {

				$variable_prices = array();
				foreach( $prices as $price ) {

					// See if this matches the EDD Download export for variable prices
					if( false !== strpos( $price, ':' ) ) {

						$price = array_map( 'trim', explode( ':', $price ) );

						$variable_prices[] = array( 'name' => $price[0], 'amount' => $price[1] );

					}

				}

				update_post_meta( $download_id, '_variable_pricing', 1 );
				update_post_meta( $download_id, 'edd_variable_prices', $variable_prices );

			}

		}

	}

	private function set_files( $download_id = 0, $files = array() ) {

		if( ! empty( $files ) ) {

			$download_files = array();
			foreach( $files as $file ) {

				$download_files[] = array( 'file' => $file, 'name' => basename( $file ) );

			}

			update_post_meta( $download_id, 'edd_download_files', $download_files );

		}

	}

	private function set_image( $download_id = 0, $image = '', $post_author = 0 ) {

		$is_url   = false !== filter_var( $image, FILTER_VALIDATE_URL );
		$is_local = $is_url && false !== strpos( $image, site_url() );
		$ext      = edd_get_file_extension( $image );

		if( $is_url && $is_local ) {

			// Image given by URL, see if we have an attachment already
			$attachment_id = attachment_url_to_postid( $image );

		} elseif( $is_url ) {

			if( ! function_exists( 'media_sideload_image' ) ) {

				require_once( ABSPATH . 'wp-admin/includes/file.php' );

			}

			// Image given by external URL
			$url = media_sideload_image( $image, $download_id, '', 'src' );

			if( ! is_wp_error( $url ) ) {

				$attachment_id = attachment_url_to_postid( $url );

			}


		} elseif( false === strpos( $image, '/' ) && edd_get_file_extension( $image ) ) {

			// Image given by name only

			$upload_dir = wp_upload_dir();

			if( file_exists( trailingslashit( $upload_dir['path'] ) . $image ) ) {

				// Look in current upload directory first
				$file = trailingslashit( $upload_dir['path'] ) . $image;

			} else {

				// Now look through year/month sub folders of upload directory for files with our image's same extension
				$files = glob( $upload_dir['basedir'] . '/*/*/*{' . $ext . '}', GLOB_BRACE );
				foreach( $files as $file ) {

					if( basename( $file ) == $image ) {

						// Found our file
						break;

					}

					// Make sure $file is unset so our empty check below does not return a false positive
					unset( $file );

				}

			}

			if( ! empty( $file ) ) {

				// We found the file, let's see if it already exists in the media library

				$guid          = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file );
				$attachment_id = attachment_url_to_postid( $guid );


				if( empty( $attachment_id ) ) {

					// Doesn't exist in the media library, let's add it

					$filetype = wp_check_filetype( basename( $file ), null );

					// Prepare an array of post data for the attachment.
					$attachment = array(
						'guid'           => $guid,
						'post_mime_type' => $filetype['type'],
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $image ),
						'post_content'   => '',
						'post_status'    => 'inherit',
						'post_author'    => $post_author
					);

					// Insert the attachment.
					$attachment_id = wp_insert_attachment( $attachment, $file, $download_id );

					// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
					require_once( ABSPATH . 'wp-admin/includes/image.php' );

					// Generate the metadata for the attachment, and update the database record.
					$attach_data = wp_generate_attachment_metadata( $attachment_id, $file );
					wp_update_attachment_metadata( $attachment_id, $attach_data );

				}

			}

		}

		if( ! empty( $attachment_id ) ) {

			return set_post_thumbnail( $download_id, $attachment_id );

		}

		return false;

	}

	private function set_taxonomy_terms( $download_id = 0, $terms = array(), $taxonomy = 'download_category' ) {

		$terms = $this->maybe_create_terms( $terms, $taxonomy );

		if( ! empty( $terms ) ) {

			wp_set_object_terms( $download_id, $terms, $taxonomy );

		}

	}

	private function maybe_create_terms( $terms = array(), $taxonomy = 'download_category' ) {

		// Return of term IDs
		$term_ids = array();

		foreach( $terms as $term ) {

			if( is_numeric( $term ) && 0 === (int) $term ) {

				$term = get_term( $term, $taxonomy );

			} else {

				$term = get_term_by( 'name', $term, $taxonomy );

				if( ! $term ) {

					$term = get_term_by( 'slug', $term, $taxonomy );

				}

			}

			if( ! empty( $term ) ) {

				$term_ids[] = $term->term_id;

			} else {

				$term_ids[] = wp_insert_term( $term, $taxonomy );

			}

		}

		return array_map( 'absint', $term_ids );
	}

	public function get_list_table_url() {
		return admin_url( 'edit.php?post_type=download' );
	}

	public function get_import_type_label() {
		return edd_get_label_plural( true );
	}

}