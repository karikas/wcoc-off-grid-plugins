<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<tr>
	<?php foreach ( $columns as $column_name => $column_display_name ) : ?>
		<?php
		$key     = str_replace( 'col_', '', $column_name );
		$class   = $column_name . ' column-' . $column_name;
		$style   = '';
		$actions = '';

		if ( in_array( $column_name, $hidden ) ) { //@codingStandardsIgnoreLine
			$style = 'display:none;';
		}

		if ( 'name' === $key ) {
			$actions = array(
				'download'       => sprintf(
					'<a href="?page=%s&tab=%s&action=%s&packages=%s&linknonce=%s">%s</a>',
					$_REQUEST['page'], //@codingStandardsIgnoreLine
					'general-options',
					'download',
					$record_key,
					wp_create_nonce( 'linknonce' ),
					__( 'Download', 'wppus' )
				),
				'change_license' => sprintf(
					'<a href="?page=%s&tab=%s&action=%s&packages=%s&linknonce=%s">%s</a>',
					$_REQUEST['page'], //@codingStandardsIgnoreLine
					'general-options',
					$license_action,
					$record_key,
					wp_create_nonce( 'linknonce' ),
					$license_action_text
				),
				'delete'         => sprintf( '<a href="?page=%s&tab=%s&action=%s&packages=%s&linknonce=%s">%s</a>',
					$_REQUEST['page'], //@codingStandardsIgnoreLine
					'general-options',
					'delete',
					$record_key,
					wp_create_nonce( 'linknonce' ),
					__( 'Delete' )
				),
			);
			$actions = $table->row_actions( $actions );
		}
		$attributes = $class . $style;
		?>
		<?php if ( 'cb' === $column_name ) : ?>
			<th scope="row" class="check-column">
				<input type="checkbox" name="packages[]" id="cb-select-<?php echo esc_attr( $record_key ); ?>" value="<?php echo esc_attr( $record_key ); ?>" />
			</th>
		<?php else : ?>
			<td class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $style ); ?>">
				<?php if ( 'col_name' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
					<?php echo $actions; ?><?php //@codingStandardsIgnoreLine ?>
				<?php elseif ( 'col_version' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_type' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_file_name' === $column_name ) : ?>
					<?php echo esc_html( $record[ $key ] ); ?>
				<?php elseif ( 'col_file_size' === $column_name ) : ?>
					<?php echo esc_html( size_format( $record[ $key ] ) ); ?>
				<?php elseif ( 'col_file_last_modified' === $column_name ) : ?>
					<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' - H:m:i', $record[ $key ] ) ); ?>
				<?php elseif ( 'col_use_license' === $column_name ) : ?>
					<?php echo esc_html( $use_license_text ); ?>
				<?php endif; ?>
			</td>
		<?php endif; ?>
	<?php endforeach; ?>
</tr>
