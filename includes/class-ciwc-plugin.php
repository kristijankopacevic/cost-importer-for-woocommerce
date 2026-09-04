<?php
/**
 * Coordinates the secure WooCommerce cost-import admin workflow.
 *
 * WordPress admin workflow and WooCommerce product updates.
 *
 * @package CostImporterForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

class CIWC_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var CIWC_Plugin|null
	 */
	private static $instance;
	const CAPABILITY = 'manage_woocommerce';

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_ciwc_upload', array( $this, 'upload' ) );
		add_action( 'admin_post_ciwc_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_ciwc_confirm', array( $this, 'confirm' ) );
		add_action( 'admin_post_ciwc_rollback', array( $this, 'rollback' ) );
		add_action( 'admin_post_ciwc_unmatched', array( $this, 'unmatched' ) );
	}

	public function menu() {
		add_submenu_page( 'woocommerce', __( 'Cost Importer', 'cost-importer-for-woocommerce' ), __( 'Cost Importer', 'cost-importer-for-woocommerce' ), self::CAPABILITY, 'ciwc', array( $this, 'page' ) );
	}

	private function guard() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to import product costs.', 'cost-importer-for-woocommerce' ), 403 );
		}
	}

	private function redirect( $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=ciwc' ) ) );
		exit;
	}

	private function transient_key( $id ) {
		return 'ciwc_preview_' . get_current_user_id() . '_' . sanitize_key( $id );
	}

	private function get_preview( $id ) {
		$data = get_transient( $this->transient_key( $id ) );
		return is_array( $data ) && (int) $data['user_id'] === get_current_user_id() ? $data : false;
	}

	public function upload() {
		$this->guard();
		check_admin_referer( 'ciwc_upload' );
		if ( empty( $_FILES['ciwc_csv'] ) || ! is_array( $_FILES['ciwc_csv'] ) || ! isset( $_FILES['ciwc_csv']['tmp_name'], $_FILES['ciwc_csv']['name'], $_FILES['ciwc_csv']['size'], $_FILES['ciwc_csv']['error'] ) || UPLOAD_ERR_OK !== absint( $_FILES['ciwc_csv']['error'] ) ) {
			$this->redirect( array( 'ciwc_error' => 'upload' ) );
		}
		$file = array(
			'tmp_name' => sanitize_text_field( wp_unslash( $_FILES['ciwc_csv']['tmp_name'] ) ),
			'name'     => sanitize_file_name( wp_unslash( $_FILES['ciwc_csv']['name'] ) ),
			'size'     => absint( $_FILES['ciwc_csv']['size'] ),
			'error'    => absint( $_FILES['ciwc_csv']['error'] ),
		);
		if ( $file['size'] > CIWC_CSV::MAX_BYTES || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect( array( 'ciwc_error' => 'file' ) );
		}
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || ( ! empty( $checked['ext'] ) && 'csv' !== $checked['ext'] ) ) {
			$this->redirect( array( 'ciwc_error' => 'type' ) );
		}
		$parsed = CIWC_CSV::parse_file( $file['tmp_name'] );
		if ( is_wp_error( $parsed ) ) {
			$this->redirect( array( 'ciwc_error' => rawurlencode( $parsed->get_error_message() ) ) );
		}
		$id = wp_generate_uuid4();
		set_transient(
			$this->transient_key( $id ),
			array(
				'user_id'  => get_current_user_id(),
				'filename' => sanitize_file_name( $file['name'] ),
				'parsed'   => $parsed,
			),
			30 * MINUTE_IN_SECONDS 
		);
		$this->redirect( array( 'ciwc_preview' => $id ) );
	}

	private function header_guess( $header, $kind ) {
		$aliases = array(
			'sku'      => array( 'sku', 'product sku', 'variation sku', 'article', 'item code' ),
			'cost'     => array( 'cost', 'cost price', 'purchase price', 'supplier cost', 'unit cost' ),
			'currency' => array( 'currency', 'currency code', 'curr' ),
			'id'       => array( 'product id', 'product_id', 'id', 'variation id' ),
		);
		foreach ( $header as $index => $cell ) {
			if ( in_array( strtolower( trim( $cell ) ), $aliases[ $kind ], true ) ) {
				return $index;
			}
		}
		return '';
	}

	private function targets() {
		$targets = array(
			'_ciwc_cost' => __( 'Cost Importer internal cost (recommended)', 'cost-importer-for-woocommerce' ),
		);
		if ( defined( 'PROFITGUARD_WC_VERSION' ) || class_exists( 'ProfitGuard_WooCommerce' ) ) {
			$targets[ apply_filters( 'ciwc_profitguard_cost_meta_key', '_profitguard_cost' ) ] = __( 'ProfitGuard-owned cost (detected)', 'cost-importer-for-woocommerce' );
		}
		if ( function_exists( 'wc_cog' ) || class_exists( 'WC_COG' ) ) {
			$targets['_wc_cog_cost'] = __( 'WooCommerce Cost of Goods cost (detected)', 'cost-importer-for-woocommerce' );
		}
		return apply_filters( 'ciwc_cost_targets', $targets );
	}

	public function preview() {
		$this->guard();
		check_admin_referer( 'ciwc_preview' );
		$id   = isset( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$data = $this->get_preview( $id );
		if ( ! $data ) {
			$this->redirect( array( 'ciwc_error' => 'expired' ) );
		}
		$header  = $data['parsed']['header'];
		$mapping = isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] ) ? map_deep( wp_unslash( $_POST['mapping'] ), 'sanitize_text_field' ) : array();
		$map     = array();
		foreach ( array( 'sku', 'cost', 'currency', 'id' ) as $field ) {
			$map[ $field ] = isset( $mapping[ $field ] ) && '' !== $mapping[ $field ] ? absint( $mapping[ $field ] ) : null;
			if ( null !== $map[ $field ] && ! array_key_exists( $map[ $field ], $header ) ) {
				$this->redirect( array( 'ciwc_error' => 'mapping' ) );
			}
		}
		$allow_id = ! empty( $_POST['allow_id_fallback'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( null === $map['sku'] || null === $map['cost'] || ( $allow_id && null === $map['id'] ) ) {
			$this->redirect( array( 'ciwc_error' => 'mapping_required' ) );
		}
		$currency   = isset( $_POST['fixed_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['fixed_currency'] ) ) ) : get_woocommerce_currency(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$currencies = get_woocommerce_currencies();
		if ( ! isset( $currencies[ $currency ] ) ) {
			$this->redirect( array( 'ciwc_error' => 'currency' ) );
		}
		$target = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : '_ciwc_cost'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( ! array_key_exists( $target, $this->targets() ) ) {
			$this->redirect( array( 'ciwc_error' => 'target' ) );
		}

		$rows      = array();
		$unmatched = array();
		$seen      = array();
		foreach ( $data['parsed']['rows'] as $offset => $row ) {
			$number       = $offset + 2;
			$sku          = isset( $row[ $map['sku'] ] ) ? trim( (string) $row[ $map['sku'] ] ) : '';
			$cost         = isset( $row[ $map['cost'] ] ) ? CIWC_CSV::parse_cost( $row[ $map['cost'] ] ) : new WP_Error( 'ciwc_cost', 'Missing cost column.' );
			$row_currency = null === $map['currency'] ? $currency : strtoupper( trim( (string) ( $row[ $map['currency'] ] ?? '' ) ) );
			$source_id    = null === $map['id'] ? '' : absint( $row[ $map['id'] ] ?? 0 );
			$reason       = '';
			if ( '' === $sku && ! ( $allow_id && $source_id ) ) {
				$reason = __( 'Missing SKU (and no allowed product ID fallback).', 'cost-importer-for-woocommerce' );
			} elseif ( is_wp_error( $cost ) ) {
				$reason = $cost->get_error_message();
			} elseif ( ! isset( $currencies[ $row_currency ] ) || $row_currency !== $currency ) {
				$reason = sprintf( __( 'Currency must be %s.', 'cost-importer-for-woocommerce' ), $currency );
			} elseif ( '' !== $sku && isset( $seen[ strtolower( $sku ) ] ) ) {
				$reason                                        = __( 'Duplicate supplier SKU row; neither duplicate will be imported.', 'cost-importer-for-woocommerce' );
				$rows[ $seen[ strtolower( $sku ) ] ]['reason'] = $reason;
			}
			$rows[] = array(
				'number'    => $number,
				'sku'       => $sku,
				'cost'      => is_wp_error( $cost ) ? '' : $cost,
				'currency'  => $row_currency,
				'source_id' => $source_id,
				'reason'    => $reason,
			);
			if ( '' !== $sku && ! isset( $seen[ strtolower( $sku ) ] ) ) {
				$seen[ strtolower( $sku ) ] = count( $rows ) - 1;
			}
		}
		$index  = $this->sku_index( array_filter( array_column( $rows, 'sku' ) ) );
		$counts = array(
			'matched'   => 0,
			'unmatched' => 0,
			'ambiguous' => 0,
			'invalid'   => 0,
			'total'     => count( $rows ),
		);
		foreach ( $rows as &$row ) {
			if ( '' !== $row['reason'] ) {
				++$counts['invalid'];
			} elseif ( '' !== $row['sku'] && isset( $index[ strtolower( $row['sku'] ) ] ) ) {
				if ( 1 === count( $index[ strtolower( $row['sku'] ) ] ) ) {
					$row['product_id'] = (int) $index[ strtolower( $row['sku'] ) ][0];
					++$counts['matched'];
				} else {
					$row['reason'] = __( 'SKU matches more than one product or variation.', 'cost-importer-for-woocommerce' );
					++$counts['ambiguous'];
				}
			} else {
				$product = $allow_id && $row['source_id'] ? wc_get_product( $row['source_id'] ) : false;
				if ( $product && in_array( $product->get_type(), array( 'simple', 'variable', 'variation' ), true ) ) {
					$row['product_id'] = $product->get_id();
					++$counts['matched'];
				} else {
					$row['reason'] = __( 'No matching product or variation SKU was found.', 'cost-importer-for-woocommerce' );
					++$counts['unmatched'];
				}
			}
			if ( ! empty( $row['reason'] ) ) {
				$unmatched[] = array( $row['number'], $row['sku'], $row['cost'], $row['currency'], $row['reason'] );
			}
		}
		unset( $row );
		$data['prepared'] = array(
			'mapping'   => $map,
			'currency'  => $currency,
			'target'    => $target,
			'allow_id'  => $allow_id,
			'rows'      => $rows,
			'unmatched' => $unmatched,
			'counts'    => $counts,
		);
		set_transient( $this->transient_key( $id ), $data, 30 * MINUTE_IN_SECONDS );
		$this->redirect(
			array(
				'ciwc_preview' => $id,
				'ciwc_stage'   => 'confirm',
			) 
		);
	}

	private function sku_index( $skus ) {
		global $wpdb;
		$index = array();
		foreach ( array_chunk( array_values( array_unique( $skus ) ), 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$args         = array_merge( array( '_sku' ), $chunk );
			$sql          = "SELECT pm.meta_value AS sku, p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value IN ({$placeholders}) AND p.post_type IN ('product','product_variation') AND p.post_status != 'trash'";
			$found        = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic placeholders are prepared.
			foreach ( $found as $item ) {
				$key             = strtolower( (string) $item['sku'] );
				$index[ $key ][] = (int) $item['ID'];
			}
		}
		return $index;
	}

	public function confirm() {
		$this->guard();
		check_admin_referer( 'ciwc_confirm' );
		$id    = isset( $_POST['preview_id'] ) ? sanitize_text_field( wp_unslash( $_POST['preview_id'] ) ) : '';
		$data  = $this->get_preview( $id );
		$typed = isset( $_POST['confirmation'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) ) : '';
		if ( ! $data || empty( $data['prepared'] ) || 'UPDATE COSTS' !== $typed ) {
			$this->redirect(
				array(
					'ciwc_preview' => $id,
					'ciwc_stage'   => 'confirm',
					'ciwc_error'   => 'confirmation',
				) 
			);
		}
		$prepared  = $data['prepared'];
		$import_id = CIWC_Repository::create_import(
			array(
				'uuid'            => wp_generate_uuid4(),
				'filename'        => $data['filename'],
				'target_meta_key' => $prepared['target'],
				'currency'        => $prepared['currency'],
				'summary'         => $prepared['counts'],
				'unmatched'       => $prepared['unmatched'],
			) 
		);
		$updated   = 0;
		$failed    = 0;
		foreach ( $prepared['rows'] as $row ) {
			if ( empty( $row['product_id'] ) || ! empty( $row['reason'] ) ) {
				continue;
			}
			try {
				$product = wc_get_product( $row['product_id'] );
				if ( ! $product ) {
					throw new RuntimeException( 'Product disappeared before update.' );
				}
				$old_exists = metadata_exists( 'post', $product->get_id(), $prepared['target'] );
				$old_value  = $old_exists ? get_post_meta( $product->get_id(), $prepared['target'], true ) : null;
				$change_id  = CIWC_Repository::stage_change( $import_id, $product->get_id(), $prepared['target'], $old_exists, $old_value, $row['cost'] );
				if ( ! $change_id ) {
					throw new RuntimeException( 'Could not create the import audit record.' );
				}
				$product->update_meta_data( $prepared['target'], $row['cost'] );
				$product->save();
				CIWC_Repository::mark_applied( $change_id );
				++$updated;
			} catch ( Throwable $error ) {
				++$failed;
			}
		}
		$summary            = $prepared['counts'];
		$summary['updated'] = $updated;
		$summary['failed']  = $failed;
		CIWC_Repository::complete_import( $import_id, $failed ? 'partial' : 'completed', $summary );
		delete_transient( $this->transient_key( $id ) );
		$this->redirect( array( 'ciwc_done' => $import_id ) );
	}

	public function unmatched() {
		$this->guard();
		check_admin_referer( 'ciwc_unmatched' );
		$import = CIWC_Repository::get_import( isset( $_REQUEST['import_id'] ) ? absint( $_REQUEST['import_id'] ) : 0 );
		if ( ! $import ) {
			wp_die( esc_html__( 'Import not found.', 'cost-importer-for-woocommerce' ), 404 );
		}
		$rows = json_decode( $import['unmatched'], true );
		CIWC_CSV::output_csv( 'cost-import-unmatched-' . $import['id'] . '.csv', array( 'Source row', 'SKU', 'Cost', 'Currency', 'Reason' ), is_array( $rows ) ? $rows : array() );
	}

	public function rollback() {
		$this->guard();
		check_admin_referer( 'ciwc_rollback' );
		$import_id = isset( $_POST['import_id'] ) ? absint( $_POST['import_id'] ) : 0;
		$import    = CIWC_Repository::get_import( $import_id );
		if ( ! $import || ! in_array( $import['status'], array( 'completed', 'partial' ), true ) ) {
			$this->redirect( array( 'ciwc_error' => 'rollback' ) );
		}
		$restored = 0;
		$skipped  = 0;
		foreach ( CIWC_Repository::get_changes( $import_id ) as $change ) {
			$current = get_post_meta( $change['product_id'], $change['meta_key'], true );
			if ( (string) $current !== (string) $change['new_value'] ) {
				++$skipped; // A later change wins; never overwrite it.
				continue;
			}
			$product = wc_get_product( $change['product_id'] );
			if ( ! $product ) {
				++$skipped;
				continue;
			}
			try {
				if ( (int) $change['old_exists'] ) {
					$product->update_meta_data( $change['meta_key'], $change['old_value'] );
				} else {
					$product->delete_meta_data( $change['meta_key'] );
				}
				$product->save();
				++$restored;
			} catch ( Throwable $error ) {
				++$skipped;
			}
		}
		CIWC_Repository::complete_import(
			$import_id,
			$skipped ? 'rollback_partial' : 'rolled_back',
			array_merge(
				(array) json_decode( $import['summary'], true ),
				array(
					'restored'         => $restored,
					'rollback_skipped' => $skipped,
				) 
			) 
		);
		$this->redirect( array( 'ciwc_done' => $import_id ) );
	}

	private function select( $name, $header, $selected, $required = false ) {
		printf( '<select name="mapping[%1$s]" %2$s><option value="">%3$s</option>', esc_attr( $name ), $required ? 'required' : '', esc_html__( 'Not mapped', 'cost-importer-for-woocommerce' ) );
		foreach ( $header as $index => $label ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $index, selected( (string) $selected, (string) $index, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private function notice() {
		if ( empty( $_GET['ciwc_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query argument.
			return;
		}
		$error    = sanitize_text_field( wp_unslash( $_GET['ciwc_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query argument.
		$messages = array(
			'upload'           => __( 'Upload failed. Choose a CSV file and try again.', 'cost-importer-for-woocommerce' ),
			'file'             => __( 'Use an uploaded CSV no larger than 2 MiB.', 'cost-importer-for-woocommerce' ),
			'type'             => __( 'Only .csv files are accepted.', 'cost-importer-for-woocommerce' ),
			'expired'          => __( 'This preview expired. Upload the CSV again.', 'cost-importer-for-woocommerce' ),
			'mapping_required' => __( 'SKU and cost mappings are required. Product ID is only available as an explicit fallback.', 'cost-importer-for-woocommerce' ),
			'confirmation'     => __( 'Type UPDATE COSTS exactly to apply this reviewed import.', 'cost-importer-for-woocommerce' ),
			'rollback'         => __( 'This import cannot be rolled back again.', 'cost-importer-for-woocommerce' ),
		);
		$message  = $messages[ $error ] ?? rawurldecode( $error );
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}

	public function page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		wp_enqueue_style( 'ciwc-admin', CIWC_URL . 'assets/css/admin.css', array(), CIWC_VERSION );
		$preview_id = isset( $_GET['ciwc_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['ciwc_preview'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query argument.
		$data       = $preview_id ? $this->get_preview( $preview_id ) : false;
		echo '<div class="wrap ciwc"><h1>' . esc_html__( 'Cost Importer for WooCommerce', 'cost-importer-for-woocommerce' ) . '</h1>';
		$this->notice();
		if ( $data && ! empty( $_GET['ciwc_stage'] ) && 'confirm' === $_GET['ciwc_stage'] && ! empty( $data['prepared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query argument.
			$this->confirm_view( $preview_id, $data );
		} elseif ( $data ) {
			$this->mapping_view( $preview_id, $data );
		} else {
			$this->upload_view();
		}
		$this->done_view();
		$this->history_view();
		echo '</div>';
	}

	private function upload_view() {
		?>
		<p><?php esc_html_e( 'Import supplier costs only after a preview and explicit confirmation. Empty or invalid costs are never treated as zero.', 'cost-importer-for-woocommerce' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ciwc-card">
			<input type="hidden" name="action" value="ciwc_upload">
			<?php wp_nonce_field( 'ciwc_upload' ); ?>
			<label for="ciwc_csv"><strong><?php esc_html_e( 'Supplier CSV', 'cost-importer-for-woocommerce' ); ?></strong></label>
			<input id="ciwc_csv" name="ciwc_csv" type="file" accept=".csv,text/csv" required>
			<p class="description"><?php esc_html_e( 'CSV only, up to 2 MiB / 2,000 data rows. Comma, semicolon, tab, and pipe delimiters are detected.', 'cost-importer-for-woocommerce' ); ?></p>
			<?php submit_button( __( 'Upload and map columns', 'cost-importer-for-woocommerce' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	private function mapping_view( $preview_id, $data ) {
		$header = $data['parsed']['header'];
		?>
		<h2><?php esc_html_e( 'Map supplier columns', 'cost-importer-for-woocommerce' ); ?></h2>
		<p><?php printf( esc_html__( 'File: %s. The parsed file is held only for this 30-minute review session.', 'cost-importer-for-woocommerce' ), '<code>' . esc_html( $data['filename'] ) . '</code>' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ciwc-card">
			<input type="hidden" name="action" value="ciwc_preview"><input type="hidden" name="preview_id" value="<?php echo esc_attr( $preview_id ); ?>">
			<?php wp_nonce_field( 'ciwc_preview' ); ?>
			<table class="form-table"><tbody>
			<tr><th><label><?php esc_html_e( 'SKU column', 'cost-importer-for-woocommerce' ); ?></label></th><td><?php $this->select( 'sku', $header, $this->header_guess( $header, 'sku' ), true ); ?><p class="description"><?php esc_html_e( 'Matches simple-product and variation SKUs exactly.', 'cost-importer-for-woocommerce' ); ?></p></td></tr>
			<tr><th><label><?php esc_html_e( 'Cost column', 'cost-importer-for-woocommerce' ); ?></label></th><td><?php $this->select( 'cost', $header, $this->header_guess( $header, 'cost' ), true ); ?></td></tr>
			<tr><th><label><?php esc_html_e( 'Currency column', 'cost-importer-for-woocommerce' ); ?></label></th><td><?php $this->select( 'currency', $header, $this->header_guess( $header, 'currency' ) ); ?><p class="description"><?php esc_html_e( 'Optional. If mapped, every value must equal the selected import currency.', 'cost-importer-for-woocommerce' ); ?></p></td></tr>
			<tr><th><label><?php esc_html_e( 'Import currency', 'cost-importer-for-woocommerce' ); ?></label></th><td><select name="fixed_currency">
			<?php
			foreach ( get_woocommerce_currencies() as $code => $name ) {
				printf( '<option value="%1$s" %2$s>%1$s — %3$s</option>', esc_attr( $code ), selected( get_woocommerce_currency(), $code, false ), esc_html( $name ) ); }
			?>
			</select></td></tr>
			<tr><th><label><?php esc_html_e( 'Cost field to update', 'cost-importer-for-woocommerce' ); ?></label></th><td><select name="target">
			<?php
			foreach ( $this->targets() as $key => $label ) {
				printf( '<option value="%1$s">%2$s</option>', esc_attr( $key ), esc_html( $label ) ); }
			?>
			</select><p class="description"><?php esc_html_e( 'Only the chosen field changes. Third-party fields are offered only after a compatible plugin identifies itself.', 'cost-importer-for-woocommerce' ); ?></p></td></tr>
			<tr><th><label><?php esc_html_e( 'Product ID fallback', 'cost-importer-for-woocommerce' ); ?></label></th><td><label><input type="checkbox" name="allow_id_fallback" value="1"> <?php esc_html_e( 'Allow an ID column only when an SKU has no match', 'cost-importer-for-woocommerce' ); ?></label><br><?php $this->select( 'id', $header, $this->header_guess( $header, 'id' ) ); ?></td></tr>
			</tbody></table>
			<?php submit_button( __( 'Build safe preview', 'cost-importer-for-woocommerce' ) ); ?>
		</form>
		<?php
	}

	private function confirm_view( $preview_id, $data ) {
		$p = $data['prepared'];
		?>
		<h2><?php esc_html_e( 'Review before updating costs', 'cost-importer-for-woocommerce' ); ?></h2>
		<div class="ciwc-card"><p><strong><?php esc_html_e( 'Target:', 'cost-importer-for-woocommerce' ); ?></strong> <code><?php echo esc_html( $p['target'] ); ?></code> &middot; <strong><?php esc_html_e( 'Currency:', 'cost-importer-for-woocommerce' ); ?></strong> <?php echo esc_html( $p['currency'] ); ?></p>
			<ul class="ciwc-counts"><li><?php printf( esc_html__( '%d matched', 'cost-importer-for-woocommerce' ), (int) $p['counts']['matched'] ); ?></li><li><?php printf( esc_html__( '%d unmatched', 'cost-importer-for-woocommerce' ), (int) $p['counts']['unmatched'] ); ?></li><li><?php printf( esc_html__( '%d ambiguous', 'cost-importer-for-woocommerce' ), (int) $p['counts']['ambiguous'] ); ?></li><li><?php printf( esc_html__( '%d invalid/duplicate', 'cost-importer-for-woocommerce' ), (int) $p['counts']['invalid'] ); ?></li></ul>
			<p><?php esc_html_e( 'Only matched, valid rows will be updated. All other rows will be retained in the unmatched report.', 'cost-importer-for-woocommerce' ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Row', 'cost-importer-for-woocommerce' ); ?></th><th>SKU</th><th><?php esc_html_e( 'Product', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Cost', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Status', 'cost-importer-for-woocommerce' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( array_slice( $p['rows'], 0, 50 ) as $row ) {
				$product_id = ! empty( $row['product_id'] ) ? (int) $row['product_id'] : '—';
				$status     = '' !== $row['reason'] ? $row['reason'] : __( 'Matched', 'cost-importer-for-woocommerce' );
				echo '<tr><td>' . (int) $row['number'] . '</td><td>' . esc_html( $row['sku'] ) . '</td><td>' . esc_html( (string) $product_id ) . '</td><td>' . esc_html( $row['cost'] ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
			}
			?>
			</tbody></table>
			<?php
			if ( count( $p['rows'] ) > 50 ) :
				?>
				<p class="description"><?php esc_html_e( 'The table shows the first 50 rows; counts cover the full CSV.', 'cost-importer-for-woocommerce' ); ?></p><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ciwc-confirm"><input type="hidden" name="action" value="ciwc_confirm"><input type="hidden" name="preview_id" value="<?php echo esc_attr( $preview_id ); ?>"><?php wp_nonce_field( 'ciwc_confirm' ); ?><label><?php esc_html_e( 'Type UPDATE COSTS to confirm:', 'cost-importer-for-woocommerce' ); ?> <input name="confirmation" autocomplete="off" required></label><?php submit_button( __( 'Apply reviewed cost updates', 'cost-importer-for-woocommerce' ), 'primary', 'submit', false ); ?></form>
		</div>
		<?php
	}

	private function done_view() {
		if ( empty( $_GET['ciwc_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query argument.
			return;
		}
		$import = CIWC_Repository::get_import( absint( $_GET['ciwc_done'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only query argument.
		if ( ! $import ) {
			return;
		}
		$summary = json_decode( $import['summary'], true );
		echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Import recorded.', 'cost-importer-for-woocommerce' ) . '</strong> ';
		printf( esc_html__( '%d costs updated; %d update failures; %d unmatched/ambiguous/invalid rows.', 'cost-importer-for-woocommerce' ), (int) ( $summary['updated'] ?? 0 ), (int) ( $summary['failed'] ?? 0 ), (int) ( ( $summary['unmatched'] ?? 0 ) + ( $summary['ambiguous'] ?? 0 ) + ( $summary['invalid'] ?? 0 ) ) );
		echo '</p></div>';
	}

	private function history_view() {
		$history = CIWC_Repository::history();
		?>
		<h2><?php esc_html_e( 'Import history', 'cost-importer-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Rollback restores only values that still equal this import’s value. Later manual or plugin edits are never overwritten.', 'cost-importer-for-woocommerce' ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'File', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Target', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Status', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Result', 'cost-importer-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Actions', 'cost-importer-for-woocommerce' ); ?></th></tr></thead><tbody>
		<?php
		if ( ! $history ) :
			?>
			<tr><td colspan="6"><?php esc_html_e( 'No imports yet.', 'cost-importer-for-woocommerce' ); ?></td></tr><?php endif; ?>
		<?php
		foreach ( $history as $import ) :
			$summary = json_decode( $import['summary'], true );
			?>
			<tr><td><?php echo esc_html( get_date_from_gmt( $import['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td><td><?php echo esc_html( $import['filename'] ); ?></td><td><code><?php echo esc_html( $import['target_meta_key'] ); ?></code></td><td><?php echo esc_html( $import['status'] ); ?></td><td><?php printf( esc_html__( '%d updated / %d failed', 'cost-importer-for-woocommerce' ), (int) ( $summary['updated'] ?? 0 ), (int) ( $summary['failed'] ?? 0 ) ); ?></td><td><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ciwc_unmatched&import_id=' . (int) $import['id'] ), 'ciwc_unmatched' ) ); ?>"><?php esc_html_e( 'Unmatched CSV', 'cost-importer-for-woocommerce' ); ?></a>
			<?php
			if ( in_array( $import['status'], array( 'completed', 'partial' ), true ) ) :
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ciwc-inline"><input type="hidden" name="action" value="ciwc_rollback"><input type="hidden" name="import_id" value="<?php echo (int) $import['id']; ?>"><?php wp_nonce_field( 'ciwc_rollback' ); ?><button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'Restore only values that have not changed since this import?', 'cost-importer-for-woocommerce' ) ); ?>');"><?php esc_html_e( 'Safe rollback', 'cost-importer-for-woocommerce' ); ?></button></form><?php endif; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}
}
