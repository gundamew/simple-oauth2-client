<div class="wrap">
    <h2><?php esc_html_e( get_admin_page_title() ); ?></h2>
    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
        <?php
            settings_fields( $this->plugin_name );
            do_settings_sections( $this->plugin_name );
        ?>
        <p>
            <?php submit_button( __( 'Save', $this->plugin_name ), 'primary', 'submit', false ); ?>
        </p>
        <input type="hidden" name="action" value="simple_oauth2_client_actions">
    </form>
</div>
