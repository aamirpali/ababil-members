<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AM_Fields {
    /** All custom field definitions from options, in the admin-configured order. */
    public static function all() {
        $opts = get_option( 'am_options', array() );
        $fields = isset( $opts['fields'] ) && is_array( $opts['fields'] ) ? array_values( $opts['fields'] ) : array();
        $order  = isset( $opts['am_fields_order'] ) && is_array( $opts['am_fields_order'] ) ? $opts['am_fields_order'] : array();
        if ( $order && $fields ) {
            usort( $fields, function ( $a, $b ) use ( $order ) {
                $ia = array_search( $a['slug'] ?? '', $order, true );
                $ib = array_search( $b['slug'] ?? '', $order, true );
                $ia = false === $ia ? PHP_INT_MAX : (int) $ia;
                $ib = false === $ib ? PHP_INT_MAX : (int) $ib;
                if ( $ia === $ib ) return 0;
                return $ia < $ib ? -1 : 1;
            } );
        }
        return $fields;
    }

    public static function get( $slug ) {
        foreach ( self::all() as $f ) { if ( $f['slug'] === $slug ) return $f; }
        return null;
    }

    public static function types() {
        return array(
            'text'     => __( 'Text', 'ababil-members' ),
            'textarea' => __( 'Textarea', 'ababil-members' ),
            'select'   => __( 'Select', 'ababil-members' ),
            'number'   => __( 'Number', 'ababil-members' ),
            'url'      => __( 'URL', 'ababil-members' ),
            'date'     => __( 'Date', 'ababil-members' ),
        );
    }

    public static function sanitize( $field, $value ) {
        $type = $field['type'] ?? 'text';
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        if ( 'textarea' === $type ) $value = sanitize_textarea_field( $value );
        elseif ( 'url' === $type ) $value = esc_url_raw( $value );
        elseif ( 'number' === $type ) $value = (string) (int) $value;
        elseif ( 'date' === $type ) $value = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
        elseif ( 'select' === $type ) {
            $opts = self::options_list( $field );
            $value = in_array( $value, $opts, true ) ? $value : '';
        } else $value = sanitize_text_field( $value );
        return mb_substr( $value, 0, 500 );
    }

    public static function options_list( $field ) {
        $raw = $field['options'] ?? '';
        $parts = array_map( 'trim', explode( "\n", str_replace( ',', "\n", $raw ) ) );
        return array_values( array_filter( $parts ) );
    }

    public static function meta_key( $slug ) { return 'am_field_' . sanitize_key( $slug ); }

    public static function value( $user_id, $slug ) {
        return get_user_meta( $user_id, self::meta_key( $slug ), true );
    }

    public static function save( $user_id, $fields, $source = array() ) {
        foreach ( $fields as $field ) {
            if ( ! isset( $field['slug'] ) ) continue;
            $key = $field['slug'];
            if ( ! array_key_exists( $key, $source ) ) continue;
            update_user_meta( $user_id, self::meta_key( $key ), self::sanitize( $field, $source[ $key ] ) );
        }
    }

    /** Render form rows for given fields. */
    public static function render_inputs( $fields, $values = array(), $prefix = '' ) {
        foreach ( $fields as $field ) :
            $slug = $field['slug'];
            $id = $prefix . 'am-field-' . esc_attr( $slug );
            $val = $values[ $slug ] ?? '';
            $req = ! empty( $field['required'] ) ? ' required' : '';
            $dep = '';
            if ( ! empty( $field['depends_on'] ) ) {
                $dep = ' data-depends-on="' . esc_attr( $field['depends_on'] ) . '" data-depends-value="' . esc_attr( $field['depends_value'] ?? '' ) . '"';
            }
            ?>
            <div class="ig-edit-row" data-ig-field="<?php echo esc_attr( $slug ); ?>"<?php echo $dep; ?>>
                <label class="ig-edit-label" for="<?php echo $id; ?>"><?php echo esc_html( $field['label'] ); ?><?php echo $req ? ' *' : ''; ?></label>
                <?php if ( 'textarea' === $field['type'] ) : ?>
                    <textarea class="ig-edit-textarea" id="<?php echo $id; ?>" name="<?php echo esc_attr( $slug ); ?>"<?php echo $req; ?>><?php echo esc_textarea( $val ); ?></textarea>
                <?php elseif ( 'select' === $field['type'] ) : ?>
                    <select class="ig-edit-input" id="<?php echo $id; ?>" name="<?php echo esc_attr( $slug ); ?>"<?php echo $req; ?>>
                        <option value=""><?php esc_html_e( '— Select —', 'ababil-members' ); ?></option>
                        <?php foreach ( self::options_list( $field ) as $opt ) : ?>
                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $opt, $val ); ?>><?php echo esc_html( $opt ); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input class="ig-edit-input" id="<?php echo $id; ?>" name="<?php echo esc_attr( $slug ); ?>" type="<?php echo esc_attr( $field['type'] ); ?>" value="<?php echo esc_attr( $val ); ?>"<?php echo $req; ?>>
                <?php endif; ?>
            </div>
            <?php
        endforeach;
    }
}
