<?php
global $file;
class WCS_Admin_Importer {
	var $id;

	/* Displays header followed by the current pages content */
	public function display_content() {
		
		$page = ( isset($_GET['step'] ) ) ? $_GET['step'] : 1;
		switch( $page ) {
		case 1 : //Step: Upload File
			$this->upload_page();
			break;
		case 2 : // Handle upload and map fields
			check_admin_referer( 'import-upload' );
			if( isset( $_POST['action'] ) ) {
				$this->handle_file();
			}
			break;
		case 3 :
			$this->confirmation();
			break;
		default : //default to home page
			$this->upload_page();
			break;
		}
	}
	/* Initial plugin page. Prompts the admin to upload the CSV file containing subscription details. */
	static function upload_page() { 
		echo '<h3>' . __( 'Step 1: Upload CSV File', 'wcs_import' ) . '</h3>';
		$action = 'admin.php?page=import_subscription&amp;step=2&amp;';
		$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size = size_format( $bytes );
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) :
			?><div class="error"><p><?php _e('Before you can upload your import file, you will need to fix the following error:'); ?></p>
			<p><strong><?php echo $upload_dir['error']; ?></strong></p></div><?php
		else :
			?>
			<p>Upload a CSV file containing details about your subscriptions to bring across to your store with WooCommerce.</p>
			<p>Choose a CSV (.csv) file to upload, then click Upload file and import.</p>
			<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr(wp_nonce_url($action, 'import-upload')); ?>">
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label for="upload"><?php _e( 'Choose a file from your computer:' ); ?></label>
							</th>
							<td>
								<input type="file" id="upload" name="import" size="25" />
								<input type="hidden" name="action" value="save" />
								<input type="hidden" name="max_file_size" value="<?php echo $bytes; ?>" />
								<small><?php printf( __('Maximum size: %s' ), $size ); ?></small>
							</td>
						</tr>
						<tr>
							<th>
								<label for="file_url"><?php _e( 'OR enter path to file:', 'wcs_import' ); ?></label>
							</th>
							<td>
								<?php echo ' ' . ABSPATH . ' '; ?><input type="text" id="file_url" name="file_url" size="50" />
							</td>
						</tr>
						<tr>
							<th><label><?php _e( 'Delimiter', 'wcs_import' ); ?></label><br/></th>
							<td><input type="text" name="delimiter" placeholder="," size="2" /></td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Upload file and import' ); ?>" />
				</p>
			</form>
			<?php
		endif;
	}

	function handle_file() {
		global $file;
		$file = wp_import_handle_upload();
		if( isset( $file['error'] ) ) {
			$this->importer_error();
		} else {
			$this->id = $file['id'];
			$this->delimiter = ( ! empty( $_POST['delimiter'] ) ) ? stripslashes( trim( $_POST['delimiter'] ) ) : ',';

			$file = get_attached_file( $this->id );

			$enc = mb_detect_encoding( $file, 'UTF-8, ISO-8859-1', true );
			if ( $enc ) setlocale( LC_ALL, 'en_US.' . $enc );
			@ini_set( 'auto_detect_line_endings', true );

			echo $this->id;
			// Get headers
			if ( ( $handle = fopen( $file, "r" ) ) !== FALSE ) {
			$row = $raw_headers = array();

			$header = fgetcsv( $handle, 0, $this->delimiter );
			while ( ( $postmeta = fgetcsv( $handle, 0, $this->delimiter ) ) !== false ) {
				foreach ( $header as $key => $heading ) {
					if ( ! $heading ) continue;
					$s_heading = strtolower( $heading );
					$row[$s_heading] = ( isset( $postmeta[$key] ) ) ? $this->format_data_from_csv( $postmeta[$key], $enc ) : '';
					$raw_headers[ $s_heading ] = $heading;
				}
				break;
			}
			fclose( $handle );
		}
		$this->map_fields($row);
		}
	}

	function format_data_from_csv( $data, $enc ) {
		return ( $enc == 'UTF-8' ) ? $data : utf8_encode( $data );
	}

	/* Step 2: Once uploaded file is recognised, the admin will be required to map CSV columns to the required fields. */
	function map_fields() {
		$action = 'admin.php?page=import_subscription&amp;step=3&amp;';
		?>
		<h3><?php _e( 'Step 2: Map Fields to Column Names', 'wcs_import' ); ?></h3>
		<form method="post" action="<?php echo esc_attr(wp_nonce_url($action, 'import-upload')); ?>">
			<table class="widefat widefat_importer">
				<thead>
					<tr>
						<th><?php _e( 'Map to', 'wcs_import' ); ?></th>
						<th><?php _e( 'Column Header', 'wcs_import' ); ?></th>
						<th><?php _e( 'Example Column Value', 'wcs_import' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<select>
								<option selected>Do not import</option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Submit' ); ?>" />
			</p>
		</form>
		<?php
	}
	
	/* Step 3: Displays the information about to be uploaded and waits for confirmation by the admin. */
	function confirmation() { 
		global $file;
		echo '<h3>' . __( 'Step 3: Confirmation', 'wcs_import' ) . '</h3>';
	?>
		<table id="import-progress" class="widefat_importer widefat">
			<thead>
				<tr>
					<th class="status">&nbsp;</th>
					<th class="row"><?php _e( 'Row', 'wcs_import' ); ?></th>
					<th><?php _e( 'Subscription', 'wcs_import' ); ?></th>
					<th class="reason"><?php _e( 'Status Msg', 'wcs_import' ); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr class="importer-loading">
					<td colspan="5"></td>
				</tr>
			</tfoot>
			<tbody></tbody>
		</table><?php
		
	}

	/* Handles displaying an error message throughout the process of importing subscriptions. */
	function importer_error() {
		global $file;
		?>
		<h3>Error</h3>
		<p>Error: <?php _e($file['error']); ?></p>
		<?php
		// Unfinished. Doesnt show anything but error message
	}
}
?>